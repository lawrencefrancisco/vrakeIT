<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
$merchant = getLoggedInMerchant();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false,'Method not allowed.'); }

$db = getDB();
$mid = $merchant['id'];
$rewardId = (int)($_POST['reward_id'] ?? 0);

// Verify ownership
$check = $db->prepare("SELECT id FROM merchant_rewards WHERE id=? AND merchant_id=?");
$check->execute([$rewardId, $mid]);
if (!$check->fetch()) { jsonResponse(false, 'Reward not found or access denied.'); }

$name = trim($_POST['reward_name'] ?? '');
$desc = trim($_POST['description'] ?? '');
$pts  = (int)($_POST['points_required'] ?? 0);
$qty  = (int)($_POST['quantity'] ?? 0);
$days = (int)($_POST['duration_days'] ?? 0);
$cat  = trim($_POST['category'] ?? 'General');

if (!$name || $pts < 10 || $qty < 1 || $days < 1) {
    jsonResponse(false, 'All fields are required (min 10 pts, 1 stock, 1 day).');
}

$expires = date('Y-m-d H:i:s', strtotime("+{$days} days"));
$db->prepare("UPDATE merchant_rewards SET reward_name=?,description=?,points_required=?,quantity=?,expires_at=?,category=? WHERE id=? AND merchant_id=?")
   ->execute([$name, $desc, $pts, $qty, $expires, $cat, $rewardId, $mid]);

jsonResponse(true, 'Reward updated successfully.');
