<?php
require 'auth.php';
require 'config.php';

$class_id = $_POST['class_id'] ?? null;
$name = $_POST['name'] ?? '';
$type = $_POST['type'] ?? '';

if (!$class_id || !$name || !$type) {
    echo "Invalid input.";
    exit();
}

// Insert new assessment
$stmt = $pdo->prepare("INSERT INTO assessment_tbl (class_id, name, type, status) VALUES (?, ?, ?, 'Saved')");
$stmt->execute([$class_id, $name, $type]);

// Get the last inserted assessment ID
$assessment_id = $pdo->lastInsertId();

// Redirect based on assessment type
if ($type == 'Essay') {
    header("Location: assessment_essay.php?assessment_id=$assessment_id");
    
} elseif ($type == 'Multiple Choice - Individual') {
    header("Location: assessment_multiple_choice.php?assessment_id=$assessment_id");

} elseif ($type == 'Essay - Collaborative') {
        header("Location: assessment_essay_collab.php?assessment_id=$assessment_id");

} elseif ($type == 'Multiple Choice - Collaborative') {
    header("Location: assessment_multiple_choice_collab.php?assessment_id=$assessment_id");

}elseif ($type == 'Recitation') {
    header("Location: assessment_recitation.php?assessment_id=$assessment_id");

} elseif ($type == 'True or False') {
    header("Location: assessment_true_false.php?assessment_id=$assessment_id");

} else {
    echo "Invalid assessment type.";
}
exit();
