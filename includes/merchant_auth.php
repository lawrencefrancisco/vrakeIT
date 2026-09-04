<?php
// Merchant auth helpers — add to includes/auth.php

function requireMerchantLogin(): void {
    startSecureSession();
    if (empty($_SESSION['merchant_id'])) {
        header('Location: ' . BASE_URL . '/merchant/login.php');
        exit;
    }
    if (isset($_SESSION['merchant_last_activity']) && (time() - $_SESSION['merchant_last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/merchant/login.php?timeout=1');
        exit;
    }
    $_SESSION['merchant_last_activity'] = time();
}

function requireMerchantGuest(): void {
    startSecureSession();
    if (!empty($_SESSION['merchant_id'])) {
        header('Location: ' . BASE_URL . '/merchant/home.php');
        exit;
    }
}

function getLoggedInMerchant(): ?array {
    if (empty($_SESSION['merchant_id'])) return null;
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM merchants WHERE id = ?");
    $stmt->execute([$_SESSION['merchant_id']]);
    return $stmt->fetch() ?: null;
}

function loginMerchant(array $merchant): void {
    session_regenerate_id(true);
    $_SESSION['merchant_id']            = $merchant['id'];
    $_SESSION['merchant_name']          = $merchant['business_name'];
    $_SESSION['merchant_last_activity'] = time();
}
