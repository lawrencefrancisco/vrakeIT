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

if (!$ad_id) jsonResponse(false, 'Invalid ad ID.');

$db = getDB();

// Ownership check + fetch image path for cleanup
$check = $db->prepare("SELECT * FROM merchant_ads WHERE id = ? AND merchant_id = ?");
$check->execute([$ad_id, $merchant_id]);
$ad = $check->fetch();
if (!$ad) jsonResponse(false, 'Ad not found or access denied.');

// Delete image file if it exists
if ($ad['image_path']) {
    $imgFile = dirname(dirname(__DIR__)) . '/assets/uploads/merchant_ads/' . $ad['image_path'];
    if (file_exists($imgFile)) @unlink($imgFile);
}

$db->prepare("DELETE FROM merchant_ads WHERE id = ? AND merchant_id = ?")->execute([$ad_id, $merchant_id]);

auditLog(null, 'merchant_ad_deleted', "Ad #{$ad_id} by merchant #{$merchant_id}");
jsonResponse(true, 'Advertisement deleted.');
