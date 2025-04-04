<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . "/vendor/autoload.php";

$mail = new PHPMailer(true);

// Enable verbose debug output for troubleshooting (optional during development)
// $mail->SMTPDebug = SMTP::DEBUG_SERVER;

// for debugging eto
$mail->SMTPDebug = SMTP::DEBUG_SERVER;

$mail->SMTPDebug = 0;


$mail->isSMTP();
$mail->SMTPAuth = true;

// SMTP server configuration
$mail->Host = "smtp.gmail.com"; // Gmail SMTP server
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port = 587; // Use 465 for SSL, or 587 for TLS

// Your email credentials
$mail->Username = "500groupfive005@gmail.com";
$mail->Password = "abnn bwwi mksx cxsq"; // Replace with your password

// Enable HTML emails
$mail->isHtml(true);
$mail->CharSet = "UTF-8"; // Ensure proper encoding

return $mail;