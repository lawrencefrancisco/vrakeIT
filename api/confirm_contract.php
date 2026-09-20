<?php
/**
 * api/confirm_contract.php
 * Called when Party 2 clicks "I Agree" on their invite page.
 * Records their consent timestamp and IP — this IS their digital signature.
 */
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
$token  = trim($_POST['token'] ?? '');
$action = $_POST['action'] ?? 'agree'; // 'agree' or 'decline'

if (!$token) {
    jsonResponse(false, 'Missing invite token.');
}

// Find the contract by token
$stmt = $db->prepare("
    SELECT id, reference_number, party1_user_id, party2_user_id, status, invite_expires_at
    FROM contracts WHERE invite_token = ?
");
$stmt->execute([$token]);
$contract = $stmt->fetch();

if (!$contract) {
    jsonResponse(false, 'Invalid or expired invite link.');
}
if ($contract['status'] !== 'waiting') {
    jsonResponse(false, 'This contract has already been ' . $contract['status'] . '.');
}
if (strtotime($contract['invite_expires_at']) < time()) {
    jsonResponse(false, 'This invite link has expired (valid for 7 days). Ask Party 1 to resubmit.');
}
// Ensure the logged-in user IS Party 2
if ((int)$contract['party2_user_id'] !== $userId) {
    jsonResponse(false, 'You are not the invited party for this contract.');
}

if ($action === 'agree') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $db->prepare("
        UPDATE contracts
        SET status = 'signed',
            p2_signed_at  = NOW(),
            p2_ip_address = ?,
            invite_token  = NULL
        WHERE id = ?
    ")->execute([$ip, $contract['id']]);

    auditLog($userId, 'contract_signed_p2', "Ref: {$contract['reference_number']}");
    jsonResponse(true, 'You have successfully agreed to the contract. It is now signed.', [
        'reference_number' => $contract['reference_number'],
    ]);
} else {
    // Decline — set back to draft so Party 1 can revise
    $db->prepare("
        UPDATE contracts
        SET status = 'draft',
            invite_token  = NULL,
            invite_sent_at = NULL,
            invite_expires_at = NULL
        WHERE id = ?
    ")->execute([$contract['id']]);

    auditLog($userId, 'contract_declined_p2', "Ref: {$contract['reference_number']}");
    jsonResponse(true, 'You have declined the contract. Party 1 will be able to revise it.', [
        'reference_number' => $contract['reference_number'],
    ]);
}
