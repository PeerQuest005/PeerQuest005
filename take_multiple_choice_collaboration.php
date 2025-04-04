<?php
require 'auth.php';
require 'config.php';

// Helper function for error handling
function showErrorAndExit($message)
{
    header('Content-Type: application/json'); // Force JSON output
    exit(json_encode(['error' => $message])); // ✅ Always return JSON
}

// Retrieve assessment_id and session details
$assessment_id = $_GET['assessment_id'] ?? null;
$roomId = $_SESSION['room_id'] ?? null;
$studentId = $_SESSION['student_id'] ?? null;

if (!$assessment_id || !$roomId || !$studentId) {
    exit("Invalid session or assessment ID. Please return to the lobby.");
}

// Check if the student has already attempted this assessment
$stmt = $pdo->prepare("SELECT COUNT(*) AS attempt_count FROM answers_mcq_tbl WHERE student_id = ? AND assessment_id = ?");
$stmt->execute([$_SESSION['student_id'], $assessment_id]);
$user_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_attempt && $user_attempt['attempt_count'] > 0) {
    showErrorAndExit("You have already attempted this assessment.");
}

// Fetch assessment details with time_limit
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$assessment) {
    showErrorAndExit("Assessment not found.");
}

$time_limit = $assessment['time_limit'] * 60; // Convert minutes to seconds

// Fetch questions for the assessment
$stmt = $pdo->prepare("SELECT * FROM questions_mcq_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$questions) {
    showErrorAndExit("No questions found for this assessment.");
}

$totalQuestions = count($questions); // Calculate total questions
$healthPerQuestion = 100 / $totalQuestions; // Calculate health decrement per question

// Handle form submission
//problem
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    try {
        $pdo->beginTransaction();

        // Fetch all student IDs in the same room
        $stmt = $pdo->prepare("SELECT student_id FROM room_ready_tbl WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($answers as $question_id => $selected_option) {
            $selected_option = trim($selected_option);
            if (!in_array(strtoupper($selected_option), ['A', 'B', 'C', 'D'])) {
                $selected_option = null;
            }

            // Fetch correct option and points
            $stmt = $pdo->prepare("SELECT correct_option, points FROM questions_mcq_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                throw new Exception("Question not found: $question_id");
            }

            $is_correct = ($selected_option === $question['correct_option']) ? $question['points'] : 0;

            // If host submits, update answers for all students in the room
            foreach ($students as $student) {
                $stmt = $pdo->prepare("INSERT INTO answers_mcq_collab_tbl 
                (room_id, assessment_id, question_id, selected_option, submitted_by, submitted_at, grades, attempt) 
                VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)
                ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), grades = VALUES(grades)");

                $stmt->execute([$roomId, $assessment_id, $question_id, $selected_option, $student['student_id'], $is_correct]);
            }
        }

        $pdo->commit();
        header("Location: submission_success.php?assessment_id=" . urlencode($assessment_id) . "&room_id=" . urlencode($roomId));
        exit();
        
        

    } catch (Exception $e) {
        $pdo->rollBack();
        showErrorAndExit("An error occurred: " . $e->getMessage());
    }
}


// Handle chat submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'sendMessage') {
    header('Content-Type: application/json'); // ✅ Ensure JSON response

    if (!isset($_SESSION['student_id'])) {
        echo json_encode(['error' => 'Session expired. Please refresh the page.']);
        exit();
    }

    $content = trim($_POST['content'] ?? '');
    if (!empty($content)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO chat_history (assessment_id, room_id, student_id, content, time_and_date) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$assessment_id, $roomId, $studentId, $content]);

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



// Check if the user is the host
$stmt = $pdo->prepare("SELECT is_host FROM room_ready_tbl WHERE room_id = ? AND student_id = ?");
$stmt->execute([$roomId, $studentId]);
$hostData = $stmt->fetch(PDO::FETCH_ASSOC);
$isHost = ($hostData && $hostData['is_host'] == 1) ? true : false;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCQ | <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_mcq_collab.css">
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
<script>
    const isHost = <?php echo json_encode($isHost); ?>;
