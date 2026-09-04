<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$submittedOtp = trim($_POST['otp'] ?? '');

if (empty($submittedOtp) || !ctype_digit($submittedOtp) || strlen($submittedOtp) !== OTP_LENGTH) {
    jsonResponse(false, 'Please enter a valid 6-digit OTP.');
}

if (empty($_SESSION['pending_reg'])) {
    jsonResponse(false, 'Registration session expired. Please register again.');
}

$reg   = $_SESSION['pending_reg'];
$email = $reg['email'];
$db    = getDB();

// Look up OTP
$stmt = $db->prepare("SELECT * FROM otp_codes WHERE email = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email]);
$record = $stmt->fetch();

if (!$record) {
    jsonResponse(false, 'No valid OTP found. Please request a new one.');
}

if (new DateTime() > new DateTime($record['expires_at'])) {
    jsonResponse(false, 'OTP has expired. Please request a new one.');
}

if ($record['otp_code'] !== $submittedOtp) {
    jsonResponse(false, 'Incorrect OTP. Please try again.');
}

$role = $reg['role'] ?? 'user';

// Create user
$stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, account_verified, role) VALUES (?, ?, ?, ?, ?, 0, ?)");
$stmt->execute([$reg['first_name'], $reg['last_name'], $email, $reg['phone'], $reg['password'], $role]);
$userId = (int)$db->lastInsertId();

// THE MEMORY BANK: Save a backup of this new VERIFIED user
$backupFile = __DIR__ . '/../users_backup.json';

// 1. Read existing backups (if the file exists)
$savedUsers = [];
if (file_exists($backupFile)) {
    $savedUsers = json_decode(file_get_contents($backupFile), true);
}

// 2. Add the newly verified user to the list
// We pull their data directly from the pending registration session!
$savedUsers[] = [
    'first'    => $_SESSION['pending_reg']['first_name'], 
    'last'     => $_SESSION['pending_reg']['last_name'], 
    'email'    => $_SESSION['pending_reg']['email'], 
    'phone'    => $_SESSION['pending_reg']['phone'],
    'password' => $_SESSION['pending_reg']['password'], // This was already hashed in register.php
    'points'   => 0,
    'verified' => 0, // We set this to 1 because they just passed the OTP!
    'role'     => $role
];

// 3. Save the file back to the server
file_put_contents($backupFile, json_encode($savedUsers, JSON_PRETTY_PRINT));

// Mark OTP used
$db->prepare("UPDATE otp_codes SET is_used = 1 WHERE id = ?")->execute([$record['id']]);

// Create session
$user = getUserById($userId);
loginUser($user);

// Store birthdate in session for OCR matching (from pending_reg if provided)
if (!empty($reg['birthdate'])) {
    $_SESSION['reg_birthdate'] = $reg['birthdate'];
}
$_SESSION['just_registered'] = true;
unset($_SESSION['pending_reg']);

auditLog($userId, 'account_created', "Email: {$email}");

// Direct users to OCR identity verification; enforcers go directly to their portal
if ($role === 'enforcer') {
    jsonResponse(true, 'Account created successfully!', ['redirect' => BASE_URL . '/enforcer_landing.php']);
} else {
    jsonResponse(true, 'Account created successfully!', ['redirect' => BASE_URL . '/verify.php']);
}
