<?php
// ======================================================================
// api/ocr_verify_id.php
// Secure OCR identity verification endpoint.
// Called by:
//   - id_verify.php  (source=registration, post-OTP new user flow)
//   - verify.php     (source=portal, any logged-in unverified user)
// ======================================================================

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/OcrProvider.php';

startSecureSession();
header('Content-Type: application/json');

// ── Auth: user must be logged in (just created via verify_otp.php)
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$db     = getDB();

// ── Source: 'registration' (id_verify.php) or 'portal' (verify.php)
$source = (($_POST['source'] ?? '') === 'portal') ? 'portal' : 'registration';

// ── Retrieve user for name-matching
$userStmt = $db->prepare("SELECT first_name, last_name, verification_status FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user) {
    jsonResponse(false, 'User not found.');
}

// ── Guard: already verified
if ($user['verification_status'] === 'Verified') {
    jsonResponse(true, 'Your account is already verified.', ['status' => 'Verified', 'already_verified' => true]);
}



// ── Check that a file was uploaded
if (empty($_FILES['id_image']['name'])) {
    jsonResponse(false, 'Please upload an image of your government-issued ID.');
}

$file    = $_FILES['id_image'];
$maxSize = OCR_MAX_FILE_SIZE;

// ── Basic PHP upload error check
if ($file['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    ];
    jsonResponse(false, $uploadErrors[$file['error']] ?? 'Upload failed. Please try again.');
}

// ── Size check
if ($file['size'] > $maxSize) {
    jsonResponse(false, 'File is too large. Maximum size is 10 MB.');
}

// ── MIME validation using finfo (never trust $_FILES['type'])
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($realMime, $allowedMimes, true)) {
    jsonResponse(false, 'Invalid file type. Please upload a JPG, PNG, or WebP image.');
}

// ── Ensure private upload directory exists
if (!is_dir(ID_UPLOAD_DIR)) {
    mkdir(ID_UPLOAD_DIR, 0750, true);
}

// ── Generate secure filename
$token    = bin2hex(random_bytes(16));
$ext      = match($realMime) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename    = "id_{$userId}_{$token}.{$ext}";
$storagePath = ID_UPLOAD_DIR . $filename;

// ── Move to private directory
if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
    jsonResponse(false, 'Failed to save uploaded file. Please try again.');
}

// ── Duplicate document detection via SHA-256 hash
$imageHash = hash_file('sha256', $storagePath);
$hashCheck = $db->prepare(
    "SELECT id FROM id_verifications WHERE image_hash = ? AND user_id != ? LIMIT 1"
);
$hashCheck->execute([$imageHash, $userId]);
if ($hashCheck->fetch()) {
    @unlink($storagePath);
    jsonResponse(false, 'This document has already been used for another account. Please use your own valid ID.');
}



// ── Retrieve registration birthdate:
//    1. From session (set immediately after OTP verification — registration flow)
//    2. From POST body (portal flow — returning user entered it on verify.php)
//    3. From id_verifications table (previously submitted birthdate)
$regBirthdate = $_SESSION['reg_birthdate'] ?? '';
if ($regBirthdate === '' && isset($_POST['birthdate']) && $_POST['birthdate'] !== '') {
    $regBirthdate = trim($_POST['birthdate']);
}
if ($regBirthdate === '') {
    // Last resort: pull from most recent id_verifications record
    $dobFallback = $db->prepare(
        "SELECT birthdate FROM id_verifications WHERE user_id = ? AND birthdate IS NOT NULL ORDER BY created_at DESC LIMIT 1"
    );
    $dobFallback->execute([$userId]);
    $dobRow = $dobFallback->fetch();
    if ($dobRow) $regBirthdate = $dobRow['birthdate'];
}

$registrationData = [
    'first_name' => $user['first_name'],
    'last_name'  => $user['last_name'],
    'birthdate'  => $regBirthdate,
];

// ── Run OCR extraction
$extracted = OcrProvider::extractFromImage($storagePath);

// ── Validate document structure/integrity
$docValidation = OcrProvider::validateDocument($extracted);

