<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/sms.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

if (empty($_SESSION['pending_reg'])) {
    jsonResponse(false, 'Registration session expired. Please register again.');
}

$reg    = $_SESSION['pending_reg'];
$email  = $reg['email'];
$phone  = $reg['phone'];
$name   = $reg['first_name'];
$method = $reg['delivery_method'];
$db     = getDB();

// Rate limit: max 3 OTPs per 10 minutes
$stmt = $db->prepare("SELECT COUNT(*) FROM otp_codes WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
$stmt->execute([$email]);
if ((int)$stmt->fetchColumn() >= 3) {
    jsonResponse(false, 'Too many OTP requests. Please wait 10 minutes before trying again.');
}

$otp     = generateOTP();
$expires = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));

$db->prepare("UPDATE otp_codes SET is_used = 1 WHERE email = ?")->execute([$email]);
$db->prepare("INSERT INTO otp_codes (email, otp_code, delivery_method, expires_at) VALUES (?, ?, ?, ?)")
   ->execute([$email, $otp, $method, $expires]);

if ($method === 'sms') {
    $result = sendOtpSMS($phone, $otp);
} else {
    $result = sendOtpEmail($email, $name, $otp);
}

if (!$result['success']) {
    jsonResponse(false, 'Failed to resend OTP: ' . $result['message']);
}

auditLog(null, 'otp_resent', "Email: {$email}, Method: {$method}");
jsonResponse(true, 'OTP resent successfully.');
