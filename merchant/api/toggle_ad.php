<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$merchant_id = (int)$_SESSION['merchant_id'];
$ad_id       = (int)($_POST['ad_id'] ?? 0);
$action      = $_POST['action'] ?? '';

if (!$ad_id || !in_array($action, ['activate', 'deactivate'])) {
    jsonResponse(false, 'Invalid request.');
}

$db    = getDB();
$check = $db->prepare("SELECT id FROM merchant_ads WHERE id = ? AND merchant_id = ?");
$check->execute([$ad_id, $merchant_id]);
if (!$check->fetch()) jsonResponse(false, 'Ad not found.');

$is_active = ($action === 'activate') ? 1 : 0;
$db->prepare("UPDATE merchant_ads SET is_active = ? WHERE id = ?")->execute([$is_active, $ad_id]);

jsonResponse(true, $action === 'activate' ? 'Ad activated.' : 'Ad deactivated.');
