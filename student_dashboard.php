<?php
require 'auth.php';
require 'config.php';

// Check if the user is a student (role 2)
if ($_SESSION['role'] != 2) {
    echo "Access denied: Students only.";
    exit();
}
// Check if student_id is set in session
if (!isset($_SESSION['student_id'])) {
    echo "Student ID is missing.";
    exit();
}
$student_id = $_SESSION['student_id'];
$message = '';

// Handle join class request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_class'])) {
    $class_code = trim($_POST['class_code']);
    
    // Check if the class with the provided code exists
    $stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_code = ?");
    $stmt->execute([$class_code]);
    $class = $stmt->fetch();

    if ($class) {
        // Check if the student is already enrolled in this class
        $stmt = $pdo->prepare("SELECT * FROM student_classes WHERE student_id = ? AND class_id = ?");
        $stmt->execute([$student_id, $class['class_id']]);
        $existingEnrollment = $stmt->fetch();

        if (!$existingEnrollment) {
            // Insert the student into student_classes table with class_id and student_id
            $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id) VALUES (?, ?)");
            $stmt->execute([$student_id, $class['class_id']]);
            $message = "Successfully joined the class!";
            
            // Redirect to the same page to avoid form resubmission on reload
            header("Location: student_dashboard.php");
            exit();
        } else {
            $message = "You have already joined this class.";
        }
    } else {
        $message = "Class code not found.";
    }
}

// Fetch all classes the student has joined
$stmt = $pdo->prepare("
    SELECT class_tbl.class_id, class_tbl.class_subject, class_tbl.class_section, class_tbl.class_code 
    FROM class_tbl 
    INNER JOIN student_classes ON class_tbl.class_id = student_classes.class_id 
    WHERE student_classes.student_id = ?
");
$stmt->execute([$student_id]);
$joined_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all pending assessments across all joined classes
// Fetch all pending assessments across all joined classes
$todo_list = [];
foreach ($joined_classes as $class) {
    $stmt = $pdo->prepare("
        SELECT assessment_id, name, type, time_limit 
        FROM assessment_tbl 
        WHERE class_id = ?
    ");
    $stmt->execute([$class['class_id']]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($assessments as $assessment) {
        // Check if the student has attempted this assessment in any of the attempt tables
        $attempted = false;

        // Check answers_esy_tbl
        $stmt = $pdo->prepare("SELECT Attempt FROM answers_esy_tbl WHERE student_id = ? AND assessment_id = ?");
        $stmt->execute([$student_id, $assessment['assessment_id']]);
        if ($stmt->fetch()) {
            $attempted = true;
        }

        // Check answers_mcq_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT Attempt FROM answers_mcq_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Check answers_tf_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT Attempt FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Check answers_mcq_collab_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT attempt FROM answers_mcq_collab_tbl WHERE submitted_by = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Add to To-Do List if not attempted
        if (!$attempted) {
            $todo_list[] = [
                'name' => $assessment['name'],
                'type' => $assessment['type'],
                'time_limit' => $assessment['time_limit'],
                'class_subject' => $class['class_subject'],
            ];
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
</head>
<body>
    <h2>Student Dashboard</h2>
    <p>Welcome to your dashboard, <strong><?php echo htmlspecialchars($_SESSION['username']); ?>!</strong></p>
    <button><a href="achievements.php?student_id=<?php echo $student_id; ?>">Achievements</a></button>

    <button><a href="logout.php">Logout</a></button>
    

    <!-- Display success/error message -->
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- Display join class form -->
    <h3>Join a Class</h3>
    <form method="post" action="student_dashboard.php">
        <input type="text" id="class_code" name="class_code" placeholder="Enter Class Code" required>
        <button type="submit" name="join_class">Join Class</button>
    </form>

    <!-- Display list of classes the student has joined -->
    <h3>Your Joined Classes</h3>
    <ul>
        <?php if (!empty($joined_classes)): ?>
            <?php foreach ($joined_classes as $class): ?>
                <li>
                    <strong><?php echo htmlspecialchars($class['class_subject']); ?></strong>
                    (Section: <?php echo htmlspecialchars($class['class_section']); ?>, Code: <?php echo htmlspecialchars($class['class_code']); ?>)
                    
                    <!-- View assessments, modules, and status for this class -->
                    <a href="view_assessment_student.php?class_id=<?php echo $class['class_id']; ?>">View Assessments</a> |
                    <a href="student_modules.php?class_id=<?php echo $class['class_id']; ?>">View Modules</a> |
                    <a href="status.php?class_id=<?php echo $class['class_id']; ?>&student_id=<?php echo $student_id; ?>">Status</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>You have not joined any classes yet.</p>
        <?php endif; ?>
    </ul>

    <!-- Display single aggregated To-Do List -->
    <h3>Quest</h3>
    <ul>
        <?php if (!empty($todo_list)): ?>
            <?php foreach ($todo_list as $todo): ?>
                <li>
                    Assessment: <strong><?php echo htmlspecialchars($todo['name']); ?></strong> 
                    (Type: <?php echo htmlspecialchars($todo['type']); ?>, Time Limit: <?php echo htmlspecialchars($todo['time_limit']); ?> minutes) 
                    - Class: <?php echo htmlspecialchars($todo['class_subject']); ?>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No pending assessments at this time.</p>
        <?php endif; ?>
    </ul>
</body>
</html>
