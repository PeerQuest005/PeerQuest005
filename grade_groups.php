<?php
require 'auth.php';
require 'config.php';

// Get assessment_id and room_id from the URL
$assessment_id = $_GET['assessment_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

if (!$assessment_id || !$room_id) {
    echo "Invalid assessment or room ID.";
    exit();
}

// Check if the user is authorized (e.g., teacher or admin)
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

// Initialize error messages array
if (!isset($_SESSION['error_messages'])) {
    $_SESSION['error_messages'] = [];
}

// Handle grade updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grades'])) {
    foreach ($_POST['grades'] as $answer_id => $grade) {
        // Fetch the max points for validation
        $stmt = $pdo->prepare("SELECT q.points FROM answers_esy_collab_tbl a JOIN questions_esy_tbl q ON a.question_id = q.question_id WHERE a.collab_id = ?");
        $stmt->execute([$answer_id]);
        $max_points = $stmt->fetchColumn();

        // Ensure grade does not exceed max points
        if ($grade > $max_points) {
            $_SESSION['error_messages'][] = "Error: Grade cannot exceed the maximum points ($max_points).";
            continue;
        }

        // Update grades
        $stmt = $pdo->prepare("UPDATE answers_esy_collab_tbl SET grades = ? WHERE collab_id = ?");
        $stmt->execute([$grade, $answer_id]);
    }

    if (empty($_SESSION['error_messages'])) {
        $_SESSION['success_message'] = "Grades updated successfully.";
    }
    
    // Redirect to avoid form resubmission issues
    header("Location: ".$_SERVER['PHP_SELF']."?assessment_id=$assessment_id&room_id=$room_id");
    exit();
}

// Fetch the assessment name
$stmt = $pdo->prepare("SELECT name FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment_name = $stmt->fetchColumn();

if (!$assessment_name) {
    echo "Invalid assessment ID.";
    exit();
}

// Fetch answers and corresponding question text, ordered by question_id
$stmt = $pdo->prepare(
    "SELECT a.collab_id AS answer_id, a.student_id, a.answer, a.submitted_at, a.grades, a.attempt, s.username, 
            q.question_text, q.question_id, q.points
     FROM answers_esy_collab_tbl a
     JOIN student_tbl s ON a.student_id = s.student_id
     JOIN questions_esy_tbl q ON a.question_id = q.question_id
     WHERE a.room_id = ? AND a.assessment_id = ?
     ORDER BY q.question_id ASC"
);
$stmt->execute([$room_id, $assessment_id]);
$answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$answers) {
    echo "No answers found for this room and assessment.";
    exit();
}

// Group answers by question
$grouped_answers = [];
foreach ($answers as $answer) {
    $grouped_answers[$answer['question_id']][] = $answer;
}

// Calculate the total points for the entire assessment
$total_points = 0;
foreach ($grouped_answers as $question_answers) {
    $total_points += $question_answers[0]['points'];
}

// Calculate the total grades for each student and for the group
$student_grades = [];
foreach ($answers as $answer) {
    $student_grades[$answer['student_id']]['total_grade'] = ($student_grades[$answer['student_id']]['total_grade'] ?? 0) + $answer['grades'];
}