</script>

    <div class="top-bar">
        <h2 class="assessment-title">Multiple-Choice Collaborative: <?php echo htmlspecialchars($assessment['name']); ?></h2>
        <p><strong>Room ID:</strong> <?php echo $roomId; ?> </span></p>
    </div>


    <!-- Volume Icon -->
    <div class="music-icon-container">
        <img id="volume-icon" src="images/icons/volume_on.webp" alt="Volume Icon" class="music-icon">
    </div>

    <!-- Background Music -->
    <audio id="background-music" autoplay loop>
        <source src="audio/mcq_take.mp3" type="audio/mp3">
    </audio>


    <div class="assessment-container">
        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($assessment['instructions'] ?? 'No instructions provided.'); ?></p>
        <p><strong>Total Points:</strong> <?php echo htmlspecialchars($assessment['total_points']); ?></p>

        <div class="timer-box">
            <progress id="progressBar" max="100" value="100"></progress>
            <div id="timer" class="timer-text"></div>
        </div>

        <div class="health-bar-wrapper">

            <div class="health-bar-container">
                <img src="images/heart.webp" alt="Health" class="health-icon">
                <div class="health-bar">
                    <div id="boss-health-progress" class="health-progress"></div>
                </div>
            </div>
            <!-- Boss GIF added here -->
            <div class="boss-gif">
                <img src="images/evil_wizard.webp" alt="Boss GIF" id="boss-image">
            </div>
            <h3 class="boss-health-text">The Evil Wizard</h3>
        </div>

<!--problem-->
        <form method="post" id="mcq-form">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-container" id="question-<?php echo $index; ?>" <?php echo ($index !== 0) ? 'style="display:none;"' : ''; ?>>
                    <p class="question-number">QUESTION #<?php echo ($index + 1); ?> <span class="points">(<?php echo $question['points']; ?> points) </span></p>
                    <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                    <div class="option-container">
                        <input type="hidden" name="answers[<?php echo htmlspecialchars($question['question_id']); ?>]" id="answer_<?php echo htmlspecialchars($question['question_id']); ?>" value="">

                        <?php
                        $options = json_decode($question['options'], true);
                        foreach ($options as $key => $value): ?>
                            <div class="option-btn" data-question-id="<?php echo $question['question_id']; ?>" onclick="selectOption('<?php echo strtoupper($key); ?>', '<?php echo $question['question_id']; ?>', this)">
                                <span class="choice-circle"><?php echo strtoupper($key); ?></span>
                                <?php echo htmlspecialchars($value); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="submit-container">
                <button type="button" id="next-button" class="submit-btn" disabled>Next</button>
                <button type="submit" id="submit-button" class="submit-btn" style="display: none;" disabled>Submit Assessment</button>
            </div>
        </form>

    </div>
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



    <script>
        let currentQuestionIndex = 0;
        const totalQuestions = <?php echo $totalQuestions; ?>;

        // ✅ Function to select an option and update hidden input value
        function selectOption(option, questionId, element) {
            const hiddenInput = document.getElementById('answer_' + questionId);
            hiddenInput.value = option;

            document.querySelectorAll(`[data-question-id="${questionId}"]`).forEach(el => {
                el.classList.remove('selected');
            });
            element.classList.add('selected');

            const nextButton = document.getElementById('next-button');
            const submitButton = document.getElementById('submit-button');

            // Enable the appropriate button based on current question
            if (currentQuestionIndex < totalQuestions - 1) {
                nextButton.disabled = false;
            } else {
                submitButton.disabled = false;
            }
        }

        // ✅ Timer logic
        const assessmentId = <?php echo json_encode($assessment_id); ?>;
        const totalTime = <?php echo (int) $assessment['time_limit']; ?> * 60; // Total time in seconds

        // Get start time from localStorage or set a new one
        let startTime = localStorage.getItem(`startTime_${assessmentId}`);
        if (!startTime) {
            document.cookie = "timed_out=true; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            startTime = Date.now();
            localStorage.setItem(`startTime_${assessmentId}`, startTime);
        }

        function updateTimer() {
            const elapsedTime = Math.floor((Date.now() - startTime) / 1000);
            const remainingTime = totalTime - elapsedTime;

            if (remainingTime <= 0) {
                clearInterval(timerInterval);
                localStorage.removeItem(`startTime_${assessmentId}`); // Clear storage after submission
                document.getElementById("mcq-form").submit();
                document.cookie = "timed_out=true";
                return;
            }

            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            document.getElementById('timer').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            const progressPercentage = (remainingTime / totalTime) * 100;
            document.getElementById('progressBar').value = progressPercentage;
        }

        const timerInterval = setInterval(updateTimer, 1000);
        updateTimer();









