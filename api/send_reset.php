<?php
require_once '../includes/db.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id, first_name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // Generate a secure 64-character token
    $token = bin2hex(random_bytes(32));
    // Set expiration to 1 hour from now
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $update = $db->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
    $update->execute([$token, $expires, $user['id']]);

    // Send Email using PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Replace with your SMTP host
        $mail->SMTPAuth   = true;
        $mail->Username   = 'vrakeit@gmail.com'; // Replace with your email
        $mail->Password   = 'tyfu izuw ixpo azrt';          // Replace with your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('noreply@vrakeit.com', 'VrakeIT Support');
        $mail->addAddress($email, $user['first_name']);

        $resetLink = "http://localhost/vrakeIT/reset_password.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - VrakeIT';
        $mail->Body    = "Hi {$user['first_name']},<br><br>Click the link below to reset your password. This link expires in 1 hour.<br><br><a href='{$resetLink}'>Reset Password</a><br><br>If you didn't request this, please ignore this email.";

        $mail->send();
    } catch (Exception $e) {
        // Log the error internally, but don't expose SMTP details to the user
        error_log("Mailer Error: {$mail->ErrorInfo}");
    }
}

// Always return success to prevent email enumeration
echo json_encode(['success' => true, 'message' => 'If that email exists, a reset link has been sent.']);