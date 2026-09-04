<?php
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(false, 'Method not allowed.');

$business_name    = trim($_POST['business_name']    ?? '');
$business_type    = trim($_POST['business_type']    ?? '');
$business_address = trim($_POST['business_address'] ?? '');
$contact_number   = trim($_POST['contact_number']   ?? '');
$email            = trim($_POST['email']             ?? '');
$password         = trim($_POST['password']          ?? '');

if (empty($business_name) || empty($business_type) || empty($business_address) ||
    empty($contact_number) || empty($email) || empty($password)) {
    jsonResponse(false, 'All required fields must be filled in.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
if (strlen($password) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
if (!isValidPhone($contact_number)) jsonResponse(false, 'Enter a valid Philippine phone number (e.g. 09XXXXXXXXX).');

// Check duplicate email
$db   = getDB();
$stmt = $db->prepare("SELECT id FROM merchants WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) jsonResponse(false, 'An account with this email already exists.');

// Business Permit — required
if (empty($_FILES['permit']['name'])) jsonResponse(false, 'Business permit is required.');

$uploadDir = dirname(dirname(__DIR__)) . '/assets/uploads/merchants/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function uploadMerchantFile(array $file, string $dir, int $maxSize, array $allowed): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxSize) return false;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed)) return false;
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('mrch_', true) . '.' . strtolower($ext);
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return false;
    return $filename;
}

$permitAllowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$permit_path   = uploadMerchantFile($_FILES['permit'], $uploadDir, 10 * 1024 * 1024, $permitAllowed);
if (!$permit_path) jsonResponse(false, 'Permit upload failed. Use JPG, PNG, or PDF under 10MB.');

$logo_path = null;
if (!empty($_FILES['logo']['name'])) {
    $logoAllowed = ['image/jpeg', 'image/png', 'image/webp'];
    $logo_path   = uploadMerchantFile($_FILES['logo'], $uploadDir, 5 * 1024 * 1024, $logoAllowed);
    if (!$logo_path) { // non-fatal, just skip
        $logo_path = null;
    }
}

$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $db->prepare("INSERT INTO merchants (business_name, business_type, business_address, contact_number, email, password, permit, logo, status) VALUES (?,?,?,?,?,?,?,?,'pending')");
    $success = $stmt->execute([$business_name, $business_type, $business_address, $contact_number, $email, $hashed, $permit_path, $logo_path]);
    
    // If the database refuses to save, grab the exact error and send it to the frontend!
    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        jsonResponse(false, "Database rejected data: " . $errorInfo[2]);
    }

    auditLog(null, 'merchant_registered', "Business: {$business_name}");
    jsonResponse(true, 'Application submitted! Please wait for admin approval before logging in.');

} catch (PDOException $e) {
    // If PDO throws a fatal exception, catch it and show it on the frontend
    jsonResponse(false, "Fatal DB Error: " . $e->getMessage());
}