// ✅ Manage question navigation (Next & Submit buttons work properly)
// ✅ Manage question navigation (Next & Submit buttons work properly)
// ✅ Manage question navigation (Next & Submit buttons work properly)
// ✅ Manage question navigation (Next & Submit buttons work properly)





        // problem
        document.addEventListener("DOMContentLoaded", function() {
            const questions = document.querySelectorAll(".question-container");
            const nextButton = document.getElementById("next-button");
            const submitButton = document.getElementById("submit-button");

            function showQuestion(index) {
                questions.forEach((q, i) => {
                    q.style.display = i === index ? "block" : "none";
                });

                // Check if current question has answer
                const currentInput = questions[index].querySelector('input[type="hidden"]');
                const hasAnswer = currentInput.value.trim() !== '';

                if (index === totalQuestions - 1) {
                    nextButton.style.display = "none";
                    submitButton.style.display = "inline-block";
                    submitButton.disabled = !hasAnswer;
                } else {
                    nextButton.style.display = "inline-block";
                    submitButton.style.display = "none";
                    nextButton.disabled = !hasAnswer;
                }
            }

            nextButton.addEventListener("click", function() {
                // Verify current question has answer
                const currentInput = questions[currentQuestionIndex].querySelector('input[type="hidden"]');
                if (!currentInput.value.trim()) {
                    alert('Please select an answer before proceeding.');
                    return;
                }

                // Move to next question
                currentQuestionIndex++;
                showQuestion(currentQuestionIndex);
            });

            // Initialize first question
            showQuestion(currentQuestionIndex);
        });


        // ✅ Prevent form submission if any answer is missing
        document.getElementById("mcq-form").addEventListener("submit", function(event) {
            let missingAnswers = false;

            document.querySelectorAll("input[type='hidden']").forEach(input => {
                if (!input.value.trim()) { // Ensure the value is not empty
                    missingAnswers = true;
                }
            });

            if (missingAnswers) {
                event.preventDefault(); // Stop submission if any answer is missing
                alert("❌ Please select an answer for all questions.");
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const totalQuestions = <?php echo $totalQuestions; ?>;
            const healthPerQuestion = 100 / totalQuestions;
            const healthProgress = document.getElementById("boss-health-progress");
            const nextButton = document.getElementById("next-button");
            const submitButton = document.getElementById("submit-button");
            let currentQuestionIndex = 0;
            const questions = document.querySelectorAll(".question-container");

            // Initialize boss health if not set
            if (!localStorage.getItem(`boss_health_${assessmentId}`)) {
                localStorage.setItem(`boss_health_${assessmentId}`, 100); // Set health to 100 at start
            }

            function updateHealthBar() {
                let bossHealth = parseFloat(localStorage.getItem(`boss_health_${assessmentId}`)) || 100;
                healthProgress.style.width = `${bossHealth}%`;

                // Apply color changes based on health percentage
                if (bossHealth > 75) {
                    healthProgress.style.background = "linear-gradient(to right, #ffffff, #e6e6e6)"; // White (High Health)
                } else if (bossHealth > 50) {
                    healthProgress.style.background = "linear-gradient(to right, #d9d9d9, #bfbfbf)"; // Light Gray (Medium-High Health)
                } else if (bossHealth > 25) {
                    healthProgress.style.background = "linear-gradient(to right, #a6a6a6, #8c8c8c)"; // Medium Gray (Mid Health)
                } else {
                    healthProgress.style.background = "linear-gradient(to right, #666666, #4d4d4d)"; // Dark Gray (Low Health)
                }
            }

            function decreaseHealth() {
                let bossHealth = parseFloat(localStorage.getItem(`boss_health_${assessmentId}`)) || 100;
                bossHealth = Math.max(0, bossHealth - healthPerQuestion);
                localStorage.setItem(`boss_health_${assessmentId}`, bossHealth);
                updateHealthBar();
            }

            function showQuestion(index) {
                questions.forEach((q, i) => {
                    q.style.display = i === index ? "block" : "none";
                });

                // Toggle buttons
                if (index === totalQuestions - 1) {
                    nextButton.style.display = "none";
                    submitButton.style.display = "inline-block";
                } else {
                    nextButton.style.display = "inline-block";
                    submitButton.style.display = "none";
                }
            }

            // 🔥 Call decreaseHealth() when clicking "Next"
            nextButton.addEventListener("click", function() {
                if (currentQuestionIndex < totalQuestions - 1) {
                    decreaseHealth(); // Reduce boss health on Next click
                    currentQuestionIndex++;
                    showQuestion(currentQuestionIndex);
                }
            });

            // Ensure health bar updates at the start
            updateHealthBar();
            showQuestion(currentQuestionIndex);
        });
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

        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };

        

                    // Chat functionality
                    const roomId = "<?php echo $roomId; ?>";
                    const studentId = "<?php echo $studentId; ?>";
