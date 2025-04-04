<?php
require 'auth.php';
require 'config.php';

// Function to extract content from .docx using ZipArchive
if (!function_exists('extractDocxContent')) {
    function extractDocxContent($file_path) {
        $content = '';
        if (file_exists($file_path)) {
            $zip = new ZipArchive;
            if ($zip->open($file_path) === TRUE) {
                // Extract word/document.xml
                $xml = $zip->getFromName('word/document.xml');
                $dom = new DOMDocument;
                @$dom->loadXML($xml);
                foreach ($dom->getElementsByTagName('p') as $paragraph) {
                    $content .= $paragraph->textContent . "\n";
                }
                $zip->close();
            } else {
                $content = "Failed to open the .docx file.";
            }
        } else {
            $content = "File not found.";
        }
        return $content;
    }
}

if (!function_exists('extractDocContent')) {
    function extractDocContent($file_path) {
        return file_exists($file_path) ? shell_exec("antiword " . escapeshellarg($file_path)) : '';
    }
}

if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

// Check if class_id is set in the URL
if (!isset($_GET['class_id']) || !is_numeric($_GET['class_id'])) {
    $_SESSION['error_role'] = 'Invalid Class ID.';
    header('Location: ./student_dashboard.php');
}

$class_id = $_GET['class_id'];

// Verify the teacher owns the class
$stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $_SESSION['teacher_id']]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

