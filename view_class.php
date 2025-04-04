<?php
require 'auth.php';
require 'config.php';

// Ensure only teachers can access
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./teacher_dashboard.php');
    exit();
}

// Validate class_id from the URL
if (!isset($_GET['class_id']) || !ctype_digit($_GET['class_id'])) {
    die("Invalid class ID.");
}

$class_id = $_GET['class_id'];

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $_SESSION['teacher_id']]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    $_SESSION['error_role'] = 'Access Denied! You do not have access.';
    header('Location: ./teacher_dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classlist | PeerQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/viewclass.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
    <?php 
        if (isset($_SESSION['error_role'])) {
            echo '<div id="error-message" class="error-message hidden">' . $_SESSION['error_role'] . '</div>';
            unset($_SESSION['error_role']); // Clear the error after displaying
        }
    ?>
    <div class="top-bar">
    <h1 class="dashboard-title"><?php echo htmlspecialchars($class['class_subject']); ?> (<?php echo htmlspecialchars($class['class_section']); ?>)</h1>
    <div class="date-picker-wrapper">
                <p id="currentDate" class="hover-date"></p>
                <div class="calendar-overlay">
                    <div id="flatpickrCalendar"></div>
                </div>
    </div>
</div>

<!-- BANNER SECTION -->
<div class="banner-container">
    <div class="banner-content">
        <h2>Welcome to Your Class, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
        <p>Engage, inspire, and create an interactive learning experience for your students. <br>
        Keep your class organized with assessments, modules, and real-time progress tracking.</p>
    </div>
    <img src="images/book.webp" alt="Book Illustration" class="banner-book">
</div>


<!-- QUICK ACTION CARDS -->
<div class="quick-actions-container">
    <!-- View Students Card -->
    <div class="quick-action-card">
        <img src="images/class.webp" alt="View Students" class="card-icon">
        <h3>Manage Students</h3>
        <p>Check all students joined in your class and monitor progress.</p>
        <a href="view_classlist.php?class_id=<?php echo $class_id; ?>" class="btn-action">View Class List</a>
    </div>

    <!-- Create Assessment Card -->
    <div class="quick-action-card">
        <img src="images/assessment.webp" alt="Create Assessment" class="card-icon">
        <h3>Assess Students</h3>
        <p>Publish engaging assessments to track student learning.</p>
        <a href="view_assessment_teacher.php?class_id=<?php echo $class_id; ?>" class="btn-action">Create Assessment</a>
    </div>

    <!-- Upload Modules Card -->
    <div class="quick-action-card">
        <img src="images/module.webp" alt="Upload Modules" class="card-icon">
        <h3>Upload Modules</h3>
        <p>Provide learning materials for your students.</p>
        <a href="teacher_modules.php?class_id=<?php echo $class_id; ?>" class="btn-action">Upload Modules</a>
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
</script>