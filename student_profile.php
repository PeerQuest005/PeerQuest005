<?php

//PANG TEACHER LANG TO GUYS SORRY!!!
//-Allen

require 'config.php';
require 'auth.php';

// Check if teacher is logged in
if ($_SESSION['role'] != 1) {
    die("Access denied: Teachers only.");
}

$student_id = $_GET['student_id'];
$class_id = $_GET['class_id'];

// Fetch student details
$stmt = $pdo->prepare("SELECT student_first_name, student_last_name FROM student_tbl WHERE student_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found.");
}

// Fetch assessments and answers for the student
$stmt = $pdo->prepare("
    SELECT 
        e.essay_id,
        e.assessment_id,
        e.question_id,
        e.answer_text,
        e.grade,
        q.question_text,
        q.points
    FROM answers_esy_tbl e
    JOIN questions_esy_tbl q ON e.question_id = q.question_id
    WHERE e.student_id = ?
");
$stmt->execute([$student_id]);
$essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle score adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['grades'] as $essay_id => $grade) {
        $stmt = $pdo->prepare("UPDATE answers_esy_tbl SET grade = ? WHERE essay_id = ?");
        $stmt->execute([$grade, $essay_id]);
    }
    echo "Grades updated successfully!";
    header("Location: student_profile.php?student_id=$student_id&class_id=$class_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile of <?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?></title>
</head>
<body>
    <h2>Profile of <?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?></h2>
    <p><a href="view_classlist.php?class_id=<?php echo $class_id; ?>">Back to Class List</a></p>

    <form method="post">
        <table border="1">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Answer</th>
                    <th>Points</th>
                    <th>Current Grade</th>
                    <th>Adjust Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($essays as $essay): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($essay['question_text']); ?></td>
                        <td><?php echo htmlspecialchars($essay['answer_text']); ?></td>
                        <td><?php echo htmlspecialchars($essay['points']); ?></td>
                        <td><?php echo htmlspecialchars($essay['grade']); ?></td>
                        <td>
                            <input 
                                type="number" 
                                name="grades[<?php echo $essay['essay_id']; ?>]" 
                                value="<?php echo htmlspecialchars($essay['grade']); ?>" 
                                min="0" 
                                max="<?php echo htmlspecialchars($essay['points']); ?>" 
                                required>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit">Update Grades</button>
    </form>

    
</body>
</html>
