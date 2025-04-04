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
$stmt = $pdo->prepare("SELECT Attempt FROM answers_esy_tbl WHERE student_id = ? AND assessment_id = ?");
$stmt->execute([$_SESSION['student_id'], $assessment_id]);
$user_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

// If student has already attempted
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

// Fetch questions for the assessment
$stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    if (!isset($_POST['answers']) || empty($_POST['answers'])) {
        echo "Please provide answers to all questions.";
        exit();
    }

    // Record answers and increment attempt count
    try {
        $pdo->beginTransaction();

        // Assuming you have the class_id available, for example from session or the assessment details
        $class_id = $assessment['class_id']; // or use $_SESSION['class_id'] if stored in session

        foreach ($_POST['answers'] as $question_id => $answer_text) {
            $stmt = $pdo->prepare("INSERT INTO answers_esy_tbl (student_id, assessment_id, question_id, answer_text, Attempt, class_id) 
                                   VALUES (?, ?, ?, ?, 1, ?)");
            $stmt->execute([$_SESSION['student_id'], $assessment_id, $question_id, $answer_text, $class_id]);
        }

        $stmt = $pdo->prepare("UPDATE answers_esy_tbl SET Attempt = Attempt + 1 WHERE student_id = ? AND assessment_id = ?");
        $stmt->execute([$_SESSION['student_id'], $assessment_id]);

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
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Essay <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_essay.css">
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>

  <!-- Volume Icon -->
  <div class="music-icon-container">
    <img id="volume-icon" src="images/icons/volume_on.webp" alt="Volume Icon" class="music-icon">
    </div>

<!-- Background Music -->
<audio id="background-music" autoplay loop>
    <source src="audio/essay-take.mp3" type="audio/mpeg">
</audio>


<div class="assessment-container">
    <h2 class="assessment-title">ESSAY: <?php echo htmlspecialchars($assessment['name']); ?></h2>
    <p><strong>Instructions:</strong> <?php echo htmlspecialchars($assessment['instructions'] ?? 'No instructions provided.'); ?></p>
    <p><strong>Total Points:</strong> <?php echo htmlspecialchars($assessment['total_points']); ?></p>

    <div class="timer-box">
    <progress id="progressBar" max="100" value="100"></progress>
    <div id="timer" class="timer-text"></div>
    </div>


    <form method="post" class="assessment-form">
    <input type="hidden" name="submit_assessment" value="1"/>
    <!-- FIX FOR TIMER: Included <input type="hidden" name="submit_assessment" value="1"/> to include hidden values so that POST will always be present regardless of which. -->
    <?php foreach ($questions as $index => $question): ?>
        <div class="question-card">
            <p class="question-number">QUESTION #<?php echo ($index + 1); ?> <span class="points">(<?php echo $question['points']; ?> points)</span></p>
            <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
            
            
    <div class="textarea-wrapper">
    <textarea class="input-field" 
        name="answers[<?php echo $question['question_id']; ?>]" rows="8" 
        required 
        placeholder="Type your answer here..." 
        oninput="updateWordCount(this)"></textarea>
    <div class="word-counter">0 words</div>
</div>
        </div>
    <?php endforeach; ?>

    <div class="navigation-buttons">
        <button type="button" id="next-btn" class="nav-btn">Next Question</button>
        <button type="submit" class="submit-btn" style="display:none;">Submit Assessment</button> 
        <!-- FIX FOR TIMER: Removed "name" from button submit -->
    </div>
</form>

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
    const assessmentId = <?php echo json_encode($assessment_id); ?>;
    const totalTime = <?php echo (int) $assessment['time_limit']; ?> * 60;  // Total time in seconds


    
    // Get start time from localStorage or set a new one
    let startTime = localStorage.getItem(`startTime_${assessmentId}`);
    if (!startTime) {
        document.cookie = "timed_out=true; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
        startTime = Date.now();
        localStorage.setItem(`startTime_${assessmentId}`, startTime);
    }

    function updateTimer() {
        const elapsedTime = Math.floor((Date.now() - startTime) / 1000); // Calculate elapsed time in seconds
        const remainingTime = totalTime - elapsedTime;

        if (remainingTime <= 0) {
            clearInterval(timerInterval);
            localStorage.removeItem(`startTime_${assessmentId}`); // Clear storage after submission
            document.querySelector('form').submit();
            document.cookie = "timed_out=true";
            return;
        }

        const minutes = Math.floor(remainingTime / 60);
        const seconds = remainingTime % 60;
        document.getElementById('timer').textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        const progressPercentage = (remainingTime / totalTime) * 100;
        document.getElementById('progressBar').value = progressPercentage;
    }

    // Run the timer function every second
    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer();



    document.addEventListener('DOMContentLoaded', () => {
    const questions = document.querySelectorAll('.question-card');
    const nextBtn = document.getElementById('next-btn');
    const submitBtn = document.querySelector('.submit-btn');
    let currentQuestion = 0;

    // Show the first question initially
    questions[currentQuestion].classList.add('active');

    // If only one question, hide next button & show submit button
    if (questions.length === 1) {
        nextBtn.style.display = 'none';
        submitBtn.style.display = 'block';
    }

    nextBtn.addEventListener('click', () => {
        // Hide the current question and show the next one
        questions[currentQuestion].classList.remove('active');
        currentQuestion++;

        if (currentQuestion < questions.length) {
            questions[currentQuestion].classList.add('active');

            // If last question, show submit button & hide next button
            if (currentQuestion === questions.length - 1) {
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'block';
            }
        }
    });
});


function updateWordCount(textarea) {
    const wordCount = textarea.value.trim().split(/\s+/).filter(word => word.length > 0).length;
    const wordText = wordCount === 1 ? 'word' : 'words';
    textarea.nextElementSibling.textContent = `${wordCount} ${wordText}`;
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

	history.pushState(null, null, location.href);
window.onpopstate = function () {
  history.go(1);
};
</script>
