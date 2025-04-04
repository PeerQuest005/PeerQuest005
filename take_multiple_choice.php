<?php
require 'auth.php';
require 'config.php';

// Helper function for error handling
function showErrorAndExit($message)
{
    echo htmlspecialchars($message);
    exit();
}

// Get assessment ID
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    showErrorAndExit("No assessment selected.");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];

    if (empty($answers)) {
        showErrorAndExit("Please answer all questions.");
    }

    try {
        $pdo->beginTransaction();

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

            // Insert the answer into answers_mcq_tbl
            $stmt = $pdo->prepare("INSERT INTO answers_mcq_tbl 
                (assessment_id, question_id, student_id, selected_option, correct_answer, attempt) 
                VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$assessment_id, $question_id, $_SESSION['student_id'], $selected_option, $is_correct]);
        }

        $pdo->commit();

        // Fetch total score
        $stmt = $pdo->prepare("SELECT SUM(correct_answer) AS total_score 
            FROM answers_mcq_tbl 
            WHERE assessment_id = ? AND student_id = ?");
        $stmt->execute([$assessment_id, $_SESSION['student_id']]);
        $total_score = $stmt->fetch(PDO::FETCH_ASSOC)['total_score'];

        // Redirect to accomplished_mcq.php after successful submission
        $timed_out = $_COOKIE['timed_out'];
        if ($timed_out == true) {
            header("Location: timed_out.php");
        } else {
            header("Location: submission_success.php");
        }
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        showErrorAndExit("An error occurred: " . $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCQ | <?php echo htmlspecialchars($assessment['name']); ?></title>
    <link rel="stylesheet" href="css/take_mcq_indiv.css">
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>

    <div class="top-bar">
        <h2 class="assessment-title">Multiple-Choice: <?php echo htmlspecialchars($assessment['name']); ?></h2>
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
    </script>
</body>

</html>