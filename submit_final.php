<?php
require 'auth.php';
require 'config.php';

$roomId = $_SESSION['room_id'] ?? null;
$assessment_id = $_GET['assessment_id'] ?? null;

if (!$roomId || !$assessment_id) {
    exit("Missing room or assessment ID.");
}

// Finalize answers for the group
$stmt = $pdo->prepare("UPDATE answers_mcq_collab_tbl SET submitted_by = 'group_finalized' WHERE room_id = ?");
$stmt->execute([$roomId]);

echo "Group answers submitted successfully!";
?>
