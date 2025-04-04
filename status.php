<?php
session_start();
require_once 'config.php'; // Ensure database connection is established

// Ensure user is logged in
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    $_SESSION['login_error'] = 'Access denied. You must be logged in.';
    header('Location: ./login.php');
    exit();
}

// Get logged-in user details
$class_id = isset($_GET['class_id']) ? (int) $_GET['class_id'] : null;
$logged_in_student_id = $_SESSION['student_id'] ?? null; // Logged-in student ID
$teacher_id = $_SESSION['teacher_id'] ?? null; // Logged-in teacher ID
$user_role = $_SESSION['role']; // 1 = Teacher, 2 = Student

// Ensure a valid class ID is provided
if (!$class_id) {
    echo "Class ID is missing.";
    exit();
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    $_SESSION['error_role'] = 'The class you are trying to access does not exist or has been deleted.';
    header('Location: ' . ($user_role == 1 ? 'teacher_dashboard.php' : 'student_dashboard.php'));
    exit();
}

// Determine if the user is a teacher or a student
$is_teacher = ($user_role == 1);
$is_student = ($user_role == 2);

// Handle student ID access restriction
if ($is_student) {
    // Force student to only access their own status
    $student_id = $logged_in_student_id;
} elseif ($is_teacher) {
    // Teachers can view any student ID
    $student_id = isset($_GET['student_id']) ? (int) $_GET['student_id'] : null;
}

// If no valid student ID is found, deny access
if (!$student_id) {
    $_SESSION['error_role'] = 'Invalid access.';
    header('Location: ' . ($is_teacher ? 'teacher_dashboard.php' : 'student_dashboard.php'));
    exit();
}

// Check if the student is enrolled in the class
$stmt = $pdo->prepare("SELECT id FROM student_classes WHERE class_id = ? AND student_id = ?");
$stmt->execute([$class_id, $student_id]);
$classExist = $stmt->fetch(PDO::FETCH_ASSOC);

// If the student is not enrolled, deny access
if (!$classExist) {
    $_SESSION['error_role'] = 'You are not enrolled in this class.';
    header('Location: ./student_dashboard.php');
    exit();
}

$show_answers = $_GET['show_answers'] ?? null;
$selected_assessment_id = $_GET['assessment_id'] ?? null;
$student_id = $_SESSION['student_id'] ?? $_GET['student_id'] ?? null;
$class_id = $_GET['class_id'] ?? null;

// Debugging: Print values to verify
if (!$student_id || !$class_id) {
    die("DEBUG: class_id = " . htmlspecialchars($class_id) . ", student_id = " . htmlspecialchars($student_id));
}

// Fetch student details
$stmt = $pdo->prepare("SELECT CONCAT(student_first_name, ' ', student_last_name) AS full_name 
                       FROM student_tbl 
                       WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found in the selected class.");
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die("Class not found.");
}

