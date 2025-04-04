<?php
require 'config.php';

$room_id = $_GET['room_id'] ?? null;
if (!$room_id) {
    echo "0";
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) AS user_count FROM room_ready_tbl WHERE room_id = ?");
$stmt->execute([$room_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
echo $userData['user_count'] ?? 0;
?>
