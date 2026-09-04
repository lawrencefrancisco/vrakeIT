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

// 1. Fetch only the reports (No complex JOINs to cause duplicate ghosts)
$stmt = $db->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Loop through and strictly attach ONLY the specific media for each report
foreach ($reports as &$r) {
    $r['formatted_date'] = date('M d, Y h:i A', strtotime($r['created_at']));
    $r['flow_label'] = match($r['flow_type']) {
        'first'        => 'With Injury / Law Enforcer',
        'second'       => 'No Injury — Full Report',
        'good_citizen' => 'Good Citizen Report',
        default        => 'Report',
    };

    // Fetch exact media for this specific report ID
    $mediaStmt = $db->prepare("SELECT file_path FROM report_media WHERE report_id = ?");
    $mediaStmt->execute([$r['id']]);
    $mediaFiles = $mediaStmt->fetchAll(PDO::FETCH_COLUMN);

    // Overwrite any old database junk with a clean array of the correct images
    $r['media_urls'] = $mediaFiles;
}

jsonResponse(true, 'Reports fetched.', ['reports' => $reports]);