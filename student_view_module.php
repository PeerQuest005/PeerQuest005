<?php
require 'auth.php'; // Ensure student authentication
require 'config.php';

if ($_SESSION['role'] !== 2) { // Assuming 2 is the role ID for students
    header("Location: login.php");
    exit();
}

// Validate and fetch the module_id from the GET request
if (!isset($_GET['module_id']) || !is_numeric($_GET['module_id'])) {
    echo "Invalid module.";
    exit();
}

$module_id = intval($_GET['module_id']);

// Fetch the module details for the provided module_id
$stmt = $pdo->prepare("SELECT m.title, m.content, m.created_at, c.class_section, c.class_subject FROM modules_tbl m INNER JOIN class_tbl c ON m.class_id = c.class_id INNER JOIN student_classes sc ON c.class_id = sc.class_id WHERE m.module_id = ? AND m.status = 'Published' AND sc.student_id = ?");
$stmt->execute([$module_id, $_SESSION['student_id']]);
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    echo "Module not found or access denied.";
    exit();
}

// Get the current page from the query string, default to page 1
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;

// Divide content into chunks of 300 characters
$content = str_split($module['content'], 600);
$total_pages = count($content);

// Validate the page number
if ($page < 1 || $page > $total_pages) {
    echo "Invalid page.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        margin: 0; /* Remove default margin */
        padding: 0; /* Remove default padding */
        min-height: 100vh; /* Ensure the body covers the full viewport height */
        background: url('images/module_background.svg') no-repeat center center fixed;
        background-size: cover; /* Ensure the image covers the entire background */
        color: #fff;
        font-family: 'Comic Sans MS', cursive, sans-serif;
    }
    .container {
        max-width: 900px; /* Keep content width manageable */
        background: rgba(0, 0, 0, 0.8);
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.5);
        margin: 5% auto; /* Center the container vertically and horizontally */
    }
    h2 {
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.6);
    }
    .btn-secondary {
        background-color: #ffcc00;
        color: #000;
        font-weight: bold;
        border: none;
        transition: transform 0.2s;
    }
    .btn-secondary:hover {
        background-color: #ffd633;
        transform: scale(1.1);
    }
    hr {
        border-top: 3px solid #ffcc00;
    }
    p {
        font-size: 1.1rem;
        line-height: 1.6;
    }
    .progress {
        background-color: #fff;
    }
    .progress-bar {
        background-color: #ffcc00;
    }
</style>

</head>
<body>
<div class="container mt-5">
    <h2><?php echo htmlspecialchars($module['title']); ?></h2>
    <p><strong>Class Section:</strong> <?php echo htmlspecialchars($module['class_section']); ?></p>
    <p><strong>Class Subject:</strong> <?php echo htmlspecialchars($module['class_subject']); ?></p>
    <p><strong>Created At:</strong> <?php echo htmlspecialchars($module['created_at']); ?></p>
    <hr>
    <div class="progress mt-3">
        <div class="progress-bar position-relative" role="progressbar" style="width: <?php echo ($page / $total_pages) * 100; ?>%; background-color: #ffcc00; color: black; text-align: right;" aria-valuenow="<?php echo $page; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_pages; ?>">
            <span class="position-absolute end-0 pe-2" style="font-weight: bold;"><?php echo round(($page / $total_pages) * 100); ?>%</span>
        </div>
    </div>
    <div>
        <p><?php echo nl2br(htmlspecialchars($content[$page - 1])); ?></p>
    </div>
    <div class="d-flex justify-content-between mt-4">
        <?php if ($page > 1): ?>
            <a href="?module_id=<?php echo $module_id; ?>&page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?module_id=<?php echo $module_id; ?>&page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>
    </div>
    <a href="student_modules.php" class="btn btn-secondary mt-3">Back to Modules</a>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
