<?php
require 'auth.php';
require 'config.php';

if ($_SESSION['role'] !== 1) {
    header("Location: login.php");
    exit();
}

// Fetch module data for editing
if (isset($_GET['module_id'])) {
    $moduleId = $_GET['module_id'];

    if (!is_numeric($moduleId)) {
        header("Location: teacher_modules.php");
        exit();
    }

    // Fetch the module details from the database
    $stmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE module_id = ? AND teacher_id = ?");
    $stmt->execute([$moduleId, $_SESSION['teacher_id']]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        header("Location: teacher_modules.php");
        exit();
    }

    // Fetch class_id from the current module
    $class_id = $module['class_id'];
} else {
    header("Location: teacher_modules.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle form submission for editing the module
    $moduleTitle = $_POST['module_title'];
    $moduleContent = $_POST['module_content'];

    $stmt = $pdo->prepare("UPDATE modules_tbl SET title = ?, content = ?, status = 'Saved' WHERE module_id = ? AND teacher_id = ?");
    $stmt->execute([$moduleTitle, $moduleContent, $moduleId, $_SESSION['teacher_id']]);

    // Set the success message and redirect to the modules page
    $message = "Module updated successfully.";
    header("Location: teacher_modules.php?class_id={$class_id}&message=" . urlencode($message));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Module - <?php echo htmlspecialchars($module['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #F7F7F7;
            font-family: 'Inter', sans-serif;
        }

        .edit-module-container {
            max-width: 1200px;
            margin: 60px auto;
            background-color: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .edit-module-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #24243A;
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #24243A;
        }

        .form-control {
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 12px;
            font-size: 1rem;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            margin-top: 20px;
        }

        .btn-update, .btn-back {
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 8px;
            text-align: center;
            border: none;
        }

        .btn-update {
            background-color: #47A99C;
            color: white;
        }

        .btn-update:hover {
            background-color: #3e8b7e;
        }

        .btn-back {
            background-color: #dcdcdcb4;
            color: #24243A;
        }

        .btn-back:hover {
            background-color: #1c1c2c;
            color: #ffffff;
        }
    </style>
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>

<div class="edit-module-container">
    <h1 class="edit-module-title">
        Edit Module <span class="module-title-dynamic">- <?php echo htmlspecialchars($module['title']); ?></span>
    </h1>

    <?php if ($message): ?>
        <p class="text-success"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label for="moduleTitle" class="form-label">Module Title</label>
            <input type="text" id="moduleTitle" name="module_title" value="<?php echo htmlspecialchars($module['title']); ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="moduleContent" class="form-label">Content</label>
            <textarea id="moduleContent" name="module_content" class="form-control" rows="5" required><?php echo htmlspecialchars($module['content']); ?></textarea>
        </div>

        <div class="btn-group">
            <button type="submit" name="update" class="btn btn-update">Update Module</button>
            <a href="teacher_modules.php?class_id=<?php echo $class_id; ?>" class="btn btn-back">Back to Modules</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
