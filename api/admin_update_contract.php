<?php
/**
 * api/admin_update_contract.php
 * Admin-only endpoint to update a contract status (settled, disputed)
 * and optionally log an admin note.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$db     = getDB();
$admin  = getAdminUser();
$ref    = trim($_POST['ref']    ?? '');
$action = trim($_POST['action'] ?? '');
$note   = trim($_POST['note']   ?? '');

if (!$ref || !$action) {
    jsonResponse(false, 'Missing required fields.');
}

$allowed = ['settled', 'fulfilled', 'disputed', 'not_settled'];
if (!in_array($action, $allowed)) {
    jsonResponse(false, 'Invalid action.');
}

// Verify contract exists
$stmt = $db->prepare("SELECT id, status FROM contracts WHERE reference_number = ?");
$stmt->execute([$ref]);
$contract = $stmt->fetch();

if (!$contract) {
    jsonResponse(false, 'Contract not found.');
}

// Build update
$fields = ['status = ?', 'updated_at = NOW()'];
$params = [$action];

if ($note) {
    $fields[] = 'admin_notes = ?';
    $params[]  = $note;
}

if ($action === 'settled' || $action === 'fulfilled') {
    $fields[] = 'settled_at = NOW()';
}

$params[] = $ref;
$sql = 'UPDATE contracts SET ' . implode(', ', $fields) . ' WHERE reference_number = ?';
$db->prepare($sql)->execute($params);

// Audit log
$adminId = $admin['id'] ?? 0;
auditLog($adminId, "admin_contract_{$action}", "Admin updated contract {$ref} to {$action}" . ($note ? ". Note: $note" : ''));

jsonResponse(true, 'Contract updated to ' . ucfirst($action) . '.', [
    'reference_number' => $ref,
    'new_status'       => $action,
]);
