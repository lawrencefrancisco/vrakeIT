<?php
// ======================================================================
// id_verify.php
// Post-registration OCR identity verification step.
// Shown immediately after OTP verification for new user accounts.
// ======================================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();

$user = getLoggedInUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Only for regular users (not enforcers/admins)
if (($user['role'] ?? 'user') !== 'user') {
    header('Location: ' . BASE_URL . '/enforcer_landing.php');
    exit;
}

// Redirect if already verified
if (($user['verification_status'] ?? 'Unverified') === 'Verified' || ($user['account_verified'] ?? 0) == 1) {
    header('Location: ' . BASE_URL . '/landing.php');
    exit;
}

$justRegistered   = !empty($_SESSION['just_registered']);
$maxAttempts      = defined('OCR_MAX_ATTEMPTS') ? OCR_MAX_ATTEMPTS : 3;
$attemptKey       = 'ocr_attempts_' . $user['id'];
$attemptsUsed     = (int)($_SESSION[$attemptKey] ?? 0);
$attemptsLeft     = max(0, $maxAttempts - $attemptsUsed);
$hasBirthdate     = !empty($_SESSION['reg_birthdate']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – Identity Verification</title>
  <meta name="description" content="Verify your identity to unlock full VrakeIT features. Upload your government-issued ID for automated verification.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    /* ── ID Verify Page Styles ── */
    :root {
      --vr-red: #E90101;
      --vr-blue: #007ED2;
      --vr-dark: #0d0d14;
      --vr-card-bg: rgba(255,255,255,0.07);
      --vr-border: rgba(255,255,255,0.12);
    }

    body { background: var(--vr-dark); font-family: 'Poppins', sans-serif; min-height: 100vh; }

    .idv-wrap {
      min-height: 100vh;
      background: linear-gradient(135deg, #0d0d14 0%, #12121f 50%, #0a0a18 100%);
      display: flex; align-items: center; justify-content: center;
      padding: 24px 16px;
    }

    .idv-card {
      width: 100%; max-width: 480px;
      background: rgba(255,255,255,0.06);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255,255,255,0.10);
      border-radius: 24px;
      padding: 32px 28px 28px;
      box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
    }

    /* Header */
    .idv-header { text-align: center; margin-bottom: 28px; }
    .idv-icon-ring {
      width: 80px; height: 80px; margin: 0 auto 16px;
      background: linear-gradient(135deg, rgba(233,1,1,0.18), rgba(0,126,210,0.18));
      border: 2px solid rgba(233,1,1,0.35);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 36px;
      animation: pulse-ring 2.5s ease-in-out infinite;
    }
    @keyframes pulse-ring {
      0%, 100% { box-shadow: 0 0 0 0 rgba(233,1,1,0.25); }
      50%       { box-shadow: 0 0 0 12px rgba(233,1,1,0); }
    }
    .idv-header h1 { font-size: 22px; font-weight: 700; color: #fff; margin: 0 0 6px; }
    .idv-header p  { font-size: 13px; color: rgba(255,255,255,0.55); margin: 0; line-height: 1.5; }

    /* Steps indicator */
    .step-dots { display: flex; justify-content: center; gap: 8px; margin-bottom: 24px; }
    .step-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: rgba(255,255,255,0.2);
      transition: all 0.3s;
    }
    .step-dot.active { background: var(--vr-red); transform: scale(1.3); }
    .step-dot.done   { background: #00c853; }

    /* Alert */
    .idv-alert {
      border-radius: 12px; font-size: 13px; padding: 12px 14px;
      margin-bottom: 18px; display: none;
      border: none; backdrop-filter: blur(8px);
    }
    .idv-alert.show { display: flex; align-items: flex-start; gap: 10px; }
    .idv-alert.danger  { background: rgba(220,53,69,0.18); color: #ff8080; border-left: 3px solid #dc3545; }
    .idv-alert.success { background: rgba(0,200,83,0.18);  color: #66ff99; border-left: 3px solid #00c853; }
    .idv-alert.info    { background: rgba(0,126,210,0.18); color: #80ccff; border-left: 3px solid #007ED2; }

    /* Info box */
    .idv-info {
      background: rgba(0,126,210,0.12);
      border: 1px solid rgba(0,126,210,0.3);
      border-radius: 14px; padding: 14px 16px; margin-bottom: 22px;
    }
    .idv-info-title { font-size: 12px; font-weight: 700; color: #80ccff; margin-bottom: 8px; letter-spacing: 0.5px; text-transform: uppercase; }
    .idv-info ul { margin: 0; padding-left: 18px; }
    .idv-info li { font-size: 12px; color: rgba(255,255,255,0.6); margin-bottom: 4px; line-height: 1.4; }

    /* Birthdate field */
    .idv-field-label { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.6); margin-bottom: 6px; letter-spacing: 0.4px; text-transform: uppercase; }
    .idv-input {
      width: 100%; background: rgba(255,255,255,0.06); border: 1.5px solid rgba(255,255,255,0.12);
      border-radius: 12px; padding: 12px 16px; color: #fff; font-family: 'Poppins', sans-serif;
      font-size: 14px; transition: border-color 0.2s;
    }
    .idv-input:focus { outline: none; border-color: rgba(233,1,1,0.5); background: rgba(255,255,255,0.09); }
    .idv-input::-webkit-calendar-picker-indicator { filter: invert(0.7); cursor: pointer; }

    /* Upload zone */
    .idv-upload-zone {
      border: 2px dashed rgba(255,255,255,0.2);
      border-radius: 16px; padding: 28px 16px; text-align: center;
      cursor: pointer; transition: all 0.25s; position: relative; overflow: hidden;
      background: rgba(255,255,255,0.03);
    }
    .idv-upload-zone:hover, .idv-upload-zone.drag-over {
      border-color: var(--vr-red);
      background: rgba(233,1,1,0.06);
    }
    .idv-upload-zone.has-file {
      border-color: #00c853; border-style: solid;
      background: rgba(0,200,83,0.05);
    }
    .idv-upload-icon { font-size: 38px; color: rgba(255,255,255,0.3); display: block; margin-bottom: 10px; transition: color 0.25s; }
    .idv-upload-zone:hover .idv-upload-icon,
    .idv-upload-zone.drag-over .idv-upload-icon { color: var(--vr-red); }
    .idv-upload-zone.has-file .idv-upload-icon { color: #00c853; }
    .idv-upload-title { font-size: 14px; font-weight: 600; color: rgba(255,255,255,0.8); margin-bottom: 4px; }
    .idv-upload-sub   { font-size: 12px; color: rgba(255,255,255,0.4); }

    /* Capture buttons */
    .idv-capture-row { display: flex; gap: 10px; margin-top: 12px; }
    .idv-capture-btn {
      flex: 1; padding: 10px 14px; border-radius: 12px; font-size: 13px; font-weight: 600;
      border: 1.5px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05);
      color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s;
      font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .idv-capture-btn:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: #fff; }
    .idv-capture-btn.camera { border-color: rgba(0,126,210,0.4); }
    .idv-capture-btn.camera:hover { background: rgba(0,126,210,0.12); border-color: var(--vr-blue); color: #80ccff; }

    /* Preview */
    .idv-preview-wrap {
      margin-top: 14px; border-radius: 14px; overflow: hidden;
      border: 2px solid #00c853; position: relative;
      background: #000; display: none;
    }
    .idv-preview-wrap img { width: 100%; max-height: 220px; object-fit: contain; display: block; }
    .idv-preview-remove {
      position: absolute; top: 8px; right: 8px;
      background: rgba(220,53,69,0.85); color: #fff; border: none;
      border-radius: 50%; width: 28px; height: 28px; cursor: pointer;
      display: flex; align-items: center; justify-content: center; font-size: 14px;
      transition: background 0.2s; backdrop-filter: blur(4px);
    }
    .idv-preview-remove:hover { background: #dc3545; }
    .idv-preview-filename {
      background: rgba(0,0,0,0.6); color: rgba(255,255,255,0.7);
      font-size: 11px; padding: 6px 12px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;
    }

    /* Attempts badge */
    .idv-attempts {
      font-size: 12px; color: rgba(255,255,255,0.4); text-align: center; margin-bottom: 14px;
    }
    .idv-attempts strong { color: #ffc107; }

    /* Processing overlay */
    .idv-processing {
      display: none; text-align: center; padding: 32px 0;
    }
    .idv-processing.show { display: block; }
    .ocr-spinner {
      width: 64px; height: 64px; margin: 0 auto 20px;
      border: 4px solid rgba(233,1,1,0.15);
      border-top-color: var(--vr-red);
      border-radius: 50%;
      animation: spin 0.9s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .idv-processing h3 { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 8px; }
    .idv-processing p  { font-size: 13px; color: rgba(255,255,255,0.5); }
    .processing-steps { margin-top: 20px; text-align: left; }
    .proc-step {
      display: flex; align-items: center; gap: 12px;
      padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.06);
      font-size: 12px; color: rgba(255,255,255,0.45);
      transition: color 0.4s;
    }
    .proc-step:last-child { border-bottom: none; }
    .proc-step.active { color: rgba(255,255,255,0.85); }
    .proc-step.done   { color: #66ff99; }
    .proc-step-icon { font-size: 16px; width: 22px; text-align: center; }

    /* Result states */
    .idv-result { display: none; text-align: center; padding: 8px 0; }
    .idv-result.show { display: block; }
    .result-icon-wrap {
      width: 80px; height: 80px; margin: 0 auto 18px;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 38px;
    }
    .result-icon-wrap.success { background: linear-gradient(135deg, #00c853, #009624); box-shadow: 0 0 40px rgba(0,200,83,0.35); }
    .result-icon-wrap.failure { background: linear-gradient(135deg, #dc3545, #b02a37); box-shadow: 0 0 40px rgba(220,53,69,0.35); }
    .result-title { font-size: 20px; font-weight: 700; margin-bottom: 10px; }
    .result-title.success { color: #66ff99; }
    .result-title.failure { color: #ff8080; }
    .result-body { font-size: 13px; color: rgba(255,255,255,0.55); line-height: 1.6; margin-bottom: 22px; }
    .result-ref { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 8px; font-family: monospace; }

    /* Buttons */
    .idv-btn-primary {
      width: 100%; padding: 14px; border-radius: 14px;
      background: linear-gradient(135deg, #E90101, #c20000);
      color: #fff; font-weight: 700; font-size: 15px;
      border: none; cursor: pointer; transition: all 0.25s;
      font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px;
      box-shadow: 0 4px 20px rgba(233,1,1,0.35);
    }
    .idv-btn-primary:hover:not(:disabled) { background: linear-gradient(135deg, #ff1f1f, #E90101); transform: translateY(-1px); box-shadow: 0 6px 28px rgba(233,1,1,0.45); }
    .idv-btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .idv-btn-outline {
      width: 100%; padding: 13px; border-radius: 14px;
      background: transparent; color: rgba(255,255,255,0.6);
      border: 1.5px solid rgba(255,255,255,0.15); cursor: pointer;
      font-weight: 600; font-size: 14px; transition: all 0.2s;
      font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px;
      text-decoration: none;
    }
    .idv-btn-outline:hover { border-color: rgba(255,255,255,0.3); color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.05); }

    .idv-btn-success {
      width: 100%; padding: 14px; border-radius: 14px;
      background: linear-gradient(135deg, #00c853, #009624);
      color: #fff; font-weight: 700; font-size: 15px;
      border: none; cursor: pointer; font-family: 'Poppins', sans-serif;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      text-decoration: none; box-shadow: 0 4px 20px rgba(0,200,83,0.35);
    }

    .idv-divider { height: 1px; background: rgba(255,255,255,0.08); margin: 18px 0; }

    /* Security note */
    .idv-security {
      display: flex; align-items: center; gap: 8px;
      font-size: 11px; color: rgba(255,255,255,0.3);
      margin-top: 16px; justify-content: center;
    }

    /* Responsive */
    @media (max-width: 400px) {
      .idv-card { padding: 24px 18px 20px; }
      .idv-capture-row { flex-direction: column; }
    }

    /* ── Camera modal ── */
    .cam-modal-backdrop {
      position: fixed; inset: 0; z-index: 1100;
      background: rgba(0,0,0,0.85);
      backdrop-filter: blur(8px);
      display: flex; align-items: center; justify-content: center;
      padding: 16px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.25s;
    }
    .cam-modal-backdrop.open { opacity: 1; pointer-events: all; }
    .cam-modal {
      width: 100%; max-width: 520px;
      background: rgba(20,20,32,0.97);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 40px 100px rgba(0,0,0,0.7);
      transform: scale(0.94); transition: transform 0.25s;
    }
    .cam-modal-backdrop.open .cam-modal { transform: scale(1); }
    .cam-modal-header {
      padding: 16px 20px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .cam-modal-header h5 { margin: 0; font-size: 15px; font-weight: 700; color: #fff; }
    .cam-close-btn {
      background: rgba(255,255,255,0.08); border: none; border-radius: 50%;
      width: 32px; height: 32px; color: rgba(255,255,255,0.7);
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      font-size: 16px; transition: background 0.2s;
    }
    .cam-close-btn:hover { background: rgba(220,53,69,0.3); color: #ff8080; }
    .cam-video-wrap {
      background: #000; position: relative;
      display: flex; align-items: center; justify-content: center;
      min-height: 260px;
    }
    #camVideo { width: 100%; max-height: 320px; object-fit: cover; display: block; }
    .cam-guide-overlay {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      pointer-events: none;
    }
    .cam-guide-frame {
      width: 72%; aspect-ratio: 1.586;
      border: 2px solid rgba(233,1,1,0.7);
      border-radius: 10px;
      box-shadow: 0 0 0 2000px rgba(0,0,0,0.35);
    }
    .cam-guide-label {
      position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%);
      font-size: 11px; color: rgba(255,255,255,0.6);
      background: rgba(0,0,0,0.55); padding: 4px 12px; border-radius: 20px;
      white-space: nowrap;
    }
    .cam-modal-footer {
      padding: 16px 20px;
      display: flex; align-items: center; gap: 10px;
    }
    .cam-switch-btn {
      padding: 10px 16px; border-radius: 12px; font-size: 13px; font-weight: 600;
      border: 1.5px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06);
      color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s;
      font-family: 'Poppins', sans-serif; display: flex; align-items: center; gap: 6px;
    }
    .cam-switch-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
    .cam-switch-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .cam-capture-btn {
      flex: 1; padding: 12px; border-radius: 14px;
      background: linear-gradient(135deg, #E90101, #c20000);
      color: #fff; font-weight: 700; font-size: 14px; border: none;
      cursor: pointer; font-family: 'Poppins', sans-serif;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      box-shadow: 0 4px 18px rgba(233,1,1,0.4); transition: all 0.2s;
    }
    .cam-capture-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(233,1,1,0.5); }
    .cam-capture-btn:active { transform: scale(0.97); }
    .cam-error-msg {
      padding: 20px; text-align: center;
      font-size: 13px; color: rgba(255,255,255,0.55); line-height: 1.6;
    }
    .cam-error-msg i { font-size: 36px; color: #ff8080; display: block; margin-bottom: 10px; }
  </style>
</head>
<body>
<div class="idv-wrap">
  <div class="idv-card">

    <!-- ── Header ── -->
    <div class="idv-header">
      <div class="idv-icon-ring">🪪</div>
      <h1>Identity Verification</h1>
      <p>Upload a clear photo of your government-issued ID or driving license.<br>We'll verify it automatically — usually within seconds.</p>
    </div>

    <!-- ── Step dots ── -->
    <div class="step-dots" id="stepDots">
      <div class="step-dot done" title="Registration"></div>
      <div class="step-dot done" title="OTP Verified"></div>
      <div class="step-dot active" title="ID Verification"></div>
    </div>

    <!-- ── Alert box ── -->
    <div class="idv-alert" id="idvAlert" role="alert"></div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- UPLOAD FORM (shown by default)                      -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div id="uploadSection">

      <!-- Info box -->
      <div class="idv-info">
        <div class="idv-info-title">📋 Accepted Documents</div>
        <ul>
          <li>Driver's License (front side)</li>
          <li>PhilSys / National ID</li>
          <li>Passport (photo page)</li>
          <li>SSS, GSIS, PhilHealth, Voter's, or PRC ID</li>
          <li>Any other valid government-issued photo ID</li>
        </ul>
      </div>

      <?php if (!$hasBirthdate): ?>
      <!-- Birthdate field — only shown if not captured during registration -->
      <div class="mb-3">
        <div class="idv-field-label">Date of Birth</div>
        <input type="date" class="idv-input" id="birthdateInput"
               max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
               placeholder="YYYY-MM-DD">
        <div style="font-size:11px;color:rgba(255,255,255,0.3);margin-top:6px;">
          Must match the date on your ID document.
        </div>
      </div>
      <?php endif; ?>

      <!-- Upload zone -->
      <div class="idv-upload-zone" id="uploadZone"
           onclick="triggerFileInput()"
           ondragover="handleDragOver(event)"
           ondragleave="handleDragLeave(event)"
           ondrop="handleDrop(event)">
        <i class="bi bi-id-card idv-upload-icon" id="uploadIcon"></i>
        <div class="idv-upload-title" id="uploadTitle">Tap to upload your ID</div>
        <div class="idv-upload-sub" id="uploadSub">JPG, PNG, or WebP &mdash; Max 10 MB</div>
        <input type="file" id="idFileInput" accept="image/jpeg,image/png,image/webp"
               style="display:none;" onchange="handleFileSelect(this.files[0])">
      </div>

      <!-- Camera / gallery capture buttons -->
      <div class="idv-capture-row">
        <button type="button" class="idv-capture-btn camera" id="openCameraBtn" onclick="openCameraModal()">
          <i class="bi bi-camera-fill"></i> Use Camera
        </button>
        <button type="button" class="idv-capture-btn" onclick="triggerFileInput()">
          <i class="bi bi-images"></i> Choose File
        </button>
      </div>

      <!-- Preview -->
      <div class="idv-preview-wrap" id="previewWrap">
        <img id="previewImg" src="" alt="ID Preview">
        <button type="button" class="idv-preview-remove" onclick="removeFile()" title="Remove image">
          <i class="bi bi-x-lg"></i>
        </button>
        <div class="idv-preview-filename" id="previewFilename"></div>
      </div>

      <div class="idv-divider"></div>

      <?php if ($attemptsLeft < $maxAttempts && $attemptsLeft > 0): ?>
      <div class="idv-attempts">
        Attempts remaining: <strong><?= $attemptsLeft ?></strong> of <?= $maxAttempts ?>
      </div>
      <?php endif; ?>

      <!-- Submit -->
      <button type="button" class="idv-btn-primary" id="verifyBtn" onclick="submitVerification()" disabled>
        <i class="bi bi-shield-check"></i> Verify My Identity
      </button>

      <div class="idv-divider"></div>

      <!-- Skip -->
      <a href="landing.php" class="idv-btn-outline" id="skipBtn" onclick="return confirmSkip()">
        <i class="bi bi-skip-forward"></i> Skip for now
      </a>

      <!-- Security note -->
      <div class="idv-security">
        <i class="bi bi-lock-fill"></i>
        Your ID is processed securely and deleted immediately after verification.
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- PROCESSING STATE                                    -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div class="idv-processing" id="processingSection">
      <div class="ocr-spinner"></div>
      <h3>Verifying your identity…</h3>
      <p>Please wait while we process your document.<br>This usually takes 5–15 seconds.</p>
      <div class="processing-steps" id="procSteps">
        <div class="proc-step" id="pStep1">
          <i class="bi bi-upload proc-step-icon"></i> Uploading document securely
        </div>
        <div class="proc-step" id="pStep2">
          <i class="bi bi-eye proc-step-icon"></i> Reading document with OCR
        </div>
        <div class="proc-step" id="pStep3">
          <i class="bi bi-patch-check proc-step-icon"></i> Validating document authenticity
        </div>
        <div class="proc-step" id="pStep4">
          <i class="bi bi-person-check proc-step-icon"></i> Comparing with your registration
        </div>
        <div class="proc-step" id="pStep5">
          <i class="bi bi-shield-lock proc-step-icon"></i> Finalising verification
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- SUCCESS STATE                                       -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div class="idv-result" id="successSection">
      <div class="result-icon-wrap success">✅</div>
      <div class="result-title success">Identity Verified!</div>
      <div class="result-body">
        Your identity has been successfully verified.<br>
        Your account is now <strong style="color:#66ff99;">Verified</strong> and has full access to all VrakeIT features.
      </div>
      <div class="result-ref" id="verifyRef"></div>
      <a href="landing.php" class="idv-btn-success" style="margin-top:18px;">
        <i class="bi bi-house-fill"></i> Go to Dashboard
      </a>
    </div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- FAILURE STATE                                       -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div class="idv-result" id="failureSection">
      <div class="result-icon-wrap failure">❌</div>
      <div class="result-title failure">Verification Failed</div>
      <div class="result-body" id="failureBody">
        Identity verification could not be completed. Please upload a clear, valid ID and make sure the information matches your registration details.
      </div>

      <div id="retrySection" style="display:none;">
        <button type="button" class="idv-btn-primary" id="retryBtn" onclick="resetToUpload()" style="margin-bottom:12px;">
          <i class="bi bi-arrow-repeat"></i> Try Again
        </button>
        <div class="idv-attempts" id="retryAttemptsNote" style="margin-bottom:12px;"></div>
      </div>

      <a href="landing.php" class="idv-btn-outline">
        <i class="bi bi-skip-forward"></i> Continue without verification
      </a>

      <div class="idv-security" style="margin-top:14px;">
        <i class="bi bi-info-circle"></i>
        You can verify later from your profile. Manual review is also available.
      </div>
    </div>

  </div><!-- /idv-card -->
</div><!-- /idv-wrap -->

<!-- ── Camera Modal ── -->
<div class="cam-modal-backdrop" id="camModalBackdrop" onclick="handleBackdropClick(event)">
  <div class="cam-modal" id="camModal">
    <div class="cam-modal-header">
      <h5><i class="bi bi-camera-video-fill me-2" style="color:#80ccff;"></i>Capture ID Photo</h5>
      <button class="cam-close-btn" id="camCloseBtn" onclick="closeCameraModal()" title="Close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="cam-video-wrap" id="camVideoWrap">
      <video id="camVideo" autoplay playsinline muted></video>
      <div class="cam-guide-overlay">
        <div class="cam-guide-frame"></div>
        <span class="cam-guide-label">Align your ID within the frame</span>
      </div>
    </div>
    <div id="camErrorMsg" class="cam-error-msg" style="display:none;">
      <i class="bi bi-camera-video-off-fill"></i>
      <strong style="color:#ff8080;">Camera unavailable</strong><br>
      <span id="camErrorText">Could not access your camera. Please allow camera permission and try again.</span>
    </div>
    <canvas id="camCanvas" style="display:none;"></canvas>
    <div class="cam-modal-footer">
      <button class="cam-switch-btn" id="camSwitchBtn" onclick="switchCamera()" title="Switch camera" disabled>
        <i class="bi bi-arrow-repeat"></i> Switch
      </button>
      <button class="cam-capture-btn" id="camCaptureBtn" onclick="capturePhoto()" disabled>
        <i class="bi bi-camera-fill"></i> Capture Photo
      </button>
    </div>
  </div>
</div>

<script>
  // ── State
  let selectedFile    = null;
  const maxAttempts   = <?= $maxAttempts ?>;
  let   attemptsUsed  = <?= $attemptsUsed ?>;
  const hasBirthdate  = <?= $hasBirthdate ? 'true' : 'false' ?>;

  // ── Element refs
  const uploadSection    = document.getElementById('uploadSection');
  const processingSection= document.getElementById('processingSection');
  const successSection   = document.getElementById('successSection');
  const failureSection   = document.getElementById('failureSection');
  const idvAlert         = document.getElementById('idvAlert');
  const uploadZone       = document.getElementById('uploadZone');
  const previewWrap      = document.getElementById('previewWrap');
  const previewImg       = document.getElementById('previewImg');
  const verifyBtn        = document.getElementById('verifyBtn');
  const uploadIcon       = document.getElementById('uploadIcon');
  const uploadTitle      = document.getElementById('uploadTitle');
  const uploadSub        = document.getElementById('uploadSub');

  // ─────────────────────────────────────────────────────────────────
  // File handling
  // ─────────────────────────────────────────────────────────────────
  function triggerFileInput() {
    document.getElementById('idFileInput').click();
  }

  // ─────────────────────────────────────────────────────────────────
  // Camera modal (getUserMedia)
  // ─────────────────────────────────────────────────────────────────
  let camStream       = null;
  let camDevices      = [];   // list of videoinput devices
  let camDeviceIndex  = 0;    // which camera is active

  async function openCameraModal() {
    const backdrop    = document.getElementById('camModalBackdrop');
    const videoEl     = document.getElementById('camVideo');
    const videoWrap   = document.getElementById('camVideoWrap');
    const errorMsg    = document.getElementById('camErrorMsg');
    const captureBtn  = document.getElementById('camCaptureBtn');
    const switchBtn   = document.getElementById('camSwitchBtn');

    // Reset to a clean state each time the modal opens
    errorMsg.style.display  = 'none';
    videoWrap.style.display = 'flex';
    captureBtn.disabled     = true;
    switchBtn.disabled      = true;
    camDevices              = [];
    camDeviceIndex          = 0;

    // Open modal first so user sees something immediately
    backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Check API support
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showCamError('Your browser does not support camera access. Please use "Choose File" instead.');
      return;
    }

    try {
      // ── Step 1: request permission with simple constraints (no deviceId yet).
      // Enumerating devices BEFORE this returns empty deviceId strings on most
      // browsers, which then causes getUserMedia to fail with OverconstrainedError.
      camStream = await navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 1280 }, height: { ideal: 720 } },
        audio: false
      });

      videoEl.srcObject = camStream;
      await videoEl.play();
      captureBtn.disabled = false;

      // ── Step 2: NOW enumerate devices — IDs are populated after permission grant
      try {
        const allDevices = await navigator.mediaDevices.enumerateDevices();
        camDevices = allDevices.filter(d => d.kind === 'videoinput' && d.deviceId);
        if (camDevices.length > 1) switchBtn.disabled = false;
      } catch (_) {
        // Enumeration failing is non-fatal; switch button just stays disabled
      }

    } catch (err) {
      let msg;
      switch (err.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
          msg = 'Camera permission was denied. Click the camera/lock icon in the address bar, set Camera to "Allow", then refresh and try again.';
          break;
        case 'NotFoundError':
        case 'DevicesNotFoundError':
          msg = 'No camera was detected on this device. Use the "Choose File" button to upload a photo instead.';
          break;
        case 'NotReadableError':
        case 'TrackStartError':
          msg = 'Your camera is in use by another application (e.g. Teams, Zoom, OBS). Close it and try again.';
          break;
        case 'OverconstrainedError':
          msg = 'Camera does not support the requested resolution. Please try again.';
          break;
        case 'SecurityError':
          msg = 'Camera access blocked by browser security policy. Make sure you are on http://localhost.';
          break;
        default:
          msg = `Camera error (${err.name}): ${err.message || 'unknown'}. Try "Choose File" instead.`;
      }
      showCamError(msg);
    }
  }

  async function startCamStream() {
    // Stop existing stream
    if (camStream) {
      camStream.getTracks().forEach(t => t.stop());
      camStream = null;
    }

    const videoEl = document.getElementById('camVideo');
    // When switching, we have real deviceIds available
    const device = camDevices[camDeviceIndex];
    const constraints = {
      video: device && device.deviceId
        ? { deviceId: { exact: device.deviceId }, width: { ideal: 1280 }, height: { ideal: 720 } }
        : { width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false
    };

    camStream = await navigator.mediaDevices.getUserMedia(constraints);
    videoEl.srcObject = camStream;
    await videoEl.play();
  }

  async function switchCamera() {
    if (camDevices.length < 2) return;
    camDeviceIndex = (camDeviceIndex + 1) % camDevices.length;
    const switchBtn = document.getElementById('camSwitchBtn');
    switchBtn.disabled = true;
    try {
      await startCamStream();
    } catch(e) { /* ignore switch errors */ }
    switchBtn.disabled = false;
  }

  function capturePhoto() {
    const videoEl  = document.getElementById('camVideo');
    const canvas   = document.getElementById('camCanvas');
    if (!camStream || videoEl.readyState < 2) return;

    canvas.width  = videoEl.videoWidth  || 1280;
    canvas.height = videoEl.videoHeight || 720;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(videoEl, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(blob => {
      if (!blob) { showAlert('Could not capture image. Please try again.', 'danger'); return; }
      const file = new File([blob], 'camera_capture.jpg', { type: 'image/jpeg' });
      closeCameraModal();
      handleFileSelect(file);
    }, 'image/jpeg', 0.92);
  }

  function closeCameraModal() {
    if (camStream) {
      camStream.getTracks().forEach(t => t.stop());
      camStream = null;
    }
    document.getElementById('camVideo').srcObject = null;
    document.getElementById('camModalBackdrop').classList.remove('open');
    document.body.style.overflow = '';
  }

  function handleBackdropClick(e) {
    if (e.target === document.getElementById('camModalBackdrop')) closeCameraModal();
  }

  function showCamError(msg) {
    document.getElementById('camVideoWrap').style.display = 'none';
    document.getElementById('camErrorText').textContent = msg;
    document.getElementById('camErrorMsg').style.display = 'block';
  }

  // Close modal on Escape key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('camModalBackdrop').classList.contains('open')) {
      closeCameraModal();
    }
  });

  function handleFileSelect(file) {
    if (!file) return;

    // Client-side validation
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
      showAlert('Please upload a JPG, PNG, or WebP image.', 'danger');
      return;
    }
    const maxMb = 10 * 1024 * 1024;
    if (file.size > maxMb) {
      showAlert('File is too large. Maximum size is 10 MB.', 'danger');
      return;
    }

    selectedFile = file;
    hideAlert();

    // Show preview
    const url = URL.createObjectURL(file);
    previewImg.src = url;
    document.getElementById('previewFilename').textContent = `📄 ${file.name} (${formatBytes(file.size)})`;
    previewWrap.style.display = 'block';

    // Update upload zone
    uploadZone.classList.add('has-file');
    uploadIcon.className = 'bi bi-check-circle-fill idv-upload-icon';
    uploadTitle.textContent = 'Document selected';
    uploadSub.textContent   = file.name;

    verifyBtn.disabled = false;
  }

  function removeFile() {
    selectedFile = null;
    previewImg.src = '';
    previewWrap.style.display = 'none';
    uploadZone.classList.remove('has-file');
    uploadIcon.className = 'bi bi-id-card idv-upload-icon';
    uploadTitle.textContent = 'Tap to upload your ID';
    uploadSub.textContent   = 'JPG, PNG, or WebP — Max 10 MB';
    verifyBtn.disabled = true;
    document.getElementById('idFileInput').value  = '';
    document.getElementById('cameraInput').value  = '';
    hideAlert();
  }

  function handleDragOver(e) { e.preventDefault(); uploadZone.classList.add('drag-over'); }
  function handleDragLeave(e){ uploadZone.classList.remove('drag-over'); }
  function handleDrop(e) {
    e.preventDefault();
    uploadZone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) handleFileSelect(file);
  }

  // ─────────────────────────────────────────────────────────────────
  // Submit verification
  // ─────────────────────────────────────────────────────────────────
  async function submitVerification() {
    if (!selectedFile) {
      showAlert('Please select an image of your government-issued ID.', 'danger');
      return;
    }

    // Birthdate check (if not pre-filled from registration)
    let birthdate = '';
    if (!hasBirthdate) {
      birthdate = (document.getElementById('birthdateInput')?.value || '').trim();
      if (!birthdate) {
        showAlert('Please enter your date of birth as it appears on your ID.', 'danger');
        return;
      }
    }

    // Switch to processing view
    showSection('processing');
    animateProcessingSteps();

    const fd = new FormData();
    fd.append('id_image', selectedFile);
    if (birthdate) fd.append('birthdate', birthdate);

    try {
      const res  = await fetch('api/ocr_verify_id.php', { method: 'POST', body: fd });
      const data = await res.json();

      attemptsUsed++;

      if (data.success) {
        // ── Success
        if (data.reference) {
          document.getElementById('verifyRef').textContent = 'Reference: ' + data.reference;
        }
        showSection('success');
        // Auto-redirect after 3.5s
        if (data.redirect) {
          setTimeout(() => { window.location.href = data.redirect; }, 3500);
        }
      } else {
        // ── Failure
        const attemptsLeft = Math.max(0, maxAttempts - attemptsUsed);
        document.getElementById('failureBody').textContent = data.message ||
          'Identity verification could not be completed. Please try again with a clear, valid ID.';

        const retrySection  = document.getElementById('retrySection');
        const retryNote     = document.getElementById('retryAttemptsNote');

        if (attemptsLeft > 0) {
          retrySection.style.display = 'block';
          retryNote.innerHTML = `Attempts remaining: <strong>${attemptsLeft}</strong> of ${maxAttempts}`;
        } else {
          retrySection.style.display = 'none';
        }

        showSection('failure');
      }

    } catch (err) {
      showAlert('Connection error. Please check your connection and try again.', 'danger');
      showSection('upload');
    }
  }

  // ─────────────────────────────────────────────────────────────────
  // Processing steps animation
  // ─────────────────────────────────────────────────────────────────
  function animateProcessingSteps() {
    const steps = ['pStep1','pStep2','pStep3','pStep4','pStep5'];
    const delays = [0, 1800, 3500, 5500, 7500];
    steps.forEach((id, i) => {
      setTimeout(() => {
        // Mark previous as done
        if (i > 0) document.getElementById(steps[i-1]).classList.replace('active','done');
        document.getElementById(id).classList.add('active');
      }, delays[i]);
    });
  }

  // ─────────────────────────────────────────────────────────────────
  // Section management
  // ─────────────────────────────────────────────────────────────────
  function showSection(which) {
    uploadSection.style.display     = which === 'upload'     ? 'block' : 'none';
    processingSection.classList.toggle('show', which === 'processing');
    successSection.classList.toggle('show', which === 'success');
    failureSection.classList.toggle('show', which === 'failure');

    // Update step dots
    const dots = document.querySelectorAll('.step-dot');
    if (which === 'success') {
      dots[2].classList.remove('active');
      dots[2].classList.add('done');
    }
  }

  function resetToUpload() {
    // Reset processing steps
    ['pStep1','pStep2','pStep3','pStep4','pStep5'].forEach(id => {
      const el = document.getElementById(id);
      el.classList.remove('active','done');
    });
    removeFile();
    hideAlert();
    showSection('upload');
  }

  // ─────────────────────────────────────────────────────────────────
  // Alert helpers
  // ─────────────────────────────────────────────────────────────────
  function showAlert(msg, type = 'danger') {
    idvAlert.className = `idv-alert show ${type}`;
    idvAlert.innerHTML = `<i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;margin-top:2px;"></i><span>${msg}</span>`;
  }
  function hideAlert() {
    idvAlert.className = 'idv-alert';
    idvAlert.innerHTML = '';
  }

  // ─────────────────────────────────────────────────────────────────
  // Helpers
  // ─────────────────────────────────────────────────────────────────
  function formatBytes(bytes) {
    if (bytes < 1024)       return bytes + ' B';
    if (bytes < 1048576)    return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
  }

  function confirmSkip() {
    return confirm('Are you sure you want to skip identity verification?\n\nYou can complete verification later from your profile, but some features may be limited until verified.');
  }

  // Show upload section by default
  showSection('upload');

  <?php if (!empty($_GET['error'])): ?>
  showAlert('<?= htmlspecialchars($_GET['error'], ENT_QUOTES) ?>', 'danger');
  <?php endif; ?>
</script>
</body>
</html>
