<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../rewards.php'); exit; }

$merchant_id  = (int)$_SESSION['merchant_id'];
$reward_id    = (int)($_POST['reward_id']    ?? 0);
$add_quantity = (int)($_POST['add_quantity'] ?? 0);
$add_duration = (int)($_POST['add_duration'] ?? 0);

if (!$reward_id) { header('Location: ../rewards.php?error=' . urlencode('Invalid reward.')); exit; }

$db    = getDB();
$check = $db->prepare("SELECT id, expires_at FROM merchant_rewards WHERE id = ? AND merchant_id = ?");
$check->execute([$reward_id, $merchant_id]);
$reward = $check->fetch();
if (!$reward) { header('Location: ../rewards.php?error=' . urlencode('Reward not found.')); exit; }

$updates = [];
$params  = [];

if ($add_quantity > 0) {
    $updates[] = "quantity = quantity + ?";
    $params[]  = $add_quantity;
}
if ($add_duration > 0) {
    // Extend from current expiry or from now, whichever is later
    $base       = ($reward['expires_at'] && strtotime($reward['expires_at']) > time()) ? $reward['expires_at'] : 'now';
    $new_expiry = date('Y-m-d H:i:s', strtotime("+{$add_duration} days", strtotime($base)));
    $updates[]  = "expires_at = ?";
    $params[]   = $new_expiry;
}

if (empty($updates)) { header('Location: ../rewards.php?error=' . urlencode('Nothing to update.')); exit; }

$params[] = $reward_id;
$db->prepare("UPDATE merchant_rewards SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);

header('Location: ../rewards.php?msg=' . urlencode('Reward replenished successfully.'));
exit;
