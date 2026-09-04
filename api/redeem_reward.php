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
$rewardId = (int)($_POST['reward_id'] ?? 0);
$db       = getDB();

if (!$rewardId) {
    jsonResponse(false, 'Invalid reward.');
}

// Load reward
$rStmt = $db->prepare("SELECT mr.*, m.business_name, m.id as mid FROM merchant_rewards mr
    JOIN merchants m ON mr.merchant_id = m.id
    WHERE mr.id = ? AND mr.is_active = 1");
$rStmt->execute([$rewardId]);
$reward = $rStmt->fetch();

if (!$reward) {
    jsonResponse(false, 'Reward not found or no longer available.');
}

// Check expiry
if ($reward['expires_at'] && strtotime($reward['expires_at']) < time()) {
    jsonResponse(false, 'This reward has expired.');
}

// Check stock
if ($reward['quantity'] !== null && $reward['quantity'] <= $reward['redeemed_count']) {
    jsonResponse(false, 'This reward is out of stock.');
}

// Load user
$user = getUserById($userId);
if (!$user) {
    jsonResponse(false, 'User not found.');
}

if ($user['points'] < $reward['points_required']) {
    jsonResponse(false, 'Insufficient points. You need ' . $reward['points_required'] . ' pts but only have ' . $user['points'] . ' pts.');
}

// Begin transaction
$db->beginTransaction();
try {
    // Deduct points
    $db->prepare("UPDATE users SET points = points - ? WHERE id = ?")->execute([$reward['points_required'], $userId]);

    // Log transaction
    $db->prepare("INSERT INTO good_citizen_transactions (user_id, points, type, description) VALUES (?, ?, 'spent', ?)")
       ->execute([$userId, $reward['points_required'], 'Redeemed: ' . $reward['reward_name'] . ' @ ' . $reward['business_name']]);

    // Generate unique voucher code
    $code = strtoupper('VCH-' . substr(uniqid(), -6) . '-' . substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Insert voucher
    $db->prepare("INSERT INTO vouchers (voucher_code, user_id, merchant_id, reward_id, expires_at) VALUES (?,?,?,?,?)")
       ->execute([$code, $userId, $reward['mid'], $rewardId, $expiresAt]);

    // Increment redeemed count and decrement stock (quantity)
    $db->prepare("UPDATE merchant_rewards SET redeemed_count = redeemed_count + 1, quantity = CASE WHEN quantity IS NOT NULL THEN quantity - 1 ELSE NULL END WHERE id = ?")->execute([$rewardId]);

    $db->commit();

    $updatedUser = getUserById($userId);
    auditLog($userId, 'reward_redeemed', "Reward ID: {$rewardId}, Code: {$code}");

    jsonResponse(true, 'Reward redeemed successfully!', [
        'voucher_code'    => $code,
        'expires_at'      => $expiresAt,
        'reward_name'     => $reward['reward_name'],
        'business_name'   => $reward['business_name'],
        'remaining_pts'   => $updatedUser['points'],
    ]);
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(false, 'Redemption failed. Please try again.');
}
