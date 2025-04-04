<?php
require 'config.php';

session_start(); // Start the session

// Google reCAPTCHA Secret Key
$recaptcha_secret = "6LfKMPAqAAAAAKjHR473VWQfzH_4clBYawgdSgNX"; // Replace with your actual secret key

// Function to verify Google reCAPTCHA response
function verifyRecaptcha($token, $secret)
{
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        'secret' => $secret,
        'response' => $token
    ];

    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

if ($_SESSION['loggedin'] == true && $_SESSION['role'] == 1) {
    header("Location: loading.php?redirect=" . urlencode("teacher_dashboard.php"));
    exit();
} elseif ($_SESSION['loggedin'] == true && $_SESSION['role'] == 2) {
    header("Location: loading.php?redirect=" . urlencode("student_dashboard.php"));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usernameOrEmail = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        // Check if input is an email using regex
        $isEmail = filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL);

        // Prepare queries with email/username conditions
        $teacherStmt = $pdo->prepare("SELECT teacher_id, username, teacher_email, teacher_password FROM teacher_tbl WHERE username = ? OR teacher_email = ?");
        $studentStmt = $pdo->prepare("SELECT student_id, username, student_email, student_password, ach_last_login, ach_streak FROM student_tbl WHERE username = ? OR student_email = ?");

        // Execute both queries
        $teacherStmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $studentStmt->execute([$usernameOrEmail, $usernameOrEmail]);

        $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        // Check if the user exists in either table and verify the password
        if ($teacher && password_verify($password, $teacher['teacher_password'])) {
            // Teacher login
            $_SESSION['username'] = $teacher['username'];
            $_SESSION['role'] = 1; // Role 1 for Teacher
            $_SESSION['loggedin'] = true;
            $_SESSION['teacher_id'] = $teacher['teacher_id']; // Store teacher_id for later use
            header("Location: loading.php?redirect=" . urlencode("teacher_dashboard.php"));
            exit();
        } elseif ($student && password_verify($password, $student['student_password'])) {
            // Student login
            // Streak logic
            $lastLogin = $student['ach_last_login'];
            $currentStreak = $student['ach_streak'];

            $today = new DateTime(); // Current date
            $yesterday = new DateTime('-1 day'); // Yesterday's date

            if ($lastLogin) {
                $lastLoginDate = new DateTime($lastLogin);
                if ($lastLoginDate->format('Y-m-d') == $yesterday->format('Y-m-d')) {
                    $currentStreak++;
                } elseif ($lastLoginDate->format('Y-m-d') != $today->format('Y-m-d')) {
                    $currentStreak = 0;
                }
            } else {
                $currentStreak = 1;
            }

            // Update last login and streak in the database
            $updateStmt = $pdo->prepare("UPDATE student_tbl SET ach_last_login = ?, ach_streak = ? WHERE student_id = ?");
            $updateStmt->execute([$today->format('Y-m-d'), $currentStreak, $student['student_id']]);

            $_SESSION['username'] = $student['username'];
            $_SESSION['role'] = 2; // Role 2 for Student
            $_SESSION['loggedin'] = true;
            $_SESSION['student_id'] = $student['student_id']; // Store student_id for later use
            header("Location: loading.php?redirect=" . urlencode("student_dashboard.php"));
            exit();
        } else {
            $_SESSION['login_error'] = "Invalid username/email or password.";
            header("Location: login.php");
            exit();
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
    <title>Login | PeerQuest</title>
    <link href="css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js?render=6LfKMPAqAAAAAD5jLzQ5-DcIqo5M6DXEJsgLcFys"></script>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
    <div class="login-container">
        <div class="logo-section">
            <img src="images/logo/pq_logo.webp" id="pq_logo" alt="PeerQuest Logo">
            <img src="images/logo/pq_logo_txt.webp" id="pq_logo_txt" alt="PeerQuest Logo Text">
        </div>

        <div class="login-card">
            <?php
            if (isset($_SESSION['success_signup'])) {
                echo '<div class="success-message">';
                echo $_SESSION['success_signup'] . '<br>';
                echo '</div>';
                unset($_SESSION['success_signup']);
            }
            ?>
            <h2 class="login-title">Login</h2>
            <form method="post" action="login.php">
                <div class="form-group">
                    <label for="username">E-MAIL OR USERNAME</label>
                    <input type="text" class="form-control" id="username" name="username"
                        placeholder="Enter your e-mail or username" required>
                </div>

                <div class="form-group">
                    <label for="password">PASSWORD</label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter your password" required>
                        <i class="toggle-password fas fa-eye-slash" id="togglePassword"></i>
                    </div>
                </div>


                <div class="form-options">
                    <label>
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot_password.php" class="forgot-password">FORGOT PASSWORD?</a>
                </div>

                <div id="error-message" class="error-message hidden">
                    <?php
                    if (isset($_SESSION['login_error'])) {
                        echo $_SESSION['login_error'];
                        unset($_SESSION['login_error']); // Clear the error after displaying
                    }
                    ?>
                </div>


                <p></p>
                <div class="form-group">
                    <button type="submit" class="btn-primary loading-btn"
                        data-target="teacher_dashboard.php">Login</button>
                </div>

                <p class="register-link">Don't have an account? <a href="signup.php">Register here</a>.</p>
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

            </form>
        </div>
    </div>




    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordField = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const errorMessageDiv = document.getElementById('error-message');
            const form = document.querySelector('form');

            // Toggle password visibility
            togglePassword.addEventListener('click', function () {
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    togglePassword.classList.remove('fa-eye-slash');
                    togglePassword.classList.add('fa-eye');
                } else {
                    passwordField.type = 'password';
                    togglePassword.classList.remove('fa-eye');
                    togglePassword.classList.add('fa-eye-slash');
                }
            });

            // Show error message with animation if it exists
            if (errorMessageDiv && errorMessageDiv.innerText.trim() !== "") {
                errorMessageDiv.classList.remove('hidden');
                errorMessageDiv.classList.add('shake');
                setTimeout(() => {
                    errorMessageDiv.classList.remove('shake');
                }, 500);
            }

            // Client-side form validation
            form.addEventListener('submit', function (event) {
                const username = document.getElementById('username').value.trim();
                const password = document.getElementById('password').value.trim();

                if (username === '' || password === '') {
                    event.preventDefault();
                    showError('Please fill in all required fields.');
                }
            });

            function showError(message) {
                errorMessageDiv.textContent = message;
                errorMessageDiv.classList.remove('hidden');
                errorMessageDiv.classList.add('shake');
                setTimeout(() => {
                    errorMessageDiv.classList.remove('shake');
                }, 500);
            }
        });

        //password retries
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const errorMessageDiv = document.getElementById('error-message');

            function getFailedAttempts() {
                return JSON.parse(localStorage.getItem('failedLoginAttempts')) || { count: 0, lastAttempt: null };
            }

            function updateFailedAttempts() {
                const data = getFailedAttempts();
                data.count++;
                data.lastAttempt = new Date().getTime(); // Store the current time in milliseconds
                localStorage.setItem('failedLoginAttempts', JSON.stringify(data));
            }

            function isLockedOut() {
                const data = getFailedAttempts();
                if (data.count >= 10) {
                    const now = new Date().getTime();
                    const timeElapsed = (now - data.lastAttempt) / 1000; // Convert to seconds
                    if (timeElapsed < 30) {
                        startCountdown(30 - timeElapsed); // Start the countdown with remaining time
                        return true;
                    } else {
                        resetFailedAttempts(); // Reset after 30 seconds
                    }
                }
                return false;
            }

            function resetFailedAttempts() {
                localStorage.setItem('failedLoginAttempts', JSON.stringify({ count: 0, lastAttempt: null }));
            }

            function startCountdown(secondsRemaining) {
                let remainingTime = Math.ceil(secondsRemaining);
                errorMessageDiv.textContent = `Too many invalid attempts. Please wait ${remainingTime} seconds.`;
                errorMessageDiv.classList.remove('hidden');

                const countdownInterval = setInterval(() => {
                    remainingTime--;
                    errorMessageDiv.textContent = `Too many invalid attempts. Please wait ${remainingTime} seconds.`;

                    if (remainingTime <= 0) {
                        clearInterval(countdownInterval);
                        errorMessageDiv.textContent = "";
                        errorMessageDiv.classList.add('hidden');
                        resetFailedAttempts(); // Reset attempts once the timer finishes
                    }
                }, 1000); // Update every second
            }

            // Trigger form submission block if locked out
            form.addEventListener('submit', function (event) {
                if (isLockedOut()) {
                    event.preventDefault(); // Prevent form submission during lockout
                    return;
                }
            });

            // If the user sees an error message after a failed attempt, update failed attempts
            if (errorMessageDiv && errorMessageDiv.innerText.trim() !== "") {
                updateFailedAttempts();
            }

            // Check lockout on page load
            if (isLockedOut()) {
                // Prevent the form from submitting during lockout (handled via submit event)
            }
        });

    </script>


</body>

</html>