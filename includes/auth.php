<?php
require_once __DIR__ . '/db.php';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', 1800); // 30 minutes

        session_set_cookie_params([
            'lifetime' => 1800, // 30 minutes
            'path'     => '/',
            'secure'   => false, // change to true if HTTPS
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }
}

function requireLogin(): void {
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    // Session timeout check — 30 minutes inactivity
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireGuest(): void {
    startSecureSession();
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/landing.php');
        exit;
    }
}

function getLoggedInUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        return $user;
    } else {
        // FAILSAFE: The session exists, but the user is gone from the database.
        // We use your existing logoutUser() function to clean up.
        logoutUser(); 
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function loginUser(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['user_name']    = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email']   = $user['email'];
    $_SESSION['last_activity'] = time();
}

function logoutUser(): void {
    session_unset();
    session_destroy();
}


