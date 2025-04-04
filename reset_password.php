<?php
require 'config.php'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") { 
    $token = $_POST['token']; 
    $hashedToken = hash("sha256", $token); // Hash the token 
    $newPassword = trim($_POST['password']); 
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT); 
 
    try { 
        // Validate the hashed token 
        $stmt = $pdo->prepare("SELECT * FROM password_reset_tbl WHERE token = ? AND expires_at > NOW()"); 
        $stmt->execute([$hashedToken]); 
        $resetRequest = $stmt->fetch(PDO::FETCH_ASSOC); 
 
        if ($resetRequest) { 
            $email = $resetRequest['email']; 
            $user_type = $resetRequest['user_type']; 
            $table = $user_type === 'student' ? 'student_tbl' : 'teacher_tbl'; 
            $emailColumn = $user_type === 'student' ? 'student_email' : 'teacher_email'; 
            $passwordColumn = $user_type === 'student' ? 'student_password' : 'teacher_password'; 
 
            // Update the password 
            $updateStmt = $pdo->prepare("UPDATE $table SET $passwordColumn = ? WHERE $emailColumn = ?"); 
            $updateStmt->execute([$hashedPassword, $email]); 
 
            // Delete the token 
            $deleteStmt = $pdo->prepare("DELETE FROM password_reset_tbl WHERE token = ?"); 
            $deleteStmt->execute([$hashedToken]); 
 
            echo "Password successfully updated. You can now log in."; 
        } else { 
            echo "Invalid or expired token."; 
        } 
    } catch (PDOException $e) { 
        die("ERROR: Could not connect. " . $e->getMessage()); 
    } 
} 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $successMessage = "Password updated successfully.";
    $errorMessage = "Failed to update password. Please try again.";
    $passwordUpdated = false;

    try {
        // Simulate password update (replace with actual logic)
        $passwordUpdated = true; 

        if ($passwordUpdated) {
            echo "<div id='message' class='message success'>$successMessage</div>";
        } else {
            echo "<div id='message' class='message error'>$errorMessage</div>";
        }
    } catch (Exception $e) {
        echo "<div id='message' class='message error'>An error occurred: " . $e->getMessage() . "</div>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Reset Password</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- External CSS -->
    <link href="css/signup.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head>
<body>
<div class="signup-container">
    <div class="signup-card">

        <!-- Page Title -->
        <h2 class="signup-title">Reset Password</h2>

        <form method="post" id="resetPasswordForm">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

            <!-- New Password Field -->
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="password" id="password" placeholder="Enter your new password" required>
                    <i class="toggle-password fas fa-eye-slash" id="togglePassword1"></i>
                </div>
                <div id="password-strength-message"></div>
                <ul class="password-rules" id="passwordRules">
                    <li id="min-characters" class="invalid">✔ At least 8 characters</li>
                    <li id="uppercase" class="invalid">✔ At least 1 uppercase letter</li>
                    <li id="lowercase" class="invalid">✔ At least 1 lowercase letter</li>
                    <li id="digit" class="invalid">✔ At least 1 number</li>
                    <li id="special-char" class="invalid">✔ At least 1 special character</li>
                </ul>
            </div>

            <!-- Confirm Password Field -->
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-container">
                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Re-enter your password" required>
                    <i class="toggle-password fas fa-eye-slash" id="togglePassword2"></i>
                </div>
                <div id="password-match-message"></div>
            </div>

            <!-- Reset Password Button -->
            <button type="submit" class="btn-primary">Reset Password</button><br><br>
            <!-- Back to Login Button -->
            <a href="login.php" class="back-btn">Back to Login</a>

        </form>
    </div>
</div>

    <script>
        const messageDiv = document.getElementById('message');
        if (messageDiv) {
            messageDiv.style.display = 'block'; // Show message
            setTimeout(() => {
                messageDiv.style.display = 'none'; // Hide message after 5 seconds
            }, 5000);
        }


        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthMessage = document.getElementById('password-strength-message');
        const passwordRules = document.getElementById('passwordRules');
        const passwordMatchMessage = document.getElementById('password-match-message');

        // Rule indicators
        const minCharacters = document.getElementById('min-characters');
        const uppercase = document.getElementById('uppercase');
        const lowercase = document.getElementById('lowercase');
        const digit = document.getElementById('digit');
        const specialChar = document.getElementById('special-char');

        // Show password rules when the user starts typing
        passwordInput.addEventListener('input', function () {
            if (passwordInput.value.length > 0) {
                passwordRules.style.display = 'block'; // Show the rules once typing starts
            } else {
                passwordRules.style.display = 'none'; // Hide when input is cleared
            }

            const password = passwordInput.value;
            let strength = checkPasswordStrength(password);

            // Check and update individual password rules
            updateRuleIndicator(password.length >= 8, minCharacters);
            updateRuleIndicator(/[A-Z]/.test(password), uppercase);
            updateRuleIndicator(/[a-z]/.test(password), lowercase);
            updateRuleIndicator(/[0-9]/.test(password), digit);
            updateRuleIndicator(/[!@#$%^&*(),.?":{}|<>]/.test(password), specialChar);

            // Display feedback based on overall strength
            if (strength === 'Weak') {
                strengthMessage.textContent = 'Weak password';
                strengthMessage.style.color = 'red';
            } else if (strength === 'Medium') {
                strengthMessage.textContent = 'Medium strength password';
                strengthMessage.style.color = 'orange';
            } else if (strength === 'Strong') {
                strengthMessage.textContent = 'Strong password';
                strengthMessage.style.color = 'green';
            } else {
                strengthMessage.textContent = '';
            }

            // Check if passwords match (live check)
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        function updateRuleIndicator(isValid, element) {
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
            }
        }

        function checkPasswordStrength(password) {
            const minLength = /.{8,}/;
            const hasUpperCase = /[A-Z]/;
            const hasLowerCase = /[a-z]/;
            const hasDigit = /[0-9]/;
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/;

            let strengthScore = 0;

            if (minLength.test(password)) strengthScore++;
            if (hasUpperCase.test(password)) strengthScore++;
            if (hasLowerCase.test(password)) strengthScore++;
            if (hasDigit.test(password)) strengthScore++;
            if (hasSpecialChar.test(password)) strengthScore++;

            if (strengthScore <= 2) return 'Weak';
            if (strengthScore === 3) return 'Medium';
            if (strengthScore >= 4) return 'Strong';
        }

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length === 0) {
                passwordMatchMessage.textContent = ''; // No message when confirm password is empty
            } else if (password === confirmPassword) {
                passwordMatchMessage.textContent = 'Passwords match!';
                passwordMatchMessage.style.color = 'green';
            } else {
                passwordMatchMessage.textContent = 'Passwords do not match.';
                passwordMatchMessage.style.color = 'red';
            }
        }

        // Prevent form submission if passwords do not match or password is weak
        document.querySelector('#resetPasswordForm').addEventListener('submit', function (event) {
            if (checkPasswordStrength(passwordInput.value) === 'Weak') {
                event.preventDefault();
                alert('Please choose a stronger password before submitting.');
            } else if (passwordInput.value !== confirmPasswordInput.value) {
                event.preventDefault();
                alert('Passwords do not match. Please try again.');
            }
        });

//pra sa fish eye password
document.addEventListener('DOMContentLoaded', function () {
    const passwordField1 = document.getElementById('password');
    const togglePassword1 = document.getElementById('togglePassword1');

    const passwordField2 = document.getElementById('confirm_password');
    const togglePassword2 = document.getElementById('togglePassword2');

    // Toggle visibility for the first password field
    togglePassword1.addEventListener('click', function () {
        if (passwordField1.type === 'password') {
            passwordField1.type = 'text';
            togglePassword1.classList.remove('fa-eye-slash');
            togglePassword1.classList.add('fa-eye');
        } else {
            passwordField1.type = 'password';
            togglePassword1.classList.remove('fa-eye');
            togglePassword1.classList.add('fa-eye-slash');
        }
    });

    // Toggle visibility for the second password field (confirm password)
    togglePassword2.addEventListener('click', function () {
        if (passwordField2.type === 'password') {
            passwordField2.type = 'text';
            togglePassword2.classList.remove('fa-eye-slash');
            togglePassword2.classList.add('fa-eye');
        } else {
            passwordField2.type = 'password';
            togglePassword2.classList.remove('fa-eye');
            togglePassword2.classList.add('fa-eye-slash');
        }
    });
});


        
    </script>
</body>
</html>
