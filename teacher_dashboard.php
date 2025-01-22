
<?php 
require 'auth.php';
require 'config.php';

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    echo "Access denied: Teachers only.";
    exit();
}

$teacher_id = $_SESSION['teacher_id']; // Use the teacher_id from the session
$message = '';

// Handle Create Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create'])) {
    $school_id = $_SESSION['school_id'];
    $subject = $_POST['subject'];
    $class_section = $_POST['class_section'];
    $class_code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // Insert class with the correct teacher_id
    $stmt = $pdo->prepare("INSERT INTO class_tbl (class_section, class_subject, class_code, teacher_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$class_section, $subject, $class_code, $teacher_id]);
    $message = "Class created successfully with code: $class_code.";

    // Redirect to prevent form resubmission
    header("Location: teacher_dashboard.php");
    exit();
}

// Handle Edit Request
if (isset($_GET['edit'])) {
    $class_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    $editClass = $stmt->fetch();
}

// Handle Update Request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $class_id = $_POST['class_id'];
    $subject = $_POST['subject'];
    $class_section = $_POST['class_section'];

    $stmt = $pdo->prepare("UPDATE class_tbl SET class_section = ?, class_subject = ? WHERE class_id = ? AND teacher_id = ?");
    $stmt->execute([$class_section, $subject, $class_id, $teacher_id]);
    $message = "Class updated successfully.";

    // Redirect to prevent form resubmission
    header("Location: teacher_dashboard.php");
    exit();
}

// Handle Delete Request
if (isset($_GET['delete'])) {
    $class_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    $message = "Class deleted successfully.";

    // Redirect to prevent URL parameter resubmission
    header("Location: teacher_dashboard.php");
    exit();
}

// Fetch all sections created by this teacher using teacher_id
$sections = $pdo->prepare("SELECT * FROM class_tbl WHERE teacher_id = ?");
$sections->execute([$teacher_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            font-family: 'Comic Sans MS', cursive, sans-serif;
        }
        .container {
            background: rgba(0, 0, 0, 0.8);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.5);
        }
        h2, h3 {
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.6);
        }
        .btn-primary, .btn-secondary {
            background-color: #ffcc00;
            color: #000;
            font-weight: bold;
            border: none;
            transition: transform 0.2s;
        }
        .btn-primary:hover, .btn-secondary:hover {
            background-color: #ffd633;
            transform: scale(1.1);
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            background: rgba(255, 255, 255, 0.1);
            margin: 10px 0;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }
        li a {
            color: #ffd633;
            font-weight: bold;
            text-decoration: none;
            margin-right: 10px;
        }
        li a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Teacher's Dashboard</h2>
        <p>Welcome to the teacher's dashboard, <strong><?php echo htmlspecialchars($_SESSION['username']); ?>!</strong></p>
        <p><a href="logout.php" class="btn btn-secondary">Logout</a></p>

        <!-- Display success/error message -->
        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Create/Edit Class Form -->
        <h3><?php echo isset($editClass) ? "Edit Class" : "Create a New Class"; ?></h3>
        <form method="post" action="teacher_dashboard.php" class="mb-4">
            <input type="hidden" name="class_id" value="<?php echo isset($editClass) ? htmlspecialchars($editClass['class_id']) : ''; ?>">
            <div class="mb-3">
                <label for="class_section" class="form-label">Class Section:</label>
                <input type="text" id="class_section" name="class_section" class="form-control" required value="<?php echo isset($editClass) ? htmlspecialchars($editClass['class_section']) : ''; ?>">
            </div>
            <div class="mb-3">
                <label for="subject" class="form-label">Subject:</label>
                <input type="text" id="subject" name="subject" class="form-control" required value="<?php echo isset($editClass) ? htmlspecialchars($editClass['class_subject']) : ''; ?>">
            </div>
            <div class="d-flex justify-content-between">
                <button type="submit" name="<?php echo isset($editClass) ? 'update' : 'create'; ?>" class="btn btn-primary">
                    <?php echo isset($editClass) ? "Update Class" : "Create Class"; ?>
                </button>
                <?php if (isset($editClass)): ?>
                    <button type="button" class="btn btn-secondary" onclick="history.back()">Back</button>
                <?php endif; ?>
            </div>
        </form>

        <!-- Display List of Sections Created by the Teacher -->
        <h3>Your Classes</h3>
        <ul>
            <?php foreach ($sections as $class): ?>
                <li>
                    <strong><?php echo htmlspecialchars($class['class_subject']); ?></strong>
                    (<a href="javascript:void(0);" onclick="copyToClipboard('<?php echo htmlspecialchars($class['class_code']); ?>')">
                        Code: <?php echo htmlspecialchars($class['class_code']); ?>
                    </a>, 
                    Section: <?php echo htmlspecialchars($class['class_section']); ?>)
                    <a href="teacher_dashboard.php?edit=<?php echo $class['class_id']; ?>">Edit</a>
                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $class['class_id']; ?>)">Delete</a>
                    <a href="view_classlist.php?class_id=<?php echo $class['class_id']; ?>">View Class List</a>
                    <a href="view_assessment_teacher.php?class_id=<?php echo $class['class_id']; ?>">View Assessment</a>
                    <a href="teacher_modules.php?class_id=<?php echo $class['class_id']; ?>">View Modules</a>
                </li>
            <?php endforeach; ?>
        </ul>

        <script>
            function confirmDelete(class_id) {
                if (confirm("Are you sure you want to delete this class/subject? All data including student's activity will be deleted.")) {
                    window.location.href = 'teacher_dashboard.php?delete=' + class_id;
                }
            }
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('Copied to clipboard: ' + text);
                }).catch(function(err) {
                    alert('Failed to copy: ' + err);
                });
            }
        </script>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
