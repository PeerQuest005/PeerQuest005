<?php
require 'config.php';
session_start();

$roomId = $_SESSION['room_id'] ?? null;
$assessment_id = $_SESSION['assessment_id'] ?? null;

if (!$roomId || !$assessment_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID or assessment ID']);
    exit();
}

// Get the status of the room
$stmt = $pdo->prepare("SELECT status FROM room_ready_tbl WHERE room_id = ? LIMIT 1");
$stmt->execute([$roomId]);
$status = $stmt->fetchColumn();

// Fetch the assessment type
$stmt = $pdo->prepare("SELECT type FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Return the room status and assessment type
echo json_encode([
    'status' => $status ?? 'waiting', // Default to 'waiting' if no status found
    'assessment_type' => $assessment['type'] ?? 'Unknown' // Default to 'Unknown' if type not found
]);

exit();
?>
