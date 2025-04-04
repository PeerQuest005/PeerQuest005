<?php
require 'config.php';

// Ensure the assessment_id and room_id are passed
$assessment_id = $_GET['assessment_id'] ?? null;
$room_id = $_GET['room_id'] ?? null;

if (!$assessment_id) {
    // Fallback: Try to fetch assessment_id based on the room_id if missing
    if ($room_id) {
        $stmt = $pdo->prepare("SELECT assessment_id FROM answers_mcq_collab_tbl WHERE room_id = ? LIMIT 1");
        $stmt->execute([$room_id]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        $assessment_id = $assessment['assessment_id'] ?? null;
    }
}

// If assessment_id is still missing, show an error
if (!$assessment_id) {
    die("Error: Assessment ID is missing.");
}

// Retrieve the assessment type from the database
$stmt = $pdo->prepare("SELECT name, type FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    die("Error: Assessment not found.");
}

$assessmentName = htmlspecialchars($assessment['name']);
$assessmentType = htmlspecialchars($assessment['type']); // Example values: 'MCQ', 'Essay', 'Recitation'
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Submitted!</title>
    <link rel="stylesheet" href="css/style.css"> <!-- External CSS -->
    
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">
    <style>
        /* Import Font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        /* General Styling */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #24243A;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }

        /* Submission Success Container */
        .submission-container {
            background: #ffffff;
            color: #24243A;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0px 8px 20px rgba(255, 255, 255, 0.2);
            width: 450px;
            animation: fadeIn 0.8s ease-in-out;
        }

        /* GIF */
        .celebration-gif {
            width: 300px;
            margin-bottom: 20px;
        }

        /* Message */
        .message {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtext {
            font-size: 1rem;
            color: #555;
            margin-bottom: 20px;
        }

        /* Modified Back Button (Now inside the container) */
        .back-button {
            display: inline-block;
            background-color: #ccc;
            color: #24243A;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            margin-top: 15px;
        }

        .back-button:hover {
            background-color: #24243A;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

    <!-- Submission Success Container -->
    <div class="submission-container">
        <img src="images/confetti.webp" alt="Celebration" class="celebration-gif">
        <div class="message">Congratulations!</div>
        <p class="subtext">Your assessment has been submitted successfully.</p>
        
        <!-- Back Button Inside the Container -->
        <a href="student_dashboard.php" class="back-button">Back to Dashboard</a>
        
        <?php if ($assessmentType === 'MCQ' || $assessmentType === 'Multiple Choice - Collaborative'): ?>
            <a href="leaderboard_mcq.php?room_id=<?php echo urlencode($room_id); ?>&assessment_id=<?php echo urlencode($assessment_id); ?>" class="back-button">Go to Scoreboard</a>


    <?php endif; ?>
    </div>

</body>
</html>