// Fetch modules for the selected class
$stmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE teacher_id = ? AND class_id = ?");
$stmt->execute([$_SESSION['teacher_id'], $class_id]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';

// Handle POST requests for modules
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['upload'])) {
        // Ask for title when uploading a file
        if (empty($_POST['module_title'])) {
            $message = "Please provide a title for the file upload.";
        } else {
            // Handle file upload
            if (isset($_FILES['module_file']) && $_FILES['module_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['module_file']['tmp_name'];
                $fileName = $_FILES['module_file']['name'];
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

                if (!in_array($fileExtension, ['doc', 'docx'])) {
                    $message = "Invalid file type. Only .doc and .docx are allowed.";
                } else {
                    $uploadDir = 'uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $filePath = $uploadDir . basename($fileName);
                    if (move_uploaded_file($fileTmpPath, $filePath)) {
                        // Extract content from the file
                        $fileContent = '';
                        if ($fileExtension === 'docx') {
                            $fileContent = extractDocxContent($filePath);
                        } else {
                            $fileContent = extractDocContent($filePath);
                        }

                        // Insert into database with title
                        $moduleTitle = $_POST['module_title'];
                        $stmt = $pdo->prepare("INSERT INTO modules_tbl (class_id, title, file_name, content, teacher_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$class_id, $moduleTitle, $fileName, $fileContent, $_SESSION['teacher_id'], 'Saved']);
                        $message = "File uploaded and content extracted successfully.";
                    } else {
                        $message = "Failed to upload the file.";
                    }
                }
            } else {
                $message = "Please select a file to upload.";
            }
        }
    } elseif (isset($_POST['create'])) {
        // Handle create from scratch
        $moduleTitle = $_POST['module_title'];
        $moduleContent = $_POST['module_content'];

        // Insert the new module into the database
        $stmt = $pdo->prepare("INSERT INTO modules_tbl (class_id, title, content, teacher_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$class_id, $moduleTitle, $moduleContent, $_SESSION['teacher_id'], 'Saved']);
        $message = "Module created successfully.";
    } elseif (isset($_POST['toggle_status'])) {
        // Handle toggle status
        $moduleId = $_POST['module_id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = ($currentStatus === 'Saved') ? 'Published' : 'Saved';
        


        // Update the status in the database
        $stmt = $pdo->prepare("UPDATE modules_tbl SET status = ? WHERE module_id = ? AND teacher_id = ?");
        $stmt->execute([$newStatus, $moduleId, $_SESSION['teacher_id']]);
        $message = "Module status updated successfully.";
    } elseif (isset($_POST['delete'])) {
        // Handle delete module
        if (isset($_POST['module_id'])) {
            $moduleId = (int)$_POST['module_id']; // Ensure it's an integer
            $stmt = $pdo->prepare("DELETE FROM modules_tbl WHERE module_id = ? AND teacher_id = ?");
            $stmt->execute([$moduleId, $_SESSION['teacher_id']]);
            $message = "Module deleted successfully.";
        } else {
            $message = "Invalid module ID.";
        }
    }
}

// Fetch updated modules
$stmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE teacher_id = ? AND class_id = ?");
$stmt->execute([$_SESSION['teacher_id'], $class_id]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modules | PeerQuest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/teacher_module.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 


</head>
<body>
<div class="sidebar">
    <div class="logo-container">
        <img src="images/logo/pq_logo.webp" class="logo" alt="PeerQuest Logo">
        <img src="images/logo/pq_white_logo_txt.webp" class="logo-text" alt="PeerQuest Logo Text">
    </div>

    <ul class="nav-links">
        <li><a href="teacher_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard"> <span>Dashboard</span></a></li>

        <?php if (isset($_GET['class_id'])): // Show these links only when viewing a class ?>
            <li><a href="view_classlist.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/class_icon.png" alt="Class List"> <span>Class List</span></a></li>
            <li><a href="teacher_modules.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/module_icon.png" alt="Modules"> <span>Modules</span></a></li>
            <li><a href="view_assessment_teacher.php?class_id=<?php echo $_GET['class_id']; ?>"><img src="images/icons/assessment_icon.png" alt="Assessments"> <span>Assessments</span></a></li>
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
        <h1 class="dashboard-title"><?php echo htmlspecialchars($class['class_subject']); ?> (<?php echo htmlspecialchars($class['class_section']); ?>) - Modules</h1>
        </div>

<div class="container">

<div class="row">
    <!-- Left: File Upload and Text Entry Section -->
    <div class="col-md-6">
        <div class="box file-upload-box">
            <ul class="nav nav-tabs" id="tabMenu">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#fileUpload">File Upload</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#textEntry">Text Entry</a>
                </li>
            </ul>

            <div class="tab-content mt-3">
                <!-- File Upload Tab -->
                <div class="tab-pane fade show active" id="fileUpload">
                    <div class="drag-drop-box">
                        <img src="images/icons/upload_icon.webp" alt="Upload Icon" class="upload-icon">
                        <p><strong>Drag and Drop files here</strong></p>
                        <p>or</p>
                        <form method="post" enctype="multipart/form-data">
                            <input type="text" name="module_title" placeholder="Module Title" class="form-control input-box" required>
                            <input type="file" name="module_file" class="form-control input-box" required>
                            <button type="submit" name="upload" class="btn btn-upload w-100">Upload File</button>
                        </form>
                    </div>
                </div>

                <!-- Text Entry Tab -->
                <div class="tab-pane fade" id="textEntry">
                    <form method="post">
                        <input type="text" name="module_title" placeholder="Module Title" class="form-control input-box" required>
                        <textarea name="module_content" placeholder="Enter Module Content" rows="5" class="form-control input-box" required></textarea>
                        <button type="submit" name="create" class="btn btn-submit w-100">Submit Module</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Lesson Modules Section -->
    <div class="col-md-6">
        <div class="box module-list-box">
            <?php if (empty($modules)): ?>
                <p class="text-center">No modules found for this class.</p>
            <?php else: ?>
                <?php foreach ($modules as $module): ?>
                    <div class="lesson-row">
                        <!-- Module Title -->
                        <div class="module-title">
                            <h3 class="module-title"><?php echo htmlspecialchars($module['title']); ?></h3>
                        </div>

                        <!-- Button Group -->
<div class="button-group">
<!-- View as Student Button (Shown only when status is Published) -->
<?php if ($module['status'] === 'Published'): ?>
            <a href="student_view_module.php?module_id=<?php echo $module['module_id']; ?>&class_id=<?php echo $class_id; ?>" class="btn-action view">
                <i class="fas fa-eye"></i>
                <span class="view-text">View as Student</span>
            </a>
        <?php endif; ?>

    <!-- Edit Button -->
    <a href="edit_modules.php?module_id=<?php echo $module['module_id']; ?>" class="btn-action edit">Edit</a>

    <!-- Publish/Unpublish -->
    <form method="POST" style="display: inline;">
        <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
        <input type="hidden" name="current_status" value="<?php echo $module['status']; ?>">
        <button type="submit" name="toggle_status" class="btn-action <?php echo $module['status'] === 'Published' ? 'unpublish' : 'publish'; ?>">
            <?php echo $module['status'] === 'Published' ? 'Unpublish' : 'Publish'; ?>
        </button>
    </form>

    <!-- Delete Button -->
    <form method="POST" action="teacher_modules.php?class_id=<?php echo $class_id; ?>" style="display: inline;" onsubmit="return confirmDelete();">
        <input type="hidden" name="module_id" value="<?php echo $module['module_id']; ?>">
        <button type="submit" name="delete" class="btn-action delete"><i class="fas fa-trash"></i></button>
    </form>
</div>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
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

function confirmDelete() {
    return confirm('Are you sure you want to delete this module?');
}


document.addEventListener("DOMContentLoaded", function () {
    const dropArea = document.querySelector(".drag-drop-box"); // Entire drag-and-drop box
    const fileInput = document.querySelector("input[name='module_file']");

    // Prevent default behavior when dragging files
    ["dragenter", "dragover", "drop"].forEach(eventName => {
        dropArea.addEventListener(eventName, function (e) {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    // Highlight the drop area on drag over
    ["dragenter", "dragover"].forEach(eventName => {
        dropArea.addEventListener(eventName, function () {
            dropArea.classList.add("highlight");
        }, false);
    });

    // Remove highlight when leaving the drop area
    ["dragleave", "drop"].forEach(eventName => {
        dropArea.addEventListener(eventName, function () {
            dropArea.classList.remove("highlight");
        }, false);
    });

    // Handle dropped files
    dropArea.addEventListener("drop", function (e) {
        e.preventDefault();
        e.stopPropagation();

        let files = e.dataTransfer.files;
        if (files.length > 0) {
            console.log("Dropped file:", files[0].name);
            fileInput.files = files; // Assign the dropped file to the input field
            
            // ✅ Auto-update the file input UI
            const fileLabel = document.querySelector(".drag-drop-box p"); 
            fileLabel.textContent = `Selected: ${files[0].name}`;
        }
    });

    // Allow clicking anywhere in the box to open the file selector
    dropArea.addEventListener("click", function () {
        fileInput.click();
    });
});


</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
