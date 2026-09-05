<?php
// admin/serve_id_image.php
// Securely serves stored ID images to authenticated admins only.
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
startSecureSession();
requireAdminLogin();

$db    = getDB();
$vid   = (int)($_GET['vid'] ?? 0);

if ($vid <= 0) { http_response_code(400); exit('Bad request'); }

$row = $db->prepare("SELECT id_file FROM id_verifications WHERE id = ? LIMIT 1");
$row->execute([$vid]);
$rec = $row->fetch();

if (!$rec || empty($rec['id_file'])) {
    http_response_code(404); exit('Image not found');
}

$path = dirname(__DIR__) . '/private/id_uploads/' . basename($rec['id_file']);

if (!file_exists($path)) {
    http_response_code(404); exit('File not on disk');
}

// Serve image with correct MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $path);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(403); exit('Forbidden');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
