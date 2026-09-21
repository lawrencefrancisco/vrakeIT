<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');
startSecureSession();

$user = getLoggedInUser();
if (!$user || $user['role'] !== 'enforcer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
try {
    // Fetch all active reports (pending or reviewing)
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email
        FROM reports r
        JOIN users u ON r.user_id = u.id
        WHERE r.status IN ('pending', 'reviewing')
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format dates and attach media
    $mediaStmt = $db->prepare("SELECT file_path FROM report_media WHERE report_id = ?");
    foreach ($reports as &$r) {
        $r['formatted_date'] = date('M d, Y h:i A', strtotime($r['created_at']));
        $mediaStmt->execute([$r['id']]);
        $r['photos'] = array_column($mediaStmt->fetchAll(PDO::FETCH_ASSOC), 'file_path');
    }
    unset($r);

    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
