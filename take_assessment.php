<?php
require 'auth.php';
require 'config.php';

// Check if the user is a student
if ($_SESSION['role'] != 2) {
    echo "Access denied: Students only.";
    exit();
}

// Check if the assessment ID and class ID are provided in the URL
if (isset($_GET['assessment_id']) && isset($_GET['class_id'])) {
    $assessment_id = $_GET['assessment_id'];
    $class_id = $_GET['class_id'];  // Retrieve class_id from the URL

    // Increment the 'ach_answered_assessments' field for the logged-in user
    $stmt = $pdo->prepare("UPDATE student_tbl SET ach_answered_assessments = ach_answered_assessments + 1 WHERE student_id = ?");
    $stmt->execute([$_SESSION['student_id']]);

    // Fetch the assessment details to determine the type
    $stmt = $pdo->prepare("SELECT type FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($assessment) {
        // Redirect to the appropriate assessment page based on type
        switch ($assessment['type']) {
            case 'Essay':
                header("Location: take_essay.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            case 'Essay - Collaborative':
                header("Location: host_or_join.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            case 'True or False':
                header("Location: take_true_false.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            case 'Multiple Choice - Individual':
                header("Location: take_multiple_choice.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            case 'Multiple Choice - Collaborative':
                header("Location: host_or_join.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            case 'Recitation':
                header("Location: take_recitation.php?assessment_id=" . $assessment_id . "&class_id=" . $class_id);
                exit();
            default:
                echo "Invalid Assessment Type.";
                exit();
        }
    } else {
        echo "Assessment not found.";
        exit();
}
} else {
    echo "Assessment ID and Class ID are required.";
    exit();
}
?>