$total_group_grade = array_sum(array_column($student_grades, 'total_grade'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Groups</title>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.162.0/dist/umd/lucide.min.js"></script>
<script>
    window.onload = function() {
        lucide.createIcons();
    };
</script>


    <style>
body {
    font-family: 'Inter', sans-serif;
    background-color: #f7f7f7;
    color: #24243A;
    margin: 0;
    padding: 0;
}

.page-container {
    margin: 30px auto;
    padding: 20px;
    background-color: #ffffff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    width: 90%;
}

h1, h3 {
    margin-bottom: 20px;
}

h1 {
    font-size: 1.5rem;
    color: #24243A;
}

h3 {
    font-size: 1rem;
    color: #24243A;
}

.answer-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.answer-table th, .answer-table td {
    padding: 10px;
    border: 1px solid #ddd;
    font-size: 0.9rem;
}

.answer-table th {
    background-color: #f7f7f7;
    text-align: left;
}

.question-section {
    margin-bottom: 25px;
}

input[type="number"] {
    width: 70px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

.submit-btn {
    margin-top: 15px;
    display: flex;
    justify-content: flex-end;
}

button {
    padding: 10px 15px;
    background-color: #47A99C;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
}

button[type="submit"] {
    padding: 13px 15px;
    background-color: #47A99C;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    width: 15%;
}

button[type="submit"]:hover {
    background-color: #24243A;
}
button:hover {
    background-color: #24243A;
}

.go-back-btn {
    margin-top: 20px;
    background-color: #6c757d;
    margin-left: 52px;
}

.go-back-btn:hover {
    background-color: #5a6268;
}
 /* Top Bar Styles */
 .top-bar {
            background: #F7F7F7;
            height: 60px;
            color: #000000;
            padding: 0 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            margin: 0; 
            overflow: visible;
        }

        .dashboard-title {
            margin-left: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #24243A;
        }

        .answer-table th:nth-child(1),
        .answer-table td:nth-child(1) {
            width: 30%;
            white-space: nowrap;
            text-align: left;
            padding-left: 10px;
        }

        .answer-table td:nth-child(2) {
            width: 50%;
            padding: 15px;
        }

        .answer-table td:nth-child(3) {
            text-align: center;
        }
        .answer-table td:nth-child(3) input[type="number"] {
            width: 80px;
            padding: 5px;
            text-align: center;
            display: block;
            margin: 0 auto;
            border: 1px solid #ccc;
            border-radius: 4px;
        }


        .total-grade {
            font-size: 1.5rem;
            color: #2c3e50;
        }

        .total-grade .underline {
            text-decoration: underline;
            font-weight: bold;
            color: #e74c3c; /* Red color for the values */
        }

        .back-to-top {
        position: fixed;
        bottom: 25px;
        right: 20px;
        background-color:rgba(53, 123, 198, 0.49);
        color: white;
        border: none;
        border-radius: 20px;
        padding: 15px 20px;
        font-size: 0.8rem;
        font-weight: bold;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
        display: none;
        transition: background-color 0.3s ease;
    }

.back-to-top:hover {
    background-color: #2c3e50;
}

.lucide-icon {
    margin-right: 8px;
    vertical-align: middle;
}

/* ✅ Success Message */
.success-message {
    background-color: #D4EDDA; /* Light green */
    color: #155724; /* Dark green text */
    border-left: 5px solid #28A745; /* Dark green left border */
    padding: 10px 80px;
    margin-bottom: 15px;
    font-size: 1rem;
    font-weight: 500;
    border-radius: 5px;
    width: fit-content;
}

/* ✅ Error Message */
.error-messages {
    background-color: #F8D7DA; /* Light red */
    color: #721C24; /* Dark red text */
    border-left: 5px solid #DC3545; /* Dark red left border */
    padding: 10px 15px;
    margin-bottom: 15px;
    font-size: 0.9rem;
    font-weight: 500;
    border-radius: 5px;
    width: fit-content;
}

/* ✅ Animation for Fading In */
.success-message,
.error-messages {
    animation: fadeIn 0.5s ease-in-out;
}

/* ✅ Fade In Animation */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

    </style>

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>
<div class="top-bar">
        <h1 class="dashboard-title">Grade Groups for Assessment: <?php echo htmlspecialchars($assessment_name); ?> | Room ID: <?php echo htmlspecialchars($room_id); ?></h1>
        </div>

        <!-- Go back button -->
        <button class="go-back-btn" onclick="window.location.href='groups.php?assessment_id=<?php echo htmlspecialchars($assessment_id); ?>&room_id=<?php echo htmlspecialchars($room_id); ?>';">Go Back</button>

    <div class="page-container">
   
        <?php 
        $num_students = count($student_grades);
        $adjusted_total_points = $total_points * $num_students;
        ?>

            <h3 class="total-grade">Total Grade for the Group: 
                <span class="underline"><?php echo htmlspecialchars($total_group_grade); ?> / <?php echo htmlspecialchars($adjusted_total_points); ?></span>
            </h3>

            <?php if (!empty($_SESSION['error_messages'])): ?>
    <div class="error-messages">
        <?php echo implode("<br>", array_map('htmlspecialchars', $_SESSION['error_messages'])); ?>
    </div>
    <?php unset($_SESSION['error_messages']); // Clear messages after displaying ?>
<?php endif; ?>

<?php if (!empty($_SESSION['success_message'])): ?>
    <div class="success-message">
        <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    </div>
    <?php unset($_SESSION['success_message']); // Clear message after displaying ?>
<?php endif; ?>

        <h3>Total Grade for Each Student:</h3>
        <table class="answer-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Total Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($student_grades as $student_id => $student): ?>
                    <?php 
                        $individual_total_points = $total_points; 
                        $percentage = ($student['total_grade'] / $individual_total_points) * 100;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($answers[array_search($student_id, array_column($answers, 'student_id'))]['username']); ?></td>
                        <td><?php echo htmlspecialchars($student['total_grade']); ?> / <?php echo htmlspecialchars($individual_total_points); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST">
        <div class="submit-btn">
                <button type="submit" name="update_grades">Update Grades</button>
            </div>
    <?php foreach ($grouped_answers as $question_id => $question_answers): ?>
        <div class="question-section">
            <h3>Question: <?php echo htmlspecialchars($question_answers[0]['question_text']); ?> (Total Points: <?php echo htmlspecialchars($question_answers[0]['points']); ?>)</h3>
            <table class="answer-table">
    <thead>
        <tr>
            <th>Student</th>
            <th>Answer</th>
            <th>Grade</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($question_answers as $answer): ?>
            <tr>
                <td><?php echo htmlspecialchars($answer['username']); ?></td>
                <td><?php echo htmlspecialchars($answer['answer']); ?></td>
                <td>
                    <input type="number" name="grades[<?php echo $answer['answer_id']; ?>]" 
                           value="<?php echo htmlspecialchars($answer['grades']); ?>" 
                           min="0" 
                           max="<?php echo htmlspecialchars($answer['points']); ?>" 
                           required>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        </div>
    <?php endforeach; ?>

           
        </form>
    </div>

    <button class="back-to-top" onclick="scrollToTop()">
    <svg class="lucide-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="4 10 12 4 20 10"></polyline>
        <line x1="12" y1="20" x2="12" y2="4"></line>
    </svg>
    Back to Top
</button>




    <script>
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', function() {
                let max = parseInt(this.max);
                if (parseInt(this.value) > max) {
                    alert("Grade cannot exceed the maximum points for this question (" + max + ").");
                    this.value = max;
                }
            });
        });

        window.onscroll = function() {
        const backToTopButton = document.querySelector('.back-to-top');
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            backToTopButton.style.display = "block";
        } else {
            backToTopButton.style.display = "none";
        }
    };

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    </script>

</body>
</html>
