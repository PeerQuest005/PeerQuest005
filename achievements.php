<?php
require 'auth.php';
require 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['role']) || ($_SESSION['role'] != 1 && $_SESSION['role'] != 2)) {
    $_SESSION['login_error'] = 'Access denied. You must be logged in.';
    header('Location: ./login.php');
    exit();
}

// Get student_id from query parameters
$student_id = $_GET['student_id'] ?? null;
$teacher_id = $_SESSION['teacher_id'] ?? null; // Logged-in teacher ID
$session_student_id = $_SESSION['student_id'] ?? null; // Logged-in student ID

if (!$student_id) {
    echo "Student ID is missing.";
    exit();
}

// Fetch the student's username
$stmt = $pdo->prepare("SELECT username FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$username = $stmt->fetchColumn();

// Role-Based Access Control: Students can only see their own achievements
$authorized = false;

// Check if the logged-in user is the student
if ($session_student_id && $session_student_id == $student_id) {
    $authorized = true;
}

// Check if the logged-in user is a teacher (all teachers can access student achievements)
if ($teacher_id) {
    $authorized = true;
}

// Redirect unauthorized students
if (!$authorized) {
    $_SESSION['error_role'] = 'Access Denied! You can only view your own achievements.';
    header('Location: ./student_dashboard.php');
    exit();
}

// Get student_id from query parameters
$student_id = $_GET['student_id'] ?? null;
$teacher_id = $_SESSION['teacher_id'] ?? null; // Logged-in teacher ID
$session_student_id = $_SESSION['student_id'] ?? null; // Logged-in student ID

if (!$student_id) {
    echo "Student ID is missing.";
    exit();
}

// Fetch the student's username
$stmt = $pdo->prepare("SELECT username FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$username = $stmt->fetchColumn();

// Role-Based Access Control: Students can only see their own achievements
$authorized = false;

// Check if the logged-in user is the student
if ($session_student_id && $session_student_id == $student_id) {
    $authorized = true;
}

// Check if the logged-in user is a teacher (all teachers can access student achievements)
if ($teacher_id) {
    $authorized = true;
}

// Redirect unauthorized students
if (!$authorized) {
    $_SESSION['error_role'] = 'Access Denied! You can only view your own achievements.';
    header('Location: ./student_dashboard.php');
    exit();
}

// Fetch student achievements
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

//Award a badge for streaks over 5 days
if ($achievements['ach_streak'] >= 5) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, '5-Day Streak']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_image, badge_earned_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, '5-Day Streak', '5_day_streak.svg', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the 5-Day Streak badge!';
    }
}

/// Example: Award a badge for reading over 10 modules
if ($achievements['ach_modules_read'] >= 10) {
    $stmt = $pdo->prepare("SELECT * FROM achievement_tbl WHERE student_id = ? AND badge_name = ?");
    $stmt->execute([$student_id, '10 Modules Master']);
    $existingBadge = $stmt->fetch();

    if (!$existingBadge) {
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_image, badge_earned_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, '10 Modules Master', '10_modules_master.svg', date('Y-m-d')]);
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
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_image, badge_earned_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, 'Assessment Beginner', 'assessment_beginner.svg', date('Y-m-d')]);
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
        $stmt = $pdo->prepare("INSERT INTO achievement_tbl (student_id, badge_name, badge_image, badge_earned_date) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, 'Collaboration Novice', 'collaboration_novice.svg', date('Y-m-d')]);
        $badge_awarded = true;
        $badge_message = 'Congratulations! You have earned the Collaboration Novice badge!';
    }
}

