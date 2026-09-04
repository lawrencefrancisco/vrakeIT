<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
startSecureSession();
requireAdminLogin();
session_unset();
session_destroy();
header('Location: login.php');
exit;
