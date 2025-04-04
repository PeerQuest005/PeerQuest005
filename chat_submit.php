
<?php
require 'auth.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'sendMessage') {
    header('Content-Type: application/json'); // Ensure JSON response

    if (!isset($_SESSION['student_id'])) {
        echo json_encode(['error' => 'Session expired. Please refresh the page.']);
        exit();
    }

    $content = trim($_POST['content'] ?? '');
    if (!empty($content)) {
        try {
            // Assuming you already have $assessment_id, $roomId, and $studentId properly set somewhere
            $stmt = $pdo->prepare("INSERT INTO chat_history (assessment_id, room_id, student_id, content, time_and_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$_POST['assessment_id'], $_POST['room_id'], $_SESSION['student_id'], $content]);

            echo json_encode(['success' => true]);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['error' => $e->getMessage()]);
            exit();
        }
    }

    echo json_encode(['error' => 'Empty message']);
    exit();
}
?>