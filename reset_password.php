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
            $updateStmt = $pdo->prepare("
                UPDATE $table 
                SET $passwordColumn = ? 
                WHERE $emailColumn = ?
            ");
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
</head>
<body>

    <h1>Reset Password</h1>

    <form method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">
        <label for="password">New Password</label>
        <input type="password" name="password" id="password" required>
        <button>Reset Password</button>
    </form>
    <a a href="login.php"><button>Back to Login</button></a>

    
</body>
</html>
