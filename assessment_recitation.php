<?php
require 'auth.php';
require 'config.php';

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
}

$teacher_id = $_SESSION['teacher_id'];
$assessment_id = $_GET['assessment_id'] ?? null;

if (!$assessment_id) {
    echo "Invalid assessment ID.";
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

$class_id = $assessment['class_id'];

// **Handle AJAX Request for Auto-Refreshing Answer**
if (isset($_GET['ajax'])) {
    $stmt = $pdo->prepare("
        SELECT answer_text
        FROM answers_reci_tbl
        WHERE question_id = (
            SELECT question_id FROM questions_reci_tbl
            WHERE class_id = ? AND assessment_id = ?
            ORDER BY created_at DESC LIMIT 1
        )
        AND student_id = (
            SELECT revealed_student_id FROM questions_reci_tbl
            WHERE class_id = ? AND assessment_id = ?
            ORDER BY created_at DESC LIMIT 1
        )
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$class_id, $assessment_id, $class_id, $assessment_id]);
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);

    echo $answer ? htmlspecialchars($answer['answer_text']) : "No answer submitted yet.";
    exit();
}

// Fetch students who joined this class
$stmt = $pdo->prepare("
    SELECT s.student_id, s.student_first_name, s.student_last_name
    FROM student_classes sc
    JOIN student_tbl s ON sc.student_id = s.student_id
    WHERE sc.class_id = ?
");
$stmt->execute([$class_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle question submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    $question_text = $_POST['question_text'] ?? '';

    if ($question_text) {
        $stmt = $pdo->prepare("
            INSERT INTO questions_reci_tbl (class_id, assessment_id, question_text)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$class_id, $assessment_id, $question_text]);

        $message = "Question displayed successfully.";
    } else {
        $message = "Please enter a question.";
    }
}

// Fetch the latest revealed question and student answer
$stmt = $pdo->prepare("
    SELECT question_text, question_id, revealed_student_id
    FROM questions_reci_tbl
    WHERE class_id = ? AND assessment_id = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$class_id, $assessment_id]);
$latest_question = $stmt->fetch(PDO::FETCH_ASSOC);

$revealed_answer = "No answer submitted yet.";

if ($latest_question && isset($latest_question['question_id'], $latest_question['revealed_student_id'])) {
    $question_id = $latest_question['question_id'];
    $revealed_student_id = $latest_question['revealed_student_id'];

    // Fetch the revealed student's latest answer
    $stmt = $pdo->prepare("
        SELECT answer_text
        FROM answers_reci_tbl
        WHERE question_id = ? AND student_id = ?
        ORDER BY timestamp DESC
        LIMIT 1
    ");
    $stmt->execute([$question_id, $revealed_student_id]);
    $answer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($answer) {
        $revealed_answer = htmlspecialchars($answer['answer_text']);
    }
}

// Handle student reveal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reveal_student_id'])) {
    $revealed_student_id = $_POST['reveal_student_id'];

    $stmt = $pdo->prepare("
        UPDATE questions_reci_tbl
        SET revealed_student_id = ?
        WHERE class_id = ? AND assessment_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$revealed_student_id, $class_id, $assessment_id]);
}

