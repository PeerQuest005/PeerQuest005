<?php
require 'auth.php';
require 'config.php';


if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
}


// Fetch assessment details
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Check if the student has already attempted this assessment
$stmt = $pdo->prepare("SELECT Attempt FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
$stmt->execute([$_SESSION['student_id'], $assessment_id]);
$user_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user_attempt && $user_attempt['Attempt'] >= 1) {
    echo "You have already attempted this assessment.";
    exit();
}

// Fetch assessment details
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

$time_limit = $assessment['time_limit'] * 60; // Convert minutes to seconds

// Fetch questions
$stmt = $pdo->prepare("SELECT * FROM questions_tf_tbl WHERE assessment_id = ? ORDER BY question_id ASC");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$questions) {
    echo "No questions found for this assessment.";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    if (!isset($_POST['answers']) || empty($_POST['answers'])) {
        echo "Please answer all questions.";
        exit();
    }

    try {
        $pdo->beginTransaction();

        foreach ($_POST['answers'] as $question_id => $answer) {
            $answer_text = ($answer === "1") ? 'True' : 'False';

            // Fetch the correct answer and points for the question
            $stmt = $pdo->prepare("SELECT correct_answer, points FROM questions_tf_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
            $question = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$question) {
                throw new Exception("Question not found: $question_id");
            }

            $is_correct = ($answer_text === $question['correct_answer']) ? $question['points'] : 0;

            // Insert student's answer
            $stmt = $pdo->prepare("
                INSERT INTO answers_tf_tbl (student_id, assessment_id, question_id, answer_text, correct_answer, Attempt)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$_SESSION['student_id'], $assessment_id, $question_id, $answer_text, $is_correct]);
        }

        if ($user_attempt) {
            $stmt = $pdo->prepare("UPDATE answers_tf_tbl SET Attempt = Attempt + 1 WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$_SESSION['student_id'], $assessment_id]);
        }

        $timed_out = $_COOKIE['timed_out'];
        $pdo->commit();
        if ($timed_out == true){
            header("Location: timed_out.php");
        } else{
            header("Location: submission_success.php");
        }
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "An error occurred: " . $e->getMessage();
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>True or False | <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_tf.css">
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>

<body>
<div class="top-bar">
    <h2 class="assessment-title">True or False: <?php echo htmlspecialchars($assessment['name']); ?></h2>
</div>

<!-- Volume Icon -->
<div class="music-icon-container">
    <img id="volume-icon" src="images/icons/volume_on.webp" alt="Volume Icon" class="music-icon">
    </div>

<!-- Background Music -->
<audio id="background-music" autoplay loop>
    <source src="audio/tf-take.mp3" type="audio/mpeg">
</audio>

    <div class="assessment-container">
        
        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($assessment['instructions'] ?? 'No instructions provided.'); ?></p>
        <p><strong>Total Points:</strong> <?php echo htmlspecialchars($assessment['total_points']); ?></p>

        <div class="timer-box">
            <progress id="progressBar" max="100" value="100"></progress>
            <div id="timer" class="timer-text"></div>
        </div>

        <form method="post">
            <!-- Answer Cards (True/False) -->
            <div class="answer-cards">
                <div class="card true-card drop-zone" id="true-drop-zone">
                <div class="card-header">TRUE</div>
                </div>

                <div class="card false-card drop-zone" id="false-drop-zone">
                <div class="card-header">FALSE</div>
                </div>
            </div>
            <!-- Question Pile -->
            <div class="question-pile" id="question-stack">
            <input type="hidden" name="submit_assessment" value="1"/>
            <?php $question_number = 1; ?>
                <?php foreach ($questions as $question): ?>
                    <div class="question-card draggable" data-id="<?php echo htmlspecialchars($question['question_id']); ?>">
                        <div class="card-back">PeerQuest</div>
                        <div class="card-front">
                            <p><?php echo htmlspecialchars($question['question_text']); ?> <span class="points"> (<?php echo htmlspecialchars($question['points']); ?> points) </span></p>
                        </div>
                        <input type="hidden" name="answers[<?php echo htmlspecialchars($question['question_id']); ?>]" value="">
                    </div>

                
                <?php endforeach; ?>
            </div>

            <!-- Skip Area -->
            <div class="drop-area" id="skip-drop">
                Drop here to skip a question
            </div>

            <div class="submit-container">
                <button class="submit-btn" type="submit">Submit Assessment</button>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
    const timeLimit = <?php echo $time_limit; ?>; // Total time in seconds
    const storageKey = `tf_assessment_timer_<?php echo $assessment_id; ?>`;

    let remainingTime = localStorage.getItem(storageKey)
        ? parseInt(localStorage.getItem(storageKey))
        : timeLimit;

    const timerElement = document.getElementById("timer");
    const progressBar = document.getElementById("progressBar");

    function updateTimerDisplay() {
        const minutes = Math.floor(remainingTime / 60);
        const seconds = remainingTime % 60;
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, "0")}`;

        // Update progress bar percentage (keeps width constant)
        const percentage = (remainingTime / timeLimit) * 100;
        progressBar.value = percentage;
    }

    const timerInterval = setInterval(() => {
        if (remainingTime > 0) {
            // Clear the previous timeout cookie
            document.cookie = "timed_out=false; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
            remainingTime--;
            localStorage.setItem(storageKey, remainingTime);
            updateTimerDisplay();
        } else {
            // When time runs out
            clearInterval(timerInterval);
            localStorage.removeItem(storageKey);

            // Set the timeout cookie
            document.cookie = "timed_out=true; path=/;";

            // Submit the form automatically when the timer ends
            document.querySelector('form').submit();
        }
    }, 1000);

    updateTimerDisplay();

    window.addEventListener("beforeunload", () => {
        localStorage.setItem(storageKey, remainingTime);
    });
});



    // Drag and Drop Logic
    document.querySelectorAll('.question-card').forEach(card => {
        card.addEventListener('click', function () {
            card.classList.add('active', 'flipped');
        });

        card.draggable = true;
        card.addEventListener('dragstart', (e) => {
            e.dataTransfer.setData('text/plain', card.dataset.id);
            setTimeout(() => {
                card.style.display = 'none'; // Temporarily hide while dragging
            }, 0);
        });

        card.addEventListener('dragend', () => {
            card.style.display = 'block'; // Show card again after drop
        });
    });

    document.querySelectorAll('#true-drop-zone, #false-drop-zone, #skip-drop').forEach(dropArea => {
        dropArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropArea.classList.add('drag-over'); // Highlight effect
        });

        dropArea.addEventListener('dragleave', () => {
            dropArea.classList.remove('drag-over'); // Remove highlight
        });

        dropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            dropArea.classList.remove('drag-over'); // Remove highlight on drop
            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.querySelector(`.question-card[data-id="${cardId}"]`);
            dropArea.appendChild(card);
            card.classList.remove('flipped');

            // Assign answer value based on drop area
            const inputField = card.querySelector('input');
            if (dropArea.id === 'true-drop-zone') {
                inputField.value = "1"; // True
            } else if (dropArea.id === 'false-drop-zone') {
                inputField.value = "0"; // False
            } else {
                inputField.value = ""; // Skipped
            }

            card.style.display = 'block'; // Ensure card is visible after drop
        });
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
window.onpopstate = function () {
  history.go(1);
};


    </script>
</body>
</html>