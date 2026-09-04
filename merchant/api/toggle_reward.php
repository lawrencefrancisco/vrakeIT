<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ../rewards.php'); exit; }

$merchant_id = (int)$_SESSION['merchant_id'];
$reward_id   = (int)($_POST['reward_id'] ?? 0);
$action      = $_POST['action'] ?? '';

if (!$reward_id || !in_array($action, ['activate','deactivate'])) {
    header('Location: ../rewards.php?error=' . urlencode('Invalid request.'));
    exit;
}

$db   = getDB();
// Ensure this reward belongs to this merchant
$check = $db->prepare("SELECT id FROM merchant_rewards WHERE id = ? AND merchant_id = ?");
$check->execute([$reward_id, $merchant_id]);
if (!$check->fetch()) { header('Location: ../rewards.php?error=' . urlencode('Reward not found.')); exit; }

$is_active = ($action === 'activate') ? 1 : 0;
$db->prepare("UPDATE merchant_rewards SET is_active = ? WHERE id = ?")->execute([$is_active, $reward_id]);

header('Location: ../rewards.php?msg=' . urlencode($action === 'activate' ? 'Reward activated.' : 'Reward deactivated.'));
exit;
