<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$merchant_id  = (int)$_SESSION['merchant_id'];
$voucher_code = strtoupper(trim($_POST['voucher_code'] ?? ''));

if (empty($voucher_code)) jsonResponse(false, 'Please enter a voucher code.');

$db = getDB();

try {
    $db->beginTransaction();

    // Lock the row for transaction safety
    $stmt = $db->prepare("SELECT v.id, v.status, v.merchant_id, v.expires_at, r.reward_name FROM vouchers v JOIN merchant_rewards r ON v.reward_id = r.id WHERE v.voucher_code = ? FOR UPDATE");
    $stmt->execute([$voucher_code]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        $db->rollBack();
        jsonResponse(false, 'Invalid voucher code. Please double-check and try again.');
    }

    if ((int)$voucher['merchant_id'] !== $merchant_id) {
        $db->rollBack();
        jsonResponse(false, 'This voucher is not valid at your store.');
    }

    if ($voucher['status'] === 'redeemed') {
        $db->rollBack();
        jsonResponse(false, 'This voucher has already been redeemed.');
    }

    if ($voucher['status'] === 'expired') {
        $db->rollBack();
        jsonResponse(false, 'This voucher has expired.');
    }

    // Time-based expiry check
    if ($voucher['expires_at'] && strtotime($voucher['expires_at']) < time()) {
        $db->prepare("UPDATE vouchers SET status = 'expired' WHERE id = ?")->execute([$voucher['id']]);
        $db->commit();
        jsonResponse(false, 'This voucher has expired (past the 7-day window).');
    }

    // Mark as redeemed
    $db->prepare("UPDATE vouchers SET status = 'redeemed', redeemed_at = NOW() WHERE id = ?")->execute([$voucher['id']]);
    $db->commit();

    auditLog(null, 'voucher_redeemed', "Code: {$voucher_code} at merchant #{$merchant_id}");
    jsonResponse(true, "Success! Voucher {$voucher_code} redeemed for: {$voucher['reward_name']}");

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Voucher Validation Error: " . $e->getMessage());
    jsonResponse(false, 'System error during validation. Please try again.');
}
