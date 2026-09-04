<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) jsonResponse(false, 'Email and password are required.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM merchants WHERE email = ?");
$stmt->execute([$email]);
$merchant = $stmt->fetch();

if (!$merchant || !password_verify($password, $merchant['password'])) {
    auditLog(null, 'merchant_login_failed', "Email: {$email}");
    jsonResponse(false, 'Invalid email or password.');
}

if ($merchant['status'] === 'pending') {
    jsonResponse(false, 'Your application is still pending admin approval. Please check back later.');
}
if ($merchant['status'] === 'rejected') {
    jsonResponse(false, 'Your merchant application was rejected. Please contact the administrator.');
}
if ($merchant['status'] === 'deactivated') {
    jsonResponse(false, 'This merchant account has been deactivated. Please contact the administrator.');
}

loginMerchant($merchant);
auditLog(null, 'merchant_login_success', "Business: {$merchant['business_name']}");
jsonResponse(true, 'Login successful.', ['redirect' => BASE_URL . '/merchant/home.php']);
