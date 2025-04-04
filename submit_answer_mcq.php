<?php
require 'config.php';
require 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'submitAnswer') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $room_id = $_POST['room_id'] ?? null;
    $student_id = $_POST['student_id'] ?? null;
    $question_id = $_POST['question_id'] ?? null;
    $selected_option = strtoupper(trim($_POST['selected_option'] ?? ''));

    if (!$assessment_id || !$room_id || !$student_id || !$question_id || !in_array($selected_option, ['A', 'B', 'C', 'D'])) {
        echo json_encode(['error' => 'Invalid input data.']);
        exit();
    }

    try {
        // Fetch correct option and points
        $stmt = $pdo->prepare("SELECT correct_option, points FROM questions_mcq_tbl WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $question = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$question) {
            echo json_encode(['error' => 'Question not found.']);
            exit();
        }

        $is_correct = ($selected_option === $question['correct_option']) ? $question['points'] : 0;

        // Insert or update the answer in the database
        $stmt = $pdo->prepare("INSERT INTO answers_mcq_tbl (room_id, assessment_id, student_id, question_id, selected_option, submitted_at, grades, attempt)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)
                ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), grades = VALUES(grades)");

        $stmt->execute([$room_id, $assessment_id, $student_id, $question_id, $selected_option, $is_correct]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

echo json_encode(['error' => 'Invalid request.']);
exit();
