<?php
// Admin auth helper
require_once __DIR__ . '/db.php';

function requireAdminLogin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php'); exit;
    }
    // Session timeout check — 30 minutes inactivity
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . '/admin/login.php?timeout=1'); exit;
    }
    $_SESSION['last_activity'] = time();


    // Verify role in DB — accept both admin and moderator
    $db   = getDB();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || !in_array($u['role'], ['admin', 'moderator'])) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . '/admin/login.php?denied=1'); exit;
    }

    // Keep session role in sync with DB
    $_SESSION['role'] = $u['role'];
}

function getAdminUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role IN ('admin', 'moderator')");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}