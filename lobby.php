<?php
require 'auth.php';
require 'config.php';

$roomId = $_SESSION['room_id'] ?? null;
$assessment_id = $_SESSION['assessment_id'] ?? null;
$student_id = $_SESSION['student_id'] ?? null;

if (!$roomId || !$assessment_id || !$student_id) {
    echo "Invalid room, assessment, or student ID.";
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ready'])) {
        $stmt = $pdo->prepare("UPDATE room_ready_tbl SET is_ready = 1 WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$roomId, $student_id]);
        header("Refresh:0");
        exit();
    }

    if (isset($_POST['cancel'])) {
        $stmt = $pdo->prepare("UPDATE room_ready_tbl SET is_ready = 0 WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$roomId, $student_id]);
        header("Refresh:0");
        exit();
    }

    if (isset($_POST['startAssessment'])) {
        // Update the room status to 'started'
        $stmt = $pdo->prepare("UPDATE room_ready_tbl SET status = 'started' WHERE room_id = ?");
        $stmt->execute([$roomId]);
    
        // Fetch the assessment type
        $stmt = $pdo->prepare("SELECT type FROM assessment_tbl WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    
        // Check the assessment type and redirect accordingly
        if ($assessment['type'] === 'Essay - Collaborative') {
            header("Location: take_essay_collab.php?assessment_id=" . urlencode($assessment_id) . "&room_id=" . urlencode($roomId));
        } else {
            header("Location: take_multiple_choice_collaboration.php?assessment_id=" . urlencode($assessment_id) . "&room_id=" . urlencode($roomId));
        }
        exit();
    }
    

    if (isset($_POST['leaveRoom'])) {
        // Check if the user leaving is the host
        $stmt = $pdo->prepare("SELECT is_host FROM room_ready_tbl WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$roomId, $student_id]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Remove the leaving user from the room
        $stmt = $pdo->prepare("DELETE FROM room_ready_tbl WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$roomId, $student_id]);

        // If the leaving user was the host, assign a new host
        if ($userData['is_host']) {
            $stmt = $pdo->prepare("SELECT student_id FROM room_ready_tbl WHERE room_id = ? ORDER BY student_id DESC LIMIT 1");
            $stmt->execute([$roomId]);
            $newHost = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($newHost) {
                $stmt = $pdo->prepare("UPDATE room_ready_tbl SET is_host = 1 WHERE student_id = ?");
                $stmt->execute([$newHost['student_id']]);
            }
        }

        // Redirect to student dashboard
        header("Location: student_dashboard.php");
        exit();
    }
}

// Fetch assessment data for validation
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch();

if ($isHost) {
    $stmt = $pdo->prepare("UPDATE room_ready_tbl SET is_ready = 1 WHERE room_id = ? AND student_id = ?");
    $stmt->execute([$roomId, $student_id]);
    $isReady = 1; // Force ready status for host
}

// Fetch participants in the room with their usernames and is_ready status
$stmt = $pdo->prepare("SELECT rr.student_id, st.username, rr.is_ready, rr.is_host, rr.status 
                        FROM room_ready_tbl rr 
                        JOIN student_tbl st ON rr.student_id = st.student_id 
                        WHERE rr.room_id = ?");
$stmt->execute([$roomId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allReady = true;
$isHost = false;
$isReady = false;
foreach ($participants as $participant) {
    if (!$participant['is_ready']) {
        $allReady = false;
    }
    if ($participant['student_id'] == $student_id) {
        if ($participant['is_host']) {
            $isHost = true;
            $isReady = true;
        }
        $isReady = (int) $participant['is_ready']; // Ensure $isReady is properly interpreted as integer
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lobby Room | PeerQuest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet"> 
    <link rel="stylesheet" href="css/lobby.css">     
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

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
<h1><?php echo htmlspecialchars($assessment['name']); ?> </h1>
    
    <div class="room-code-container" onclick="copyToClipboard()">
        <span class="room-code" id="roomId"><?php echo htmlspecialchars($roomId); ?></span>
        <i class="fas fa-copy copy-icon"></i>
    </div>
    <p class="instruction">Click the code to copy it for sharing.</p>

    <div class="row">
        <!-- Host Column -->
        <div class="column">
            <h3>Host</h3>
            <div id="hostSection"></div>
        </div>

        <!-- Other Participants Column -->
        <div class="column">
            <h3>Participants</h3>
            <div id="participantsSection"></div>
        </div>
    </div>

    <!-- Ready/Cancel Button for Both Players -->
    <div class="btn-section">
        <form method="post">
            <?php if ($isReady === 0): ?>
                <button name="ready" class="btn-custom"><i class="fas fa-thumbs-up"></i> I'm Ready</button>
            <?php else: ?>
                <button name="cancel" class="btn-custom btn-red"><i class="fas fa-ban"></i> Cancel</button>
            <?php endif; ?>
        </form>

        <?php if ($isHost): ?>
    <form method="post" class="start-assessment-container">
        <button type="submit" name="startAssessment" class="btn-custom" id="startAssessmentButton">
            <i class="fas fa-play-circle"></i> Start Assessment
        </button>
    </form>
<?php endif; ?>




    <!-- Centered Leave Room Button -->
    <div class="center-button">
        <form method="post">
            <button type="submit" name="leaveRoom" class="btn-custom btn-red">
                <i class="fas fa-door-open"></i> Leave Room
            </button>
        </form>
    </div>
</div>

<!-- Popup for Host Notification -->
<div class="popup-overlay-host" id="hostPopup">
    <div class="popup-message">
        <i class="fas fa-check-circle"></i>
        <p>All participants are ready. You can now start the assessment.</p>
    </div>
</div>

<!-- Popup for Waiting for Host -->
<div class="popup-overlay" id="waitingPopup">
    <div class="popup-message">
        <i class="fas fa-clock"></i>
        <p>All participants are ready. Waiting for the host to start the assessment...</p>
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

const isHost = <?php echo json_encode($isHost); ?>;
  function updateParticipants() {
    fetch('fetch_participants.php?t=' + new Date().getTime())
        .then(response => response.json())
        .then(data => {
            let hostSection = document.getElementById('hostSection');
            let participantsSection = document.getElementById('participantsSection');
            let allReady = true;

            hostSection.innerHTML = '';
            participantsSection.innerHTML = '';

            data.forEach(participant => {
                let listItem = `
                <div class="participant-item">
                    <span class="participant-name">
                        ${participant.username} 
                        ${participant.is_host ? '<span class="host-badge">(Host)</span>' : ''}
                    </span>
                    <i class="${participant.is_ready ? 'ready fas fa-check-circle' : 'not-ready fas fa-times-circle'} status-icon"></i>
                </div>`;


                if (participant.is_host) {
                    hostSection.innerHTML += listItem;
                } else {
                    participantsSection.innerHTML += listItem;
                }

                if (!participant.is_ready) {
                    allReady = false;
                }
            });

            // Display popup for participants (waiting for host)
            let waitingPopup = document.getElementById('waitingPopup');
            if (allReady && !<?php echo json_encode($isHost); ?>) {
                waitingPopup.style.display = 'flex';  // Ensure the popup appears as a flexbox (or adjust as needed)
            } else {
                waitingPopup.style.display = 'none';
            }

            // Display popup for the host (all participants are ready)
            let hostPopup = document.getElementById('hostPopup');
            if (allReady && <?php echo json_encode($isHost); ?>) {
                hostPopup.style.display = 'block';  // Host notification at the top
            } else {
                hostPopup.style.display = 'none';
            }

            if (isHost) {
                const allReady = data.every(p => p.is_ready);
                const startBtn = document.getElementById('startAssessmentButton');
                startBtn.style.display = allReady ? 'block' : 'none';
            }

        })
        .catch(error => console.error('Error fetching participants:', error));
}


    function copyToClipboard() {
        let roomIdText = document.getElementById('roomId').innerText;
        navigator.clipboard.writeText(roomIdText).then(() => {
            alert('Room ID copied to clipboard: ' + roomIdText);
        });
    }

    setInterval(updateParticipants, 2000);
    updateParticipants();

    function checkRoomStatus() {
    fetch('check_status.php?t=' + new Date().getTime())  // Add timestamp to avoid caching
        .then(response => response.json())
        .then(data => {
            // Check if the room status is 'started'
            if (data.status === 'started') {
                // Redirect based on the assessment type
                if (data.assessment_type === 'Essay - Collaborative') {
                    window.location.href = 'take_essay_collab.php?assessment_id=<?php echo htmlspecialchars($assessment_id); ?>';
                } else {
                    window.location.href = 'take_multiple_choice_collaboration.php?assessment_id=<?php echo htmlspecialchars($assessment_id); ?>';
                }
            }
        })
        .catch(error => console.error('Error:', error));
}


    // Poll every 2 seconds for participants and room status
    setInterval(() => {
        updateParticipants();
        checkRoomStatus();
    }, 2000);

    // Initial call to populate the list immediately
    updateParticipants();

    function copyToClipboard() {
        let roomIdText = document.getElementById('roomId').innerText;
        navigator.clipboard.writeText(roomIdText).then(function() {
            alert('Room ID copied to clipboard: ' + roomIdText);
        }).catch(function(err) {
            alert('Failed to copy: ' + err);
        });
    }    
</script>

</body>
</html>
