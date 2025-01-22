<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;

// Fetch assessment data
$assessment = null;
if ($assessment_id) {
    $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        die("Assessment not found.");
    }
} else {
    die("No assessment selected.");
}

// Fetch existing questions
$stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total points dynamically
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instructions = $_POST['instructions'] ?? $assessment['instructions'];
    $time_limit = $_POST['time_limit'] ?? $assessment['time_limit'];

    if (isset($_POST['save_assessment']) || isset($_POST['publish_assessment'])) {
        if (count($questions) === 0) {
            $error_message = "Please add at least one question before saving or publishing.";
        } else {
            $status = isset($_POST['publish_assessment']) ? 'Published' : 'Saved';
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = ?, instructions = ?, time_limit = ?, total_points = ? WHERE assessment_id = ?");
            $stmt->execute([$status, $instructions, $time_limit, $total_points, $assessment_id]);

            $assessment['instructions'] = $instructions;
            $assessment['time_limit'] = $time_limit;
            $assessment['status'] = $status;

            $save_message = isset($_POST['save_assessment']) ? "Assessment saved successfully!" : "";
            $publish_message = isset($_POST['publish_assessment']) ? "Assessment published successfully!" : "";
        }
    }

    if (isset($_POST['update_assessment'])) {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET instructions = ?, time_limit = ? WHERE assessment_id = ?");
        $stmt->execute([$instructions, $time_limit, $assessment_id]);

        $assessment['instructions'] = $instructions;
        $assessment['time_limit'] = $time_limit;

        $update_message = "Assessment updated successfully!";
    }

    if (isset($_POST['unpublish_assessment'])) {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = 'Saved' WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);

        $assessment['status'] = 'Saved';
        $unpublish_message = "Assessment unpublished successfully!";
    }

    if (isset($_POST['save_changes'])) {
        $question_id = $_POST['question_id'];
        $question_text = $_POST['question_text'];
        $points = $_POST['points'];
        $correct_answer = $_POST['correct_answer'];
    
        if (empty($question_text)) {
            $error_message = "Question text cannot be empty.";
        } else {
            $stmt = $pdo->prepare("UPDATE questions_tf_tbl SET question_text = ?, points = ?, correct_answer = ? WHERE question_id = ?");
            $stmt->execute([$question_text, $points, $correct_answer, $question_id]);
    
            // Update total points dynamically after editing a question
            $stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ?");
            $stmt->execute([$assessment_id]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            $total_points = array_sum(array_column($questions, 'points'));
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET total_points = ? WHERE assessment_id = ?");
            $stmt->execute([$total_points, $assessment_id]);
    
            header("Location: assessment_true_false.php?assessment_id=$assessment_id");
            exit();
        }
    }
    

    // Handle adding a new True/False question
    if (isset($_POST['add_question'])) {
        $question_text = $_POST['question'] ?? '';
        $points = $_POST['points'] ?? 1; // Default to 1 point per question
        $correct_answer = $_POST['correct_answer'] ?? 'True'; // Default answer is True
        $time_limit = $_POST['time_limit'] ?? $assessment['time_limit']; // Get time limit from the form

        if (empty($question_text)) {
            $error_message = "Question text cannot be empty.";
        } else {
            // Insert the new question
            $stmt = $pdo->prepare("INSERT INTO questions_tf_tbl (assessment_id, question_text, points, correct_answer) VALUES (?, ?, ?, ?)");
            $stmt->execute([$assessment_id, $question_text, $points, $correct_answer]);

            // Update the assessment with the new time limit
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET time_limit = ? WHERE assessment_id = ?");
            $stmt->execute([$time_limit, $assessment_id]);

            // Redirect to refresh the page after adding the question
            header("Location: assessment_true_false.php?assessment_id=$assessment_id");
            exit();
        }
    }

    // Handle removing a question
    if (isset($_POST['remove_question'])) {
        $question_id = $_POST['question_id'] ?? null;
        if ($question_id) {
            $stmt = $pdo->prepare("DELETE FROM questions_tf_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
        }

        header("Location: assessment_true_false.php?assessment_id=$assessment_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit True/False Assessment: <?php echo htmlspecialchars($assessment['name'] ?? ''); ?></title>
</head>
<body>
    <h2>Edit True/False Assessment: <?php echo htmlspecialchars($assessment['name'] ?? ''); ?></h2>

    <!-- Back Button -->
    <button onclick="history.back()" class="btn btn-secondary">Back</button>
    <a href="teacher_dashboard.php" class="btn btn-primary">Home</a>

    <!-- Display Messages -->
    <?php if (!empty($save_message)): ?>
        <p style="color: green;"><?php echo $save_message; ?></p>
    <?php endif; ?>
    <?php if (!empty($publish_message)): ?>
        <p style="color: green;"><?php echo $publish_message; ?></p>
    <?php endif; ?>
    <?php if (!empty($update_message)): ?>
        <p style="color: green;"><?php echo $update_message; ?></p>
    <?php endif; ?>
    <?php if (!empty($unpublish_message)): ?>
        <p style="color: green;"><?php echo $unpublish_message; ?></p>
    <?php endif; ?>
    <?php if (!empty($error_message)): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <!-- Instructions and Time Limit -->
        <form method="post">
        <h3>Instructions</h3>
        <textarea name="instructions"><?php echo htmlspecialchars($assessment['instructions'] ?? ''); ?></textarea>\
        <h3>Time Limit (in minutes)</h3>
        <input type="number" name="time_limit" value="<?php echo htmlspecialchars($assessment['time_limit'] ?? ''); ?>" />

        

        <!-- Save/Publish/Unpublish Buttons -->
        <?php if ($assessment['status'] === 'Published'): ?>
            <button type="submit" name="update_assessment">Update Assessment</button>
            <button type="submit" name="unpublish_assessment">Unpublish Assessment</button>
        <?php else: ?>
            <button type="submit" name="save_assessment">Save Assessment</button>
            <button type="submit" name="publish_assessment">Publish Assessment</button>
        <?php endif; ?>
    

    <!-- Total Points -->
    <h3>Total Points: <?php echo htmlspecialchars($total_points); ?></h3>

    <!-- Add Questions -->
    <h3>Add True/False Questions</h3>
    <form method="post">
    
        

        <input type="text" name="question" placeholder="Question">
        <input type="number" name="points" min="1" value="1" placeholder="Points" required>
        <select name="correct_answer">
            <option value="True">True</option>
            <option value="False">False</option>
        </select>
        <button type="submit" name="add_question">Add Question</button>
        </form>
    </form>

    <!-- Current Questions -->
    <h3>Current Questions</h3>
    <ol>
    <?php foreach ($questions as $question): ?>
        <li>
            <form method="post">
                <input type="hidden" name="question_id" value="<?php echo $question['question_id']; ?>">
                
                <p>Question:</p>
                <textarea name="question_text" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                
                <p>Points:</p>
                <input type="number" name="points" value="<?php echo htmlspecialchars($question['points']); ?>" min="1" required>
                
                <p>Correct Answer:</p>
                <select name="correct_answer">
                    <option value="True" <?php echo $question['correct_answer'] === 'True' ? 'selected' : ''; ?>>True</option>
                    <option value="False" <?php echo $question['correct_answer'] === 'False' ? 'selected' : ''; ?>>False</option>
                </select>
                
                <button type="submit" name="save_changes">Save Changes</button>
                <button type="submit" name="remove_question">Remove</button>
            </form>
        </li>
    <?php endforeach; ?>
    </ol>

</body>
</html>
