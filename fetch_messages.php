<?php
require 'auth.php'; // Auth logic to check for authorized access (ensure user is logged in, etc.)
require 'config.php'; // Database connection file (ensure PDO is properly set up)

// Ensure the necessary parameters are passed via GET
if (isset($_GET['assessment_id']) && isset($_GET['room_id'])) {
    $assessment_id = $_GET['assessment_id'];
    $roomId = $_GET['room_id'];

    // Fetch chat messages via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'fetchMessages') {
        try {
            // Prepare the SQL query to fetch chat messages based on assessment_id and room_id
            $stmt = $pdo->prepare("SELECT chat_history.*, student_tbl.username 
                                   FROM chat_history 
                                   JOIN student_tbl ON chat_history.student_id = student_tbl.student_id 
                                   WHERE chat_history.assessment_id = ? 
                                   AND chat_history.room_id = ? 
                                   ORDER BY chat_history.time_and_date ASC");
            $stmt->execute([$assessment_id, $roomId]); // Execute with the provided parameters

            // Fetch all results as an associative array
            $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Return the results as a JSON response
            exit(json_encode($chatMessages));
        } catch (PDOException $e) {
            // In case of error, return the error message as JSON
            exit(json_encode(['error' => $e->getMessage()]));
        }
    }
} else {
    // In case of missing parameters, return an error message
    exit(json_encode(['error' => 'Missing parameters']));
}
