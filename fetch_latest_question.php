<?php
require 'auth.php';
require 'config.php';

header('Content-Type: application/json');

$question_id = $_POST['question_id'] ?? null;
$selected_option = $_POST['selected_option'] ?? null;
$assessment_id = $_POST['assessment_id'] ?? null;
$room_id = $_POST['room_id'] ?? null;
$student_id = $_POST['student_id'] ?? null;

if (!$question_id || !$selected_option || !$assessment_id || !$room_id || !$student_id) {
    echo json_encode(['error' => 'Invalid input data']);
    exit();
}

try {
    $stmt = $pdo->prepare("INSERT INTO answers_mcq_tbl 
                           (room_id, assessment_id, question_id, selected_option, student_id, submitted_at) 
                           VALUES (?, ?, ?, ?, ?, NOW())
                           ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option)");
    $stmt->execute([$room_id, $assessment_id, $question_id, $selected_option, $student_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}