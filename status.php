<?php
require 'config.php';
require 'auth.php';

$student_id = $_GET['student_id'] ?? null;
$class_id = $_GET['class_id'] ?? null;
$show_answers = $_GET['show_answers'] ?? null;
$selected_assessment_id = $_GET['assessment_id'] ?? null;

if (!$student_id || !$class_id) {
    die("Invalid student or class ID.");
}

// Fetch student details
$stmt = $pdo->prepare("SELECT CONCAT(student_first_name, ' ', student_last_name) AS full_name FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    die("Class not found.");
}

// Fetch all assessments related to the class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch student's answers and grades for each assessment
$stmt = $pdo->prepare("SELECT assessment_id, MAX(attempt) AS max_attempt, SUM(grade) AS total_grade FROM (
    SELECT assessment_id, attempt, grade FROM answers_esy_tbl WHERE student_id = ?
    UNION ALL
    SELECT assessment_id, attempt, grades AS grade FROM answers_mcq_collab_tbl WHERE submitted_by = ?
    UNION ALL
    SELECT assessment_id, attempt, correct_answer AS grade FROM answers_mcq_tbl WHERE student_id = ?
    UNION ALL
    SELECT assessment_id, attempt, correct_answer AS grade FROM answers_tf_tbl WHERE student_id = ?
) AS combined_answers GROUP BY assessment_id");
$stmt->execute([$student_id, $student_id, $student_id, $student_id]);
$student_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate the overall grade
$student_answers_map = [];
$total_grade = 0;
$total_points = 0;

foreach ($student_answers as $answer) {
    $student_answers_map[$answer['assessment_id']] = [
        'max_attempt' => $answer['max_attempt'],
        'total_grade' => $answer['total_grade']
    ];
    $total_grade += $answer['total_grade'];
}

foreach ($assessments as $assessment) {
    $total_points += $assessment['total_points'];
}

$overall_grade = $total_points > 0 ? round(($total_grade / $total_points) * 100) : 0;

// Fetch answers if specific assessment is selected
$answers = [];
$selected_total_points = 0;
if ($show_answers && $selected_assessment_id) {
    $stmt = $pdo->prepare("SELECT question_text, answer_text, grade, correct_option, correct_answer FROM (
        SELECT qt.question_text, ae.answer_text, ae.grade, NULL AS correct_option, NULL AS correct_answer FROM answers_esy_tbl ae
        JOIN questions_esy_tbl qt ON ae.question_id = qt.question_id
        WHERE ae.assessment_id = ? AND ae.student_id = ?
        UNION ALL
        SELECT qt.question_text, amc.selected_option AS answer_text, amc.grades AS grade, qt.correct_option, NULL AS correct_answer FROM answers_mcq_collab_tbl amc
        JOIN questions_mcq_tbl qt ON amc.question_id = qt.question_id
        WHERE amc.assessment_id = ? AND amc.submitted_by = ?
        UNION ALL
        SELECT qt.question_text, am.selected_option AS answer_text, am.correct_answer AS grade, qt.correct_option, NULL AS correct_answer FROM answers_mcq_tbl am
        JOIN questions_mcq_tbl qt ON am.question_id = qt.question_id
        WHERE am.assessment_id = ? AND am.student_id = ?
        UNION ALL
        SELECT qt.question_text, at.answer_text, at.correct_answer AS grade, NULL AS correct_option, qt.correct_answer FROM answers_tf_tbl at
        JOIN questions_tf_tbl qt ON at.question_id = qt.question_id
        WHERE at.assessment_id = ? AND at.student_id = ?
    ) AS all_answers");
    $stmt->execute([$selected_assessment_id, $student_id, $selected_assessment_id, $student_id, $selected_assessment_id, $student_id, $selected_assessment_id, $student_id]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch total points for the selected assessment
    $stmt = $pdo->prepare("SELECT total_points FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$selected_assessment_id]);
    $selected_total_points = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status for <?php echo htmlspecialchars($student['full_name']); ?></title>
    <style>
        .circular-progress {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: conic-gradient(
                #0dcaf0 <?php echo $overall_grade; ?>%,
                #343a40 <?php echo $overall_grade; ?>%
            );
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #ffffff;
            background-color: #343a40;
        }
    </style>
</head>
<body>
    <h2>Status for <?php echo htmlspecialchars($student['full_name']); ?> in <?php echo htmlspecialchars($class['class_subject']); ?> - <?php echo htmlspecialchars($class['class_section']); ?></h2>
    <?php if ($_SESSION['role'] == 1): // Role 1 = Teacher ?>
    <p><a href="view_classlist.php?class_id=<?php echo $class_id; ?>">Back to Class List</a></p>
    <?php else: // For students ?>
        <p><a href="student_dashboard.php">Back to Dashboard</a></p>
    <?php endif; ?>

    <?php if (!$show_answers): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Overall Grade</h4>
            <div class="circular-progress">
                <span><?php echo $overall_grade; ?>%</span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$show_answers): ?>
        <?php if (count($assessments) > 0): ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>Assessment Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Total Grade</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assessments as $assessment): ?>
                        <?php
                        $assessment_id = $assessment['assessment_id'];
                        $total_grade = $student_answers_map[$assessment_id]['total_grade'] ?? 0;
                        $status = $total_grade > 0 ? 'Completed' : 'Not Attempted';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($assessment['name']); ?></td>
                            <td><?php echo htmlspecialchars($assessment['type']); ?></td>
                            <td><?php echo $status; ?></td>
                            <td><?php echo $total_grade; ?> / <?php echo $assessment['total_points']; ?></td>
                            <td><a href="?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&show_answers=true&assessment_id=<?php echo $assessment_id; ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No assessments found for this class.</p>
        <?php endif; ?>
    <?php else: ?>
        <h3>Answers for Assessment ID: <?php echo htmlspecialchars($selected_assessment_id); ?></h3>
        <table border="1">
            <thead>
                <tr>
                    <th>Question Text</th>
                    <th>Answer</th>
                    <th>Grade</th>
                    <?php if ($answers && isset($answers[0]['correct_option']) && !is_null($answers[0]['correct_option'])): ?>
                        <th>Correct Option</th>
                    <?php elseif ($answers && isset($answers[0]['correct_answer']) && !is_null($answers[0]['correct_answer'])): ?>
                        <th>Correct Answer</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($answers as $answer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($answer['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($answer['answer_text']); ?></td>
                        <td><?php echo htmlspecialchars($answer['grade']); ?></td>
                        <?php if (isset($answer['correct_option']) && !is_null($answer['correct_option'])): ?>
                            <td><?php echo htmlspecialchars($answer['correct_option']); ?></td>
                        <?php elseif (isset($answer['correct_answer']) && !is_null($answer['correct_answer'])): ?>
                            <td><?php echo htmlspecialchars($answer['correct_answer']); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p>Total Grade: <?php echo array_sum(array_column($answers, 'grade')); ?> / <?php echo $selected_total_points; ?></p>
        <p><a href="?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>">Back to Assessments</a></p>
    <?php endif; ?>
</body>
</html>
