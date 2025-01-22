<?php
require 'auth.php';
require 'config.php';

// Check if the user is a student
if ($_SESSION['role'] != 2) {
    echo "Access denied: Students only.";
    exit();
}

// Get class_id from session or URL
$class_id = $_SESSION['class_id'] ?? $_GET['class_id'] ?? null;

if (!$class_id) {
    echo "Invalid or missing class ID.";
    exit();
}

// Fetch the student_id from the session (or database if not available in the session)
$student_id = $_SESSION['student_id'] ?? null;

if (!$student_id) {
    // Attempt to fetch student_id from the database
    $stmt = $pdo->prepare("SELECT student_id FROM student_tbl WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_id = $student['student_id'];
    } else {
        echo "Student not found.";
        exit();
    }
}

// Fetch published assessments for the specific class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE status = 'Published' AND class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getTotalPointsForMC($pdo, $assessment_id) {
    $stmt = $pdo->prepare("SELECT SUM(points) AS total_points FROM questions_mcq_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_points'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Assessments</title>
</head>
<body>
    <h2>Available Assessments</h2>
    <a href="student_dashboard.php" class="btn btn-primary">Dashboard</a>
    <table border="1">
        <thead>
            <tr>
                <th>Assessment Name</th>
                <th>Type</th>
                <th>Total Points</th>
                <th>Time Limit (minutes)</th>
                <th>Action</th>
                <th>Leaderboards</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($assessments as $assessment): ?>
            <?php
            // Determine the URL based on assessment type
            $url = '';
            $total_points = htmlspecialchars($assessment['total_points']);

            if ($assessment['type'] == 'Essay') {
                $url = "take_essay.php?assessment_id=" . $assessment['assessment_id'];
            } else if ($assessment['type'] == 'True or False') {
                $url = "take_true_false.php?assessment_id=" . $assessment['assessment_id'];
            } else if ($assessment['type'] == 'Multiple Choice - Individual') {
                $url = "take_multiple_choice.php?assessment_id=" . $assessment['assessment_id'];
                $total_points = getTotalPointsForMC($pdo, $assessment['assessment_id']);
            } else if ($assessment['type'] == 'Multiple Choice - Collaborative') {
                $url = "host_or_join.php?assessment_id=" . $assessment['assessment_id'];
            } else if ($assessment['type'] == 'Recitation') {
                $url = "take_recitation.php?assessment_id=" . $assessment['assessment_id'];
            } else {
                $url = null; // Invalid type, no link generated
            }

            // Check if the student has attempted the assessment
            $attempted = false;

            // Check answers_esy_tbl
            $stmt = $pdo->prepare("SELECT Attempt FROM answers_esy_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }

            // Check answers_mcq_tbl
            if (!$attempted) {
                $stmt = $pdo->prepare("SELECT Attempt FROM answers_mcq_tbl WHERE student_id = ? AND assessment_id = ?");
                $stmt->execute([$student_id, $assessment['assessment_id']]);
                if ($stmt->fetch()) {
                    $attempted = true;
                }
            }

            // Check answers_tf_tbl
            if (!$attempted) {
                $stmt = $pdo->prepare("SELECT Attempt FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
                $stmt->execute([$student_id, $assessment['assessment_id']]);
                if ($stmt->fetch()) {
                    $attempted = true;
                }
            }

            // Check answers_mcq_collab_tbl
            if (!$attempted) {
                $stmt = $pdo->prepare("SELECT attempt FROM answers_mcq_collab_tbl WHERE submitted_by = ? AND assessment_id = ?");
                $stmt->execute([$student_id, $assessment['assessment_id']]);
                if ($stmt->fetch()) {
                    $attempted = true;
                }
            }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                <td><?php echo htmlspecialchars($assessment['type']); ?></td>
                <td><?php echo $total_points; ?></td>
                <td><?php echo htmlspecialchars($assessment['time_limit']); ?></td>
                <td>
                    <?php if ($attempted): ?>
                        <button disabled>Completed</button>
                    <?php elseif ($url): ?>
                        <form action="take_assessment.php" method="get">
                            <input type="hidden" name="assessment_id" value="<?php echo $assessment['assessment_id']; ?>">
                            <input type="submit" value="Take Assessment">
                        </form>
                    <?php else: ?>
                        Invalid Type
                    <?php endif; ?>
                </td>
                <td>
                    <a href="leaderboards.php?student_id=<?php echo htmlspecialchars($student_id); ?>&class_id=<?php echo htmlspecialchars($class_id); ?>&assessment_id=<?php echo $assessment['assessment_id']; ?>">Check Leaderboards</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
