<?php
require 'auth.php';
require 'config.php';

// Fetch assessment details
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Check if the student has already attempted this assessment
$stmt = $pdo->prepare("SELECT Attempt FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
$stmt->execute([$_SESSION['student_id'], $assessment_id]);
$user_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_attempt && $user_attempt['Attempt'] >= 1) {
    echo "You have already attempted this assessment.";
    exit();
}

// Fetch assessment details
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

$time_limit = $assessment['time_limit'] * 60; // Convert minutes to seconds

// Fetch questions for the assessment
$stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ? AND correct_answer IN ('True', 'False')");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$questions) {
    echo "No questions found for this assessment.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['answers']) || empty($_POST['answers'])) {
        echo "Please answer all questions.";
        exit();
    }

    try {
        $pdo->beginTransaction();

        foreach ($_POST['answers'] as $question_id => $answer) {
            $answer_text = ($answer === "1") ? 'True' : 'False';

            // Fetch the correct answer and points for the question
            $stmt = $pdo->prepare("SELECT correct_answer, points FROM questions_tf_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                throw new Exception("Question not found: $question_id");
            }

            $is_correct = ($answer_text === $question['correct_answer']) ? $question['points'] : 0;

            // Insert the student's answer into answers_tf_tbl
            $stmt = $pdo->prepare("
                INSERT INTO answers_tf_tbl (student_id, assessment_id, question_id, answer_text, correct_answer, Attempt)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$_SESSION['student_id'], $assessment_id, $question_id, $answer_text, $is_correct]);
        }

        if ($user_attempt) {
            $stmt = $pdo->prepare("UPDATE answers_tf_tbl SET Attempt = Attempt + 1 WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$_SESSION['student_id'], $assessment_id]);
        }

        $pdo->commit();
        echo "<p>Assessment submitted successfully!</p>";
        echo '<a href="student_dashboard.php" class="btn btn-primary">Back to Assessments</a>';
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "An error occurred: " . $e->getMessage();
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take True/False Assessment: <?php echo htmlspecialchars($assessment['name']); ?></title>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const timeLimit = <?php echo $time_limit; ?>; // Time limit in seconds
            const storageKey = `tf_assessment_timer_<?php echo $assessment_id; ?>`;

            let remainingTime = localStorage.getItem(storageKey)
                ? parseInt(localStorage.getItem(storageKey))
                : timeLimit;

            const timerElement = document.getElementById("timer");
            const formElement = document.querySelector("form");

            const updateTimerDisplay = () => {
                const minutes = Math.floor(remainingTime / 60);
                const seconds = remainingTime % 60;
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, "0")}`;
            };

            const timerInterval = setInterval(() => {
                if (remainingTime > 0) {
                    remainingTime--;
                    localStorage.setItem(storageKey, remainingTime);
                    updateTimerDisplay();
                } else {
                    clearInterval(timerInterval);
                    localStorage.removeItem(storageKey);
                    alert("Time is up! Submitting your answers.");
                    formElement.submit();
                }
            }, 1000);

            updateTimerDisplay();

            window.addEventListener("beforeunload", () => {
                localStorage.setItem(storageKey, remainingTime);
            });
        });
    </script>
</head>
<body>
    <h2>Take True/False Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>
    <h3>Pick the best answer for each question and show off your skills—you’ve got this!</h3>
    <p>Total Points: <?php echo htmlspecialchars($assessment['total_points']); ?></p>
    <p>Time Limit: <?php echo htmlspecialchars($assessment['time_limit']); ?> minutes</p>
    <p>Time Remaining: <span id="timer"></span></p>

    <!-- Assessment Form -->
    <form method="post">
        <?php foreach ($questions as $question): ?>
            <div>
                <p><?php echo htmlspecialchars($question['question_text']); ?> (<?php echo htmlspecialchars($question['points']); ?> points)</p>
                <label>
                    <input type="radio" name="answers[<?php echo htmlspecialchars($question['question_id']); ?>]" value="1" required>
                    True
                </label>
                <label>
                    <input type="radio" name="answers[<?php echo htmlspecialchars($question['question_id']); ?>]" value="0" required>
                    False
                </label>
            </div>
        <?php endforeach; ?>
        <button type="submit">Submit Assessment</button>
    </form>
</body>
</html>