const chatCircle = document.getElementById('chat-circle');
const chatPanel = document.getElementById('chat-panel');
const closeChat = document.querySelector('.chat-close');
const chatTooltip = document.getElementById('chat-tooltip');
const chatMessages = document.getElementById('chat-messages');
const chatInput = document.getElementById('chat-message-input');
const sendButton = document.getElementById('send-message');

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
}// Fetch chat messages every 2 seconds
setInterval(fetchChatMessages, 2000);

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

// JavaScript function to send message
function sendMessage() {
    const content = chatInput.value.trim();
    if (content) {
        const formData = new URLSearchParams(); // Define formData here
        formData.append('ajax', 'sendMessage');
        formData.append('assessment_id', assessmentId);
        formData.append('room_id', roomId);
        formData.append('student_id', studentId);
        formData.append('content', content);

        fetch('chat_submit.php', {  // Change the URL to your new PHP file
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString()
        })
        .then(response => response.text()) // Get raw response first
        .then(text => {
            console.log("Server Response:", text); // Debugging: Log raw response

            try {
                const data = JSON.parse(text); // Try parsing JSON
                if (data.error) {
                    console.error("Chat Error:", data.error);
                } else {
                    chatInput.value = ''; // Clear input field
                    fetchChatMessages(); // Refresh chat
                }
            } catch (e) {
                console.error("Invalid JSON response:", text);
            }
        })
        .catch(error => console.error("Chat Send Error:", error));
    }
}








// ✅ Trigger "Send" on "Enter" key press inside input field
chatInput.addEventListener('keypress', (event) => {
    if (event.key === 'Enter' || event.keyCode === 13) {
        event.preventDefault(); // Prevent form submission
        sendMessage(); // Call sendMessage function
    }
});

// ✅ Trigger focus on chat input when "/" is pressed
document.addEventListener('keypress', (event) => {
    if (event.key === '/') {
        event.preventDefault(); // Prevent typing the "/" character in other places
        chatInput.focus(); // Focus on the chat input
    }
});



document.addEventListener("DOMContentLoaded", function() {
    if (!isHost) {
        // Disable selecting answers
        document.querySelectorAll(".option-btn").forEach(btn => {
            // btn.style.pointerEvents = "none";
            // btn.style.opacity = "0.5"; // Gray out options
        });

        // Disable Next and Submit buttons
        document.getElementById("next-button").disabled = true;
        document.getElementById("submit-button").disabled = true;
    }
});
function checkSubmissionStatus() {
    fetch(`check_submission_status.php?assessment_id=${assessmentId}&room_id=${roomId}`)
    .then(response => response.json())
    .then(data => {
        if (data.submitted) {
            window.location.href = `submission_success.php?assessment_id=${assessmentId}&room_id=${roomId}`; // ✅ Redirect with correct params
        }
    })
    .catch(error => console.error("Error checking submission:", error));
}


//si host lang pde mag select ng choice

//same question display host and peer 

// Poll every 2 seconds
if (!isHost) {
    setInterval(checkSubmissionStatus, 2000);
}

document.getElementById("mcq-form").addEventListener("submit", function(event) {
    if (!isHost) {
        event.preventDefault();
        alert("Only the host can submit answers.");
    }
});
    </script>


</body>

</html>


