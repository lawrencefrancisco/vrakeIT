<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/merchant_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireMerchantLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$merchant_id  = (int)$_SESSION['merchant_id'];
$title        = trim($_POST['title']       ?? '');
$description  = trim($_POST['description'] ?? '');

if (empty($title)) jsonResponse(false, 'Ad title is required.');

$image_path = null;
if (!empty($_FILES['image']['name'])) {
    $uploadDir = dirname(dirname(__DIR__)) . '/assets/uploads/merchant_ads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $file    = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) jsonResponse(false, 'Image upload failed.');
    if ($file['size'] > 5 * 1024 * 1024) jsonResponse(false, 'Image must be under 5MB.');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) jsonResponse(false, 'Only JPG, PNG, or WebP images are allowed.');

    $ext        = pathinfo($file['name'], PATHINFO_EXTENSION);
    $image_path = uniqid('ad_', true) . '.' . strtolower($ext);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $image_path)) {
        jsonResponse(false, 'Could not save image. Check folder permissions.');
    }
}

$db   = getDB();
$stmt = $db->prepare("INSERT INTO merchant_ads (merchant_id, title, description, image_path) VALUES (?,?,?,?)");
$stmt->execute([$merchant_id, $title, $description, $image_path]);

auditLog(null, 'merchant_ad_added', "Ad: {$title} by merchant #{$merchant_id}");
jsonResponse(true, 'Advertisement published!');
