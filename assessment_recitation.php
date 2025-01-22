<?php
require 'auth.php';
require 'config.php';

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    echo "Access denied: Teachers only.";
    exit();
}

$teacher_id = $_SESSION['teacher_id'];

// Fetch assessment details
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Fetch the assessment
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

$class_id = $assessment['class_id'];

// Fetch students who joined this class
$stmt = $pdo->prepare("
    SELECT s.student_id, s.student_first_name, s.student_last_name
    FROM student_classes sc
    JOIN student_tbl s ON sc.student_id = s.student_id
    WHERE sc.class_id = ?
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle question submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    $question_text = $_POST['question_text'] ?? '';

    if ($question_text) {
        // Insert the question into questions_reci_tbl
        $stmt = $pdo->prepare("
            INSERT INTO questions_reci_tbl (class_id, assessment_id, question_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$class_id, $assessment_id, $question_text]);

        $message = "Question saved successfully.";
    } else {
        $message = "Please enter a question.";
    }
}

// Fetch the latest question for this class and assessment
$stmt = $pdo->prepare("
    SELECT question_text, revealed_student_id
    FROM questions_reci_tbl
    WHERE class_id = ? AND assessment_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$class_id, $assessment_id]);
$latest_question = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle student reveal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal_student_id'])) {
    $revealed_student_id = $_POST['reveal_student_id'];

    // Update the latest question with the revealed student ID
    $stmt = $pdo->prepare("
        UPDATE questions_reci_tbl
        SET revealed_student_id = ?
        WHERE class_id = ? AND assessment_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$revealed_student_id, $class_id, $assessment_id]);

    // Fetch the revealed student's name
    $stmt = $pdo->prepare("
        SELECT student_first_name, student_last_name
        FROM student_tbl
        WHERE student_id = ?
    ");
    $stmt->execute([$revealed_student_id]);
    $revealed_student = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assessment'])) {
    // Delete the assessment from the database
    $stmt = $pdo->prepare("DELETE FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);

    // Redirect back to the teacher's assessment view
    header("Location: view_assessment_teacher.php?class_id=" . htmlspecialchars($class_id));
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Recitation for Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .balloon {
            display: inline-block;
            width: 60px;
            height: 80px;
            background-color: #ddd;
            border-radius: 50%;
            text-align: center;
            margin: 10px;
            cursor: pointer;
            font-size: 24px;
            line-height: 80px;
        }
        .balloon:hover {
            background-color: #ffc107;
        }
        #questionDisplay {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mt-4 text-end">
            <form method="post">
                <button type="submit" name="delete_assessment" class="btn btn-danger" onclick="return confirm('Changes will not be saved. Are you sure you want to exit?')">Exit</button>
            </form>
        </div>

        <h2>Recitation for Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>
        <p>Class Section: <strong><?php echo htmlspecialchars($assessment['class_id']); ?></strong></p>

        <!-- Success/Error Message -->
        <?php if ($message): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Enter a Question -->
        <form method="post">
            <div class="mb-3">
                <label for="questionInput" class="form-label">Enter Question:</label>
                <input type="text" id="questionInput" name="question_text" class="form-control" placeholder="Type your question here" required>
            </div>
            <button type="submit" name="submit_question" class="btn btn-primary">Save Question</button>
        </form>

        <!-- Display the Latest Question -->
        <div id="questionDisplay" class="alert alert-secondary mt-4">
        Latest Question: 
        <?php 
        if ($latest_question && isset($latest_question['question_text'])) {
            echo htmlspecialchars($latest_question['question_text']);
        } else {
            echo "No question has been added yet.";
        }
        ?>
        </div>


        <!-- Revealed Student -->
        <h3>Revealed Student:</h3>
        <div class="alert alert-info">
            <?php
            if (!empty($revealed_student)) {
                echo htmlspecialchars($revealed_student['student_first_name'] . ' ' . $revealed_student['student_last_name']);
            } else {
                echo "No student revealed yet.";
            }
            ?>
        </div>

        <!-- Balloons for Students -->
        <h3>Click a Balloon to Reveal a Student:</h3>
        <div>
            <?php foreach ($students as $student): ?>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="reveal_student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                    <button type="submit" name="reveal_student" class="balloon">
                        🎈
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
