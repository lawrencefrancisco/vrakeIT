<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    auditLog((int)$userId, 'logout', '');
}
logoutUser();
jsonResponse(true, 'Logged out.', ['redirect' => BASE_URL . '/index.php']);
