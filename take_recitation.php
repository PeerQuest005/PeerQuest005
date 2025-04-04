<?php
require 'auth.php';
require 'config.php';
$assessment_id = $_GET['assessment_id'] ?? $_POST['assessment_id'] ?? null;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// AJAX Request Handling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    if (!$assessment_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid assessment ID.']);
        exit();
    }

    // Fetch the latest question and revealed student
    $stmt = $pdo->prepare("SELECT qr.question_text, s.student_id, s.student_first_name, s.student_last_name
        FROM questions_reci_tbl qr
        LEFT JOIN student_tbl s ON qr.revealed_student_id = s.student_id
        WHERE qr.assessment_id = ?
        ORDER BY qr.created_at DESC
        LIMIT 1");
    $stmt->execute([$assessment_id]);
    $latest_question = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($latest_question) {
        $response = [
            'success' => true,
            'question_text' => $latest_question['question_text'],
            'student_id' => isset($latest_question['student_id']) ? $latest_question['student_id'] : null, // ✅ Add student_id
            'student_name' => isset($latest_question['student_first_name'], $latest_question['student_last_name']) 
                ? $latest_question['student_first_name'] . ' ' . $latest_question['student_last_name']
                : null
        ];
    }
    else {
        $response = [
            'success' => true,
            'question_text' => null,
            'student_name' => null
        ];
    }

    echo json_encode($response);
    exit();
}

// Fetch assessment details (initial load)
if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Fetch assessment details along with class_id
$stmt = $pdo->prepare("SELECT a.*, c.class_section, c.class_subject 
                       FROM assessment_tbl a 
                       JOIN class_tbl c ON a.class_id = c.class_id 
                       WHERE a.assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle answer submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answer_text'])) {


    $answer_text = trim($_POST['answer_text']);
    $assessment_id = $_POST['assessment_id'] ?? null;
    $student_id = $_SESSION['student_id'] ?? null;

    // 🔥 DEBUG: Log errors
    $log_file = 'error_log.txt';
    file_put_contents($log_file, "Received: AnswerText = $answer_text, AssessmentID = $assessment_id, StudentID = $student_id\n", FILE_APPEND);

    if (!$assessment_id || !$student_id) {
        file_put_contents($log_file, "❌ ERROR: Missing assessment ID or student ID\n", FILE_APPEND);
        echo json_encode(["success" => false, "message" => "Missing assessment ID or student ID."]);
        exit();
    }

    // Get the latest question ID
    $stmt = $pdo->prepare("SELECT question_id FROM questions_reci_tbl WHERE assessment_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$assessment_id]);
    $question_id = $stmt->fetchColumn();

    if (!$question_id) {
        file_put_contents($log_file, "❌ ERROR: No active question found\n", FILE_APPEND);
        echo json_encode(["success" => false, "message" => "No active question found for this assessment."]);
        exit();
    }

    if (!empty($answer_text)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO answers_reci_tbl (assessment_id, question_id, student_id, answer_text, timestamp) 
                                   VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$assessment_id, $question_id, $student_id, $answer_text]);
    
            file_put_contents("error_log.txt", "✅ SUCCESS: Answer stored in DB\n", FILE_APPEND);
            echo json_encode(["success" => true, "message" => "Answer submitted successfully!"]);
        } catch (PDOException $e) {
            file_put_contents("error_log.txt", "❌ DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
        }
    
    } 
    exit();
}

