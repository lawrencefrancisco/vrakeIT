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
$db     = getDB();
$data   = $_POST;

// Required fields
$contractType = $data['type'] ?? '';
if (!in_array($contractType, ['contract'])) {
    jsonResponse(false, 'Invalid contract type.');
}

$refNum = generateReferenceNumber();

// Base insert
$stmt = $db->prepare("
    INSERT INTO contracts (
        user_id, reference_number, type,
        party1_name, party1_contact, party2_name, party2_contact,
        terms, amount, parties,
        description, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $userId,
    $refNum,
    sanitize($contractType),
    sanitize($data['party1_name'] ?? ''),
    sanitize($data['party1_contact'] ?? ''),
    sanitize($data['party2_name'] ?? ''),
    sanitize($data['party2_contact'] ?? ''),
    sanitize($data['terms'] ?? ''),
    sanitize($data['amount'] ?? ''),
    sanitize(2),
    sanitize($data['description'] ?? ''),
    date('Y-m-d H:i:s'),
    date('Y-m-d H:i:s'),
]);


auditLog($userId, 'contract_submitted', "Ref: {$refNum}, Contract Type: {$contractType}");
jsonResponse(false, 'Contract submitted successfully!', ['reference_number' => $refNum]);