// Fetch all assessments related to the selected class
$stmt = $pdo->prepare("
    SELECT *
    FROM assessment_tbl
    WHERE class_id = :class_id
    AND status = 'Published'
");
$stmt->execute(['class_id' => $class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student's answers and grades for each assessment related to the selected class
// Updated to include the new MCQ Collab assessment type.
$stmt = $pdo->prepare("SELECT assessment_id, MAX(attempt) AS max_attempt, SUM(grade) AS total_grade FROM (
    SELECT assessment_id, attempt, grade FROM answers_esy_tbl WHERE student_id = ? 
    UNION ALL
    SELECT assessment_id, attempt, grades AS grade FROM answers_esy_collab_tbl WHERE student_id = ? 
    UNION ALL
    SELECT assessment_id, attempt, correct_answer AS grade FROM answers_mcq_tbl WHERE student_id = ? 
    UNION ALL
    SELECT assessment_id, attempt, grades AS grade FROM answers_mcq_collab_tbl WHERE submitted_by = ? 
    UNION ALL
    SELECT assessment_id, attempt, correct_answer AS grade FROM answers_tf_tbl WHERE student_id = ?
) AS combined_answers WHERE assessment_id IN (SELECT assessment_id FROM assessment_tbl WHERE class_id = ?) 
GROUP BY assessment_id");
$stmt->execute([$student_id, $student_id, $student_id, $student_id, $student_id, $class_id]);
$student_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate the overall grade
$student_answers_map = [];
$total_grade = 0;
$total_points = 0;

foreach ($student_answers as $answer) {
    $student_answers_map[$answer['assessment_id']] = [
        'max_attempt' => $answer['max_attempt'],
        'total_grade' => $answer['total_grade']
    ];
    $total_grade += $answer['total_grade'];
}

foreach ($assessments as $assessment) {
    $total_points += $assessment['total_points'];
}

$overall_grade = $total_points > 0 ? round(($total_grade / $total_points) * 100) : 0;

// Fetch answers if specific assessment is selected
$answers = [];
$selected_total_points = 0;
if ($show_answers && $selected_assessment_id) {
    // Updated query includes MCQ Collab answers.
    $stmt = $pdo->prepare("SELECT question_text, answer_text, grade, correct_option, correct_answer FROM (
        SELECT qt.question_text, ae.answer_text, ae.grade, NULL AS correct_option, NULL AS correct_answer 
        FROM answers_esy_tbl ae
        JOIN questions_esy_tbl qt ON ae.question_id = qt.question_id
        JOIN assessment_tbl at ON at.assessment_id = ae.assessment_id
        WHERE ae.assessment_id = ? AND ae.student_id = ? AND at.class_id = ?
        UNION ALL
        SELECT qt.question_text, aec.answer AS answer_text, aec.grades AS grade, NULL AS correct_option, NULL AS correct_answer 
        FROM answers_esy_collab_tbl aec
        JOIN questions_esy_tbl qt ON aec.question_id = qt.question_id
        JOIN assessment_tbl at ON at.assessment_id = aec.assessment_id
        WHERE aec.assessment_id = ? AND aec.student_id = ? AND at.class_id = ?
        UNION ALL
        SELECT qt.question_text, am.selected_option AS answer_text, am.correct_answer AS grade, qt.correct_option, NULL AS correct_answer 
        FROM answers_mcq_tbl am
        JOIN questions_mcq_tbl qt ON am.question_id = qt.question_id
        JOIN assessment_tbl at ON at.assessment_id = am.assessment_id
        WHERE am.assessment_id = ? AND am.student_id = ? AND at.class_id = ?
        UNION ALL
        SELECT qt.question_text, mcc.selected_option AS answer_text, mcc.grades AS grade, qt.correct_option, NULL AS correct_answer 
        FROM answers_mcq_collab_tbl mcc
        JOIN questions_mcq_tbl qt ON mcc.question_id = qt.question_id
        JOIN assessment_tbl at ON at.assessment_id = mcc.assessment_id
        WHERE mcc.assessment_id = ? AND mcc.submitted_by = ? AND at.class_id = ?
        UNION ALL
        SELECT qt.question_text, atf.answer_text, atf.correct_answer AS grade, NULL AS correct_option, qt.correct_answer 
        FROM answers_tf_tbl atf
        JOIN questions_tf_tbl qt ON atf.question_id = qt.question_id
        JOIN assessment_tbl at2 ON at2.assessment_id = atf.assessment_id
        WHERE atf.assessment_id = ? AND atf.student_id = ? AND at2.class_id = ?
    ) AS all_answers");
    
    $stmt->execute([
        // For answers_esy_tbl:
        $selected_assessment_id, $student_id, $class_id,
        // For answers_esy_collab_tbl:
        $selected_assessment_id, $student_id, $class_id,
        // For answers_mcq_tbl:
        $selected_assessment_id, $student_id, $class_id,
        // For answers_mcq_collab_tbl:
        $selected_assessment_id, $student_id, $class_id,
        // For answers_tf_tbl:
        $selected_assessment_id, $student_id, $class_id
    ]);
    
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch total points for the selected assessment
    $stmt = $pdo->prepare("SELECT total_points FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$selected_assessment_id]);
    $selected_total_points = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <link rel="stylesheet" href="css/status.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <title>Status of <?php echo htmlspecialchars($student['full_name']); ?> | PeerQuest</title>
   
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>

<!-- SIDEBAR BEGINS HERE -->
<div class="sidebar">
    <div class="logo-container">
        <img src="images/logo/pq_logo.webp" class="logo" alt="PeerQuest Logo">
        <img src="images/logo/pq_white_logo_txt.webp" class="logo-text" alt="PeerQuest Logo Text">
    </div>

    <ul class="nav-links">
    <?php if ($_SESSION['role'] === 1): ?>
    <!-- Show "Go Back for Teacher" if the user is a teacher -->
        <li><a href="teacher_dashboard.php" ><img src="images/Home_white_icon.png" alt="Dashboard"><span>Dashboard</span></a></li>
    <?php elseif ($_SESSION['role'] === 2): ?>
    <!-- Show "Back for Student" if the user is a student -->
       <li><a href="student_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard"> <span>Dashboard</span></a></li> 
       <li><a href="achievements.php?student_id=<?php echo $student_id; ?>"><img src="images/achievements_white_icon.png" alt="Achievements"> <span>Achievements</span></a></li>
       <li><a href="student_modules.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/module_icon.png" alt="Modules"> <span>Modules</span></a></li>
       <li><a href="view_assessment_student.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/assessment_icon.png" alt="Assessments"> <span>Assessments</span></a></li>
       <li><a href="status.php?class_id=<?php echo urlencode($class_id); ?>"><img src="images/icons/status.png" alt="My Status"> <span>My Status</span></a></li>
    <?php endif; ?>
    </ul>

    <div class="logout">
        <a href="logout.php" class="logout-btn">
            <img src="images/logout_white_icon.png" alt="Logout"> <span>LOG OUT</span>
        </a>
    </div>
</div>

<button class="toggle-btn" onclick="toggleSidebar()">
    <img src="images/sidebar_close_icon.png" id="toggleIcon" alt="Toggle Sidebar">
</button>

<div class="content">
    <div class="top-bar">
        <h1 class="dashboard-title"><?php echo htmlspecialchars($class['class_subject']); ?>
            (<?php echo htmlspecialchars($class['class_section']); ?>) - Status</h1>
    </div>

    <!-- SIDEBAR ENDS HERE -->

    <?php if ($_SESSION['role'] == 1): // Role 1 = Teacher ?>
        <!-- Teacher-specific content could be added here -->
    <?php else: // For students ?>
        <p><a href="student_dashboard.php"></a></p>
    <?php endif; ?>

    <?php if (!$show_answers): ?>
        <div class="card d-flex">
            <h3>Overall Grade</h3>
            <div class="circular-progress">
                <span><?php echo $overall_grade; ?>%</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
    <?php if (!$show_answers): ?>
        <?php if (count($assessments) > 0): ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>Assessment Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Total Grade</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($assessments as $assessment): ?>
                    <?php
                    $assessment_id = $assessment['assessment_id'];
                    $total_grade = $student_answers_map[$assessment_id]['total_grade'] ?? 0;
                    $max_attempt = $student_answers_map[$assessment_id]['max_attempt'] ?? 0;
                    $status = $max_attempt > 0 ? 'Completed' : 'Not Attempted';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                        <td><?php echo htmlspecialchars($assessment['type']); ?></td>
                        <td><?php echo $status; ?></td>
                        <td><?php echo $total_grade; ?> / <?php echo $assessment['total_points']; ?></td>
                        <td><a href="?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&show_answers=true&assessment_id=<?php echo $assessment_id; ?>">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No assessments found for this class.</p>
        <?php endif; ?>
    <?php else: ?>
        <h3>Assessment Details</h3>
        <table border="1">
            <thead>
                <tr>
                    <th>Question Text</th>
                    <th>Answer</th>
                    <th>Grade</th>
                    <?php if ($answers && isset($answers[0]['correct_option']) && !is_null($answers[0]['correct_option'])): ?>
                        <th>Correct Option</th>
                    <?php elseif ($answers && isset($answers[0]['correct_answer']) && !is_null($answers[0]['correct_answer'])): ?>
                        <th>Correct Answer</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($answers as $answer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($answer['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($answer['answer_text']); ?></td>
                        <td><?php echo htmlspecialchars($answer['grade']); ?></td>
                        <?php if (isset($answer['correct_option']) && !is_null($answer['correct_option'])): ?>
                            <td><?php echo htmlspecialchars($answer['correct_option']); ?></td>
                        <?php elseif (isset($answer['correct_answer']) && !is_null($answer['correct_answer'])): ?>
                            <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p>Total Grade: <?php echo array_sum(array_column($answers, 'grade')); ?> / <?php echo $selected_total_points; ?></p>
    <?php endif; ?>
    </div>
<script> 
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('collapsed');
        document.querySelector('.content').classList.toggle('expanded');
        document.querySelector('.top-bar').classList.toggle('expanded');
        const toggleIcon = document.getElementById('toggleIcon');
        if (document.querySelector('.sidebar').classList.contains('collapsed')) {
            toggleIcon.src = "images/sidebar_open_icon.png";
        } else {
            toggleIcon.src = "images/sidebar_close_icon.png";
        }
    }

    function updateDate() {
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        const currentDate = new Date().toLocaleDateString('en-PH', options);
        document.getElementById('currentDate').textContent = currentDate;
    }
    updateDate();
</script>

</body>
</html>
