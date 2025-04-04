<?php
require 'auth.php';
require 'config.php';

if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
}

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

// Fetch class_id associated with the assessment
$class_id = $assessment['class_id'] ?? null;

if (!$class_id) {
    die("Class ID not found for the selected assessment.");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hostRoom'])) {
        // Host Room
        $roomId = rand(1000, 9999); // Generate random room ID
        $_SESSION['room_id'] = $roomId;

        // Add the host to the room_ready_tbl
        $stmt = $pdo->prepare("INSERT INTO room_ready_tbl (room_id, student_id, assessment_id, class_id, is_ready, is_host) VALUES (?, ?, ?, ?, 0, 1)");
        $stmt->execute([$roomId, $_SESSION['student_id'], $assessment_id, $class_id]);

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
    
        // Check the number of participants in the room
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_ready_tbl WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $participantCount = $stmt->fetchColumn();
    
        if ($participantCount >= 4) { // Check if the room already has 4 participants
            echo "Room is full. You cannot join. <br>";
            echo '<button onclick="history.back()">Go Back</button>';
            exit();
        }
        
    
        $_SESSION['room_id'] = $roomId;
    
        // Add the participant to the room_ready_tbl
        $stmt = $pdo->prepare("INSERT INTO room_ready_tbl (room_id, student_id, assessment_id, class_id, is_ready, is_host) VALUES (?, ?, ?, ?, 0, 0)");
        $stmt->execute([$roomId, $_SESSION['student_id'], $assessment_id, $class_id]);
    
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
    <title>Lobby | PeerQuest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #24243A;
            font-family: 'Inter', sans-serif;
            color: white;
            text-align: center;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .lobby-container {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0px 4px 15px rgba(37, 37, 37, 0.5);
            max-width: 700px;
            width: 100%;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.2rem;
            margin-bottom: 30px;
        }

        h3 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        p{
            font-size: 0.9rem;
        }

        .column {
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .btn-custom {
            background-color:rgb(255, 164, 29);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-custom i {
            margin-right: 10px;
        }

        .btn-custom:hover {
            background-color: #24243A;
        }

        .form-control {
            border: 2px solid white;
            border-radius: 8px;
            padding: 10px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            margin-top: 10px;
        }

        .form-control::placeholder {
            color: #ddd;
        }

        /* Volume icon container */
        .music-icon-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 999;
        }

        .music-icon {
            width: 40px;
            height: 40px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .music-icon:hover {
            transform: scale(1.1);
        }

        .modal-title{
            color: #24243A;
            font-size: 1rem;
            font-weight:600;
        }
        .modal-body{
            color: #24243A;
        }
        .rules-text p {
        text-align: justify;
        color: #24243A;
        margin: 10px 0;
        }

        .form-control{
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid white;
        }

        .btn-rules {
            background-color: #ffffff;
            color: #24243A;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-rules:hover {
            background-color: #24243A;
            color: white;
        }

    </style>


<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>
    <!-- Volume Icon -->
    <div class="music-icon-container">
    <img id="volume-icon" src="images/icons/volume_off.webp" alt="Volume Icon" class="music-icon">
</div>

<!-- Background Music -->
<audio id="background-music" loop>
    <source src="audio/lobby.mp3" type="audio/mpeg">
</audio>

    <div class="lobby-container">
        <h1>ESSAY COLLABORATIVE: <?php echo htmlspecialchars($assessment['name']); ?> </h1>
        <h2>Would you like to host or join a room?</h2>

        <div class="row">
           <!-- Host Room Column -->
<div class="col-md-6">
    <div class="column">
        <h3>Host a Room</h3>
        <p>Create and invite your peers to join the room.</p>
        
        <!-- Hosting Rules Button -->
        <button type="button" class="btn-custom btn-rules" data-bs-toggle="modal" data-bs-target="#rulesModal">
            <i class="fas fa-info-circle"></i> Hosting Rules
        </button>

        <form method="post">
            <input type="hidden" name="assessment_id" value="<?= htmlspecialchars($assessment_id) ?>">
            <button name="hostRoom" class="btn-custom"><i class="fas fa-users"></i>Host a Room</button>
        </form>
    </div>
</div>

<!-- Rules Modal -->
<div class="modal fade" id="rulesModal" tabindex="-1" aria-labelledby="rulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rulesModalLabel">Hosting Rules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="max-height: 300px; overflow-y: auto;">
                <div class="rules-text">
                    <p>Rule 1: The host must ensure that participants join on time.</p>
                    <p>Rule 2: A maximum of 4 participants is allowed per room.</p>
                    <p>Rule 3: Only the host can start the assessment.</p>
                    <p>Rule 4: Ensure all participants understand the rules before starting.</p>
                    <p>Rule 5: The host must not leave the room before the session ends.</p>
                </div>
            </div>
        </div>
    </div>
</div>


            <!-- Join Room Column -->
            <div class="col-md-6">
                <div class="column">
                    <h3>Join a Room</h3>
                    <p>Enter the room code to join an ongoing session.</p>
                    <form method="post">
                        <input type="text" name="roomPin" placeholder="Enter Room Code" class="form-control" required>
                        <button name="joinRoom" class="btn-custom"><i class="fas fa-door-open"></i>Join a Room</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
     // Music playback logic
     const volumeIcon = document.getElementById('volume-icon');
        const audio = document.getElementById('background-music');
        let isPlaying = false;

        function toggleMusic() {
            if (isPlaying) {
                audio.pause();
                volumeIcon.src = 'images/icons/volume_off.webp'; // Switch to muted icon
            } else {
                audio.play();
                volumeIcon.src = 'images/icons/volume_on.webp'; // Switch to volume on icon
            }
            isPlaying = !isPlaying;
        }

        volumeIcon.addEventListener('click', toggleMusic);
    
    </script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

