<?php
require 'auth.php';
require 'config.php';

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
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

if (isset($_GET['delete'])) {
    $class_id = $_GET['delete'];

    // Delete related questions first
    $pdo->prepare("DELETE FROM questions_mcq_tbl WHERE assessment_id IN (SELECT assessment_id FROM assessment_tbl WHERE class_id = ?)")->execute([$class_id]);
    $pdo->prepare("DELETE FROM questions_esy_tbl WHERE assessment_id IN (SELECT assessment_id FROM assessment_tbl WHERE class_id = ?)")->execute([$class_id]);
    $pdo->prepare("DELETE FROM questions_tf_tbl WHERE assessment_id IN (SELECT assessment_id FROM assessment_tbl WHERE class_id = ?)")->execute([$class_id]);

    // Delete related assessments
    $pdo->prepare("DELETE FROM assessment_tbl WHERE class_id = ?")->execute([$class_id]);

    // Delete related modules
    $pdo->prepare("DELETE FROM modules_tbl WHERE class_id = ?")->execute([$class_id]);

    // Finally, delete the class
    $stmt = $pdo->prepare("DELETE FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);

    $_SESSION['message'] = "Class deleted successfully.";
    header("Location: teacher_dashboard.php");
    exit();
}


// Fetch all sections created by this teacher using teacher_id
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'asc';
$order_by = ($sort_order == 'desc') ? 'DESC' : 'ASC';

$sections = $pdo->prepare("SELECT * FROM class_tbl WHERE teacher_id = ? ORDER BY class_subject $order_by");
$sections->execute([$teacher_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);


// Handle Duplicate Request
if (isset($_GET['duplicate'])) {
    $class_id = $_GET['duplicate'];

    // Fetch the class details
    $stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
    $stmt->execute([$class_id, $teacher_id]);
    $class = $stmt->fetch();

    if ($class) {
        // Generate a new unique class code
        $new_class_code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        // Append "(Copy)" to the class subject
        $new_class_subject = $class['class_subject'] . " (Copy)";

        // Insert a duplicate class entry with a new class_code
        $stmt = $pdo->prepare("INSERT INTO class_tbl (class_section, class_subject, class_code, teacher_id) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $class['class_section'],
            $new_class_subject, // New class subject with "(Copy)"
            $new_class_code, // New class code
            $teacher_id
        ]);

        // Get the new class ID
        $new_class_id = $pdo->lastInsertId();

        // === Duplicate Assessments ===
        $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $assessment_mapping = []; // Store new assessment IDs

        foreach ($assessments as $assessment) {
            $stmt = $pdo->prepare(
                "INSERT INTO assessment_tbl (class_id, teacher_id, name, type, status, time_limit, assessment_mode, total_points, instructions) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $new_class_id,
                $teacher_id,
                $assessment['name'] ,
                $assessment['type'],
                $assessment['status'],
                $assessment['time_limit'],
                $assessment['assessment_mode'],
                $assessment['total_points'],
                $assessment['instructions']
            ]);

            $new_assessment_id = $pdo->lastInsertId();
            $assessment_mapping[$assessment['assessment_id']] = $new_assessment_id;
        }

        // === Duplicate Modules ===
        $stmt = $pdo->prepare("SELECT * FROM modules_tbl WHERE class_id = ?");
        $stmt->execute([$class_id]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($modules as $module) {
            $stmt = $pdo->prepare(
                "INSERT INTO modules_tbl (class_id, teacher_id, title, content, file_name, status) 
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $new_class_id,
                $teacher_id,
                $module['title'] ,
                $module['content'],
                $module['file_name'],
                $module['status']
            ]);
        }

        // === Duplicate Questions (For Each Assessment) ===
        foreach ($assessment_mapping as $old_assessment_id => $new_assessment_id) {
            // Duplicate MCQ Questions
            $stmt = $pdo->prepare("SELECT * FROM questions_mcq_tbl WHERE assessment_id = ?");
            $stmt->execute([$old_assessment_id]);
            $questions_mcq = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($questions_mcq as $question) {
                $stmt = $pdo->prepare(
                    "INSERT INTO questions_mcq_tbl (assessment_id, question_text, options, correct_option, points) 
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $new_assessment_id,
                    $question['question_text'],
                    $question['options'],
                    $question['correct_option'],
                    $question['points']
                ]);
            }

            // Duplicate Essay Questions
            $stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
            $stmt->execute([$old_assessment_id]);
            $questions_esy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($questions_esy as $question) {
                $stmt = $pdo->prepare(
                    "INSERT INTO questions_esy_tbl (assessment_id, question_text, question_number, points, guided_answer, correct_answer) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $new_assessment_id,
                    $question['question_text'],
                    $question['question_number'],
                    $question['points'],
                    $question['guided_answer'],
                    $question['correct_answer']
                ]);
            }

            // Duplicate True or False Questions
            $stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ?");
            $stmt->execute([$old_assessment_id]);
            $questions_tf = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($questions_tf as $question) {
                $stmt = $pdo->prepare(
                    "INSERT INTO questions_tf_tbl (assessment_id, question_text, points, guided_answer, correct_answer) 
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $new_assessment_id,
                    $question['question_text'],
                    $question['points'],
                    $question['guided_answer'],
                    $question['correct_answer']
                ]);
            }
        }

        $message = "Class, assessments, modules, and questions duplicated successfully with new class code: $new_class_code.";
        header("Location: teacher_dashboard.php?message=" . urlencode($message));
        exit();
    } else {
        echo "Class not found or you do not own this class.";
        exit();
    }
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/teacher_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
            <li><a href="teacher_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard">
                    <span>Dashboard</span></a></li>
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
            <h1 class="dashboard-title">Teacher’s Dashboard</h1>
            <div class="date-picker-wrapper">
                <p id="currentDate" class="hover-date"></p>
                <div class="calendar-overlay">
                    <div id="flatpickrCalendar"></div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR ENDS HERE -->

        <!-- Display success/error message -->
        <?php if ($message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <div class="dashboard-header">
            <h3 class="body-title"><img src="images/icons/myclasses_dark_icon.webp" alt="My Class Icon"
                    class="button-icon">My Classes </h3>
            <!-- Create Class Button -->
            <button class="create-class-btn" onclick="openCreateClassModal()">
                Create Class
                <img src="images/icons/createclass_icon.webp" alt="Create Class Icon" class="button-icon">
            </button>

        </div>

        <div class="sort-container">
            <label for="sort">Sort by Subject:</label>
            <select id="sort" onchange="sortSubjects()">
                <option value="asc" <?php echo ($sort_order == 'asc') ? 'selected' : ''; ?>>A-Z</option>
                <option value="desc" <?php echo ($sort_order == 'desc') ? 'selected' : ''; ?>>Z-A</option>
            </select>
        </div>





        <!-- Create Class Modal -->
        <div class="popup-overlay" id="createClassOverlay"></div>
        <div class="popup" id="createClassModal">
            <div class="popup-content">
                <span class="close-popup" onclick="closeCreateClassModal()">&times;</span>
                <h3>Create a New Class</h3>
                <form method="post" action="teacher_dashboard.php">
                    

                    <div class="input-group">
                        <label for="subject">Class Name:</label>
                        <input type="text" id="create_subject" name="subject" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="class_section">Class Section:</label>
                        <input type="text" id="create_class_section" name="class_section" required>
                    </div>
                    <div class="popup-actions">
                        <button type="button" class="btn-cancel" onclick="closeCreateClassModal()">Cancel</button>
                        <button type="submit" name="create" class="btn-submit">Create Class</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Class Modal -->
        <div class="popup-overlay" id="editClassOverlay"></div>
        <div class="popup" id="editClassModal">
            <div class="popup-content">
                <span class="close-popup" onclick="closeEditClassModal()">&times;</span>
                <h3>Edit Class</h3>
                <form method="post" action="teacher_dashboard.php">
                    <input type="hidden" id="edit_class_id" name="class_id">

                    <div class="input-group">
                        <label for="edit_class_section">Class Section:</label>
                        <input type="text" id="edit_class_section" name="class_section" required>
                    </div>

                    <div class="input-group">
                        <label for="edit_subject">Subject:</label>
                        <input type="text" id="edit_subject" name="subject" required>
                    </div>

                    <div class="popup-actions">
                        <button type="button" class="btn-cancel" onclick="closeEditClassModal()">Cancel</button>
                        <button type="submit" name="update" class="btn-submit">Update Class</button>
                    </div>
                </form>
            </div>
        </div>


        <?php
        $index = 0; // Initialize index for colors
        $headerColors = ['#357AC6'];
        $colorCount = count($headerColors);

        // Array of images
        $classImages = [
            'images/2.png',
            'images/3.png',
            'images/4.png',
            'images/5.png',
            'images/6.png',
            'images/7.png'
           
        ];
        $imageCount = count($classImages);
        ?>

        <div class="class-container">
            <?php foreach ($sections as $index => $class): ?>
                <div class="class-card">

                    <!-- Class Header with Dynamic Colors -->
                    <div class="class-header"
                        style="background: <?php echo $headerColors[$index % count($headerColors)]; ?>;">
                        <span class="class-title"><?php echo htmlspecialchars($class['class_subject']); ?></span>

                        <!-- Icon Container -->

                        <div class="icon-actions">
                                <!-- Duplicate Button -->
                            <a href="javascript:void(0);" class="icon-btn" 
                            onclick="confirmDuplicate(<?php echo $class['class_id']; ?>)">
                                <i class="fas fa-clone"></i> <!-- Clone Icon -->
                            </a>
                            <a href="javascript:void(0);" class="icon-btn"
                                onclick="openEditClassModal('<?php echo $class['class_id']; ?>', '<?php echo htmlspecialchars($class['class_section']); ?>', '<?php echo htmlspecialchars($class['class_subject']); ?>')">
                                <i class="fas fa-edit"></i> <!-- Edit Icon -->
                            </a>
                            <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $class['class_id']; ?>)"
                                class="icon-btn">
                                <i class="fas fa-trash"></i> <!-- Delete Icon -->
                            </a>
                        </div>
                    </div>


                    <!-- Class Body -->
                    <div class="class-body">
                        <!-- Dynamic Class Image -->
                        <img src="<?php echo $classImages[$index % $imageCount]; ?>" alt="Class Image" class="class-image">

                        <div class="class-content">
                            <div class="class-info">
                                <p>Section: <?php echo htmlspecialchars($class['class_section']); ?></p>

                                <div class="class-code-container">
                                    <p>Code:
                                        <span id="classCode-<?php echo $class['class_id']; ?>">
                                            <?php echo htmlspecialchars($class['class_code']); ?>
                                        </span>
                                        <img src="images/icons/copy_icon.webp" alt="Copy Icon" class="copy-icon"
                                            onclick="copyToClipboard('<?php echo $class['class_code']; ?>')" />
                                    </p>
                                </div>

                                <!-- Fetch and Display Total Students Joined -->
                                <?php
                                $stmt = $pdo->prepare("SELECT COUNT(*) AS total_students FROM student_classes WHERE class_id = ?");
                                $stmt->execute([$class['class_id']]);
                                $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'] ?? 0;
                                ?>
                                <p>Total Students Joined: <strong><?php echo $total_students; ?></strong></p>
                            </div>

                            <!-- Teacher Actions -->
                            <div class="class-actions">
                                <a href="view_class.php?class_id=<?php echo $class['class_id']; ?>" class="btn-action">View
                                    Class</a>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

        <script>
            function confirmDelete(class_id) {
                if (confirm("Are you sure you want to delete this class/subject? All data including student's activity will be deleted.")) {
                    window.location.href = 'teacher_dashboard.php?delete=' + class_id;
                }
            }
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function () {
                    alert('Copied to clipboard: ' + text);
                }).catch(function (err) {
                    alert('Failed to copy: ' + err);
                });
            }

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

            //calendar
            document.addEventListener("DOMContentLoaded", function () {
                const currentDate = new Date();
                const options = { weekday: "long", month: "long", day: "numeric" };
                document.getElementById("currentDate").textContent = currentDate.toLocaleDateString("en-PH", options);

                // Initialize Flatpickr calendar
                flatpickr("#flatpickrCalendar", {
                    inline: true,
                    onChange: function (selectedDates) {
                        const formattedDate = selectedDates[0].toLocaleDateString("en-PH", options);
                        document.getElementById("currentDate").textContent = formattedDate;
                    },
                });
            });

            // Create Class Modal Logic
            function openCreateClassModal() {
                document.getElementById("createClassModal").style.display = "block";
                document.getElementById("createClassOverlay").style.display = "block";
            }

            function closeCreateClassModal() {
                document.getElementById("createClassModal").style.display = "none";
                document.getElementById("createClassOverlay").style.display = "none";
            }

            // Edit Class Modal Logic
            function openEditClassModal(classId, classSection, subject) {
                // Populate hidden and text inputs
                document.getElementById("edit_class_id").value = classId;
                document.getElementById("edit_class_section").value = classSection;
                document.getElementById("edit_subject").value = subject;

                // Show the edit modal and overlay
                document.getElementById("editClassModal").style.display = "block";
                document.getElementById("editClassOverlay").style.display = "block";
            }

            function closeEditClassModal() {
                // Hide the edit modal and overlay
                document.getElementById("editClassModal").style.display = "none";
                document.getElementById("editClassOverlay").style.display = "none";
            }

            // Prevent Back Button Functionality            
            window.history.pushState(null, null, window.location.href);
            window.onpopstate = function () {
                window.history.pushState(null, null, window.location.href);
            };

            //sorting
            function sortSubjects() {
                var sortValue = document.getElementById("sort").value;
                window.location.href = "teacher_dashboard.php?sort=" + sortValue;
            }

            function confirmDuplicate(class_id) {
    if (confirm("Are you sure you want to duplicate this class?")) {
        window.location.href = 'teacher_dashboard.php?duplicate=' + class_id;
    }
}

        </script>
    </div>
</body>

</html>