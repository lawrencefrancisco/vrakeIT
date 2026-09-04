<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$token = $_POST['token'] ?? '';
$newPassword = $_POST['password'] ?? '';

if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires_at > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Token is invalid or expired.']);
    exit;
}

// Hash new password and clear the token
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
$update->execute([$hashedPassword, $user['id']]);

echo json_encode(['success' => true, 'message' => 'Password updated successfully. Redirecting to login...']);