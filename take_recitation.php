<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;

// AJAX Request Handling
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    if (!$assessment_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid assessment ID.']);
        exit();
    }

    // Fetch the latest question and revealed student
    $stmt = $pdo->prepare("SELECT qr.question_text, s.student_first_name, s.student_last_name
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
            'student_name' => isset($latest_question['student_first_name'], $latest_question['student_last_name']) 
                ? $latest_question['student_first_name'] . ' ' . $latest_question['student_last_name']
                : null
        ];
    } else {
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

$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    echo "Assessment not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Assessment</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .question-display {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .no-question {
            color: gray;
            font-style: italic;
        }

        .student-name {
            font-size: 1.25rem;
            color: blue;
            margin-top: 10px;
        }

        .balloon-animation {
            font-size: 1.25rem;
            text-align: center;
            color: darkred;
        }
    </style>
</head>
<body class="container my-4">
    <!-- Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Take Assessment</h1>
        <a href="student_dashboard.php" class="btn btn-primary">Home</a>
    </div>

    <!-- Assessment Details -->
    <div class="mb-4">
        <h2 class="h4">Assessment: <?php echo htmlspecialchars($assessment['name']); ?></h2>
        <p class="text-muted">Type: <?php echo htmlspecialchars($assessment['type']); ?></p>
    </div>

    <!-- Question Display -->
    <div class="alert alert-secondary question-display no-question" id="questionDisplay">
        Waiting for the teacher to show a question...
    </div>

    <!-- Revealed Student -->
    <div class="alert alert-info student-name" id="studentNameDisplay">
        No student revealed yet.
    </div>

    <!-- Balloon Animation Placeholder -->
    <div class="balloon-animation mt-5">
        <h2>Balloon Animation</h2>
        <p>-- Placeholder for animation --</p>
    </div>

    <script>
        // Function to fetch the latest question and revealed student
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
                        // Update the question display
                        $('#questionDisplay').text(response.question_text || "Waiting for the teacher to show a question...");
                        $('#questionDisplay').toggleClass('no-question', !response.question_text);

                        // Update the revealed student display
                        if (response.student_name) {
                            $('#studentNameDisplay').text("It's your turn: " + response.student_name);
                        } else {
                            $('#studentNameDisplay').text("No student revealed yet.");
                        }
                    }
                },
                error: function() {
                    console.error("Failed to fetch the latest recitation data.");
                }
            });
        }

        // Poll the server every 5 seconds
        setInterval(fetchLatestData, 100);

        // Fetch data immediately on page load
        fetchLatestData();
    </script>
</body>
</html>
