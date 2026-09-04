<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId   = (int)$_SESSION['user_id'];
$idType   = sanitize($_POST['id_type'] ?? '');
$fullName = sanitize($_POST['full_name'] ?? '');
$birthdate = $_POST['birthdate'] ?? '';
$address  = sanitize($_POST['address'] ?? '');
$db       = getDB();

if (!$idType || !$fullName || !$birthdate || !$address) {
    jsonResponse(false, 'All fields are required.');
}

// Check pending/approved verification
$stmt = $db->prepare("SELECT id, status FROM id_verifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$existing = $stmt->fetch();
if ($existing && in_array($existing['status'], ['pending', 'approved'])) {
    jsonResponse(false, 'You already have a ' . $existing['status'] . ' verification request.');
}

// Check if BOTH files were uploaded
if (empty($_FILES['id_file']['name']) || empty($_FILES['selfie_file']['name'])) {
    jsonResponse(false, 'Please upload both your valid ID and a selfie.');
}

$idFile     = $_FILES['id_file'];
$selfieFile = $_FILES['selfie_file'];
$maxSize    = 10 * 1024 * 1024; // 10MB

// Validate ID File
if ($idFile['error'] !== UPLOAD_ERR_OK || $idFile['size'] > $maxSize || !in_array($idFile['type'], ALLOWED_IMAGE_TYPES)) {
    jsonResponse(false, 'Invalid ID file. Must be a JPG/PNG under 10MB.');
}

// Validate Selfie File
if ($selfieFile['error'] !== UPLOAD_ERR_OK || $selfieFile['size'] > $maxSize || !in_array($selfieFile['type'], ALLOWED_IMAGE_TYPES)) {
    jsonResponse(false, 'Invalid Selfie file. Must be a JPG/PNG under 10MB.');
}

$verifyDir = UPLOAD_DIR . 'verifications/';
if (!is_dir($verifyDir)) mkdir($verifyDir, 0755, true);

// Process ID File
$idExt      = pathinfo($idFile['name'], PATHINFO_EXTENSION);
$idFileName = 'id_' . $userId . '_' . time() . '.' . $idExt;
if (!move_uploaded_file($idFile['tmp_name'], $verifyDir . $idFileName)) {
    jsonResponse(false, 'Failed to upload ID file.');
}

// Process Selfie File
$selfieExt      = pathinfo($selfieFile['name'], PATHINFO_EXTENSION);
$selfieFileName = 'selfie_' . $userId . '_' . time() . '.' . $selfieExt;
if (!move_uploaded_file($selfieFile['tmp_name'], $verifyDir . $selfieFileName)) {
    jsonResponse(false, 'Failed to upload Selfie file.');
}

// Insert everything into the database
$db->prepare("INSERT INTO id_verifications (user_id, id_type, full_name, birthdate, address, id_file, selfie_file) VALUES (?,?,?,?,?,?,?)")
   ->execute([$userId, $idType, $fullName, $birthdate, $address, 'verifications/' . $idFileName, 'verifications/' . $selfieFileName]);

auditLog($userId, 'verification_submitted', "ID type: {$idType}");
jsonResponse(true, 'Verification submitted! We will review your request within 1–3 business days.');