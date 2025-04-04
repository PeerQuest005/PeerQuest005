<?php
require 'auth.php';
require 'config.php';

// Ensure the user is a student (role 2)
if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
    exit();
}

$student_id = $_GET['student_id'];

if ($_SESSION['student_id'] != $student_id) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Students Only.';
    header('Location: ./student_dashboard.php');
} else {
    $stmt = $pdo->prepare("
    SELECT class_tbl.class_id, class_tbl.class_subject, class_tbl.class_section 
    FROM class_tbl 
    INNER JOIN student_classes ON class_tbl.class_id = student_classes.class_id 
    WHERE student_classes.student_id = ?
    ORDER BY class_tbl.class_subject ASC, class_tbl.class_section ASC
");
    $stmt->execute([$student_id]);
    $joined_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $subject_assessments = [];
}


// Fetch all published assessments grouped by subject and section
foreach ($joined_classes as $class) {
    $stmt = $pdo->prepare("
        SELECT assessment_id, name, type, time_limit
        FROM assessment_tbl 
        WHERE class_id = ? AND status = 'Published'
    ");
    $stmt->execute([$class['class_id']]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter out completed assessments
    $filtered_assessments = [];
    foreach ($assessments as $assessment) {
        $assessment_id = $assessment['assessment_id'];

        // Check if student has submitted this assessment
        $completed = false;

        // Check if assessment exists in essay answer table
        $stmt_check = $pdo->prepare("SELECT 1 FROM answers_esy_tbl WHERE student_id = ? AND assessment_id = ?");
        $stmt_check->execute([$student_id, $assessment_id]);
        if ($stmt_check->fetch()) {
            $completed = true;
        }

        // Check if assessment exists in multiple-choice answer table
        if (!$completed) {
            $stmt_check = $pdo->prepare("SELECT 1 FROM answers_mcq_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt_check->execute([$student_id, $assessment_id]);
            if ($stmt_check->fetch()) {
                $completed = true;
            }
        }

        // Check if assessment exists in true/false answer table
        if (!$completed) {
            $stmt_check = $pdo->prepare("SELECT 1 FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt_check->execute([$student_id, $assessment_id]);
            if ($stmt_check->fetch()) {
                $completed = true;
            }
        }

        // Check if assessment exists in collaborative multiple-choice table
        if (!$completed) {
            $stmt_check = $pdo->prepare("SELECT 1 FROM answers_mcq_collab_tbl WHERE submitted_by = ? AND assessment_id = ?");
            $stmt_check->execute([$student_id, $assessment_id]);
            if ($stmt_check->fetch()) {
                $completed = true;
            }
        }

        // If assessment is NOT completed, add to list
        if (!$completed) {
            $filtered_assessments[] = $assessment;
        }
    }

    // If there are remaining assessments, add them to the subject
    if (!empty($filtered_assessments)) {
        $subject_key = $class['class_subject'] . " (" . $class['class_section'] . ")"; // Proper format
        $subject_assessments[$subject_key] = [
            'class_id' => $class['class_id'],
            'assessments' => $filtered_assessments
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest | PeerQuest</title>
    <link rel="stylesheet" href="css/quest.css">
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
            <li><a href="student_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard">
                    <span>Dashboard</span></a></li>
            <li><a href="achievements.php?student_id=<?php echo $student_id; ?>"><img
                        src="images/achievements_white_icon.png" alt="Achievements"> <span>Achievements</span></a></li>
            <li><a href="quest.php?student_id=<?php echo $student_id; ?>"><img src="images/myquest_white_icon.png"
                        alt="Achievements"> <span>My Quests</span></a></li>
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

            <h1 class="dashboard-title"> My Quest</h1>
        </div>
        <div class="quest-container">
            <?php if (!empty($subject_assessments)): ?>
                <?php foreach ($subject_assessments as $subject => $data): ?>
                    <div class="subject-card">
                        <h3 class="subject-title">
                            <?php echo htmlspecialchars($subject); ?>        <?php echo htmlspecialchars($data['class_section']); ?>
                            
                        </h3>
                        <div class="quest-grid">
                            <?php foreach ($data['assessments'] as $assessment): ?>
                                <div class="quest-card">
                                    <div class="quest-details">
                                        <p class="quest-assessment">
                                            <strong><?php echo htmlspecialchars($assessment['type']); ?>:</strong>
                                            <?php echo htmlspecialchars($assessment['name']); ?>
                                        </p>
                                    </div>
                                    <div class="quest-footer">
                                        <p class="quest-timer"><strong>Time Limit:</strong>
                                            <?php echo htmlspecialchars($assessment['time_limit']); ?> minutes</p>
                                        <a href="view_assessment_student.php?class_id=<?php echo $data['class_id']; ?>&assessment_id=<?php echo $assessment['assessment_id']; ?>"
                                            class="btn-action">View Quest</a>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-assessment-container">

                    <img src="images/charaubelle/C_asleep.webp" alt="Charaubelle Sleeping" class="charaubelle-sleep">
                    <p class="no-assessment-message">No quest for now. <br> Enjoy a well-deserved break!</p>
                </div>
            <?php endif; ?>
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
    </script>
</body>

</html>