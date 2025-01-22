<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;

// Fetch assessment data
$assessment = null;
if ($assessment_id) {
    $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch();
    if (!$assessment) {
        echo "Assessment not found.";
        exit();
    }
} else {
    echo "No assessment selected.";
    exit();
}

// Fetch existing questions
$stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dynamically calculate total points
$total_points = array_sum(array_column($questions, 'points'));

// Update total points in the assessment table
$stmt = $pdo->prepare("UPDATE assessment_tbl SET total_points = ? WHERE assessment_id = ?");
$stmt->execute([$total_points, $assessment_id]);

// Initialize messages
$save_message = '';
$publish_message = '';
$update_message = '';
$unpublish_message = '';
$error_message = '';

// Handle Save/Publish/Update/Unpublish actions
// Handle Save/Publish/Update/Unpublish actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_assessment']) || isset($_POST['publish_assessment'])) {
        if ($total_points <= 0 || count($questions) === 0) {
            $error_message = "Please ensure Total Points (>0) and at least one question are set before saving or publishing.";
        } else {
            $status = isset($_POST['publish_assessment']) ? 'Published' : 'Saved';
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = ? WHERE assessment_id = ?");
            $stmt->execute([$status, $assessment_id]);

            // Set messages for save and publish
            $save_message = isset($_POST['save_assessment']) ? "Assessment saved successfully!" : "";
            $publish_message = isset($_POST['publish_assessment']) ? "Assessment published successfully!" : "";

            // Redirect to refresh page
            header("Location: assessment_essay.php?assessment_id=$assessment_id");
            exit();
        }
    }

    if (isset($_POST['update_assessment'])) {
        // Update instructions and time limit
        $instructions = $_POST['instructions'] ?? $assessment['instructions'];
        $time_limit = $_POST['time_limit'] ?? $assessment['time_limit'];

        $stmt = $pdo->prepare("UPDATE assessment_tbl SET instructions = ?, time_limit = ?, total_points = ? WHERE assessment_id = ?");
        $stmt->execute([$instructions, $time_limit, $total_points, $assessment_id]);

        $update_message = "Assessment updated successfully!";

        // Redirect to refresh page
        header("Location: assessment_essay.php?assessment_id=$assessment_id");
        exit();
    }

    if (isset($_POST['unpublish_assessment'])) {
        // Unpublish assessment
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = 'Saved' WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);

        $unpublish_message = "Assessment unpublished successfully!";

        // Redirect to refresh page
        header("Location: assessment_essay.php?assessment_id=$assessment_id");
        exit();
    }


    // Handle adding a new question
    if (isset($_POST['add_question'])) {
        $question_text = $_POST['question'] ?? '';
        $points = $_POST['points'] ?? 0;
        $guided_answer = $_POST['guided_answer'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO questions_esy_tbl (assessment_id, question_text, points, guided_answer) VALUES (?, ?, ?, ?)");
        $stmt->execute([$assessment_id, $question_text, $points, $guided_answer]);

        header("Location: assessment_essay.php?assessment_id=$assessment_id");
        exit();
    }

    // Handle removing a question
    if (isset($_POST['remove_question'])) {
        $question_id = $_POST['question_id'] ?? null;
        if ($question_id) {
            $stmt = $pdo->prepare("DELETE FROM questions_esy_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
        }

        header("Location: assessment_essay.php?assessment_id=$assessment_id");
        exit();
    }

    // Handle updating a question
    if (isset($_POST['update_question'])) {
        $question_id = $_POST['question_id'];
        $question_text = $_POST['question_text'];
        $points = $_POST['points'];
        $guided_answer = $_POST['guided_answer'];

        $stmt = $pdo->prepare("UPDATE questions_esy_tbl SET question_text = ?, points = ?, guided_answer = ? WHERE question_id = ?");
        $stmt->execute([$question_text, $points, $guided_answer, $question_id]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Assessment: <?php echo htmlspecialchars($assessment['name']); ?></title>
    <script>
        async function updateField(endpoint, data) {
            await fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            });
        }

        function updateInstructions() {
            const instructions = document.getElementById('instructions').value;
            updateField('', { update_assessment: 1, instructions });
        }

        function updateTimeLimit() {
            const timeLimit = document.getElementById('time-limit').value;
            updateField('', { update_assessment: 1, time_limit: timeLimit });
        }

        function updateQuestion(questionId) {
            const questionText = document.getElementById(`question-text-${questionId}`).value;
            const points = document.getElementById(`question-points-${questionId}`).value;
            const guidedAnswer = document.getElementById(`question-guided-answer-${questionId}`).value;

            updateField('', {
                update_question: 1,
                question_id: questionId,
                question_text: questionText,
                points,
                guided_answer: guidedAnswer
            });
        }

        async function removeQuestion(questionId) {
            if (confirm("Are you sure you want to remove this question?")) {
                updateField('', { remove_question: 1, question_id: questionId }).then(() => location.reload());
            }
        }
    </script>
</head>
<body>
    <h2>Edit Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>

    <!-- Back Button -->
    <button onclick="history.back()" class="btn btn-secondary">Back</button>
    <a href="teacher_dashboard.php" class="btn btn-primary">Home</a>

    <!-- Display Messages -->
    <?php if ($save_message): ?>
        <p style="color: green;"><?php echo $save_message; ?></p>
    <?php endif; ?>
    <?php if ($publish_message): ?>
        <p style="color: green;"><?php echo $publish_message; ?></p>
    <?php endif; ?>
    <?php if ($update_message): ?>
        <p style="color: green;"><?php echo $update_message; ?></p>
    <?php endif; ?>
    <?php if ($unpublish_message): ?>
        <p style="color: green;"><?php echo $unpublish_message; ?></p>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <!-- Instructions and Time Limit -->
    <h3>Instructions</h3>
    <textarea id="instructions" onchange="updateInstructions()"><?php echo htmlspecialchars($assessment['instructions']); ?></textarea>

    <h3>Time Limit (in minutes)</h3>
    <input type="number" id="time-limit" value="<?php echo $assessment['time_limit']; ?>" onchange="updateTimeLimit()" />

    <!-- Total Points -->
    <h3>Total Points: <?php echo htmlspecialchars($total_points); ?></h3>

    <!-- Update and Unpublish Form -->
    <form method="post">
        <?php if ($assessment['status'] === 'Published'): ?>
            <button type="submit" name="update_assessment">Update Assessment</button>
            <button type="submit" name="unpublish_assessment">Unpublish Assessment</button>
        <?php else: ?>
            <button type="submit" name="save_assessment">Save Assessment</button>
            <button type="submit" name="publish_assessment">Publish Assessment</button>
        <?php endif; ?>
    </form>

    <!-- Add Questions to Assessment -->
    <h3>Add Questions</h3>
    <form method="post">
        <label>Question:</label>
        <input type="text" name="question" required>
        <label>Points:</label>
        <input type="number" name="points" min="0" required>
        <label>Guided Answer (optional):</label>
        <textarea name="guided_answer"></textarea>
        <button type="submit" name="add_question">Add Question</button>
    </form>

    <!-- Display Current Questions -->
    <h3>Current Questions</h3>
    <ol>
        <?php foreach ($questions as $question): ?>
            <li>
                <input type="text" id="question-text-<?php echo $question['question_id']; ?>" value="<?php echo htmlspecialchars($question['question_text']); ?>" onchange="updateQuestion('<?php echo $question['question_id']; ?>')">
                <input type="number" id="question-points-<?php echo $question['question_id']; ?>" value="<?php echo $question['points']; ?>" min="0" onchange="updateQuestion('<?php echo $question['question_id']; ?>')">
                <textarea id="question-guided-answer-<?php echo $question['question_id']; ?>" onchange="updateQuestion('<?php echo $question['question_id']; ?>')"><?php echo htmlspecialchars($question['guided_answer']); ?></textarea>
                <button type="button" onclick="removeQuestion('<?php echo $question['question_id']; ?>')">Remove</button>
            </li>
        <?php endforeach; ?>
    </ol>
</body>
</html>
