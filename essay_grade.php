<?php
require 'auth.php';
require 'config.php';

// Check if the user is a teacher (role = 1)
if ($_SESSION['role'] != 1) {
    echo "Access denied: Teachers only.";
    exit();
}

// Fetch all submitted essays for a specific assessment
$assessment_id = $_GET['assessment_id'] ?? null;
if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

$stmt = $pdo->prepare("
    SELECT 
        e.essay_id,
        e.assessment_id,
        e.question_id,
        e.answer_text,
        e.grade,
        s.teacher_first_name,
        s.teacher_last_name,
        s.username
    FROM answers_esy_tbl e
    JOIN student_tbl s ON e.student_id = s.teacher_id
    WHERE e.assessment_id = ?
");
$stmt->execute([$assessment_id]);
$essays = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission to grade essays
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['grades'] as $essay_id => $grade) {
        $stmt = $pdo->prepare("UPDATE answers_esy_tbl SET grade = ? WHERE essay_id = ?");
        $stmt->execute([$grade, $essay_id]);
    }
    echo "Grades updated successfully!";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grade Essays</title>
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>
    <h2>Grade Essays for Assessment ID: <?php echo htmlspecialchars($assessment_id); ?></h2>
    
    <form method="post">
        <table border="1">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Username</th>
                    <th>Question ID</th>
                    <th>Answer</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($essays as $essay): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($essay['teacher_first_name'] . " " . $essay['teacher_last_name']); ?></td>
                        <td><?php echo htmlspecialchars($essay['username']); ?></td>
                        <td><?php echo htmlspecialchars($essay['question_id']); ?></td>
                        <td><?php echo htmlspecialchars($essay['answer_text']); ?></td>
                        <td>
                            <input 
                                type="number" 
                                name="grades[<?php echo $essay['essay_id']; ?>]" 
                                value="<?php echo htmlspecialchars($essay['grade']); ?>" 
                                min="0" 
                                required>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit">Submit Grades</button>
    </form>
</body>
</html>
