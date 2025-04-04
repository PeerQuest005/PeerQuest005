<?php
require 'config.php';

// Retrieve room_id from GET request
$room_id = $_GET['room_id'] ?? null;

if (!$room_id) {
    die("Error: Room ID is missing.");
}

// Fetch assessment_id based on the given room_id
$stmt = $pdo->prepare("SELECT DISTINCT assessment_id FROM answers_mcq_collab_tbl WHERE room_id = ? LIMIT 1");
$stmt->execute([$room_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
$assessment_id = $assessment['assessment_id'] ?? null;

if (!$assessment_id) {
    die("Error: Assessment ID not found for the given Room ID.");
}

// Retrieve all rooms under the same assessment_id
$stmt = $pdo->prepare("SELECT DISTINCT room_id FROM answers_mcq_collab_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Retrieve assessment details
$stmt = $pdo->prepare("SELECT name FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
$assessment_name = $assessment['name'] ?? 'Unknown Assessment';

// Fetch all students who submitted answers under all rooms of the same assessment
$stmt = $pdo->prepare("
    SELECT a.room_id, s.student_id, s.username, COALESCE(SUM(a.grades), 0) AS total_grade
    FROM answers_mcq_collab_tbl a
    JOIN student_tbl s ON a.submitted_by = s.student_id
    WHERE a.assessment_id = ?
    GROUP BY a.room_id, s.student_id, s.username
    ORDER BY total_grade DESC
");
$stmt->execute([$assessment_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare an array to store the highest score per room
$room_scores = [];

foreach ($rooms as $room) {
    $room_id = $room['room_id'];

    // Get students in the current room
    $room_students = array_filter($students, function ($student) use ($room_id) {
        return $student['room_id'] == $room_id;
    });

    // Get the highest score in the room (if students exist)
    $top_student = reset($room_students); // First student in sorted list
    $highest_score = $top_student['total_grade'] ?? 0; // Default to 0 if no students

    // Store room ID and its highest score
    $room_scores[] = [
        'room_id' => $room_id,
        'highest_score' => $highest_score
    ];
}

// Sort rooms by highest score in descending order
usort($room_scores, function ($a, $b) {
    return $b['highest_score'] <=> $a['highest_score'];
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard-MCQ Collab | PeerQuest</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/leaderboard_mcq.css">
</head>

<body>

    <div class="leaderboard-container">
    <div class="button-container">
    <button onclick="history.back()" class="dashboard-button">Go Back</button>

</div>


        <h2 class="mb-4">Leaderboard for Assessment: <?php echo htmlspecialchars($assessment_name); ?></h2>

        <?php 
        $room_rank = 1; // Room ranking counter
        foreach ($room_scores as $room_data): 
            $room_id = $room_data['room_id'];
            $highest_score = $room_data['highest_score'];

            // Assign medals based on room ranking (Only top 3 get medals)
            $room_medal = "";
            if ($room_rank === 1) {
                $room_medal = "🥇"; // Gold
            } elseif ($room_rank === 2) {
                $room_medal = "🥈"; // Silver
            } elseif ($room_rank === 3) {
                $room_medal = "🥉"; // Bronze
            }

            // Get students in this room
            $room_students = array_filter($students, function ($student) use ($room_id) {
                return $student['room_id'] == $room_id;
            });

            // Sort students in descending order by total_grade (in case order is needed)
            usort($room_students, function ($a, $b) {
                return $b['total_grade'] <=> $a['total_grade'];
            });
        ?>
        
        <h3 class="mb-3">Rank <?php echo $room_rank; ?> <?php echo $room_medal; ?> Room ID: <?php echo htmlspecialchars($room_id); ?> | Total Score: <?php echo htmlspecialchars($highest_score); ?></h3>

        <?php if (!empty($room_students)): ?>
            <?php foreach ($room_students as $student): ?>
                <div class="leaderboard-card">
                    <span><?php echo htmlspecialchars($student['username']); ?></span>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No students found in this room.</p>
        <?php endif; ?>

        <?php 
            $room_rank++; // Increment room rank for the next room
        endforeach; 
        ?>
    </div>

</body>

</html>
