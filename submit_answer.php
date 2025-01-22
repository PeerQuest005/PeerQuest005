<?php
require 'auth.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve POST data
    $room_id = $_POST['room_id'] ?? null;
    $assessment_id = $_POST['assessment_id'] ?? null;
    $question_id = $_POST['question_id'] ?? null;
    $selected_option = $_POST['selected_option'] ?? null;
    $submitted_by = $_SESSION['student_id'] ?? $_SESSION['user_id'] ?? null;

    if (!$room_id || !$assessment_id || !$question_id || !$selected_option || !$submitted_by) {
        echo json_encode(["status" => "error", "message" => "Missing required data."]);
        exit();
    }

    try {
        // Fetch the correct option and points for the question
        $stmt = $pdo->prepare("SELECT correct_option, points FROM questions_mcq_tbl WHERE question_id = ? AND assessment_id = ?");
        $stmt->execute([$question_id, $assessment_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$question) {
            echo json_encode(["status" => "error", "message" => "Question not found or invalid assessment."]);
            exit();
        }

        $correct_option = $question['correct_option'];
        $points = $question['points'];

        // Check if the selected option is correct
        $is_correct = ($selected_option === $correct_option);
        $gradeIncrement = $is_correct ? $points : 0;

        // Check if this answer has already been submitted by the user
        $stmt = $pdo->prepare("SELECT id FROM answers_mcq_collab_tbl WHERE room_id = ? AND assessment_id = ? AND question_id = ? AND submitted_by = ?");
        $stmt->execute([$room_id, $assessment_id, $question_id, $submitted_by]);
        $existingAnswer = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingAnswer) {
            // If an answer exists, update it with the new choice and adjust the grade accordingly
            $stmt = $pdo->prepare("UPDATE answers_mcq_collab_tbl 
                                  SET selected_option = ?, grades = ?, submitted_at = CURRENT_TIMESTAMP 
                                  WHERE id = ?");
            $stmt->execute([$selected_option, $gradeIncrement, $existingAnswer['id']]);

            echo json_encode(["status" => "success", "message" => "Answer updated successfully."]);
        } else {
            // If no existing answer, insert a new entry
            $stmt = $pdo->prepare("INSERT INTO answers_mcq_collab_tbl (room_id, assessment_id, question_id, selected_option, submitted_by, grades, attempt) 
                                   VALUES (?, ?, ?, ?, ?, ?, 1)");
            $stmt->execute([$room_id, $assessment_id, $question_id, $selected_option, $submitted_by, $gradeIncrement]);

            echo json_encode(["status" => "success", "message" => "Answer submitted successfully."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Error submitting answer: " . $e->getMessage()]);
    }
}
?>
