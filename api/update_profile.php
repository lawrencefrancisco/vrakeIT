<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId    = (int)$_SESSION['user_id'];
$firstName = sanitize($_POST['first_name'] ?? '');
$lastName  = sanitize($_POST['last_name'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$email     = trim($_POST['email'] ?? '');
$db        = getDB();

if (!$firstName || !$lastName || !$email || !$phone) {
    jsonResponse(false, 'All fields are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email address.');
}
if (!isValidPhone($phone)) {
    jsonResponse(false, 'Invalid Philippine mobile number.');
}

// Check duplicate email (exclude current user)
$stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->execute([$email, $userId]);
if ($stmt->fetch()) {
    jsonResponse(false, 'Email is already used by another account.');
}

// Handle avatar upload
$avatarName = null;
if (!empty($_FILES['avatar']['name'])) {
    $file    = $_FILES['avatar'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        jsonResponse(false, 'Avatar image must be under 5MB.');
    }
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        jsonResponse(false, 'Only JPG, PNG, GIF or WebP images are allowed.');
    }
    $avatarDir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($avatarDir)) {
        mkdir($avatarDir, 0755, true);
    }
    $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
    $avatarName = 'avatar_' . $userId . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $avatarDir . $avatarName)) {
        jsonResponse(false, 'Failed to upload avatar.');
    }
}

if ($avatarName) {
    $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, avatar=? WHERE id=?")
       ->execute([$firstName, $lastName, $email, $phone, $avatarName, $userId]);
} else {
    $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=? WHERE id=?")
       ->execute([$firstName, $lastName, $email, $phone, $userId]);
}
auditLog($userId, 'profile_updated', '');
$user = getUserById($userId);
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

jsonResponse(true, 'Profile updated successfully!', [
    'avatar_url' => getAvatarUrl($user['avatar']),
    'name'       => $_SESSION['user_name'],
]);
