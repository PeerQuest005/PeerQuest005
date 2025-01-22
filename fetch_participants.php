<?php
session_start();
require 'config.php';

$roomId = $_SESSION['room_id'] ?? null;
if (!$roomId) {
    echo json_encode(['error' => 'Invalid room ID']);
    exit();
}

$stmt = $pdo->prepare("SELECT rr.student_id, st.username, rr.is_ready, rr.is_host, rr.status 
                        FROM room_ready_tbl rr 
                        JOIN student_tbl st ON rr.student_id = st.student_id 
                        WHERE rr.room_id = ?");
$stmt->execute([$roomId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($participants);
?>
