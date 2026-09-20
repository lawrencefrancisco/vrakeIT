<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.');
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM contracts WHERE party1_user_id = ? OR party2_user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId, $userId]);
$contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contracts as &$c) {
    $c['formatted_date'] = date('M d, Y h:i A', strtotime($c['created_at']));

    $c['amount'] = is_numeric($c['amount']) ? $c['amount'] : null;
}

jsonResponse(true, 'Contracts fetched.', ['contracts' => $contracts]);