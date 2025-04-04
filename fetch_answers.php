<?php
require 'config.php';

$roomId = $_GET['room_id'] ?? null;

if ($roomId) {
    // Fetch all votes grouped by question_id and selected_option
    $stmt = $pdo->prepare("
        SELECT question_id, selected_option, COUNT(*) as vote_count 
        FROM answers_mcq_collab_tbl 
        WHERE room_id = ? 
        GROUP BY question_id, selected_option
    ");
    $stmt->execute([$roomId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all questions and options to initialize with 0 counts
    $stmt = $pdo->prepare("SELECT question_id, options FROM questions_mcq_tbl");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize response array with all options set to 0 votes
    $response = [];
    foreach ($questions as $question) {
        $question_id = $question['question_id'];
        $options = json_decode($question['options'], true);
        $response[$question_id] = [];

        foreach ($options as $key => $value) {
            $response[$question_id][$key] = 0; // Default vote count to 0
        }
    }

    // Update response array with actual vote counts
    foreach ($answers as $answer) {
        $question_id = $answer['question_id'];
        $selected_option = $answer['selected_option'];
        $vote_count = $answer['vote_count'];

        $response[$question_id][$selected_option] = $vote_count;
    }

    echo json_encode($response);
}
?>
