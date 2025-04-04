<?php
require 'config.php';
require 'auth.php';

// Ensure user is logged in
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    $_SESSION['login_error'] = 'Access denied. You must be logged in.';
    header('Location: ./login.php');
    exit();
}

// Get `assessment_id` and `class_id` from query parameters
$assessment_id = $_GET['assessment_id'] ?? null;
$class_id = $_GET['class_id'] ?? null;

// Validate input
if (!$assessment_id || !$class_id) {
    echo "Invalid or missing assessment ID or class ID.";
    exit();
}

$logged_in_student_id = $_SESSION['student_id'] ?? null; // Logged-in student ID
$teacher_id = $_SESSION['teacher_id'] ?? null; // Logged-in teacher ID
$is_teacher = ($_SESSION['role'] == 1);
$is_student = ($_SESSION['role'] == 2);

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    $_SESSION['error_role'] = 'The class you are trying to access does not exist.';
    header('Location: ' . ($is_teacher ? 'teacher_dashboard.php' : 'student_dashboard.php'));
    exit();
}

// Fetch assessment name
$stmt = $pdo->prepare("SELECT name FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
$assessment_name = $assessment['name'] ?? 'Unknown Assessment';

// Role-Based Access Control
$authorized = false;

// If a student, check if they are enrolled in the class
if ($is_student) {
    $stmt = $pdo->prepare("SELECT id FROM student_classes WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$class_id, $logged_in_student_id]);
    $classExist = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($classExist) {
        $authorized = true;
    }
}

// If a teacher, allow access
if ($is_teacher) {
    $authorized = true;
}

// Redirect unauthorized users
if (!$authorized) {
    $_SESSION['error_role'] = 'Access Denied! You are not enrolled in this class.';
    header('Location: ./student_dashboard.php');
    exit();
}
// Retrieve class details and assessment ID
$class_id = $_GET['class_id'] ?? '';
$class_subject = $_GET['class_subject'] ?? 'Unknown Subject';
$class_section = $_GET['class_section'] ?? 'Unknown Section';
$assessment_id = $_GET['assessment_id'] ?? '';

// Fetch assessment name directly from the database
$stmt = $pdo->prepare("SELECT name FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
$assessment_name = $assessment['name'] ?? 'Unknown Assessment';
$stmt = $pdo->prepare("SELECT id FROM student_classes WHERE class_id = ? AND student_id = ?");
$stmt->execute([$class_id, $_SESSION['student_id']]);
$classExist = $stmt->fetch(PDO::FETCH_ASSOC);


// Ensure necessary IDs are provided
if (empty($class_id) || empty($assessment_id)) {
    die("Invalid request. Please select a valid class and assessment.");
}



// Fetch all students in the same class
$stmt = $pdo->prepare("SELECT s.student_id, s.student_first_name, s.student_last_name
                       FROM student_classes sc
                       JOIN student_tbl s ON sc.student_id = s.student_id
                       WHERE sc.class_id = ?");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize leaderboard data
$leaderboard = [];

foreach ($students as $student) {
    $student_id = $student['student_id'];
    $total_grade = 0;

    // Fetch grades for Essay
    $stmt = $pdo->prepare("SELECT SUM(grade) AS total_grade FROM answers_esy_tbl 
                           WHERE assessment_id = ? AND student_id = ?");
    $stmt->execute([$assessment_id, $student_id]);
    $essay_grade = $stmt->fetchColumn();
    $total_grade += $essay_grade ?: 0;

    // Fetch grades for Multiple Choice (Individual)
    $stmt = $pdo->prepare("SELECT SUM(correct_answer) AS total_grade FROM answers_mcq_tbl 
                           WHERE assessment_id = ? AND student_id = ?");
    $stmt->execute([$assessment_id, $student_id]);
    $mcq_grade = $stmt->fetchColumn();
    $total_grade += $mcq_grade ?: 0;

    // Fetch grades for True/False
    $stmt = $pdo->prepare("SELECT SUM(correct_answer) AS total_grade FROM answers_tf_tbl 
                           WHERE assessment_id = ? AND student_id = ?");
    $stmt->execute([$assessment_id, $student_id]);
    $tf_grade = $stmt->fetchColumn();
    $total_grade += $tf_grade ?: 0;

    // Collaborative Multiple Choice grades (optional, if applicable)
    $stmt = $pdo->prepare("SELECT SUM(grades) AS total_grade FROM answers_mcq_collab_tbl 
                           WHERE assessment_id = ? AND submitted_by = ?");
    $stmt->execute([$assessment_id, $student_id]);
    $mcq_collab_grade = $stmt->fetchColumn();
    $total_grade += $mcq_collab_grade ?: 0;

    // Add student data to the leaderboard
    $leaderboard[] = [
        'student_id' => $student_id,
        'name' => $student['student_first_name'] . ' ' . $student['student_last_name'],
        'total_grade' => $total_grade
    ];
}

// Sort leaderboard by total grades in descending order
usort($leaderboard, function ($a, $b) {
    return $b['total_grade'] <=> $a['total_grade'];
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard | PeerQuest</title>
    <link rel="stylesheet" href="css/leaderboard.css">
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>

    <div class="back-container">

        <button onclick="history.back()" class="points-badge">Go Back</button>
    </div>
    <div class="leaderboard-header">
        <h2>Class: <?php echo htmlspecialchars($class_subject); ?> (<?php echo htmlspecialchars($class_section); ?>)
        </h2>
        <h3>Assessment: <?php echo htmlspecialchars($assessment_name); ?></h3>
    </div>



    <div class="leaderboard-container">
        <img src="images/trophy.png" alt="Leaderboard Trophy" class="trophy-icon">
        <h1>Leaderboard</h1>
        <?php
        $position = 1;
        foreach ($leaderboard as $entry) {
            echo '<div class="leaderboard-entry">';

            // Position and crown container
            echo '<div class="position-container">';
            echo '<div class="position">' . $position++ . '</div>';

            // Crown for top 3 players
            if ($position == 2) {
                echo '<img src="images/m_gold.webp" alt="Gold Crown" class="crown">';
            } elseif ($position == 3) {
                echo '<img src="images/m_silver.webp" alt="Silver Crown" class="crown">';
            } elseif ($position == 4) {
                echo '<img src="images/m_bronze.webp" alt="Bronze Crown" class="crown">';
            }

            echo '</div>';  // End position-container
        
            // Player details
            echo '<div class="player-info">';
            echo '<div class="player-name">' . htmlspecialchars($entry['name']) . '</div>';
            echo '</div>';

            // Points on the right
            echo '<div class="points-badge">Points: ' . htmlspecialchars($entry['total_grade']) . '</div>';

            echo '</div>';  // End leaderboard-entry
        }
        ?>
    </div>

    </div>

</body>

</html>