if (!$docValidation['valid']) {
    // Store failed attempt
    $db->prepare(
        "INSERT INTO id_verifications
         (user_id, status, source, ocr_document_valid, ocr_failure_reason, image_hash)
         VALUES (?, 'rejected', ?, 0, ?, ?)"
    )->execute([$userId, $source, $docValidation['reason'], $imageHash]);

    // Update user verification status
    $db->prepare(
        "UPDATE users SET verification_status = 'Unverified' WHERE id = ?"
    )->execute([$userId]);

    auditLog($userId, 'ocr_verification_failed', "Reason: {$docValidation['reason']}");

    @unlink($storagePath); // Delete image after processing

    $extractedFields = buildExtractedFields($extracted, []);
    jsonResponse(false, buildDocFailureMessage($docValidation['reason']), [
        'status'            => 'Unverified',
        'extracted_fields'  => $extractedFields,
        'failure_detail'    => [
            'reason'     => 'document_invalid',
            'detail'     => $docValidation['reason'],
            'ocr_engine' => $extracted['ocr_engine'] ?? 'unknown',
        ],
    ]);
}

// ── Match identity against registration data
$matchResult = OcrProvider::matchIdentity($extracted, $registrationData);

// ── Build audit details (no raw OCR text exposed to client)
$ocrDocNumber = $extracted['doc_number'] ?? '';
$ocrReference = 'OCR-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

// ── Determine verification outcome
if ($matchResult['overall_match']) {
    // ━━ SUCCESS ━━
    // Update user to Verified
    $db->prepare(
        "UPDATE users
         SET account_verified = 1,
             verification_status   = 'Verified',
             verified_at           = NOW(),
             verification_method   = 'ocr_auto',
             verification_reference = ?
         WHERE id = ?"
    )->execute([$ocrReference, $userId]);

    // Store verification record
    $db->prepare(
        "INSERT INTO id_verifications
         (user_id, status, source, id_type, full_name,
          ocr_extracted_name, ocr_extracted_dob, ocr_extracted_doc_number, ocr_extracted_expiry,
          ocr_confidence_score, ocr_document_valid, ocr_name_match, ocr_dob_match,
          ocr_reference, image_hash, birthdate)
         VALUES (?, 'verified', ?, ?, ?,
                 ?, ?, ?, ?,
                 ?, 1, ?, ?,
                 ?, ?, ?)"
    )->execute([
        $userId,
        $source,
        $extracted['doc_type'],
        $user['first_name'] . ' ' . $user['last_name'],
        $extracted['name'],
        $extracted['dob'],
        $ocrDocNumber,
        $extracted['expiry'],
        round($extracted['confidence'], 4),
        $matchResult['name_match'] ? 1 : 0,
        $matchResult['dob_match'] ? 1 : 0,
        $ocrReference,
        $imageHash,
        $regBirthdate ?: null,
    ]);

    auditLog($userId, 'ocr_verification_success', "Ref: {$ocrReference}, Score: {$matchResult['score']}");

    @unlink($storagePath); // Delete image after processing — per data retention policy

    // Clear session flags
    unset($_SESSION['reg_birthdate']);

    jsonResponse(true, 'Identity verified successfully. Your account has been verified.', [
        'status'           => 'Verified',
        'reference'        => $ocrReference,
        'redirect'         => BASE_URL . '/landing.php',
        'extracted_fields' => buildExtractedFields($extracted, $matchResult),
        'failure_detail'   => null,
    ]);

} else {
    // ━━ MISMATCH ━━
    $failReason = buildMismatchReason($matchResult);

    $db->prepare(
        "INSERT INTO id_verifications
         (user_id, status, source, id_type, full_name,
          ocr_extracted_name, ocr_extracted_dob, ocr_extracted_doc_number,
          ocr_confidence_score, ocr_document_valid, ocr_name_match, ocr_dob_match,
          ocr_failure_reason, ocr_reference, image_hash, birthdate)
         VALUES (?, 'rejected', ?, ?, ?,
                 ?, ?, ?,
                 ?, 1, ?, ?,
                 ?, ?, ?, ?)"
    )->execute([
        $userId,
        $source,
        $extracted['doc_type'],
        $user['first_name'] . ' ' . $user['last_name'],
        $extracted['name'],
        $extracted['dob'],
        $ocrDocNumber,
        round($extracted['confidence'], 4),
        $matchResult['name_match'] ? 1 : 0,
        $matchResult['dob_match'] ? 1 : 0,
        $failReason,
        $ocrReference,
        $imageHash,
        $regBirthdate ?: null,
    ]);

    $db->prepare(
        "UPDATE users SET verification_status = 'Unverified' WHERE id = ?"
    )->execute([$userId]);

    auditLog($userId, 'ocr_verification_mismatch', "Ref: {$ocrReference}, Name: {$matchResult['name_match']}, DOB: {$matchResult['dob_match']}");

    @unlink($storagePath); // Delete image after processing

    jsonResponse(false, buildUserFacingError($matchResult), [
        'status'             => 'Unverified',
        'extracted_fields'   => buildExtractedFields($extracted, $matchResult),
        'failure_detail'     => [
            'reason'      => 'identity_mismatch',
            'name_match'  => $matchResult['name_match'],
            'dob_match'   => $matchResult['dob_match'],
            'name_score'  => $matchResult['name_score'] ?? 0,
            'ocr_engine'  => $extracted['ocr_engine'] ?? 'unknown',
        ],
    ]);
}

