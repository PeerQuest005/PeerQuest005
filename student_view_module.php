<?php
require 'auth.php'; // Ensure authentication
require 'config.php';

// Check if the user has the correct role (student or teacher)
if ($_SESSION['role'] != 1 && $_SESSION['role'] != 2) {
    $_SESSION['login_error'] = 'Access denied. You must be logged in.';
    header('Location: ./login.php');
}

// Validate and fetch the module_id from the GET request
if (!isset($_GET['module_id']) || !is_numeric($_GET['module_id'])) {
    echo "Invalid module.";
    exit();
}

$module_id = intval($_GET['module_id']);

// Prepare the query with dynamic checks for students or teachers
if ($_SESSION['role'] === 2) {
    // Student query
    $stmt = $pdo->prepare("
        SELECT m.module_id, m.title, m.content, m.created_at, m.class_id, c.class_section, c.class_subject 
        FROM modules_tbl m 
        INNER JOIN class_tbl c ON m.class_id = c.class_id 
        INNER JOIN student_classes sc ON c.class_id = sc.class_id 
        WHERE m.module_id = ? AND m.status = 'Published' AND sc.student_id = ?
    ");
    $stmt->execute([$module_id, $_SESSION['student_id']]);
} elseif ($_SESSION['role'] === 1) {
    // Teacher query
    $stmt = $pdo->prepare("
        SELECT m.module_id, m.title, m.content, m.created_at, m.class_id, c.class_section, c.class_subject 
        FROM modules_tbl m 
        INNER JOIN class_tbl c ON m.class_id = c.class_id 
        WHERE m.module_id = ? AND m.status = 'Published' AND c.teacher_id = ?
    ");
    $stmt->execute([$module_id, $_SESSION['teacher_id']]);
}


// Fetch the module details
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    echo "Module not found or access denied.";
    exit();
}

// Get the current page from the query string, default to page 1
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;

// Divide content into chunks of 600 characters
$content = str_split($module['content'], 600);
$total_pages = count($content);

// Validate the page number
if ($page < 1 || $page > $total_pages) {
    echo "Invalid page.";
    exit();
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
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['title']); ?> | PeerQuest</title>
    <link rel="stylesheet" href="css/module_view.css">
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
    <div class="container">

        <?php if ($_SESSION['role'] === 1): ?>
            <!-- Show "Go Back for Teacher" if the user is a teacher -->
            <a class="btn" href="teacher_modules.php?class_id=<?php echo $module['class_id']; ?>">Back to Modules</a>
        <?php elseif ($_SESSION['role'] === 2): ?>
            <!-- Show "Back for Student" if the user is a student -->
            <a class="btn" href="student_modules.php?class_id=<?php echo $module['class_id']; ?>">Back to Modules</a>
        <?php endif; ?>

        <h2><?php echo htmlspecialchars($module['title']); ?></h2>
        <p><strong>Class: </strong> <?php echo htmlspecialchars($module['class_subject']); ?>
            (<?php echo htmlspecialchars($module['class_section']); ?>)</p>

        <div class="module-header">
            <img src="images/charaubelle/C_module.webp" alt="Module Decor" class="module-decor">
        </div>
        <!-- Progress bar -->
        <div class="progress-container">
            <div class="progress-bar" style="width: <?php echo ($page / $total_pages) * 100; ?>%;">
                <span><?php echo round(($page / $total_pages) * 100); ?>%</span>
            </div>
        </div>

        <!-- Module content -->
        <div id="targetSection" class="content-section">
            <p><?php echo nl2br(htmlspecialchars($content[$page - 1])); ?></p>
        </div>

        <!-- Navigation buttons -->
        <div class="navigation-buttons">
            <?php if ($page > 1): ?>
                <a href="?module_id=<?php echo $module_id; ?>&page=<?php echo $page - 1; ?>" class="btn">Previous</a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?module_id=<?php echo $module_id; ?>&page=<?php echo $page + 1; ?>#targetSection"
                    class="btn">Next</a>
            <?php endif; ?>
        </div>
    </div>
</body>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var target = document.getElementById("targetSection");
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
</script>

</html>