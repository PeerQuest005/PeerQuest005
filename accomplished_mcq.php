<?php
require 'auth.php';
require 'config.php';

// Get the student ID from the session
$student_id = $_SESSION['student_id'] ?? null;
$class_id = $_SESSION['class_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest';

if ($student_id) {
    // Increment ach_collaborated by 1
    $stmt = $pdo->prepare("UPDATE student_tbl SET ach_collaborated = ach_collaborated + 1 WHERE student_id = ?");
    $stmt->execute([$student_id]);
}

// Get assessment ID from the URL
$assessment_id = $_GET['assessment_id'] ?? null;

// Check if assessment ID exists and is valid
if (!$assessment_id) {
    die("No assessment selected.");
}

// Check if the user has already attempted the assessment
$stmt = $pdo->prepare("SELECT COUNT(*) FROM answers_mcq_collab_tbl WHERE assessment_id = ? AND submitted_by = ?");
$stmt->execute([$assessment_id, $student_id]);
$attempt_count = $stmt->fetchColumn();



// Fetch assessment data for validation
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch();

if (!$assessment) {
    die("Assessment not found.");
}

$_SESSION['assessment_id'] = $assessment_id;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Victory</title>
    <script>
        (function() {
            history.pushState(null, null, document.URL);
            window.addEventListener('popstate', function () {
                history.pushState(null, null, document.URL);
            });
        })();
    </script>
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>
    <p>Victory <?php echo htmlspecialchars($username); ?>!</p>
    
    <a href="leaderboards.php?student_id=<?php echo htmlspecialchars($student_id); ?>&class_id=<?php echo htmlspecialchars($class_id); ?>&assessment_id=<?php echo htmlspecialchars($assessment['assessment_id']); ?>">
        Check Leaderboards
    </a>
    
    <br><br>
    
    <a href="student_dashboard.php">Dashboard</a>
</body>
</html>