// ─────────────────────────────────────────────────────────────────────
// Helper: build extracted fields for frontend display
// ─────────────────────────────────────────────────────────────────────
function buildExtractedFields(array $extracted, array $matchResult): array
{
    return [
        'doc_type'   => $extracted['doc_type']   ?? '',
        'name'       => $extracted['name']       ?? '',
        'dob'        => $extracted['dob']        ?? '',
        'doc_number' => $extracted['doc_number'] ?? '',
        'expiry'     => $extracted['expiry']     ?? '',
        'confidence' => round(($extracted['confidence'] ?? 0) * 100),
        'ocr_engine' => $extracted['ocr_engine'] ?? 'unknown',
        'name_match' => $matchResult['name_match'] ?? null,
        'dob_match'  => $matchResult['dob_match']  ?? null,
        'readable'   => $extracted['readable']   ?? false,
    ];
}

// ─────────────────────────────────────────────────────────────────────
// Helper: build a user-facing error message (no raw OCR data exposed)
// ─────────────────────────────────────────────────────────────────────
function buildUserFacingError(array $match): string
{
    if (!$match['name_match'] && !$match['dob_match']) {
        return 'The name and date of birth on the document do not match your registration details. Please ensure you upload your own valid ID and that your registration information is correct.';
    }
    if (!$match['name_match']) {
        return 'The name on the document does not match your registration details. Please ensure the name matches exactly as entered during registration.';
    }
    if (!$match['dob_match']) {
        return 'The date of birth on the document does not match your registration details. Please check your date of birth and try again.';
    }
    return 'Identity verification could not be completed. Please upload a clear, valid ID and ensure your information matches your registration details.';
}

// ─────────────────────────────────────────────────────────────────────
// Helper: sanitize document validation reasons for user display
// ─────────────────────────────────────────────────────────────────────
function buildDocFailureMessage(string $internalReason): string
{
    // Map internal technical reasons to friendly user messages
    $map = [
        'OCR extraction failed'          => 'We could not read your document. Please upload a clearer photo.',
        'not clearly readable'           => 'Your document image is not clear enough. Please take a well-lit, focused photo.',
        'does not appear to be'          => 'The uploaded image does not appear to be a valid government-issued ID.',
        'sufficient confidence'          => 'The document could not be read with enough confidence. Please upload a clearer image.',
        'Name could not be extracted'    => 'We could not read the name from your document. Ensure the name area is fully visible and not blurry.',
        'Date of birth could not'        => 'We could not read the date of birth from your document. Ensure the birthdate area is clearly visible.',
        'expired'                        => 'Your document has expired. Please upload a valid, non-expired ID.',
    ];
    foreach ($map as $keyword => $friendly) {
        if (stripos($internalReason, $keyword) !== false) return $friendly;
    }
    return 'We could not verify your document. Please upload a clear, valid government-issued ID.';
}

// ─────────────────────────────────────────────────────────────────────
// Helper: build internal mismatch reason for DB storage
// ─────────────────────────────────────────────────────────────────────
function buildMismatchReason(array $match): string
{
    $parts = [];
    if (!$match['name_match']) $parts[] = 'name_mismatch';
    if (!$match['dob_match'])  $parts[] = 'dob_mismatch';
    return implode(', ', $parts) . sprintf(' (score=%.4f)', $match['score']);
}
