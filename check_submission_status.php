<?php
require 'config.php';

$roomId = $_GET['room_id'] ?? null;
$assessment_id = $_GET['assessment_id'] ?? null;

if (!$roomId || !$assessment_id) {
    exit(json_encode(['error' => 'Invalid request.']));
}

// Check if at least one answer was submitted in this room
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM answers_mcq_collab_tbl WHERE room_id = ? AND assessment_id = ?");
$stmt->execute([$roomId, $assessment_id]);
$submitted = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

echo json_encode(['submitted' => $submitted]);
?>
