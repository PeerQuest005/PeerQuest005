<?php 
session_start(); // Start session to store the message 
require 'config.php'; 
 
$email = $_POST["email"]; 
$user_type = null; 
 
try { 
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
 
        // Insert into password_reset_tbl 
        $resetStmt = $pdo->prepare("INSERT INTO password_reset_tbl (email, user_type, token, expires_at) VALUES (?, ?, ?, ?)"); 
        $resetStmt->execute([$email, $user_type, $token_hash, $expiry]); 
 
        // Send email 
        $mail = require 'mailer.php'; 
        $mail->setFrom("500groupfive005@gmail.com", "PeerQuest-password"); 
        $mail->addAddress($email); 
        $mail->Subject = "Password Reset"; 
 
        // Ensure email is sent as HTML 
        $mail->isHTML(true); 
        $mail->CharSet = "UTF-8"; // Set correct character encoding 
 
        $mail->Body = ' 
            <html> 
            <head> 
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"> 
            <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head> 
            <body> 
                <p>To reset your password, follow this secure link: <a href="https://peer-quest.com//reset_password.php?token=' . $token . '" target="_blank"> 
                <b>here</b></a></p>
                 
                <p>Or copy and paste this link into your browser:</p> 
                <p><a href="https://peer-quest.com/reset_password.php?token=' . $token . '" target="_self"> 
                https://peer-quest.com/reset_password.php?token=' . $token . '</a></p> 
 
                <p>This email was sent because you requested a password reset. If you did not request this, please ignore it.</p> 
            </body> 
            </html>'; 
 
        $mail->send(); 
 
        // Store success message and type 
        $_SESSION['message'] = "Reset email sent. Please check your inbox (and spam)."; 
        $_SESSION['message_type'] = 'success'; 
    } else { 
        // Store error message and type 
        $_SESSION['message'] = "Email not found."; 
        $_SESSION['message_type'] = 'error'; 
    } 
 
    // Redirect back to forgot_password.php 
    header("Location: forgot_password.php"); 
    exit(); 
 
} catch (Exception $e) { 
    die("Error: " . $e->getMessage()); 
}