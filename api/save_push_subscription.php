<?php
/**
 * api/save_push_subscription.php
 * Saves a browser's Web Push subscription to the DB.
 * Called by the enforcer portal after obtaining push permission.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow Cloudflare tunnel

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

// Allow unauthenticated saves for debugging — we'll identify by session if available
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if (!$userId) {
    jsonResponse(false, 'Not logged in. Please login as enforcer first.');
}

$raw = file_get_contents('php://input');
$sub = json_decode($raw, true);

if (empty($sub['endpoint']) || empty($sub['keys']['p256dh']) || empty($sub['keys']['auth'])) {
    jsonResponse(false, 'Invalid subscription object. Got: ' . substr($raw, 0, 200));
}

$db = getDB();

// Upsert — update if endpoint already exists for this user
$stmt = $db->prepare("
    INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        user_id = VALUES(user_id),
        p256dh  = VALUES(p256dh),
        auth    = VALUES(auth)
");

try {
    $stmt->execute([
        $userId,
        $sub['endpoint'],
        $sub['keys']['p256dh'],
        $sub['keys']['auth'],
    ]);
    jsonResponse(true, 'Subscription saved for user ' . $userId);
} catch (Exception $e) {
    jsonResponse(false, 'Could not save subscription: ' . $e->getMessage());
}
