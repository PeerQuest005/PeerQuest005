<?php
require 'auth.php';
require 'config.php';

$class_id = $_GET['class_id'] ?? null;
$message = $_GET['message'] ?? '';

if (!isset($class_id) || !$class_id) {
    echo "Invalid class ID.";
    exit();
}

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

// Fetch class details
$stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $_SESSION['teacher_id']]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error_role'] = 'Access Denied! You do not own this class.';
    header('Location: ./teacher_dashboard.php');
}


// Handle form submission to create or update an assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $name = $_POST['name'] ?? 'Untitled Assessment';
    $type = $_POST['type'] ?? 'Essay'; // Default type
    $status = ($type === 'Recitation') ? 'Published' : 'Saved'; // Set "Published" for Recitation
    $time_limit = $_POST['time_limit'] ?? 10;
    $assessment_mode = $_POST['mode'] ?? 'Individual';
    $instructions = $_POST['instructions'] ?? '';

    if ($assessment_id) {
        // Update existing assessment
        $stmt = $pdo->prepare(
            "UPDATE assessment_tbl 
            SET name = ?, type = ?, time_limit = ?, assessment_mode = ?, instructions = ?, status = ? 
            WHERE assessment_id = ?"
        );
        $stmt->execute([$name, $type, $time_limit, $assessment_mode, $instructions, $status, $assessment_id]);
        $message = "Assessment updated successfully!";
    } else {
        $total_points = 0;

        // Insert new assessment
        $stmt = $pdo->prepare(
            "INSERT INTO assessment_tbl 
            (class_id, teacher_id, name, type, status, time_limit, assessment_mode, total_points, instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $class_id,
            $_SESSION['teacher_id'],
            $name,
            $type,
            $status,
            $time_limit,
            $assessment_mode,
            $total_points,
            $instructions
        ]);

        $assessment_id = $pdo->lastInsertId();
        $message = "Assessment created successfully!";
    }

    // Redirect to the appropriate page based on the type of assessment
    switch ($type) {
        case 'Essay':
            header("Location: assessment_essay.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Multiple Choice - Individual':
            header("Location: assessment_multiple_choice.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Multiple Choice - Collaborative':
            header("Location: assessment_multiple_choice_collab.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Essay - Collaborative':
            header("Location: assessment_essay_collab.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Recitation':
            header("Location: assessment_recitation.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'True or False':
            header("Location: assessment_true_false.php?assessment_id=$assessment_id&message=$message");
            break;
        default:
            die("Invalid assessment type.");
    }
    exit();
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();
if (!$class) {
    echo "Class not found.";
    exit();
}

// Fetch assessments for the class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate assessments into saved and published categories
$saved_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Saved');
$published_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Published');

// Get sorting option from user selection
$sort_option = $_GET['sort'] ?? 'default'; // Default sorting method

// Fetch assessments for the class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate assessments into saved and published categories
$saved_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Saved');
$published_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Published');

// Apply sorting if the user selected an option
if ($sort_option === 'type') {
    usort($published_assessments, fn($a, $b) => strcasecmp($a['type'], $b['type'])); // Case-insensitive sorting
} elseif ($sort_option === 'name') {
    usort($published_assessments, fn($a, $b) => strcasecmp($a['name'], $b['name'])); // Case-insensitive sorting
}

// Handle assessment duplication
if (isset($_GET['duplicate_assessment_id']) && isset($_GET['class_id'])) {
    $assessment_id_to_duplicate = $_GET['duplicate_assessment_id'];
    $selected_class_id = $_GET['class_id']; // Get the selected class ID

    // Fetch the assessment to duplicate
    $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ? AND teacher_id = ?");
    $stmt->execute([$assessment_id_to_duplicate, $_SESSION['teacher_id']]);
    $assessment = $stmt->fetch();

    if ($assessment) {
        // Check if the teacher owns the selected class
        $stmt = $pdo->prepare("SELECT class_id FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
        $stmt->execute([$selected_class_id, $_SESSION['teacher_id']]);
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($class) {
            // Insert the duplicated assessment into the selected class only
            $stmt = $pdo->prepare(
                "INSERT INTO assessment_tbl 
                (class_id, teacher_id, name, type, status, time_limit, assessment_mode, total_points, instructions) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $selected_class_id,
                $_SESSION['teacher_id'],
                $assessment['name'] . " (Copy)",  // Append "(Copy)" to name
                $assessment['type'],
                $assessment['status'],
                $assessment['time_limit'],
                $assessment['assessment_mode'],
                $assessment['total_points'],
                $assessment['instructions']
            ]);

            // Get the new assessment ID for the duplicated assessment
            $new_assessment_id = $pdo->lastInsertId();

            // Duplicate questions based on assessment type
            if (strpos($assessment['type'], 'Essay') !== false) {
                // Duplicate questions from questions_esy_tbl
                $stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
                $stmt->execute([$assessment_id_to_duplicate]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($questions as $question) {
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
            } elseif ($assessment['type'] == "True or False") {
                // Duplicate questions from questions_tf_tbl
                $stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ?");
                $stmt->execute([$assessment_id_to_duplicate]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($questions as $question) {
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
            } elseif ($assessment['type'] == "Multiple Choice - Individual") {
                // Duplicate questions from questions_mcq_tbl
                $stmt = $pdo->prepare("SELECT * FROM questions_mcq_tbl WHERE assessment_id = ?");
                $stmt->execute([$assessment_id_to_duplicate]);
                $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($questions as $question) {
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
            }

            $message = "Assessment and its questions duplicated successfully!";
            header("Location: view_assessment_teacher.php?class_id=" . $selected_class_id . "&message=$message");
            exit();
        } else {
            echo "Invalid class selection or you do not own this class.";
            exit();
        }
    } else {
        echo "Assessment not found or you do not own this assessment.";
        exit();
    }
}



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Creation | PeerQuest </title>
    <link rel="stylesheet" href="css/teacher_assessment.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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

            <?php if (isset($_GET['class_id'])): // Show these links only when viewing a class ?>
                <li><a href="view_classlist.php?class_id=<?php echo $_GET['class_id']; ?>"><img
                            src="images/icons/class_icon.png" alt="Class List"> <span>Class List</span></a></li>
                <li><a href="teacher_modules.php?class_id=<?php echo $_GET['class_id']; ?>"><img
                            src="images/icons/module_icon.png" alt="Modules"> <span>Modules</span></a></li>
                <li><a href="view_assessment_teacher.php?class_id=<?php echo $_GET['class_id']; ?>"><img
                            src="images/icons/assessment_icon.png" alt="Assessments"> <span>Assessments</span></a></li>
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
            <h1 class="dashboard-title"><?php echo htmlspecialchars($class['class_subject']); ?>
                (<?php echo htmlspecialchars($class['class_section']); ?>) - Assessments</h1>
        </div>

        <!-- SIDEBAR ENDS HERE -->


        <div class="commmon-container">
            <div class="create-assessment">
                <h3>Create New Assessment</h3>
                <form method="post">
                    <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">

                    <div class="form-group">
                        <label for="name">Assessment Name:</label>
                        <input type="text" name="name" id="name" placeholder="Enter assessment name" required>
                    </div>

                    <div class="form-group">
                        <label for="type">Assessment Type:</label>
                        <div class="custom-dropdown">
                            <select name="type" id="type" required onchange="toggleDropdownIcon(this)">
                                <option value="Essay">Essay - Individual</option>
                                <!-- <option value="Recitation">Recitation</option> -->
                                <option value="True or False">True or False</option>
                                <option value="Multiple Choice - Individual">Multiple Choice</option>
                                <option value="Multiple Choice - Collaborative">Multiple Choice - Collaborative</option>
                                <option value="Essay - Collaborative">Essay - Collaborative</option>
                            </select>
                            <span class="dropdown-icon">▼</span>
                        </div>
                    </div>

                    <div class="form-row button-row">
                        <button type="submit" class="btn-create">Create Assessment</button>
                    </div>
                </form>
            </div>

            <div class="saved-assessments-container">
                <ul class="assessment-list">
                    <h3>Saved Assessments</h3>
                    <?php if (!empty($saved_assessments)): ?>
                        <?php foreach ($saved_assessments as $assessment): ?>
                            <li class="assessment-item">
                                <span><?php echo htmlspecialchars($assessment['name']); ?>
                                    (<?php echo htmlspecialchars($assessment['type']); ?>)</span>
                                <div class="actions">
                                    <a href="<?php
                                    switch ($assessment['type']) {
                                        case 'Essay':
                                            echo "assessment_essay.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'True or False':
                                            echo "assessment_true_false.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Multiple Choice - Individual':
                                            echo "assessment_multiple_choice.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Essay - Collaborative':
                                            echo "assessment_essay_collab.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Recitation':
                                            echo "assessment_recitation.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        default:
                                            echo "#";
                                    }
                                    ?>"
                                        class="btn-action <?php echo ($assessment['type'] === 'Recitation') ? 'start' : 'edit'; ?>">
                                        <?php echo ($assessment['type'] === 'Recitation') ? 'Start Assessment' : 'Edit'; ?>
                                    </a>
                                   
                                    <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=publish"
                                        class="btn-action publish">Publish</a>
                                    <a href="view_assessment_teacher.php?class_id=<?php echo $_GET['class_id']; ?>&duplicate_assessment_id=<?php echo $assessment['assessment_id']; ?>"
                                        class="btn-action duplicate" onclick="return confirm('Are you sure you want to duplicate this assessment?');">
                                        <i class="fas fa-clone"></i>
                                    </a>
                                    <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=delete"
                                        class="btn-action delete" onclick="return confirm('Are you sure you want to delete this assessment?');"><i
                                            class="fas fa-trash"></i> </a>
                                </div>

                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-assessments">No saved assessments available.</p>
                    <?php endif; ?>
                </ul>
            </div>


            <div class="published-assessments-container">
                <div class="assessment-header">
                    <h3>Published Assessments</h3>
                    <div class="sort-container">
                        <label for="sort">Arrange by:</label>
                        <select id="sort" name="sort" onchange="sortAssessments()">
                            <option value="default" <?php echo ($sort_option === 'default') ? 'selected' : ''; ?>>Default
                            </option>
                            <option value="type" <?php echo ($sort_option === 'type') ? 'selected' : ''; ?>>Type</option>
                            <option value="name" <?php echo ($sort_option === 'name') ? 'selected' : ''; ?>>Name</option>
                        </select>
                    </div>
                </div>
                <ul class="assessment-list">


                    <?php if (!empty($published_assessments)): ?>
                        <?php foreach ($published_assessments as $assessment): ?>
                            <li class="assessment-item">
                                <span><?php echo htmlspecialchars($assessment['name']); ?>
                                    (<?php echo htmlspecialchars($assessment['type']); ?>)</span>


                                <div class="actions">
                                    <!-- View Groups Button (Only for Collaborative Multiple Choice and Essay) -->
                                    <?php if ($assessment['type'] === 'Essay - Collaborative'): ?>
                                        <a href="groups.php?assessment_id=<?php echo $assessment['assessment_id']; ?>" class="btn-action groups">View Groups</a>
                                    <?php endif; ?>

                                    <?php if ($assessment['type'] === 'Multiple Choice - Collaborative'): ?>
                                        <a href="groups_mcq.php?assessment_id=<?php echo $assessment['assessment_id']; ?>" class="btn-action groups">View Groups</a>
                                    <?php endif; ?>

                                    <a href="<?php
                                    switch ($assessment['type']) {
                                        case 'Essay':
                                            echo "assessment_essay.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'True or False':
                                            echo "assessment_true_false.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Multiple Choice - Individual':
                                            echo "assessment_multiple_choice.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Essay - Collaborative':
                                            echo "assessment_essay_collab.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        case 'Recitation':
                                            echo "assessment_recitation.php?assessment_id=" . $assessment['assessment_id'];
                                            break;
                                        default:
                                            echo "error";
                                    }
                                    ?>"
                                        class="btn-action <?php echo ($assessment['type'] === 'Recitation') ? 'start' : 'edit'; ?>">
                                        <?php echo ($assessment['type'] === 'Recitation') ? 'Start Assessment' : 'Edit'; ?>
                                    </a>

                                    <!-- Unpublish Button -->
                                    <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=unpublish"
                                        class="btn-action unpublish"  onclick="return confirm('Are you sure you want to unpublish this assessment?');">Unpublish</a>

                                        <a href="view_assessment_teacher.php?class_id=<?php echo $_GET['class_id']; ?>&duplicate_assessment_id=<?php echo $assessment['assessment_id']; ?>"
                                        class="btn-action duplicate" onclick="return confirm('Are you sure you want to duplicate this assessment?');">
                                        <i class="fas fa-clone"></i>
                                    </a>

                                    <!-- Delete Button -->
                                    <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=delete"
                                        class="btn-action delete" onclick="return confirm('Are you sure you want to delete this assessment?');">
                                        <i class="fas fa-trash"></i>
                                    </a>

                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-assessments">No published assessments available.</p>
                    <?php endif; ?>
                </ul>
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

        function toggleDropdownIcon(selectElement) {
            let dropdownIcon = selectElement.nextElementSibling;
            if (selectElement.value) {
                dropdownIcon.style.transform = "rotate(180deg)";
            } else {
                dropdownIcon.style.transform = "rotate(0deg)";
            }
        }

        function sortAssessments() {
            var sortOption = document.getElementById('sort').value;
            var url = new URL(window.location.href);
            url.searchParams.set('sort', sortOption);
            window.location.href = url.toString();
        }

    </script>
</body>

</html>