<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;
$action = $_GET['action'] ?? null;

if (!isset($assessment_id) || !$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Fetch assessment details
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch();

if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

// Perform actions based on the action parameter
if (in_array($action, ['publish', 'unpublish', 'delete'])) {
    if ($action === 'publish') {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = 'Published' WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        $message = "Assessment published successfully.";
    } elseif ($action === 'unpublish') {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = 'Saved' WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        $message = "Assessment unpublished successfully.";
    } elseif ($action === 'delete') {
        // Delete assessment and its questions
        $stmt = $pdo->prepare("DELETE FROM questions_esy_tbl WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);

        $stmt = $pdo->prepare("DELETE FROM assessment_tbl WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);

        $message = "Assessment deleted successfully.";
    }
} else {
    echo "Invalid action.";
    exit();
}

// Redirect back to view_assessment_teacher.php with a success message
header("Location: view_assessment_teacher.php?class_id=" . urlencode($assessment['class_id']) . "&message=" . urlencode($message));
exit();
?>
