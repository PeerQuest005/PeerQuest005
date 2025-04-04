<?php
require 'config.php';

$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    exit(json_encode(['error' => 'Invalid request']));
}

try {
    $stmt = $pdo->prepare("SELECT question_modifier_ctr FROM room_ready_tbl WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['question_modifier_ctr' => $progress['question_modifier_ctr'] ?? 0]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
