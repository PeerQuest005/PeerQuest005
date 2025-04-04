<?php
require 'auth.php';
require 'config.php';
require 'js/count_users.php';

if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
}


// Retrieve assessment_id and session details
$assessment_id = $_GET['assessment_id'] ?? null;
$roomId = $_SESSION['room_id'] ?? null;
$studentId = $_SESSION['student_id'] ?? null;

if (!$assessment_id || !$roomId || !$studentId) {
    exit("Invalid session or assessment ID. Please return to the lobby.");
}


// Handle real-time check for expiration via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'checkExpiration') {
    try {
        $stmt = $pdo->prepare("SELECT expired FROM expired_status WHERE assessment_id = ? AND student_id = ?");
        $stmt->execute([$assessment_id, $studentId]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($status && $status['expired'] == 1) {
            echo json_encode([
                'expired' => true,
                'message' => 'Your time ran out!',
                'redirect_url' => 'timed_out.php'
            ]);
        } else {
            echo json_encode(['expired' => false]);
        }
        exit();
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}


// Handle real-time check for completion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'checkCompletion') {
    try {
        $stmt = $pdo->prepare("SELECT completed FROM assessment_status WHERE assessment_id = ? AND student_id = ?");
        $stmt->execute([$assessment_id, $studentId]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['completed' => ($status && $status['completed'] == 1)]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}


// Fetch assessment details
try {
    $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assessment) {
        exit("Error: Assessment not found.");
    }

    // Get time limit (in minutes) and convert to seconds
    $timeLimit = isset($assessment['time_limit']) ? $assessment['time_limit'] * 60 : 0;
    $startTime = time();
    $endTime = $startTime + $timeLimit;
} catch (PDOException $e) {
    exit("Error fetching assessment: " . $e->getMessage());
}

// Fetch the number of users in the room
$userCount = countUsersInRoom($pdo, $roomId);

// Fetch unanswered questions and randomize order
try {
    // Fetch all unanswered questions for the current student
    $stmt = $pdo->prepare("SELECT q.* FROM questions_esy_tbl q
                           LEFT JOIN answers_esy_collab_tbl a ON q.question_id = a.question_id AND a.student_id = ?
                           WHERE q.assessment_id = ? AND a.question_id IS NULL
                           ORDER BY RAND()");
    $stmt->execute([$studentId, $assessment_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        header("Location: student_dashboard.php");  // All questions answered
        exit();
    }

    $currentQuestionIndex = 0;  // Always start with the first random question
    $question = $questions[$currentQuestionIndex];
} catch (PDOException $e) {
    exit("Error retrieving questions: " . $e->getMessage());
}


// Ensure class_id is available
$classId = $_GET['class_id'] ?? null;

// Handle form submission and store the user's answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_question_id'])) {
    $question_id = (int) $_POST['current_question_id'];
    $answer = trim($_POST["answer_$question_id"] ?? '');

    if ($answer !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO answers_esy_collab_tbl 
            (assessment_id, room_id, student_id, class_id, question_id, answer, grades, submitted_at, attempt, ready) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), 1, 0) 
            ON DUPLICATE KEY UPDATE answer = ?, grades = 0, submitted_at = NOW()");
            $stmt->execute([$assessment_id, $roomId, $studentId, !empty($classId) ? $classId : 0, $question_id, $answer, $answer]);
            if (isset($_COOKIE['timed_out'])) {
                setcookie('timed_out', '', time() - 3600, '/');
                $expiredStmt = $pdo->prepare("INSERT INTO expired_status (assessment_id, student_id, expired) 
                                        VALUES (?, ?, 1) 
                                        ON DUPLICATE KEY UPDATE expired = 1");
                $expiredStmt->execute([$assessment_id, $studentId]);
                header("Location: timed_out.php");
                exit();
            }

            // Check if this was the last question
            if (count($questions) == 1) {
                // Mark assessment as completed
                $completeStmt = $pdo->prepare("INSERT INTO assessment_status (assessment_id, student_id, completed) 
                                              VALUES (?, ?, 1) 
                                              ON DUPLICATE KEY UPDATE completed = 1");
                $completeStmt->execute([$assessment_id, $studentId]);

                // Redirect to the dashboard after submission
                header("Location: submission_success.php");  // ✅ Corrected redirection
                exit();
            } else {
                // Redirect to the next question
                header("Location: take_essay_collab.php?assessment_id=$assessment_id");
                exit();
            }
        } catch (PDOException $e) {
            exit("Error saving answer: " . $e->getMessage());
        }
    }
}



// Handle chat submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'sendMessage') {
    $content = trim($_POST['content']);
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO chat_history (assessment_id, room_id, student_id, content, time_and_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$assessment_id, $roomId, $studentId, $content]);

            exit(json_encode(['success' => true]));
        } catch (PDOException $e) {
            exit(json_encode(['error' => $e->getMessage()]));
        }
    }
    exit(json_encode(['error' => 'Empty message']));
}