if (!$assessment) {
    echo "Done Recitation ";
    echo '<a href="student_dashboard.php">
    <button type="button">Go to Dashboard</button>
  </a>';
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/take_recitation.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    <source src="audio/taking-ast.mp3" type="audio/mpeg">
</audio>


<div class="container">
    <!-- Header -->
    <div class="container mt-4 text-center">
        <h1 class="h3 recitation-title">Take Recitation</h1>
        <button class="btn btn-danger" onclick="showLeaveModal()">Leave Assessment</button>
    </div>

    <div class="recitation-header">
                <h2 class="recitation-title">Recitation for Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>
                <p class="recitation-class">Class: <strong><?php
                    if ($assessment && isset($assessment['class_subject'], $assessment['class_section'])) {
                        echo htmlspecialchars($assessment['class_subject']) . " (" . htmlspecialchars($assessment['class_section']) . ")";
                    } else {
                        echo "Class details not found.";
                    } ?></strong>
                </p>

    </div>
    <!-- Recitation Display Section -->
    <div class="container mt-4">
        <div class="row g-4 align-items-start">
            <!-- Question Display -->
            <div class="col-md-6">
                <div class="latest-question-card p-4">
                    <h4 class="section-title text-center">Latest Question</h4>
                    <div id="questionDisplay" class="alert alert-question text-center">
                        <h2 class="display-quest">
                            <?php 
                            if ($latest_question && isset($latest_question['question_text'])) {
                                echo htmlspecialchars($latest_question['question_text']);
                            } else {
                                echo "Waiting for the teacher to show a question...";
                            }
                            ?>
                        </h2>
                    </div>
                </div>
            </div>

            <!-- Revealed Student -->
            <div class="col-md-6">
                <div class="latest-question-card p-4">
                    <h4 class="section-title text-center">Revealed Student</h4>
                    <div id="studentNameDisplay" class="alert alert-info text-center">
                        <h2 class="display-quest">
                            No student revealed yet.
                        </h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Answer Input (Only for Revealed Student) -->
    <div class="container mt-3 text-center">
        <div class="answer-box">
            <textarea id="answerText" class="form-control answer-textarea" rows="3" placeholder="Type your answer here..." disabled></textarea>
            <button id="submitAnswer" class="btn btn-submit mt-2" disabled required>Send Answer</button>
        </div>
    </div>


    <!-- Leave Confirmation Modal -->
    <div id="leaveModal" class="modal-overlay">
        <div class="modal-content">
            <button class="close-modal" onclick="closeLeaveModal()">&times;</button>
            <h2>Leave Assessment</h2>
            <p>Are you sure you want to leave? This recitation assessment is ongoing. Notify your teacher.</p>
            <div class="modal-buttons">
                <button class="btn-exit" onclick="leaveAssessment()">Yes, Leave</button>
            </div>
        </div>
    </div>

</div>

<script>
let studentId = <?php echo json_encode($_SESSION['student_id']); ?>;
let currentRevealedStudent = null;

function fetchLatestData() {
    $.ajax({
        url: 'take_recitation.php',
        type: 'GET',
        data: {
            ajax: 'true',
            assessment_id: <?php echo htmlspecialchars($assessment_id); ?>
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // ✅ Update question display
                $('#questionDisplay').text(response.question_text || "Waiting for the teacher to show a question...");
                $('#questionDisplay').toggleClass('no-question', !response.question_text);

                // ✅ Update revealed student display
                if (response.student_name) {
                    $('#studentNameDisplay').text("It's your turn: " + response.student_name);

                    // ✅ Enable textarea and "Send Answer" button IMMEDIATELY when the student matches
                    if (response.student_id && response.student_id == studentId) {
                        $('#answerText').prop('disabled', false);
                        $('#submitAnswer').prop('disabled', false);  // ✅ ENABLE BUTTON RIGHT AWAY
                    } else {
                        $('#answerText').prop('disabled', true);
                        $('#submitAnswer').prop('disabled', true);
                    }
                } else {
                    $('#studentNameDisplay').text("No student revealed yet.");
                    $('#answerText').prop('disabled', true);
                    $('#submitAnswer').prop('disabled', true);
                }
            }
        },
        error: function() {
            console.error("Failed to fetch the latest recitation data.");
        }
    });
}


// ✅ Allow the student to submit an answer only when the button is enabled
$('#submitAnswer').click(function() {
    let answerText = $('#answerText').val().trim();

    if (answerText !== "") {
        $.ajax({
            url: 'take_recitation.php',
            type: 'POST',
            dataType: 'json',
            data: {
                answer_text: answerText,
                assessment_id: <?php echo json_encode($assessment_id); ?>
            },
            success: function(response) {
            if (response.success) {
                alert("✅ Answer submitted successfully!");
                $('#answerText').val(""); // ✅ Clear text box
                $('#answerText').prop('disabled', true);
                $('#submitAnswer').prop('disabled', true);
            } else {
                alert("❌ Error: " + response.message);
                console.error("❌ Debug Info:", response);
            }
            },
            error: function(xhr, status, error) {
                console.error("❌ AJAX Error:", xhr.responseText); // 🔥 Show exact error
                alert("❌ An error occurred while submitting your answer. Check console (F12).");
            }
        });
    } else {
        alert("❌ Answer cannot be empty.");
    }
});

setInterval(fetchLatestData, 3000);
fetchLatestData();

// ✅ Close modal when clicking the close button
$('.close-modal').on('click', function() {
    $('#revealModal').fadeOut();
});

function showLeaveModal() {
    document.getElementById("leaveModal").style.display = "block";
}

function closeLeaveModal() {
    document.getElementById("leaveModal").style.display = "none";
}

function leaveAssessment() {
    window.location.href = "student_dashboard.php"; // Redirect to student dashboard
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

</script>

</body>
</html>