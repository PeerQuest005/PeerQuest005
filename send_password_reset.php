<?php
require 'config.php';

$email = $_POST["email"];
$user_type = null;

try {
    // Check if the email exists in student_tbl or teacher_tbl
    $stmt = $pdo->prepare("SELECT * FROM student_tbl WHERE student_email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_type = 'student';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM teacher_tbl WHERE teacher_email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $user_type = 'teacher';
        }
    }

    if ($user_type) {
        $token = bin2hex(random_bytes(16));
        $token_hash = hash("sha256", $token);
        $expiry = date("Y-m-d H:i:s", time() + 60 * 30);

        // Insert token into password_reset_tbl
        $resetStmt = $pdo->prepare("INSERT INTO password_reset_tbl (email, user_type, token, expires_at) VALUES (?, ?, ?, ?)");
        $resetStmt->execute([$email, $user_type, $token_hash, $expiry]);

        // Send reset email
        $mail = require 'mailer.php';
        $mail->setFrom("500groupfive005@gmail.com", "PeerQuest-your-password");
        $mail->addAddress($email); // The email provided by the user
        $mail->Subject = "Password Reset";
        $mail->Body = "Click <a href='http://localhost/peerquest/reset_password.php?token=$token'>here</a> to reset your password.";

        $mail->send();

        echo "Reset email sent. Please check your inbox (and spam).";
    } else {
        echo "Email not found.";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
