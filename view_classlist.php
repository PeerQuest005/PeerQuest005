<?php
require 'config.php';
require 'auth.php';


// Check user role and redirect accordingly
if ($_SESSION['role'] == 1) { // Role 1: Teacher
    $dashboard_link = '<p><a href="teacher_dashboard.php">Back to Dashboard</a></p>';
} elseif ($_SESSION['role'] == 2) { // Role 2: Student
    $dashboard_link = '<p><a href="student_dashboard.php">Back to Dashboard</a></p>';
} else {
    die("Access denied: Invalid user role.");
}

$class_id = $_GET['class_id'];

// Fetch the class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die("Class not found.");
}

// Fetch students who joined this class, including student_id for linking
$stmt = $pdo->prepare("
    SELECT s.student_id, CONCAT(s.student_first_name, ' ', s.student_last_name) AS full_name, s.username
    FROM student_classes sc
    JOIN student_tbl s ON sc.student_id = s.student_id
    WHERE sc.class_id = ?
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class List for <?php echo htmlspecialchars($class['class_subject']); ?></title>
</head>
<body>
    <h2>Class List for <?php echo htmlspecialchars($class['class_subject']); ?> - <?php echo htmlspecialchars($class['class_section']); ?></h2>
    
    <!-- View Leaderboards Link -->
    <form action="leaderboards.php" method="get">
        <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">
        <label for="assessment_id">View Leaderboards:</label>
        <select name="assessment_id" id="assessment_id" required>
            <option value="" disabled selected>Select Assessment</option>
            <?php
            // Fetch assessments for this class
            $stmt = $pdo->prepare("SELECT assessment_id, name FROM assessment_tbl WHERE class_id = ?");
            $stmt->execute([$class_id]);
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($assessments as $assessment): ?>
                <option value="<?php echo htmlspecialchars($assessment['assessment_id']); ?>">
                    <?php echo htmlspecialchars($assessment['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Go</button>
    </form>

    <?php if (count($students) > 0): ?>
        <ul>
            <?php foreach ($students as $student): ?>
                <li>
    <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo htmlspecialchars($student['username']); ?>)

    <button>
        <a href="status.php?student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>">Status</a>
    </button>
    - 
    <button>
        <a href="achievements.php?student_id=<?php echo $student['student_id']; ?>">Achievements</a>
    </button>
    - 
    <button>
        <a href="student_profile.php?student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>">View Profile</a>
    </button>
</li>

            <?php endforeach; ?>
        </ul>
        <?php echo $dashboard_link; ?>
    <?php else: ?>
        <p>No students have joined this class yet.</p>
    <?php endif; ?>
</body>
</html>
