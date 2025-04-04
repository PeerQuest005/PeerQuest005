<?php
require 'auth.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_code = $_POST['class_code'] ?? '';
    $student_id = $_SESSION['student_id'] ?? null;

    if (!$class_code || !$student_id) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid class code.']);
        exit();
    }

    // Check if the class with the provided code exists
    $stmt = $pdo->prepare("SELECT class_id, class_subject, class_section FROM class_tbl WHERE class_code = ?");
    $stmt->execute([$class_code]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$class) {
        echo json_encode(['status' => 'error', 'message' => 'Class code not found.']);
        exit();
    }

    $class_id = $class['class_id'];

    // Check if the student is already enrolled in this class
    $stmt = $pdo->prepare("SELECT 1 FROM student_classes WHERE student_id = ? AND class_id = ?");
    $stmt->execute([$student_id, $class_id]);

    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'You have already joined this class.']);
        exit();
    }

    // If student is not in the class, enroll them
    $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id) VALUES (?, ?)");
    if ($stmt->execute([$student_id, $class_id])) {
        // Return success with new class details
        echo json_encode([
            'status' => 'success',
            'message' => 'Successfully joined the class!',
            'class' => [
                'class_id' => $class_id,
                'class_subject' => $class['class_subject'],
                'class_section' => $class['class_section']
            ]
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to join class. Please try again.']);
    }
}
?>
