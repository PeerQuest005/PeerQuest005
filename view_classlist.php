<?php
require 'config.php';
require 'auth.php';

// Check user role and redirect accordingly
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

$class_id = $_GET['class_id'] ?? '';

// Fetch class details
$stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE class_id = ? AND teacher_id = ?");
$stmt->execute([$class_id, $_SESSION['teacher_id']]);
$class = $stmt->fetch();

if (!$class) {
    $_SESSION['error_role'] = 'Access Denied! You do not own this class.';
    header('Location: ./teacher_dashboard.php');
}

// Get sorting parameters from GET request
$sort_column = $_GET['sort'] ?? 's.student_last_name';
$sort_order = $_GET['order'] ?? 'asc';

// Restrict sorting columns to prevent SQL injection
$allowed_columns = [
    'first_name' => 's.student_first_name',
    'last_name' => 's.student_last_name',
    'username' => 's.username'
];

// Validate sorting column
$sort_column = $allowed_columns[$sort_column] ?? 's.student_last_name';
$sort_order = ($sort_order === 'desc') ? 'DESC' : 'ASC';

// Fetch students and apply sorting
$stmt = $pdo->prepare("
    SELECT s.student_id, CONCAT(s.student_first_name, ' ', s.student_last_name) AS full_name, 
           s.student_first_name, s.student_last_name, s.username
    FROM student_classes sc
    JOIN student_tbl s ON sc.student_id = s.student_id
    WHERE sc.class_id = ?
    ORDER BY $sort_column $sort_order
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if student_id and class_id are provided via GET request
if (isset($_GET['student_id']) && isset($_GET['class_id'])) {
    $student_id = $_GET['student_id'];
    $class_id = $_GET['class_id'];

    // Prepare the DELETE SQL statement to remove the student from the class
    $stmt = $pdo->prepare("DELETE FROM student_classes WHERE student_id = ? AND class_id = ?");
    $stmt->execute([$student_id, $class_id]);

    // After deletion, redirect back to the class list page
    header('Location: view_classlist.php?class_id=' . $class_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View <?php echo htmlspecialchars($class['class_subject']); ?> | PeerQuest</title>
    <link rel="stylesheet" href="css/classlist.css">
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
                (<?php echo htmlspecialchars($class['class_section']); ?>) - Class List</h1>
        </div>

        <!-- View Leaderboards -->
        <div class="leaderboard-section">
            <form action="leaderboards.php" method="get">
                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">
                <input type="hidden" name="class_subject"
                    value="<?php echo htmlspecialchars($class['class_subject']); ?>">
                <input type="hidden" name="class_section"
                    value="<?php echo htmlspecialchars($class['class_section']); ?>">

                <h3>View Leaderboards:
                    <select name="assessment_id" required>
                        <option value="" disabled selected>Select Assessment</option>
                        <?php
                        // Fetch assessments for this class
                        $stmt = $pdo->prepare("SELECT assessment_id, name FROM assessment_tbl WHERE class_id = ?");
                        $stmt->execute([$class_id]);
                        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($assessments as $assessment): ?>
                            <option value="<?php echo htmlspecialchars($assessment['assessment_id']); ?>"
                                data-name="<?php echo htmlspecialchars($assessment['name']); ?>">
                                <?php echo htmlspecialchars($assessment['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="go-btn">Go</button>
                </h3>
            </form>
        </div>

        <!-- Sorting Section - Now Aligned to the Left -->
        <div class="sorting-container">
            <form method="GET" action="">
                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">

                <div class="sort-container">
                    <h3 for="sort">Sort Students by:</h3>
                    <select name="sort" id="sort">
                        <option value="first_name" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'first_name') ? 'selected' : ''; ?>>First Name</option>
                        <option value="last_name" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'last_name') ? 'selected' : ''; ?>>Last Name</option>
                        <option value="username" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'username') ? 'selected' : ''; ?>>Username</option>
                    </select>

                    <h3 for="order">Order:</h3>
                    <select name="order" id="order">
                        <option value="asc" <?php echo (!isset($_GET['order']) || $_GET['order'] == 'asc') ? 'selected' : ''; ?>>Ascending</option>
                        <option value="desc" <?php echo (isset($_GET['order']) && $_GET['order'] == 'desc') ? 'selected' : ''; ?>>Descending</option>
                    </select>

                    <button type="submit" class="sort-go-btn">Sort</button>
                </div>
            </form>
        </div>



        <!-- Display total number of students -->
        <H3>Total Students: <?php echo count($students); ?></H3>

        <!-- Student List -->
        <?php if (count($students) > 0): ?>
            <ul class="student-list">
                <?php foreach ($students as $student): ?>
                    <li>
                        <span class="student-name"><?php echo htmlspecialchars($student['full_name']); ?>
                            (<?php echo htmlspecialchars($student['username']); ?>)</span>

                        <div class="actions">
                            <a href="status.php?student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>"
                                class="btn status">Assessment Status</a>
                            <a href="achievements.php?student_id=<?php echo $student['student_id']; ?>"
                                class="btn achievements">Achievements</a>
                            <a href="student_profile.php?student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>"
                                class="btn profile">Grade Essay</a>
                            <a href="view_classlist.php?student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>"
                                class="btn delete"
                                onclick="return confirm('Are you sure you want to remove this student?')">Remove Student</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="no-students">🚀 No students have joined this class yet.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.content').classList.toggle('expanded');
            document.querySelector('.top-bar').classList.toggle('expanded');
            const toggleIcon = document.getElementById('toggleIcon');
            toggleIcon.src = document.querySelector('.sidebar').classList.contains('collapsed') ? "images/sidebar_open_icon.png" : "images/sidebar_close_icon.png";
        }
    </script>

</body>

</html>