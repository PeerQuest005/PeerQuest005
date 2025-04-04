<?php
session_start();
require 'config.php';

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


// Handle AJAX request to check username availability
if (isset($_POST['check_username'])) {
    $username = trim($_POST['username']);

    if (!preg_match('/^[a-zA-Z0-9._ -]+$/', $username)) {
        echo "invalid";
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM teacher_tbl WHERE username = ?
                           UNION
                           SELECT * FROM student_tbl WHERE username = ?");
    $stmt->execute([$username, $username]);

    echo ($stmt->rowCount() > 0) ? "exists" : "available";
    exit();
}

// Handle AJAX request to check email availability
if (isset($_POST['check_email'])) {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT * FROM teacher_tbl WHERE teacher_email = ?
                           UNION
                           SELECT * FROM student_tbl WHERE student_email = ?");
    $stmt->execute([$email, $email]);

    echo ($stmt->rowCount() > 0) ? "exists" : "available";
    exit();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $errors = [];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role = (int) $_POST['role'];

    // Validate first name and last name (letters, numbers, and spaces allowed)
    if (!preg_match('/^[a-zA-Z0-9._ -]+$/', $first_name)) {
        $errors[] = "First Name contains invalid characters.";
    }

    if (!preg_match('/^[a-zA-Z0-9._ -]+$/', $last_name)) {
        $errors[] = "Last Name contains invalid characters.";
    }

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9._ -]+$/', $username)) {
        $errors[] = "Username contains invalid characters.";
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Password validation rules
    if (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)
    ) {
        $errors[] = "Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.";
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check if username already exists
    $stmt = $pdo->prepare("SELECT username FROM teacher_tbl WHERE username = ? UNION SELECT username FROM student_tbl WHERE username = ?");
    $stmt->execute([$username, $username]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Username already exists.";
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT teacher_email FROM teacher_tbl WHERE teacher_email = ? UNION SELECT student_email FROM student_tbl WHERE student_email = ?");
    $stmt->execute([$email, $email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Email already exists.";
    }


    // Redirect back if errors exist
    if (!empty($errors)) {
        $_SESSION['signup_errors'] = $errors;
        header("Location: signup.php");
        exit();
    }

    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Insert based on role
        if ($role === 1) { // Teacher
            $stmt = $pdo->prepare("INSERT INTO teacher_tbl (teacher_first_name, teacher_last_name, username, teacher_email, teacher_password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $username, $email, $hashed_password]);
        } elseif ($role === 2) { // Student
            $stmt = $pdo->prepare("INSERT INTO student_tbl (student_first_name, student_last_name, username, student_email, student_password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $username, $email, $hashed_password]);
        } else {
            die("Invalid role selected.");
        }

        // Redirect to login page after successful signup
        $_SESSION['success_signup'] = "Successfully signed up!";
        header("Location: login.php");
        exit();
    } catch (PDOException $e) {
        die("ERROR: Could not execute. " . $e->getMessage());
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | PeerQuest</title>
    <link rel="stylesheet" href="css/signup.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js?render=6LfKMPAqAAAAAD5jLzQ5-DcIqo5M6DXEJsgLcFys"></script>


    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
    <div class="signup-card">

        <!-- Back Button at the Top-Left -->
        <button type="button" class="back-btn" id="backBtn">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <?php
        if (isset($_SESSION['signup_errors'])) {
            echo '<div id="error-message" class="error-message">';
            foreach ($_SESSION['signup_errors'] as $error) {
                echo htmlspecialchars($error) . '<br>';
            }
            echo '</div>';
            unset($_SESSION['signup_errors']);
        }
        ?>
        <h2 class="signup-title">Create Account</h2>

        <script>
            document.addEventListener("DOMContentLoaded", function () {
                grecaptcha.ready(function () {
                    document.querySelector("form").addEventListener("submit", function (event) {
                        event.preventDefault(); // Prevent default submission
                        grecaptcha.execute("6LfKMPAqAAAAAD5jLzQ5-DcIqo5M6DXEJsgLcFys", { action: "submit" }).then(function (token) {
                            document.getElementById("recaptcha_token").value = token;
                            document.querySelector("form").submit(); // Submit the form after getting the token
                        });
                    });
                });
            });
        </script>

        <form action="signup.php" method="POST">

            <!-- Step 1: Personal Details -->
            <div id="step-1">
                <div class="form-row">
                    <a href="login.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="text" class="form-control" id="email" name="email">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <i class="toggle-password fas fa-eye-slash" id="togglePassword1"
                            onclick="togglePassword('password', 'togglePassword1')"></i>
                    </div>
                    <ul id="password-requirements">
                        <li id="min-characters"> At least 8 characters</li>
                        <li id="uppercase"> At least one uppercase letter (A-Z)</li>
                        <li id="lowercase"> At least one lowercase letter (a-z)</li>
                        <li id="digit"> At least one number (0-9)</li>
                        <li id="special-char"> At least one special character (!@#$%^&*())</li>
                    </ul>
                    <p id="password-strength-message"></p>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            required>
                        <i class="toggle-password fas fa-eye-slash" id="togglePassword2"
                            onclick="togglePassword('confirm_password', 'togglePassword2')"></i>
                    </div>
                </div>

                <!-- Next Button -->
                <button type="button" class="btn-primary" id="nextBtn" disabled>Next</button>
            </div>

            <!-- Step 2: Role Selection and T&C -->

            <div id="step-2" style="display: none;">
                <div class="form-group">
                    <label for="role">Are you a teacher or a student?</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="" disabled selected>Select your role</option>
                        <option value="1">I'm a teacher</option>
                        <option value="2">I'm a student</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="createAccount-link">
                        <div class="checkbox-container">
                            <input type="checkbox" id="termsCheckbox">
                            <label for="termsCheckbox">I agree to the <a href="javascript:void(0)"
                                    id="showTermsLink">Terms and Conditions</a></label>
                        </div>

                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-primary" id="createButton" disabled>Create Account</button>

                <!-- Sign-in Link -->
                <p class="sign-in-text">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            </div>

            <input type="hidden" name="recaptcha_token" id="recaptcha_token">

        </form>

        <!-- Terms and Conditions Modal -->
        <div class="terms-modal" id="termsModal">
            <div class="terms-content">
                <button class="close-btn" id="closeModal">&times;</button>
                <h2>Terms and Conditions</h2>
                <iframe src="terms_and_condition.php" style="width: 100%; height: 400px; border: none;"></iframe>
            </div>
        </div>


    </div>




    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const step1 = document.getElementById("step-1");
            const step2 = document.getElementById("step-2");
            const nextBtn = document.getElementById("nextBtn");
            const backBtn = document.getElementById("backBtn");
            const errorContainer = document.querySelector(".error-message");
            const createButton = document.getElementById("createButton");
            const termsCheckbox = document.getElementById("termsCheckbox");

            function hideErrors() {
                if (errorContainer) {
                    errorContainer.style.display = "none";
                }
            }

            // **Hide Back Button initially**
            backBtn.style.display = "none";

            // **Disable Next Button initially**
            nextBtn.disabled = true;

            // **Enable Next Button only if all required fields are filled**
            function validateStep1() {
                const firstName = document.getElementById("first_name").value.trim();
                const lastName = document.getElementById("last_name").value.trim();
                const username = document.getElementById("username").value.trim();
                const email = document.getElementById("email").value.trim();
                const password = document.getElementById("password").value.trim();
                const confirmPassword = document.getElementById("confirm_password").value.trim();

                if (firstName && lastName && username && email && password && confirmPassword) {
                    nextBtn.disabled = false; // ✅ Enable Next button
                } else {
                    nextBtn.disabled = true; // ❌ Keep it disabled if any field is empty
                }
            }

            // **Attach validation event listener to each input field**
            document.querySelectorAll("#step-1 input").forEach(input => {
                input.addEventListener("input", validateStep1);
            });

            // **Fix: Next Button Click should go to Step 2**
            nextBtn.addEventListener("click", function () {
                hideErrors();
                step1.style.display = "none";
                step2.style.display = "block";
                backBtn.style.display = "block"; // ✅ Show Back Button when moving to Step 2
            });

            // **Fix: Back Button should return to Step 1**
            backBtn.addEventListener("click", function () {
                hideErrors();
                step1.style.display = "block";
                step2.style.display = "none";
                backBtn.style.display = "none"; // ✅ Hide Back Button again when in Step 1
            });

            // **Enable Create Account button only when T&C checkbox is checked**
            termsCheckbox.addEventListener("change", function () {
                createButton.disabled = !termsCheckbox.checked;
            });
        });


        // Show Terms Modal
        document.getElementById("showTermsLink").addEventListener("click", function () {
            document.getElementById("termsModal").style.display = "flex"; // ✅ Correctly opens the modal
        });


        function togglePassword(fieldId, iconId) {
            var passwordField = document.getElementById(fieldId);
            var eyeIcon = document.getElementById(iconId);

            if (passwordField.type === "password") {
                passwordField.type = "text";
                eyeIcon.classList.remove("fa-eye-slash");
                eyeIcon.classList.add("fa-eye");
            } else {
                passwordField.type = "password";
                eyeIcon.classList.remove("fa-eye");
                eyeIcon.classList.add("fa-eye-slash");
            }
        }

        function showTerms() {
            document.getElementById('termsModal').style.display = 'block';
        }

        function closeTerms() {
            document.getElementById('termsModal').style.display = 'none';
        }


        document.querySelector('.terms-content iframe').addEventListener('load', function () {
            const iframeDocument = this.contentDocument || this.contentWindow.document;
            iframeDocument.addEventListener('scroll', function () {
                const iframeScrollHeight = iframeDocument.documentElement.scrollHeight;
                const iframeScrollTop = iframeDocument.documentElement.scrollTop;
                const iframeClientHeight = iframeDocument.documentElement.clientHeight;

                if (iframeScrollTop + iframeClientHeight >= iframeScrollHeight - 10) {
                    document.getElementById('termsCheckbox').disabled = false;
                }
            });
        });


        function validateTerms() {
            if (!document.getElementById('termsCheckbox').checked) {
                alert('Please agree to the Terms and Conditions before submitting.');
                return false;
            }
            return true;
        }


        // Validate first name, last name, and username (only alphabets)
        const alphaRegex = /^[a-zA-Z]+$/;

        document.getElementById('first_name').addEventListener('input', function () {
            const firstName = this.value;
            const feedback = document.getElementById('first-name-feedback');
            if (!alphaRegex.test(firstName)) {
                feedback.textContent = "First name must contain only alphabets.";
            } else {
                feedback.textContent = "";
            }
        });

        document.getElementById('last_name').addEventListener('input', function () {
            const lastName = this.value;
            const feedback = document.getElementById('last-name-feedback');
            if (!alphaRegex.test(lastName)) {
                feedback.textContent = "Last name must contain only alphabets.";
            } else {
                feedback.textContent = "";
            }
        });

        document.getElementById('username').addEventListener('input', function () {
            const username = this.value;
            const feedback = document.getElementById('username-feedback');
            if (!alphaRegex.test(username)) {
                feedback.textContent = "Username must contain only alphabets.";
            } else {
                feedback.textContent = "";
            }
        });

        $(document).ready(function () {
            // Check username availability
            $('#username').on('input', function () {
                var username = $(this).val();
                checkUsernameAvailability(username);
            });

            // Check email availability
            $('#email').on('input', function () {
                var email = $(this).val();
                checkEmailAvailability(email);
            });

            // Function to check username availability
            function checkUsernameAvailability(username) {
                $.ajax({
                    url: 'signup.php',
                    type: 'POST',
                    data: {
                        check_username: true,
                        username: username
                    },
                    success: function (response) {
                        if (response === "exists") {
                            $('#username-feedback').text("This username already exists.");
                        } else if (response === "invalid") {
                            $('#username-feedback').text("Username must contain only alphabets.");
                        } else {
                            $('#username-feedback').text("");
                        }
                    }
                });
            }

            // Function to check email availability
            function checkEmailAvailability(email) {
                $.ajax({
                    url: 'signup.php',
                    type: 'POST',
                    data: {
                        check_email: true,
                        email: email
                    },
                    success: function (response) {
                        if (response === "exists") {
                            $('#email-feedback').text("This email already exists.");
                        } else {
                            $('#email-feedback').text("");
                        }
                    }
                });
            }
        });


        document.querySelector('.terms-content iframe').addEventListener('load', function () {
            const iframeDocument = this.contentDocument || this.contentWindow.document;
            iframeDocument.addEventListener('scroll', function () {
                const iframeScrollHeight = iframeDocument.documentElement.scrollHeight;
                const iframeScrollTop = iframeDocument.documentElement.scrollTop;
                const iframeClientHeight = iframeDocument.documentElement.clientHeight;

                if (iframeScrollTop + iframeClientHeight >= iframeScrollHeight - 10) {
                    document.getElementById('termsCheckbox').disabled = false;
                }
            });
        });

        //eto din gumagana, pra sa disabled/enabled ng creation button from tnc


        document.addEventListener("DOMContentLoaded", function () {
            const termsModal = document.getElementById("termsModal");

            const closeModal = document.getElementById("closeModal");

            // Show modal when the link is clicked
            showTermsLink.addEventListener("click", () => {
                termsModal.style.display = 'flex';
            });

            // Close modal when (X) is clicked
            closeModal.addEventListener('click', () => {
                termsModal.style.display = 'none';
            });

            // Hide modal if user clicks outside of it
            window.addEventListener('click', (event) => {
                if (event.target === termsModal) {
                    termsModal.style.display = 'none';
                }
            });
        });


        //pra nmn sa password security

        // Password strength validation logic
        const passwordField = document.getElementById('password');
        const passwordRequirements = document.getElementById('password-requirements'); // UL containing password rules
        const strengthMessage = document.getElementById('password-strength-message');

        // Initially hide the password requirements
        passwordRequirements.style.display = 'none';
        strengthMessage.style.display = 'none';

        // Indicators for password rules
        const minLengthIndicator = document.getElementById('min-characters');
        const upperCaseIndicator = document.getElementById('uppercase');
        const lowerCaseIndicator = document.getElementById('lowercase');
        const digitIndicator = document.getElementById('digit');
        const specialCharIndicator = document.getElementById('special-char');

        passwordField.addEventListener('input', function () {
            const password = passwordField.value;

            if (password.length > 0) {
                passwordRequirements.style.display = 'block'; // Show rules when typing starts
                strengthMessage.style.display = 'block'; // Show strength message
            } else {
                passwordRequirements.style.display = 'none'; // Hide when empty
                strengthMessage.style.display = 'none';
            }

            // Validate and update indicators
            toggleRequirementIndicator(minLengthIndicator, /.{8,}/.test(password));
            toggleRequirementIndicator(upperCaseIndicator, /[A-Z]/.test(password));
            toggleRequirementIndicator(lowerCaseIndicator, /[a-z]/.test(password));
            toggleRequirementIndicator(digitIndicator, /[0-9]/.test(password));
            toggleRequirementIndicator(specialCharIndicator, /[!@#$%^&*(),.?":{}|<>]/.test(password));

            // Determine password strength and update message
            const strength = checkPasswordStrength(password);
            updateStrengthMessage(strength);
        });

        // Function to toggle the validity of password requirements
        function toggleRequirementIndicator(element, isValid) {
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
            }
        }

        // Function to determine password strength
        function checkPasswordStrength(password) {
            let score = 0;
            if (/.{8,}/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score++;

            if (score <= 2) return 'Weak';
            if (score === 3) return 'Medium';
            return 'Strong';
        }

        // Function to update password strength message
        function updateStrengthMessage(strength) {
            strengthMessage.textContent = `${strength} password`;
            strengthMessage.style.color = strength === 'Weak' ? 'red' : strength === 'Medium' ? 'orange' : 'green';
        }
    </script>
</body>

</html>