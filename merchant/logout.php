<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/merchant_auth.php';
startSecureSession();
// Only destroy merchant-specific session keys
unset($_SESSION['merchant_id'], $_SESSION['merchant_name'], $_SESSION['merchant_last_activity']);
// If no driver session either, destroy entirely
if (empty($_SESSION['user_id'])) {
    session_destroy();
}
header('Location: login.php');
exit;
