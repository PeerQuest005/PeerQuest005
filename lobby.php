<?php
require 'auth.php';
require 'config.php';

$roomId = $_SESSION['room_id'] ?? null;
$assessment_id = $_SESSION['assessment_id'] ?? null;

if (!$roomId || !$assessment_id) {
    echo "Invalid room or assessment ID.";
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ready'])) {
        // Mark participant as ready
        $stmt = $pdo->prepare("UPDATE room_ready_tbl SET is_ready = 1 WHERE room_id = ? AND student_id = ?");
        $stmt->execute([$roomId, $_SESSION['student_id']]);
        header("Refresh:0");
        exit();
    }

    if (isset($_POST['startAssessment'])) {
        // Host starts the assessment
        $stmt = $pdo->prepare("UPDATE room_ready_tbl SET status = 'started' WHERE room_id = ?");
        $stmt->execute([$roomId]);
        header("Location: take_multiple_choice_collaboration.php?assessment_id=" . urlencode($assessment_id));
        exit();
    }
}

// Fetch participants in the room with their usernames
$stmt = $pdo->prepare("SELECT rr.student_id, st.username, rr.is_ready, rr.is_host, rr.status 
                        FROM room_ready_tbl rr 
                        JOIN student_tbl st ON rr.student_id = st.student_id 
                        WHERE rr.room_id = ?");
$stmt->execute([$roomId]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allReady = true;
$isHost = false;

// Check if all participants are ready and determine if the user is the host
foreach ($participants as $participant) {
    if (!$participant['is_ready']) {
        $allReady = false;
    }
    if ($participant['student_id'] == $_SESSION['student_id'] && $participant['is_host']) {
        $isHost = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lobby</title>

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
<h1 onclick="copyToClipboard()" style="cursor: pointer;">
    Lobby for Room > <span id="roomId"><?php echo htmlspecialchars($roomId); ?></span> < (click the code to Copy)
</h1>


    <ul>
        <?php foreach ($participants as $participant): ?>
            <li>
                <?php
                echo htmlspecialchars($participant['username']) . ' - ' .
                     ($participant['is_ready'] ? "✔ Ready" : "❌ Not Ready") .
                     ($participant['is_host'] ? " (Host)" : "");
                ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- "I'm Ready" Button -->
    <?php if (!$allReady): ?>
        <form method="post">
            <button name="ready">I'm Ready</button>
        </form>
    <?php endif; ?>

    <!-- Waiting for host message -->
    <?php if ($allReady && !$isHost): ?>
    <p>Waiting for the host to start the assessment...</p>
    <?php endif; ?>


    <!-- Start Assessment Button for the Host -->
    <?php if ($isHost && $allReady): ?>
        <form method="post">
            <button type="submit" name="startAssessment">Start Assessment</button>
        </form>
    <?php endif; ?>

    <!-- AJAX Script for Participants -->
    <script>
    function updateParticipants() {
        fetch('fetch_participants.php?t=' + new Date().getTime())  // Add timestamp to avoid caching
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    return;
                }

                let participantsList = document.querySelector('ul');
                participantsList.innerHTML = '';

                data.forEach(participant => {
                    let listItem = document.createElement('li');
                    listItem.innerHTML = `${participant.username} - ` +
                        (participant.is_ready == 1 ? "✔ Ready" : "❌ Not Ready") +
                        (participant.is_host == 1 ? " (Host)" : "");
                    participantsList.appendChild(listItem);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    function checkRoomStatus() {
        fetch('check_status.php?t=' + new Date().getTime())  // Add timestamp to avoid caching
            .then(response => response.json())
            .then(data => {
                if (data.status === 'started') {
                    window.location.href = 'take_multiple_choice_collaboration.php?assessment_id=<?php echo htmlspecialchars($assessment_id); ?>';
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


    function checkReadinessAndRefresh() {
        fetch('fetch_participants.php?t=' + new Date().getTime())
            .then(response => response.json())
            .then(data => {
                let allReady = data.every(participant => participant.is_ready == 1);
                if (allReady) {
                    location.reload();
                }
            })
            .catch(error => console.error('Error:', error));
    }

    // Check readiness every 5 seconds
    setInterval(checkReadinessAndRefresh, 5000);



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
