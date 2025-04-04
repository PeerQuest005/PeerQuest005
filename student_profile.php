<?php

//PANG TEACHER LANG TO GUYS SORRY!!!
//-Allen

require 'config.php';
require 'auth.php';

// Check if teacher is logged in
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./teacher_dashboard.php');
}

$student_id = $_GET['student_id'];
$class_id = $_GET['class_id'];

// Fetch student details
$stmt = $pdo->prepare("SELECT student_first_name, student_last_name FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

// Fetch assessments and answers for the student
$stmt = $pdo->prepare("
    SELECT 
        e.essay_id,
        e.assessment_id,
        e.question_id,
        e.answer_text,
        e.grade,
        q.question_text,
        q.points
    FROM answers_esy_tbl e
    JOIN questions_esy_tbl q ON e.question_id = q.question_id
    WHERE e.student_id = ?
");
$stmt->execute([$student_id]);
$essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle score adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['grades'] as $essay_id => $grade) {
        $stmt = $pdo->prepare("UPDATE answers_esy_tbl SET grade = ? WHERE essay_id = ?");
        $stmt->execute([$grade, $essay_id]);
    }
    echo "Grades updated successfully!";
    header("Location: student_profile.php?student_id=$student_id&class_id=$class_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile of <?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?> | PeerQuest</title>
    <link rel="stylesheet" href="css/status.css">
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
            <li><a href="teacher_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard"> <span>Dashboard</span></a></li>
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
        <h1 class="dashboard-title">Essay Assessment of <?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?> </h1>
        </div>

<!-- SIDEBAR ENDS HERE -->

    <div class="container">

        <form method="post">
            <table>
                <thead>
                    <tr>
                        <th>Question</th>
                        <th>Answer</th>
                        <th>Points</th>
                        <th>Current Grade</th>
                        <th>Adjust Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($essays as $essay): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($essay['question_text']); ?></td>
                            <td><?php echo htmlspecialchars($essay['answer_text']); ?></td>
                            <td><?php echo htmlspecialchars($essay['points']); ?></td>
                            <td><?php echo htmlspecialchars($essay['grade']); ?></td>
                            <td>
                                <input 
                                    type="number" 
                                    name="grades[<?php echo $essay['essay_id']; ?>]" 
                                    value="<?php echo htmlspecialchars($essay['grade']); ?>" 
                                    min="0" 
                                    max="<?php echo htmlspecialchars($essay['points']); ?>" 
                                    required>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="update" type="submit">Update Grades</button>
        </form>
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
    
        </script>
</body>
</html>

