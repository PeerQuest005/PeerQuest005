<?php
require 'config.php';
session_start();

$roomId = $_SESSION['room_id'] ?? null;

if (!$roomId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid room ID']);
    exit();
}

$stmt = $pdo->prepare("SELECT status FROM room_ready_tbl WHERE room_id = ?");
$stmt->execute([$roomId]);
$status = $stmt->fetchColumn();

echo json_encode(['status' => $status]);
exit();
?>
