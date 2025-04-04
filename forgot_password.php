<?php
session_start();
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null; // 'success' or 'error' 
unset($_SESSION['message'], $_SESSION['message_type']); // Clear session variables after displaying 
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Forgot Password | PeerQuest</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .error-message {
            color: red;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .modal-body.success {
            color: green;
            font-weight: bold;
        }
    </style>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

</head>

<body>
    <div class="login-container">
        <div class="logo-section">
            <img src="images/logo/pq_logo.webp" id="pq_logo" alt="PeerQuest Logo">
            <img src="images/logo/pq_logo_txt.webp" id="pq_logo_txt" alt="PeerQuest Logo Text">
        </div>

        <div class="login-card">
            <h2 class="login-title">Forgot Password</h2>

            <!-- Display error message if present -->
            <?php if ($message && $message_type === 'error'): ?>
                <div class="error-message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" action="send_password_reset.php">
                <div class="form-group">
                    <label for="email">EMAIL</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email"
                        required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn-primary">Send Reset Link</button>
                </div>

                <p class="register-link"><a href="login.php">Back to Login</a></p>
            </form>
        </div>
    </div>

    <!-- Bootstrap Modal for Success Message -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body success">
                    <?= htmlspecialchars($message) ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="redirectToLogin()">Go to Login</button>

                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            <?php if ($message && $message_type === 'success'): ?>
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php endif; ?>
        });
        function redirectToLogin() {
            window.location.href = 'login.php';
        }

    </script>
</body>

</html>