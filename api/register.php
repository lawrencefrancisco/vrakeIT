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

$firstName  = sanitize($_POST['first_name'] ?? '');
$lastName   = sanitize($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$birthdate  = trim($_POST['birthdate'] ?? '');
$password   = $_POST['password'] ?? '';
$delivery   = $_POST['delivery_method'] ?? 'sms'; // 'sms' or 'email'
$agreedTos  = $_POST['agree_tos'] ?? '0';
$role       = $_POST['role'] ?? 'user';

if (!in_array($role, ['user', 'enforcer'])) {
    $role = 'user';
}

// Validate
if (!$firstName || !$lastName || !$email || !$phone || !$password) {
    jsonResponse(false, 'All fields are required.');
}
if ($birthdate && !DateTime::createFromFormat('Y-m-d', $birthdate)) {
    jsonResponse(false, 'Invalid date of birth.');
}
// Basic age check: must be at least 16
if ($birthdate) {
    $age = (new DateTime($birthdate))->diff(new DateTime())->y;
    if ($age < 16) {
        jsonResponse(false, 'You must be at least 16 years old to register.');
    }
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}
if (!isValidPhone($phone)) {
    jsonResponse(false, 'Invalid Philippine mobile number (e.g. 09XXXXXXXXX).');
}
if (strlen($password) < 8) {
    jsonResponse(false, 'Password must be at least 8 characters.');
}
if ($agreedTos !== '1') {
    jsonResponse(false, 'You must agree to the Terms and Conditions.');
}
if (!in_array($delivery, ['sms', 'email'])) {
    jsonResponse(false, 'Invalid OTP delivery method.');
}

$db = getDB();

// Check duplicate email
// Check for duplicate email or phone number
$stmt = $db->prepare("SELECT email, phone FROM users WHERE email = ? OR phone = ?");
$stmt->execute([$email, $phone]);
$existingUser = $stmt->fetch();

if ($existingUser) {
    if ($existingUser['email'] === $email) {
        jsonResponse(false, 'An account with this email already exists.');
    } else {
        jsonResponse(false, 'This mobile number is already registered.');
    }
}



// Generate OTP
$otp     = generateOTP();
$expires = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));

// Invalidate old OTPs for this email
$db->prepare("UPDATE otp_codes SET is_used = 1 WHERE email = ?")->execute([$email]);

// Store OTP
$db->prepare("INSERT INTO otp_codes (email, otp_code, delivery_method, expires_at) VALUES (?, ?, ?, ?)")
   ->execute([$email, $otp, $delivery, $expires]);

// Send OTP
if ($delivery === 'sms') {
    $result = sendOtpSMS($phone, $otp);
} else {
    $result = sendOtpEmail($email, $firstName, $otp);
}

if (!$result['success']) {
    jsonResponse(false, 'Failed to send OTP: ' . $result['message']);
}

// Store pending registration data in session
$_SESSION['pending_reg'] = [
    'first_name'      => $firstName,
    'last_name'       => $lastName,
    'email'           => $email,
    'phone'           => $phone,
    'birthdate'       => $birthdate,
    'password'        => password_hash($password, PASSWORD_BCRYPT),
    'delivery_method' => $delivery,
    'role'            => $role,
];

auditLog(null, 'otp_sent', "Email: {$email}, Method: {$delivery}");
jsonResponse(true, 'OTP sent successfully.', [
    'redirect'        => BASE_URL . '/otp.php',
    'delivery_method' => $delivery,
    'masked_target'   => $delivery === 'sms'
        ? preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $phone)
        : preg_replace('/(.{2}).+(@.+)/', '$1****$2', $email),
]);
