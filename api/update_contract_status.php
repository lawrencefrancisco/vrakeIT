<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId = (int)$_SESSION['user_id'];
$ref    = trim($_POST['reference_number'] ?? '');
$status = trim($_POST['status'] ?? '');

$allowed = ['settled', 'not_settled', 'disputed'];

if (!$ref || !in_array($status, $allowed, true)) {
    jsonResponse(false, 'Invalid request.');
}

$db = getDB();

// Make sure the contract belongs to this user before updating
$stmt = $db->prepare("SELECT id FROM contracts WHERE reference_number = ? AND (party1_user_id = ? OR party2_user_id = ?)");
$stmt->execute([$ref, $userId, $userId]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    jsonResponse(false, 'Contract not found.');
}

$update = $db->prepare("UPDATE contracts SET status = ?, updated_at = NOW() WHERE reference_number = ? AND (party1_user_id = ? OR party2_user_id = ?)");
$update->execute([$status, $ref, $userId, $userId]);

jsonResponse(true, 'Contract status updated.');