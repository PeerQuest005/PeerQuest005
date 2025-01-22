<?php
require 'auth.php';
require 'config.php';

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

        foreach ($_POST['answers'] as $question_id => $answer_text) {
            $stmt = $pdo->prepare("INSERT INTO answers_esy_tbl (student_id, assessment_id, question_id, answer_text, Attempt) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$_SESSION['student_id'], $assessment_id, $question_id, $answer_text]);
        }

        $stmt = $pdo->prepare("UPDATE answers_esy_tbl SET Attempt = Attempt + 1 WHERE student_id = ? AND assessment_id = ?");
        $stmt->execute([$_SESSION['student_id'], $assessment_id]);

        $pdo->commit();
        echo "<p>Assessment submitted successfully!</p>";
        echo '<a href="student_dashboard.php" class="btn btn-primary">Back to Assessments</a>';
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
    <title>Take Assessment: <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_essay.css">
    <script>
        function startAssessment() {
    // Hide Charaubelle and the companion container
    document.getElementById('main-container').style.display = 'none';

    // Show the assessment container
    const assessmentContainer = document.getElementById('assessment-container');
    assessmentContainer.style.display = 'block';
    assessmentContainer.style.visibility = 'visible';

    // Get the initial time limit from the assessment or restore it from localStorage
    const assessmentId = <?php echo json_encode($assessment_id); ?>; // Unique ID for this assessment
    const localStorageKey = `remainingTime_${assessmentId}`;
    let timeLimit = localStorage.getItem(localStorageKey)
        ? parseInt(localStorage.getItem(localStorageKey), 10)
        : <?php echo (int) $assessment['time_limit']; ?> * 60; // Convert minutes to seconds

    const timerElement = document.getElementById('timer');

    function updateTimer() {
        const minutes = Math.floor(timeLimit / 60);
        const seconds = timeLimit % 60;
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

        if (timeLimit <= 0) {
            clearInterval(timerInterval);
            localStorage.removeItem(localStorageKey); // Clear the stored time
            alert("Time's up! Your assessment will be submitted.");
            document.querySelector('form').submit(); // Automatically submit the form
        } else {
            timeLimit--;
            localStorage.setItem(localStorageKey, timeLimit); // Save the remaining time to localStorage
        }
    }

    const timerInterval = setInterval(updateTimer, 1000);
    updateTimer(); // Initialize the timer immediately
}

// Add an event listener to start the assessment when the page loads or the user interacts
document.addEventListener("DOMContentLoaded", function () {
    const companionDialogue = document.getElementById('companion-dialogue');
    const response1 = document.getElementById('response-1');
    const response2 = document.getElementById('response-2');
    const response3 = document.getElementById('response-3');
    const skipButton = document.getElementById('dialogue-skip');

    let typingEffectActive = true;

    function typeText(element, text, callback) {
        let index = 0;
        element.textContent = "";
        const interval = setInterval(() => {
            if (!typingEffectActive) {
                element.textContent = text;
                clearInterval(interval);
                if (callback) callback();
                return;
            }

            if (index < text.length) {
                element.textContent += text[index];
                index++;
            } else {
                clearInterval(interval);
                if (callback) callback();
            }
        }, 50); // Typing speed
    }

    skipButton.addEventListener('click', () => {
        typingEffectActive = false; // Stop typing effect
    });

    response1.addEventListener('click', () => {
        typingEffectActive = true;
        typeText(companionDialogue, "Don’t forget to read the instructions and keep track of the time. Are you ready to start?", () => {
            response1.classList.add('hidden');
            response2.classList.remove('hidden');
        });
    });

    response2.addEventListener('click', () => {
        typingEffectActive = true;
        typeText(companionDialogue, "Great! Good luck—you’ll do well!", () => {
            response2.classList.add('hidden');
            response3.classList.remove('hidden');
        });
    });

    response3.addEventListener('click', () => {
        startAssessment();
    });

    typeText(companionDialogue, "Hi! Ready for your essay?");
});

    </script>
</head>
<body>
    <div id="main-container">
        <img id="charaubelle" src="images/Charaubelle_blinking.gif" alt="Charaubelle">
        <div id="study-companion-container">
            <div id="top-bar">Charaubelle</div>
            <div id="study-companion">
                <p id="companion-dialogue"></p>
                <div id="dialogue-skip">Skip</div>
            </div>
            <div id="dialogue-box">
                <button id="response-1" class="response">Hello Charaubelle, I think so.</button>
                <button id="response-2" class="response hidden">Let's do this!</button>
                <button id="response-3" class="response hidden">Start Assessment</button>
            </div>
        </div>
    </div>

    <div id="assessment-container" style="display: none;">
        <h2>Take Assessment</h2>
        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($assessment['instructions'] ?? 'No instructions provided.'); ?></p>
        <p><strong>Time Remaining: <span id="timer"></span></strong></p>
        <form method="post">
            <?php foreach ($questions as $question): ?>
                <div>
                    <p><?php echo htmlspecialchars($question['question_text']); ?> (<?php echo $question['points']; ?> points)</p>
                    <textarea name="answers[<?php echo $question['question_id']; ?>]" required></textarea>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="submit_assessment">Submit Assessment</button>
        </form>
    </div>
</body>
</html>