<?php
require 'auth.php';
require 'config.php';

// Get student_id from query parameter (e.g., achievements.php?student_id=123)
if (!isset($_GET['student_id'])) {
    echo "Student ID is missing.";
    exit();
}

$student_id = $_GET['student_id'];

// Fetch the student's username
$stmt = $pdo->prepare("SELECT username FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$username = $stmt->fetchColumn();

// Fetch achievements data for the student
$stmt = $pdo->prepare("SELECT ach_streak, ach_modules_read, ach_answered_assessments, ach_collaborated FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$achievements = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$achievements) {
    echo "No achievement data found for the selected student.";
    exit();
}

// Check and award badges based on achievements
$badge_awarded = false;
$badge_message = '';

// Example: Award a badge for streaks over 5 days
if ($achievements['ach_streak'] >= 5) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, '5-Day Streak']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_earned_date) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, '5-Day Streak', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the 5-Day Streak badge!';
    }
}

// Example: Award a badge for reading over 10 modules
if ($achievements['ach_modules_read'] >= 10) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, '10 Modules Master']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_earned_date) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, '10 Modules Master', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the 10 Modules Master badge!';
    }
}

// Example: Award a badge for answering over 20 assessments
if ($achievements['ach_answered_assessments'] >= 5) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, 'Assessment Beginner']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_earned_date) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, 'Assessment Beginner', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the Assessment Beginner badge!';
    }
}

// Example: Award a badge for collaborating over 5 times
if ($achievements['ach_collaborated'] >= 5) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, 'Collaboration Novice']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_earned_date) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, 'Collaboration Novice', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the Collaboration Novice badge!';
    }
}

// Fetch all badges earned by the student
$stmt = $pdo->prepare("SELECT badge_name, badge_earned_date FROM achievement_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$earned_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements</title>
</head>
<body>
    <h2>Achievements</h2>

    <!-- Teacher's view -->
    <p>Viewing achievements for <strong><?php echo htmlspecialchars($username); ?>'s</strong> </p>

    <button onclick="history.back()">Go Back</button>
    <button><a href="logout.php">Logout</a></button>

    <!-- Display achievements -->
    <h3>Student's Achievements</h3>
    <ul>
        <li><strong>Current Streak:</strong> <?php echo htmlspecialchars($achievements['ach_streak']); ?> days</li>
        <li><strong>Modules Read:</strong> <?php echo htmlspecialchars($achievements['ach_modules_read']); ?> modules</li>
        <li><strong>Assessments Answered:</strong> <?php echo htmlspecialchars($achievements['ach_answered_assessments']); ?> assessments</li>
        <li><strong>Collaborations:</strong> <?php echo htmlspecialchars($achievements['ach_collaborated']); ?> collaborations</li>
    </ul>

    <?php if ($badge_awarded): ?>
        <p style="color: green;"><strong><?php echo htmlspecialchars($badge_message); ?></strong></p>
    <?php endif; ?>

    <!-- Display earned badges -->
    <h3>Earned Badges</h3>
    <?php if ($earned_badges): ?>
        <ul>
            <?php foreach ($earned_badges as $badge): ?>
                <li><strong><?php echo htmlspecialchars($badge['badge_name']); ?></strong> - Earned on: <?php echo htmlspecialchars($badge['badge_earned_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>This student has not earned any badges yet. Keep up the great work!</p>
    <?php endif; ?>

    <h3>Keep up the good work!</h3>
</body>
</html>