// Fetch chat messages via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'fetchMessages') {
    try {
        $stmt = $pdo->prepare("SELECT chat_history.*, student_tbl.username FROM chat_history 
                       JOIN student_tbl ON chat_history.student_id = student_tbl.student_id 
                       WHERE chat_history.assessment_id = ? AND chat_history.room_id = ? 
                       ORDER BY chat_history.time_and_date ASC");
        $stmt->execute([$assessment_id, $roomId]);

        $chatMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        exit(json_encode($chatMessages));
    } catch (PDOException $e) {
        exit(json_encode(['error' => $e->getMessage()]));
    }
}


// Mark as expired when time runs out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'setExpired') {
    try {
        $stmt = $pdo->prepare("INSERT INTO expired_status (assessment_id, student_id, expired) 
                              VALUES (?, ?, 1) 
                              ON DUPLICATE KEY UPDATE expired = 1");
        $stmt->execute([$assessment_id, $studentId]);

        echo json_encode(['success' => true]);
        exit();
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Essay Collab | <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_essay_collab.css">
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">


</head>

<body>

    <body>

        <div class="top-bar">
            <h2 class="assessment-title">Essay Collaborative: <?php echo htmlspecialchars($assessment['name']); ?></h2>

            <p><strong>Room ID:</strong> <?php echo $roomId; ?> | Users: <span
                    id="user-count"><?php echo $userCount; ?></span></p>
        </div>
        <!-- Volume Icon -->
        <div class="music-icon-container">
            <img id="volume-icon" src="images/icons/volume_on.webp" alt="Volume Icon" class="music-icon">
        </div>

        <!-- Background Music -->
        <audio id="background-music" autoplay loop>
            <source src="audio/collab-take.mp3" type="audio/mpeg">
        </audio>

        <div class="assessment-container">


            <p><strong>Instructions:</strong>
                <?php echo htmlspecialchars($assessment['instructions'] ?? 'No instructions provided.'); ?></p>
            <p><strong>Total Points:</strong> <?php echo htmlspecialchars($assessment['total_points']); ?></p>



            <div class="timer-box">
                <progress id="progressBar" max="100" value="100"></progress>
                <div id="timer" class="timer-text"></div>
            </div>

            <form method="POST" id="collab_question" class="assessment-form">
                <div class="question-card active">
                    <p class="question-number">QUESTION:
                    <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                    </p>
                    <div class="textarea-wrapper">
                        <textarea class="input-field" name="answer_<?php echo $question['question_id']; ?>" rows="5"
                            cols="50" required placeholder="Type your answers here..."
                            oninput="updateWordCount(this)"></textarea>
                        <div class="word-counter">0 words</div>
                        <input type="hidden" name="current_question_id"
                            value="<?php echo $question['question_id']; ?>" />
                    </div>
                </div>

                <div class="navigation-buttons">
                    <button type="submit" id="next-button" class="submit-btn">
                        <?php echo (count($questions) == 1) ? "Submit Assessment" : "Next Question"; ?>
                    </button>
                </div>
            </form>

            <!-- Floating Chat Button -->
            <div id="chat-circle">
                <img src="images/charaubelle/C_teacher_eyesmile.webp" alt="Chat Icon" width="70px">
            </div>
            <div id="chat-tooltip">Tap me to chat with your peers</div>

            <!-- Chat Section -->
            <div id="chat-panel">
                <div class="chat-header">
                    <p class="chat-close">Close</p>
                    <h3>Room Chat</h3>
                </div>
                <div class="chat-messages" id="chat-messages"></div>
                <div class="chat-input-section">
                    <input type="text" id="chat-message-input" placeholder="'/' to type a message">
                    <button id="send-message" class="btn btn-success">Send</button>
                </div>
            </div>
            <!-- Universal Action Modal (For Right-Click, Copy, Cut, Paste) -->
            <div id="actionModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeActionModal()">&times;</span>
                    <img src="images/charaubelle/C_angry.webp" alt="Angry Warning" class="modal-gif">
                    <h2>Oops! 🚫</h2>
                    <p id="modal-message">This action is not allowed.</p>
                    <button onclick="closeActionModal()" class="btn-primary">OK</button>
                </div>
            </div>


            <script>
                const assessmentId = "<?php echo $assessment_id; ?>";
                const roomId = "<?php echo $roomId; ?>";
                const studentId = "<?php echo $studentId; ?>";
                const chatMessages = document.getElementById('chat-messages');
                const chatInput = document.getElementById('chat-message-input');
                const sendButton = document.getElementById('send-message');
                sendButton.addEventListener('click', sendMessage);

                const timerDisplay = document.getElementById('timer');

                // Retrieve assessment end time from PHP
                const timeLimit = <?php echo isset($timeLimit) ? $timeLimit : 0; ?>;
                const now = Math.floor(Date.now() / 1000);
                let endTime = parseInt(localStorage.getItem(`endTime_${assessmentId}_${studentId}`), 10);

                // If no endTime is stored, set it
                if (!endTime || isNaN(endTime)) {
                    endTime = now + timeLimit;
                    localStorage.setItem(`endTime_${assessmentId}_${studentId}`, endTime);
                }


                function startTimer() {
                    function updateTimer() {
                        const now = Math.floor(Date.now() / 1000);
                        const remainingTime = Math.max(0, endTime - now);
                        const minutes = Math.floor(remainingTime / 60);
                        const seconds = remainingTime % 60;
                        timerDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;

                        // Update progress bar
                        const progressBar = document.getElementById('progressBar');
                        const percentage = (remainingTime / timeLimit) * 100;
                        progressBar.value = percentage;

                        if (remainingTime <= 0) {
                            clearInterval(timerInterval);
                            markAsExpired(); // Auto-mark as expired
                            document.cookie = "timed_out=true; path=/";
                            document.getElementById('collab_question').submit();
                        }
                    }



                    updateTimer();
                    const timerInterval = setInterval(updateTimer, 1000);
                }

                function markAsExpired() {
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `ajax=setExpired&assessment_id=${assessmentId}&student_id=${studentId}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                window.location.href = "timed_out.php";
                            }
                        })
                        .catch(error => console.error("Expiration Error:", error));
                }

                function checkExpirationStatus() {
                    fetch(`?ajax=checkExpiration&assessment_id=${assessmentId}&student_id=${studentId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.expired) {
                                alert("This assessment has expired. Redirecting to the dashboard.");
                                window.location.href = "student_dashboard.php";
                            }
                        })
                        .catch(error => console.error("Expiration Check Error:", error));
                }

                // Start checking expiration status every 5 seconds
                setInterval(checkExpirationStatus, 500);
                startTimer();

                // Fetch chat messages dynamically
                function fetchChatMessages() {
                    fetch(`?ajax=fetchMessages&assessment_id=${assessmentId}&room_id=${roomId}`)
                        .then(response => response.json())
                        .then(data => {
                            chatMessages.innerHTML = '';
                            data.forEach(msg => {
                                const messageDiv = document.createElement('div');
                                messageDiv.innerHTML = `<strong>${msg.username}:</strong> ${msg.content}`;
                                chatMessages.appendChild(messageDiv);
                            });
                        })
                        .catch(error => console.error("Chat Fetch Error:", error));
                }
                setInterval(fetchChatMessages, 2000);

                function checkExpirationStatus() {
                    fetch(`?ajax=checkExpiration&assessment_id=${assessmentId}&student_id=${studentId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.expired) {
                                window.location.href = "timed_out.php";
                            }
                        })
                        .catch(error => console.error("Expiration Check Error:", error));
                }

                // Check expiration every 5 seconds
                setInterval(checkExpirationStatus, 1000);

                document.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    document.getElementById("rightClickModal").style.display = "flex";
                });

                // Close modal function
                // Function to show the modal with dynamic messages
                function showActionModal(message) {
                    document.getElementById("modal-message").innerText = message;
                    document.getElementById("actionModal").style.display = "flex";
                }

                // Close the modal
                function closeActionModal() {
                    document.getElementById("actionModal").style.display = "none";
                }

                // Disable Right-Click
                document.addEventListener('contextmenu', (e) => {
                    e.preventDefault();
                    showActionModal("Right-click is disabled to prevent copying.");
                });

                // Disable Copy
                document.addEventListener('copy', (e) => {
                    e.preventDefault();
                    showActionModal("Copying content is not allowed.");
                });

                // Disable Cut
                document.addEventListener('cut', (e) => {
                    e.preventDefault();
                    showActionModal("Cutting content is not allowed.");
                });

                // Disable Paste
                document.addEventListener('paste', (e) => {
                    e.preventDefault();
                    showActionModal("Pasting content is not allowed.");
                });

                // Send message when "Send" button is clicked
                sendButton.addEventListener('click', sendMessage);

                // Trigger "Send" on "Enter" key press inside input field
                chatInput.addEventListener('keypress', (event) => {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault(); // Prevent form submission
                        sendMessage(); // Call sendMessage function
                    }
                });

                // Trigger focus on chat input when "/" is pressed
                document.addEventListener('keypress', (event) => {
                    if (event.key === '/') {
                        event.preventDefault(); // Prevent typing the "/" character in other places
                        chatInput.focus(); // Focus on the chat input
                    }
                });

                function sendMessage() {
                    const content = chatInput.value.trim();
                    if (content) {
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `ajax=sendMessage&assessment_id=${assessmentId}&student_id=${studentId}&content=${encodeURIComponent(content)}`
                        })
                            .then(response => response.json())
                            .then(() => {
                                chatInput.value = ''; // Clear input field after sending
                                fetchChatMessages(); // Refresh the chat messages
                            })
                            .catch(error => console.error("Chat Send Error:", error));
                    }
                }


                // Music playback logic
                const volumeIcon = document.getElementById('volume-icon');
                const audio = document.getElementById('background-music');

                // Check if music should be muted
                let isPlaying = localStorage.getItem('musicMuted') !== 'true';

                // Apply the stored mute state
                if (!isPlaying) {
                    audio.muted = true;
                    volumeIcon.src = 'images/icons/volume_off.webp';
                }

                function toggleMusic() {
                    isPlaying = !isPlaying;
                    audio.muted = !isPlaying;

                    // Store the mute state in localStorage
                    localStorage.setItem('musicMuted', audio.muted ? 'true' : 'false');

                    volumeIcon.src = audio.muted ? 'images/icons/volume_off.webp' : 'images/icons/volume_on.webp';
                }

                volumeIcon.addEventListener('click', toggleMusic);




                const chatCircle = document.getElementById('chat-circle');
                const chatPanel = document.getElementById('chat-panel');
                const closeChat = document.querySelector('.chat-close');
                const chatTooltip = document.getElementById('chat-tooltip');

                // Show tooltip on hover
                chatCircle.addEventListener('mouseenter', () => {
                    chatTooltip.style.display = 'block';
                });

                chatCircle.addEventListener('mouseleave', () => {
                    chatTooltip.style.display = 'none';
                });

                // Open chat panel
                chatCircle.addEventListener('click', () => {
                    chatPanel.style.display = 'block';
                    chatCircle.style.display = 'none'; // Hide circle when chat opens
                });

                // Close chat panel
                closeChat.addEventListener('click', () => {
                    chatPanel.style.display = 'none';
                    chatCircle.style.display = 'flex'; // Show circle when chat closes
                });

                function updateWordCount(textarea) {
                    const wordCount = textarea.value.trim().split(/\s+/).filter(word => word.length > 0).length;
                    const wordText = wordCount === 1 ? 'word' : 'words';
                    textarea.nextElementSibling.textContent = `${wordCount} ${wordText}`;
                }

            </script>



    </body>

</html>