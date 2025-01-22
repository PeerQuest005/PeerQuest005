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
        header("Location: teacher_dashboard.php");
        exit();
    }

    // Fetch the module details from the database
    $stmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE module_id = ? AND teacher_id = ?");
    $stmt->execute([$moduleId, $_SESSION['teacher_id']]);
    $module = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$module) {
        header("Location: teacher_dashboard.php");
        exit();
    }
} else {
    header("Location: teacher_dashboard.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle form submission for editing the module
    $moduleTitle = $_POST['module_title'];
    $moduleContent = $_POST['module_content'];

    $stmt = $pdo->prepare("UPDATE modules_tbl SET title = ?, content = ?, status = 'Saved' WHERE module_id = ? AND teacher_id = ?");
    $stmt->execute([$moduleTitle, $moduleContent, $moduleId, $_SESSION['teacher_id']]);

    // Set the success message and redirect to the teacher_dashboard.php page
    $message = "Module updated successfully.";
    header("Location: teacher_dashboard.php?message=" . urlencode($message));
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            font-family: 'Comic Sans MS', cursive, sans-serif;
        }
        .container {
            background: rgba(0, 0, 0, 0.7);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.5);
        }
        h2 {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.6);
        }
        .btn-primary {
            background-color: #ffcc00;
            color: #000;
            font-weight: bold;
            border: none;
            transition: transform 0.2s;
        }
        .btn-primary:hover {
            background-color: #ffd633;
            transform: scale(1.1);
        }
        .btn-secondary {
            background-color: #2a5298;
            color: #fff;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .btn-secondary:hover {
            background-color: #3b6baa;
            transform: scale(1.1);
        }
        textarea {
            background-color: #fff;
            color: #000;
            border: 2px solid #ffcc00;
            border-radius: 5px;
        }
        textarea:focus {
            border-color: #ffd633;
            outline: none;
            box-shadow: 0 0 5px #ffd633;
        }
        input[type="text"] {
            background-color: #fff;
            color: #000;
            border: 2px solid #ffcc00;
            border-radius: 5px;
        }
        input[type="text"]:focus {
            border-color: #ffd633;
            outline: none;
            box-shadow: 0 0 5px #ffd633;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Edit Module</h2>
    <p class="text-success"> <?php echo $message ?? ''; ?> </p>

    <form method="post">
        <div class="mb-3">
            <label for="module_title" class="form-label">Module Title</label>
            <input type="text" name="module_title" id="module_title" class="form-control" value="<?php echo htmlspecialchars($module['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="module_content" class="form-label">Content</label>
            <textarea name="module_content" id="module_content" class="form-control" rows="5" required><?php echo htmlspecialchars($module['content']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Update Module</button>
    </form>

    <a href="teacher_dashboard.php" class="btn btn-secondary mt-3">Back to Modules</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
