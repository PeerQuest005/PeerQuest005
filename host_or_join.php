<?php
require 'auth.php';
require 'config.php';

// Get the student ID from the session
$student_id = $_SESSION['student_id'] ?? null;

if ($student_id) {
    // Increment ach_collaborated by 1
    $stmt = $pdo->prepare("UPDATE student_tbl SET ach_collaborated = ach_collaborated + 1 WHERE student_id = ?");
    $stmt->execute([$student_id]);
}

// Get assessment ID from the URL
$assessment_id = $_GET['assessment_id'] ?? null;

// Check if assessment ID exists and is valid
if (!$assessment_id) {
    die("No assessment selected.");
}

// Check if the user has already attempted the assessment
$stmt = $pdo->prepare("SELECT COUNT(*) FROM answers_mcq_collab_tbl WHERE assessment_id = ? AND submitted_by = ?");
$stmt->execute([$assessment_id, $student_id]);
$attempt_count = $stmt->fetchColumn();

if ($attempt_count > 0) {
    die("You have already answered this assessment.");
}

// Fetch assessment data for validation
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch();

if (!$assessment) {
    die("Assessment not found.");
}

$_SESSION['assessment_id'] = $assessment_id;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hostRoom'])) {
        // Host Room
        $roomId = rand(1000, 9999); // Generate random room ID
        $_SESSION['room_id'] = $roomId;

        // Add the host to the room_ready_tbl
        $stmt = $pdo->prepare("INSERT INTO room_ready_tbl (room_id, student_id, assessment_id, is_ready, is_host) VALUES (?, ?, ?, 0, 1)");
        $stmt->execute([$roomId, $_SESSION['student_id'], $assessment_id]);

        header("Location: lobby.php?room_id=$roomId&assessment_id=$assessment_id");
        exit();
    } elseif (isset($_POST['joinRoom'])) {
        // Join Room
        $roomId = $_POST['roomPin'] ?? null;

        if (!$roomId) {
            echo "Please enter a valid room PIN.";
            exit();
        }

        // Validate room ID and assessment ID
        $stmt = $pdo->prepare("SELECT * FROM room_ready_tbl WHERE room_id = ? AND assessment_id = ?");
        $stmt->execute([$roomId, $assessment_id]);
        $room = $stmt->fetch();

        if (!$room) {
            echo "Invalid Room PIN or Room does not match the selected assessment.";
            exit();
        }

        $_SESSION['room_id'] = $roomId;

        // Add the participant to the room_ready_tbl
        $stmt = $pdo->prepare("INSERT INTO room_ready_tbl (room_id, student_id, assessment_id, is_ready, is_host) VALUES (?, ?, ?, 0, 0)");
        $stmt->execute([$roomId, $_SESSION['student_id'], $assessment_id]);

        header("Location: lobby.php?room_id=$roomId&assessment_id=$assessment_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Host or Join</title>

    <script>
        (function() {
            history.pushState(null, null, document.URL);
            window.addEventListener('popstate', function () {
            history.pushState(null, null, document.URL);
            });
        })();
</script>

</head>
<body>
<a href="student_dashboard.php" class="btn btn-primary">Dashboard</a>

    <h1>Collaborative Assessment</h1>
    <h2>Would you like to host or join a room?</h2>
    <form method="post">
        <!-- Include assessment_id in the form -->
        <input type="hidden" name="assessment_id" value="<?= htmlspecialchars($assessment_id) ?>">
        <button name="hostRoom">Host a Room</button>
        <br><br>
        <input type="text" name="roomPin" placeholder="Enter Room Code to Join">
        <button name="joinRoom">Join a Room</button>
    </form>
</body>
</html>