// Fetch all badges earned by the student
$stmt = $pdo->prepare("SELECT badge_name, badge_image, badge_earned_date FROM achievement_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$earned_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);


$achievementGifs = [
    "Streak" => "images/streak.webp",
    "Modules Read" => "images/read.webp",
    "Assessments Answered" => "images/answer.webp",
    "Collaborations" => "images/collab.webp"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements | PeerQuest</title>
    <link rel="stylesheet" href="css/achievement.css">


    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
    <div class="top-bar">
        <h1 class="dashboard-title"><?php echo htmlspecialchars($username); ?>'s Achievements and Badges</h1>

    </div>

    <button onclick="history.back()" class="back-btn">Return To Dashboard</button>

    <?php if ($_SESSION['role'] == 2): ?>
        <div class="welcome-section">
            <img src="images/charaubelle/C_teacher_eyesmile.webp" alt="Welcome Owl">
            <div class="welcome-box">
                <p class="welcome-text">This is your achievements page,
                    <strong><?php echo htmlspecialchars($_SESSION['username']); ?>!</strong></p>
                <p class="welcome-subtext">A reflection of your dedication and progress! Every milestone here is proof of
                    how far you’ve come—keep pushing forward, you’re doing amazing!</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="main-container">
        <div class="grid-container">
            <!-- Left Column: Achievements and Progress -->
            <div class="achievements-column">
                <h2>Achievements and Progress</h2>
                <div class="achievement-container">
                    <!-- Achievement Cards -->
                    <div class="achievement-card streak">
                        <div class="achievement-content">
                            <img src="<?php echo $achievementGifs['Streak']; ?>" alt="Streak Badge"
                                class="achievement-gif">
                            <h3>Streak</h3>
                            <p><span
                                    class="highlight"><?php echo htmlspecialchars($achievements['ach_streak']); ?></span>
                                <?php echo ($achievements['ach_streak'] == 1) ? 'day' : 'days'; ?></p>
                        </div>
                        <div class="progress-bar">
                            <div class="progress"
                                style="width: <?php echo min(100, ($achievements['ach_streak'] / 5) * 100); ?>%;"></div>
                        </div>
                    </div>

                    <div class="achievement-card modules">
                        <div class="achievement-content">
                            <img src="<?php echo $achievementGifs['Modules Read']; ?>" alt="Modules Read Badge"
                                class="achievement-gif">
                            <h3>Modules Read</h3>
                            <p><span
                                    class="highlight"><?php echo htmlspecialchars($achievements['ach_modules_read']); ?></span>
                                <?php echo ($achievements['ach_modules_read'] == 1) ? 'module' : 'modules'; ?></p>
                        </div>
                        <div class="progress-bar">
                            <div class="progress"
                                style="width: <?php echo min(100, ($achievements['ach_modules_read'] / 10) * 100); ?>%;">
                            </div>
                        </div>
                    </div>

                    <div class="achievement-card answered">
                        <div class="achievement-content">
                            <img src="<?php echo $achievementGifs['Assessments Answered']; ?>"
                                alt="Assessments Answered Badge" class="achievement-gif">
                            <h3>Assessments Completed</h3>
                            <p><span
                                    class="highlight"><?php echo htmlspecialchars($achievements['ach_answered_assessments']); ?></span>
                                <?php echo ($achievements['ach_answered_assessments'] == 1) ? 'assessment' : 'assessments'; ?>
                            </p>
                        </div>
                        <div class="progress-bar">
                            <div class="progress"
                                style="width: <?php echo min(100, ($achievements['ach_answered_assessments'] / 20) * 100); ?>%;">
                            </div>
                        </div>
                    </div>

                    <div class="achievement-card collabs">
                        <div class="achievement-content">
                            <img src="<?php echo $achievementGifs['Collaborations']; ?>" alt="Collaborations Badge"
                                class="achievement-gif">
                            <h3>Collaborations</h3>
                            <p><span
                                    class="highlight"><?php echo htmlspecialchars($achievements['ach_collaborated']); ?></span>
                                <?php echo ($achievements['ach_collaborated'] == 1) ? 'collaboration' : 'collaborations'; ?>
                            </p>
                        </div>
                        <div class="progress-bar">
                            <div class="progress"
                                style="width: <?php echo min(100, ($achievements['ach_collaborated'] / 5) * 100); ?>%;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Earned Badges -->
            <div class="badges-column">
                <h2>Earned Badges</h2>
                <div class="badge-gallery">
                    <?php if ($earned_badges): ?>
                        <?php foreach ($earned_badges as $badge): ?>
                            <div class="badge-card">
                                <?php
                                // Verify if the image exists
                                $imagePath = "images/badges/" . htmlspecialchars($badge['badge_image']);
                                if (!file_exists($imagePath) || empty($badge['badge_image'])) {
                                    $imagePath = "images/badges/default_badge.svg"; // Fallback image
                                }
                                ?>
                                <img src="<?php echo $imagePath; ?>" alt="Badge Image" class="badge-img">
                                <div class="badge-info">
                                    <h3><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                                    <p>Earned on: <?php echo htmlspecialchars($badge['badge_earned_date']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <p class="no-badges">No badges yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <script>
            function toggleSidebar() {
                document.querySelector('.sidebar').classList.toggle('collapsed');
                document.querySelector('.content').classList.toggle('expanded');
                document.querySelector('.top-bar').classList.toggle('expanded');
                const toggleIcon = document.getElementById('toggleIcon');
                if (document.querySelector('.sidebar').classList.contains('collapsed')) {
                    toggleIcon.src = "images/sidebar_open_icon.png";
                } else {
                    toggleIcon.src = "images/sidebar_close_icon.png";
                }
            }


            function updateDate() {
                const options = { weekday: 'long', month: 'long', day: 'numeric' };
                const currentDate = new Date().toLocaleDateString('en-PH', options);
                document.getElementById('currentDate').textContent = currentDate;
            }
            updateDate();

            function getBadgeImage($badgeName) {
                $badgeImages = [
                    "5-Day Streak" => "5_day_streak.svg",
                    "10 Modules Master" => "10_modules_master.svg",
                    "Assessment Beginner" => "assessment_beginner.svg",
                    "Collaboration Novice" => "collaboration_novice.svg"
                ];
                return $badgeImages[$badgeName] ?? "default_badge.svg"; // Default image if badge not found
            }

        </script>
</body>

</html>