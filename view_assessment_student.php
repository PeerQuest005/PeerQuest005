<?php
require 'auth.php';
require 'config.php';

// Check if the user is a student
if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
}

// Get class_id from session or URL and validate
$class_id = $_SESSION['class_id'] ?? $_GET['class_id'] ?? null;

// Validate class_id as a positive integer
if (!$class_id || !ctype_digit((string)$class_id)) {
    $_SESSION['error_role'] = 'Invalid or missing class ID.';
    header('Location: student_dashboard.php');
    exit();
}
$class_id = (int)$class_id;

// Fetch class details (subject and section)
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT id FROM student_classes WHERE class_id = ? AND student_id = ?");
$stmt->execute([$class_id, $_SESSION['student_id']]);
$classExist = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$classExist){
    $_SESSION['error_role'] = 'You are not enrolled in the class you are trying to access.';
    header('Location: student_dashboard.php');
    exit();
}
if (!$class) {
    $_SESSION['error_role'] = 'The class you are trying to access does not exist or has been deleted.';
    header('Location: student_dashboard.php');
    exit();
}

// Fetch the student_id from the session (or database if not available in the session)
$student_id = $_SESSION['student_id'] ?? null;

if (!$student_id) {
    // Attempt to fetch student_id from the database
    $stmt = $pdo->prepare("SELECT student_id FROM student_tbl WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_id = $student['student_id'];
    } else {
        echo "Student not found.";
        exit();
    }
}

// Fetch published assessments for the specific class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE status = 'Published' AND class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getTotalPointsForMC($pdo, $assessment_id) {
    $stmt = $pdo->prepare("SELECT SUM(points) AS total_points FROM questions_mcq_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_points'] ?? 0;
}

// Fetch class details (subject and section)
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

$assessmentImages = [
    'essay' => 'images/essay_img.webp',
    'true_or_false' => 'images/Tf_img.webp',
    'recitation' => 'images/essay_img.webp',
    'multiple_choice___individual' => 'images/mcq_img.webp',
    'multiple_choice_collaborative' => 'images/mcq_collab_img.webp',
];

function checkSubmission($pdo, $student_id, $assessment_id) {
    $tables = [
        'answers_esy_tbl' => 'student_id',
        'answers_esy_collab_tbl' => 'student_id',
        'answers_mcq_tbl' => 'student_id',
        'answers_tf_tbl' => 'student_id',
        'answers_mcq_collab_tbl' => 'submitted_by'  // Assuming collaborative assessments use 'submitted_by'
    ];

    foreach ($tables as $table => $column) {
        $stmt = $pdo->prepare("SELECT 1 FROM $table WHERE $column = ? AND assessment_id = ?");
        $stmt->execute([$student_id, $assessment_id]);
        if ($stmt->fetch()) {
            return true;  // Found a submission
        }
    }

    return false;  // No submission found
}

function getQuestionCount($pdo, $assessment_id, $type) {
    $table = '';
    switch ($type) {
        case 'Essay':
        case 'Essay - Collaborative':
            $table = 'questions_esy_tbl';
            break;
        case 'True or False':
            $table = 'questions_tf_tbl';
            break;
        case 'Multiple Choice - Individual':
        case 'Multiple Choice - Collaborative':
            $table = 'questions_mcq_tbl';
            break;
        default:
            return 0; // Handle unknown types gracefully
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM $table WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] ?? 0;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
	<title>Assessments | PeerQuest</title>
    <link rel="stylesheet" href="css/student_assessment.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
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
        <li><a href="student_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard"> <span>Dashboard</span></a></li>
        <li><a href="achievements.php?student_id=<?php echo $student_id; ?>"><img src="images/achievements_white_icon.png" alt="Achievements"> <span>Achievements</span></a></li>
        <li><a href="student_modules.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/module_icon.png" alt="Modules"> <span>Modules</span></a></li>
            <li><a href="view_assessment_student.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/assessment_icon.png" alt="Assessments"> <span>Assessments</span></a></li>
            <li><a href="status.php?class_id=<?php echo urlencode($class_id); ?>"><img src="images/icons/status.png" alt="My Status"> <span>My Status</span></a></li>
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
            (<?php echo htmlspecialchars($class['class_section']); ?>) - Assessments</h1>
    </div>

    <!-- SIDEBAR ENDS HERE -->

    <?php if (empty($assessments)): ?>
        <div class="no-assessment-container">
            
            <img src="images/charaubelle/C_asleep.webp" alt="Charaubelle Sleeping" class="charaubelle-sleep">
            <p class="no-assessment-message">No assessments for now. <br> Enjoy a well-deserved break!</p>
        </div>
    <?php else: ?>
        <div class="assessment-list">
            <?php foreach ($assessments as $assessment): ?>
                <?php
                // Determine the correct image for the assessment type
                $assessmentType = strtolower(str_replace([' ', '-'], '_', trim($assessment['type'])));
                $imageSrc = $assessmentImages[$assessmentType] ?? 'images/default_assessment_img.webp';

                // Check if the student has already attempted this assessment
                $attempted = checkSubmission($pdo, $student_id, $assessment['assessment_id']);
                $questionCount = getQuestionCount($pdo, $assessment['assessment_id'], $assessment['type']);
                ?>
                <div class="assessment-card">
                    <div class="assessment-image">
                        <img src="<?php echo $imageSrc; ?>" alt="Assessment Image">
                    </div>
                    <div class="assessment-details">
                        <h3 class="assessment-title"><?php echo htmlspecialchars($assessment['name']); ?></h3>
                        <span class="assessment-type"> Assessment Type:</span>
                        <span class="assessment-mode"><?php echo strtoupper($assessment['type']); ?></span>

                        <div class="assessment-meta">
                            <div class="meta-item">
                                <img src="images/icons/clock_icon.webp" alt="Time Icon">
                                <span><?php echo $assessment['time_limit']; ?> minutes</span>
                            </div>
                            <div class="meta-item">
                                <img src="images/icons/points_icon.webp" alt="Points Icon">
                                <span><?php echo $assessment['total_points']; ?> Points</span>
                            </div>
                            <div class="meta-item">
                                <img src="images/icons/questions_icon.webp" alt="Question Icon">
                                <span><?php echo $questionCount; ?> questions</span>
                            </div>
                        </div>
                    </div>
                    <div class="assessment-action">
                        <?php if ($attempted): ?>
                            <button class="completed-btn" disabled> Completed</button>
                        <?php else: ?>
                            <form action="charaubelle_dialogue_assessment.php" method="get">
                                <input type="hidden" name="assessment_id" value="<?php echo $assessment['assessment_id']; ?>">
                                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">  <!-- Add this line -->
                                <button type="submit" class="take-assessment-btn">Take Assessment</button>
                            </form>
                        <?php endif; ?>

                       <!-- Leaderboards Button -->
                        <?php if ($attempted): ?>
                            <form action="leaderboards.php" method="get">
                                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">
                                <input type="hidden" name="class_subject" value="<?php echo htmlspecialchars($class['class_subject']); ?>">
                                <input type="hidden" name="class_section" value="<?php echo htmlspecialchars($class['class_section']); ?>">
                                <input type="hidden" name="assessment_id" value="<?php echo $assessment['assessment_id']; ?>">
                                <button type="submit" class="leaderboard-btn">Check Leaderboards</button>
                            </form>
                        <?php else: ?>
                            <button class="leaderboard-btn" disabled>Check Leaderboards</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
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
</script>

</body>
</html>
