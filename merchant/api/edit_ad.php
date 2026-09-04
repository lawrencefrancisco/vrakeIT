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
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');

if (!$ad_id)    jsonResponse(false, 'Invalid ad ID.');
if (!$title)    jsonResponse(false, 'Ad title is required.');

$db = getDB();

// Ownership check
$check = $db->prepare("SELECT * FROM merchant_ads WHERE id = ? AND merchant_id = ?");
$check->execute([$ad_id, $merchant_id]);
$ad = $check->fetch();
if (!$ad) jsonResponse(false, 'Ad not found or access denied.');

// Handle optional new image upload
$image_path = $ad['image_path']; // Keep existing by default
if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = dirname(dirname(__DIR__)) . '/assets/uploads/merchant_ads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $file    = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];

    if ($file['size'] > 5 * 1024 * 1024) jsonResponse(false, 'Image must be under 5MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) jsonResponse(false, 'Only JPG, PNG, or WebP images are allowed.');

    $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = uniqid('ad_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $new_filename)) {
        jsonResponse(false, 'Could not save image. Check folder permissions.');
    }

    // Delete old image if it exists
    if ($ad['image_path'] && file_exists($uploadDir . $ad['image_path'])) {
        @unlink($uploadDir . $ad['image_path']);
    }
    $image_path = $new_filename;
}

$stmt = $db->prepare("UPDATE merchant_ads SET title = ?, description = ?, image_path = ? WHERE id = ? AND merchant_id = ?");
$stmt->execute([$title, $description, $image_path, $ad_id, $merchant_id]);

auditLog(null, 'merchant_ad_updated', "Ad #{$ad_id} by merchant #{$merchant_id}");
jsonResponse(true, 'Advertisement updated successfully!');