// Handle assessment deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assessment'])) {
    $stmt = $pdo->prepare("DELETE FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    
    header("Location: view_assessment_teacher.php?class_id=" . htmlspecialchars($class_id));
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Reci | PeerQuest</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/assessment_recitation.css">
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>

  <!-- Volume Icon -->
  <div class="music-icon-container">
    <img id="volume-icon" src="images/icons/volume_on.webp" alt="Volume Icon" class="music-icon">
    </div>

<!-- Background Music -->
<audio id="background-music" loop>
    <source src="audio/study_music.mp3" type="audio/mpeg">
</audio>

    <div class="container">

        <div class="mt-4 text-end">
            <form method="post">
            <button type="button" class="btn btn-danger" onclick="showExitModal()">Exit Assessment</button>
            </form>
        </div>

        <div class="recitation-header">
            <h2 class="recitation-title">Recitation for Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>
            <p class="recitation-class">
                Class: 
                <strong>
                <?php
                    // Fetch class details for display
                    $stmt = $pdo->prepare("SELECT class_section, class_subject FROM class_tbl WHERE class_id = ?");
                    $stmt->execute([$class_id]);
                    $class_info = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($class_info) {
                        echo htmlspecialchars($class_info['class_subject']) . " (" . htmlspecialchars($class_info['class_section']) . ")";
                    } else {
                        echo "Class details not found.";
                    }
                ?>
                </strong>
            </p>
        </div>


        <!-- Success/Error Message -->
        <?php if ($message): ?>
         <div id="notification" class="custom-alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>


            <!-- Gamified Layout -->
        <div class="container">
            <div class="row g-4 align-items-start">
                <!-- Enter Question Column -->
                <div class="col-md-6">
                    <div class="question-form-card p-4">
                        <h4 class="section-title">Enter Your Question</h4>
                        <form method="post">
                            <div class="mb-3">
                                <label for="questionInput" class="form-label">Enter Question:</label>
                                <input type="text" id="questionInput" name="question_text" class="form-control question-input"
                                    placeholder="Type your question here" required>
                            </div>
                            <button type="submit" name="submit_question" class="btn btn-submit">Show Question</button>
                        </form>
                    </div>
                </div>

                <!-- Display Latest Question Column -->
                <div class="col-md-6">
                    <div class="latest-question-card p-4">
                        <h4 class="section-title">Latest Question</h4>
                        <div id="questionDisplay" class="alert alert-question">
                        <p>
                         <h2 class = "display-quest">
                                <?php 
                                if ($latest_question && isset($latest_question['question_text'])) {
                                    echo htmlspecialchars($latest_question['question_text']);
                                } else {
                                    echo "No question has been added yet.";
                                }
                                ?>
                            </h2>
                        </p>
                        <hr>
                        <h5>Student's Answer:</h5><p id="answerDisplay"><?php echo $revealed_answer; ?><button class="btn btn-randomize" onclick="location.reload();">Refresh Page</button></p>
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="balloon-title">Click a Balloon to Reveal a Name:</h3>
        <div class="balloon-container">
            <?php 
            shuffle($students); // Shuffle the order of students randomly
            $balloonCount = count($students); 

            foreach ($students as $index => $student): 
                // Randomly cycle through the balloon images
                $balloonImageIndex = ($index % 5) + 1; 
            ?>
                <form method="post" class="balloon-form">
                <input type="hidden" name="reveal_student_id" value="<?php echo htmlspecialchars($student['student_id']); ?>">
                    <button type="submit" name="reveal_student" class="balloon-button" data-student-name="<?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?>">
                        <div class="balloon-wrapper">
                            <img src="images/balloons/b<?php echo $balloonImageIndex; ?>.webp" class="balloon-image" id="balloon<?php echo $index + 1; ?>" alt="Balloon">
                            <span class="student-name-overlay"></span> <!-- Overlay for student name -->
                        </div>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>


            <!-- Randomize Button -->
            <form method="post" class="randomize-form">
                <button type="submit" class="btn btn-randomize">Randomize Balloons</button>
            </form>

            <audio id="popSound" src="audio/balloon_pop.mp3"></audio> <!-- Balloon popping sound -->

            <!-- Modal Structure -->
            <div id="revealModal" class="modal-overlay">
                <div class="modal-content">
                    <button class="close-modal">&times;</button>
                    <img src="images/balloons/balloon_reveal.webp" alt="Balloon Reveal" class="modal-gif">
                    <h2 id="modalStudentName">The balloon revealed: <span id="modalName"></span></h2>
                </div>
            </div>


            
<!-- Exit Confirmation Modal -->
<div id="exitModal" class="modal-overlay">
    <div class="modal-content">
        <button class="close-modal" onclick="closeExitModal()">&times;</button>
        <h2 id="modalStudentName">Exit Confirmation</h2>
        <p id="modalMessage">When you click "Yes", this recitation assessment will be deleted. Are you sure you want to exit?</p>
        <div class="modal-buttons">
            <form method="post">
                <button type="submit" name="delete_assessment" class="btn-exit">Yes, Exit Assessment</button>
            </form>
        </div>
    </div>
</div>


<script> 
function showExitModal() {
    document.getElementById("exitModal").style.display = "block";
}

function closeExitModal() {
    document.getElementById("exitModal").style.display = "none";
}

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

            document.querySelectorAll('.balloon-button').forEach(button => {
            button.addEventListener('click', function (event) {
            this.closest("form").submit();

            const balloonImg = this.querySelector('.balloon-image');
            const studentNameOverlay = this.querySelector('.student-name-overlay');
            const popSound = document.getElementById('popSound');
            const studentName = this.getAttribute('data-student-name');

            // Get the balloon index
            const balloonSrc = balloonImg.src;  
            const balloonMatch = balloonSrc.match(/b(\d+).webp/); // Extract number from "b1.webp", "b2.webp", etc.

            if (balloonMatch) {
            let balloonIndex = balloonMatch[1]; // Extract number from filename (1-5)

            // Update the balloon image to its popped version
            balloonImg.src = images/balloons/b${balloonIndex}_pop.webp;
            } else {
            console.error("Balloon image not found.");
            }

            // Show the student name overlay
            studentNameOverlay.textContent = studentName;
            studentNameOverlay.style.opacity = 1;

            // Play pop sound
            popSound.play();

            // Delay showing the modal 
            setTimeout(() => {
            document.getElementById('modalName').textContent = studentName;
            document.getElementById('revealModal').style.display = 'block';
            }, 1000);  // Delay for 1 second
            });
            });


// Close modal functionality
document.querySelector('.close-modal').addEventListener('click', function () {
    document.getElementById('revealModal').style.display = 'none';
});

// Auto-hide notification after 2 seconds
document.addEventListener("DOMContentLoaded", function () {
    const notification = document.getElementById("notification");
    if (notification) {
        setTimeout(() => {
            notification.classList.add("hidden");
        }, 5000); // 2 seconds\ 
    }
});


function refreshAnswer() {
        let assessmentId = "<?php echo $assessment_id; ?>";
        let classId = "<?php echo $class_id; ?>";

        let xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                document.getElementById("answerDisplay").innerHTML = xhr.responseText;
            }
        };
        xhr.open("GET", location.href + "?ajax=1&assessment_id=" + assessmentId + "&class_id=" + classId, true);
        xhr.send();
    }

    // Auto-refresh answer every 5 seconds
    setInterval(refreshAnswer, 5000);
    </script>
</body>
</html>