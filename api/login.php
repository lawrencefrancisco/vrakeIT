<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$email     = trim($_POST['email'] ?? '');
$password  = trim($_POST['password'] ?? '');
$loginType = $_POST['login_type'] ?? 'user';

if (empty($email) || empty($password)) {
    jsonResponse(false, 'Email and password are required.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    auditLog(null, 'login_failed', "Email: {$email}");
    jsonResponse(false, 'Invalid email or password.');
}

// Block deactivated accounts
if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
    auditLog($user['id'], 'login_blocked_deactivated', "Email: {$email}");
    jsonResponse(false, 'Your account has been deactivated. Please contact the administrator.');
}

$userRole = $user['role'] ?? 'user';
if ($userRole !== $loginType) {
    auditLog(null, 'login_failed_role', "Email: {$email}, Expected: {$loginType}, Got: {$userRole}");
    jsonResponse(false, 'Unauthorized access. Please use the correct portal.');
}

loginUser($user);
auditLog($user['id'], 'login_success', "Email: {$email}");

$redirectUrl = ($userRole === 'enforcer') ? BASE_URL . '/enforcer_landing.php' : BASE_URL . '/landing.php';
jsonResponse(true, 'Login successful.', ['redirect' => $redirectUrl]);
