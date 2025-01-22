<?php
require 'config.php';

session_start(); // Start the session

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Check the username in both teacher_tbl and student_tbl
        $teacherStmt = $pdo->prepare("SELECT teacher_id, username, teacher_password FROM teacher_tbl WHERE username = ?");
        $studentStmt = $pdo->prepare("SELECT student_id, username, student_password FROM student_tbl WHERE username = ?");

        // Execute both queries
        $teacherStmt->execute([$username]);
        $studentStmt->execute([$username]);

        $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        // Check if the user exists in either table and verify the password
        if ($teacher && password_verify($password, $teacher['teacher_password'])) {
            // Teacher login
            $_SESSION['username'] = $teacher['username'];
            $_SESSION['role'] = 1; // Role 1 for Teacher
            $_SESSION['loggedin'] = true;
            $_SESSION['teacher_id'] = $teacher['teacher_id']; // Store teacher_id for later use
            header("Location: teacher_dashboard.php");
            exit();
        } elseif ($student && password_verify($password, $student['student_password'])) {
            // Student login
            $_SESSION['username'] = $student['username'];
            $_SESSION['role'] = 2; // Role 2 for Student
            $_SESSION['loggedin'] = true;
            $_SESSION['student_id'] = $student['student_id']; // Store student_id for later use
            header("Location: student_dashboard.php");
            exit();
        } else {
            echo "Invalid username or password.";
        }
    } catch (PDOException $e) {
        die("ERROR: Could not connect. " . $e->getMessage());
    }
}
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/login_register.css" rel="stylesheet">

</head>

<div class="login-card">
        <h2 class="login-title">Login</h2>
        <form method="post" action="login.php">
            <div class="form-group">
                <label for="username">E-MAIL OR USERNAME</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Ms.joanne" required>
            </div>

            <div class="form-group">
                <label for="password">PASSWORD</label>
                <div class="password-container">
                    <input type="password" class="form-control" id="password" name="password" placeholder="password123" required>
                    <span class="toggle-password">&#128065;</span> <!-- Eye icon -->
                </div>
            </div>

            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="forgot_password.php" class="forgot-password">FORGOT PASSWORD?</a>
            </div>

            <div class="form-group">
                <button type="submit" class="btn-primary">Login</button>
            </div>
            <p class="register-link">Don't have an account? <a href="signup.php">Register here</a>.</p>
        </form>
    </div>
</body>
</html>
