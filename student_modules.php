<?php
require 'auth.php'; // Ensure student authentication
require 'config.php';

if (isset($_GET['view_as_teacher']) && $_GET['view_as_teacher'] == 1 && isset($_GET['class_id'])) {
    $view_as_teacher = true;
} else {
    if ($_SESSION['role'] !== 2) { // Ensure the user is a student if not viewing as a teacher
        header("Location: login.php");
        exit();
    }
    $view_as_teacher = false;
}

// if (!$view_as_teacher) {
//     $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_classes WHERE student_id = ? AND class_id = ?");
//     $stmt->execute([$_SESSION['student_id'], $class_id]);
//     $is_enrolled = $stmt->fetchColumn();

//     if (!$is_enrolled) {
//         $_SESSION['error_role'] = 'You are not enrolled in this class.';
//         header('Location: student_dashboard.php');
//         exit();
//     }
//     $class_id = $enrolled_class['class_id'];
// } else {
//     // For teachers, ensure class_id is provided and valid
//     $class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
//     if ($class_id <= 0) {
//         $_SESSION['error_role'] = 'Invalid class ID.';
//         header('Location: teacher_dashboard.php');
//         exit();
//     }
// }

// Fetch class details
$classStmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$classStmt->execute([$class_id]);
$class = $classStmt->fetch(PDO::FETCH_ASSOC);

$student_id = $_SESSION['student_id'] ?? null; // Ensure student_id is set

// Increment ach_modules_read when View Content is accessed
if (isset($_GET['module_id'])) {
    $module_id = intval($_GET['module_id']);

    if (isset($_GET['view_as_teacher']) && $_GET['view_as_teacher'] == 1) {
        // If the teacher is viewing, redirect without incrementing the count
        header("Location: student_view_module.php?module_id=$module_id&view_as_teacher=1&class_id=" . intval($_GET['class_id']));
        exit();
    } else {
        // Normal student flow with count increment
        $student_id = intval($_SESSION['student_id']);
        $updateStmt = $pdo->prepare("UPDATE student_tbl SET ach_modules_read = ach_modules_read + 1 WHERE student_id = ?");
        $updateStmt->execute([$student_id]);

        // Redirect to module view page
        header("Location: student_view_module.php?module_id=$module_id");
        exit();
    }
}


if ($view_as_teacher) {
    $class_id = intval($_GET['class_id']);
    $classStmt = $pdo->prepare("SELECT class_section, class_subject FROM class_tbl WHERE class_id = ?");
    $classStmt->execute([$class_id]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
} else {
    $classStmt = $pdo->prepare("
        SELECT c.class_section, c.class_subject 
        FROM student_classes sc 
        INNER JOIN class_tbl c ON sc.class_id = c.class_id 
        WHERE sc.student_id = ? 
        LIMIT 1");
    $classStmt->execute([$_SESSION['student_id']]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);
}


// Get class_id from the URL (GET parameter)
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
            
// Fetch the class information using the class_id
$classStmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$classStmt->execute([$class_id]);
$class = $classStmt->fetch(PDO::FETCH_ASSOC);

// Display the subject and section in the title

$moduleStmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE class_id = ? AND status = 'Published' ORDER BY created_at DESC");
$moduleStmt->execute([$class_id]);
$modules = $moduleStmt->fetchAll(PDO::FETCH_ASSOC);

$moduleImages = [
    'images/module_img.webp',
    'images/module_img1.webp',
    'images/module_img2.webp',
    'images/module_img3.webp'
];

if (!$class) {
    $_SESSION['error_role'] = 'The class you are trying to access does not exist or has been deleted.';
    header('Location: ' . ($view_as_teacher ? 'teacher_dashboard.php' : 'student_dashboard.php'));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class['class_subject']); ?> | PeerQuest</title>
    <link rel="stylesheet" href="css/student_assessment.css">

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 
<style>
    /* Separate styling for View Content button */
    .view-content-btn {
        background: #4A90E2; /* Same blue color */
        color: white;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none; /* Remove underlines */
        border: none;
        border-radius: 8px;
        display: inline-flex; /* Keep alignment same */
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.3s ease, transform 0.2s ease;
        text-decoration: none;
        padding: 15px 60px;
    }

    
    /* Hover Effect */
    .view-content-btn:hover {
        background: #24243A; /* Darker shade on hover */
        transform: translateY(-2px);
        text-decoration: none;
    }
</style>

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
        <h1 class="dashboard-title">
            <?php 
            echo htmlspecialchars($class['class_subject']) . " (" . htmlspecialchars($class['class_section']) . ") - Modules";
            ?>
    </h1>
</div>


    <!-- SIDEBAR ENDS HERE -->

    <?php if (empty($modules)): ?>
        <!-- If no modules, display empty state -->
        <div class="no-assessment-container">
            <img src="images/charaubelle/C_wink.webp" alt="No Modules" class="charaubelle-sleep">
            <p class="no-assessment-message">There are no modules available at the moment. <br> Please check back later!</p>
        </div>
    <?php else: ?>
        <div class="assessment-list">
    <?php foreach ($modules as $module): ?>
        <?php $randomImage = $moduleImages[array_rand($moduleImages)]; ?>
                <div class="assessment-card">
                    <div class="assessment-image">
                        <img src="<?php echo $randomImage; ?>" alt="Module Image">
                    </div>

            <div class="assessment-details">
                <h3 class="assessment-title"><?php echo htmlspecialchars($module['title']); ?></h3>

                <div class="assessment-meta">
                    <div class="meta-item">
                        <img src="images/icons/date_icon.webp" alt="Date Icon"> 
                        <span> Date Published: <?php echo date("F j, Y", strtotime($module['created_at'])); ?></span>

                    </div>
                </div>
            </div>

            <div class="assessment-action">
                <?php if (!empty($module['content'])): ?>
                    <?php if ($view_as_teacher): ?>
                        <a href="?module_id=<?php echo $module['module_id']; ?>&view_as_teacher=1&class_id=<?php echo $class_id; ?>" class="view-content-btn">View Content</a>
                    <?php else: ?>
                        <a href="?module_id=<?php echo $module['module_id']; ?>" class="view-content-btn">View Content</a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($module['file_path'])): ?>
                    <a href="<?php echo htmlspecialchars($module['file_path']); ?>" class="take-assessment-btn" download>Download File</a>
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