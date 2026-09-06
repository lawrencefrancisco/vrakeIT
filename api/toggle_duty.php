<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$user = getLoggedInUser();
if ($user['role'] !== 'enforcer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$is_on_duty = (isset($_POST['is_on_duty']) && $_POST['is_on_duty'] === '1') ? 1 : 0;
$userId = (int)$user['id'];

$db = getDB();
try {
    $stmt = $db->prepare("UPDATE users SET is_on_duty = ? WHERE id = ?");
    $stmt->execute([$is_on_duty, $userId]);
    
    echo json_encode(['success' => true, 'is_on_duty' => $is_on_duty]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
