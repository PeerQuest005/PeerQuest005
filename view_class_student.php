<?php
require 'auth.php';
require 'config.php';

session_start(); // Ensure session is started

if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Students Only.';
    header('Location: ./student_dashboard.php');
}

$class_id = $_GET['class_id'] ?? null;
$student_id = $_SESSION['student_id'] ?? null; // Ensure student_id is set

$stmt = $pdo->prepare("SELECT id FROM student_classes WHERE class_id = ? AND student_id = ?");
$stmt->execute([$_GET['class_id'], $_SESSION['student_id']]);
$classExist = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$classExist){
    $_SESSION['error_role'] = 'You are not enrolled in the class you are trying to access.';
    header('Location: student_dashboard.php');
    exit();
}

// Debugging: Check if class_id is being passed correctly
if (!$class_id) {
    die("Error: Invalid class ID. Debug: class_id is missing.");
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

// Debugging: Check if the class details are fetched
if (!$class) {
    die("Error: Class not found. Debug: class_id = " . htmlspecialchars($class_id));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['class_subject']); ?> | PeerQuest</title>
    <link rel="stylesheet" href="css/viewclass.css">
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
        <?php
        if (isset($_SESSION['error_role'])) {
            echo '<div id="error-message" class="error-message hidden">' . $_SESSION['error_role'] . '</div>';
            unset($_SESSION['error_role']); // Clear the error after displaying
        }
        ?>
        <div class="top-bar">

        <h1 class="dashboard-title"><?php echo htmlspecialchars($class['class_subject']); ?>
        (<?php echo htmlspecialchars($class['class_section']); ?>) - Class</h1>
            </div>

        <!-- BANNER SECTION -->
<div class="banner-container">
    <div class="banner-content">
        <h2>Welcome to Your Class, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>Access your learning materials, complete assessments, and track your progress. <br>
        Stay engaged and collaborate with your classmates for an interactive learning experience.</p>
    </div>
    <img src="images/book.webp" alt="Book Illustration" class="banner-book">
</div>

<!-- QUICK ACTION CARDS -->
<div class="quick-actions-container">

    <!-- My Status Card -->
    <div class="quick-action-card">
        <img src="images/status.webp" alt="My Status" class="card-icon">
        <h3>Status</h3>
        <p>Check your grades and track your progress in this class.</p>
        <a href="status.php?class_id=<?php echo $class_id; ?>" class="btn-action">View Status</a>
    </div>


    <!-- Take Assessments Card -->
    <div class="quick-action-card">
        <img src="images/assessment.webp" alt="Take Assessments" class="card-icon">
        <h3>Assessments</h3>
        <p>Test your knowledge and track your performance.</p>
        <a href="view_assessment_student.php?class_id=<?php echo $class_id; ?>" class="btn-action">View Assessments</a>
    </div>

    <!-- Access Modules Card -->
    <div class="quick-action-card">
        <img src="images/module.webp" alt="View Modules" class="card-icon">
        <h3>Learning Modules</h3>
        <p>Review important lessons and study materials anytime.</p>
        <a href="student_modules.php?class_id=<?php echo $class_id; ?>" class="btn-action">View Modules</a>
    </div>
</div>

    </div>

        <!-- SIDEBAR ENDS HERE -->


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