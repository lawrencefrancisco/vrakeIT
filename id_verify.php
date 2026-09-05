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

    /* ═══════════════════════════════════════════════════════════
       FULL-SCREEN IN-BROWSER ID SCANNER
       No native camera app. Ever. getUserMedia only.
    ═══════════════════════════════════════════════════════════ */
    .cam-scanner-overlay {
      position: fixed; inset: 0; z-index: 1100;
      background: #000;
      opacity: 0; pointer-events: none;
      transition: opacity 0.22s;
    }
    .cam-scanner-overlay.open { opacity: 1; pointer-events: all; }

    /* Video fills the entire screen — this IS the camera */
    #camVideo {
      position: absolute; inset: 0;
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }

    /* ── Top bar: title + close btn overlaid on video ── */
    .cam-top-bar {
      position: absolute; top: 0; left: 0; right: 0;
      padding: max(env(safe-area-inset-top, 0px), 12px) 16px 20px;
      display: flex; align-items: center; justify-content: space-between;
      background: linear-gradient(to bottom, rgba(0,0,0,0.75) 0%, transparent 100%);
      z-index: 20;
    }
    .cam-top-title {
      font-size: 15px; font-weight: 700; color: #fff;
      display: flex; align-items: center; gap: 8px;
      text-shadow: 0 1px 6px rgba(0,0,0,0.8);
    }
    .cam-close-btn {
      width: 40px; height: 40px; border-radius: 50%;
      background: rgba(0,0,0,0.45); border: 1.5px solid rgba(255,255,255,0.2);
      color: #fff; font-size: 16px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(6px); transition: background 0.2s;
    }
    .cam-close-btn:hover { background: rgba(220,53,69,0.55); }

    /* ── Bottom bar: switch + capture ── */
    .cam-bottom-bar {
      position: absolute; bottom: 0; left: 0; right: 0;
      padding: 20px 32px max(env(safe-area-inset-bottom, 0px), 28px);
      display: flex; align-items: center; justify-content: center; gap: 28px;
      background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
      z-index: 20;
    }
    /* Spacer to balance the layout (same size as switch btn) */
    .cam-spacer { width: 52px; height: 52px; }
    .cam-flash-btn {
      width:52px; height:52px; border-radius:50%;
      background:rgba(255,255,255,0.12); border:1.5px solid rgba(255,255,255,0.25);
      color:rgba(255,255,255,0.5); font-size:20px; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      transition: all 0.2s;
    }
    .cam-flash-btn:disabled { opacity:0.25; cursor:not-allowed; }
    .cam-flash-btn.on {
      background:rgba(255,220,0,0.22); border-color:rgba(255,220,0,0.7);
      color:#FFE000; box-shadow:0 0 14px rgba(255,220,0,0.45);
    }

    /* Circular switch-camera button */
    .cam-switch-btn {
      width: 52px; height: 52px; border-radius: 50%;
      background: rgba(255,255,255,0.15);
      border: 1.5px solid rgba(255,255,255,0.3);
      color: #fff; font-size: 20px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      backdrop-filter: blur(6px); transition: all 0.2s;
    }
    .cam-switch-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .cam-switch-btn:not(:disabled):hover { background: rgba(255,255,255,0.25); }

    /* Big circular shutter / capture button */
    .cam-capture-btn {
      width: 76px; height: 76px; border-radius: 50%;
      background: linear-gradient(135deg, #E90101, #b00000);
      border: 4px solid rgba(255,255,255,0.35);
      color: #fff; font-size: 30px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 0 2px rgba(233,1,1,0.4), 0 6px 28px rgba(233,1,1,0.5);
      transition: transform 0.12s, box-shadow 0.12s;
    }
    .cam-capture-btn:not(:disabled):active { transform: scale(0.88); }
    .cam-capture-btn:disabled { opacity: 0.35; cursor: not-allowed; }

    /* ── Tip pill (drawn over frame area by JS, overlaid via CSS) ── */
    #camTipBar {
      position: absolute;
      left: 0; right: 0;
      bottom: 140px;       /* above the capture button bar */
      display: none;
      justify-content: center; align-items: center;
      z-index: 20;
      pointer-events: none;
    }
    .cam-tip-label {
      font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.92);
      background: rgba(0,0,0,0.6); backdrop-filter: blur(8px);
      padding: 6px 18px; border-radius: 24px;
      border: 1px solid rgba(255,255,255,0.15);
      text-shadow: 0 1px 4px rgba(0,0,0,0.8);
    }

    /* ── Error state (fullscreen overlay) ── */
    #camErrorMsg {
      position: absolute; inset: 0; z-index: 30;
      display: none; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 40px 28px; text-align: center;
      background: rgba(0,0,0,0.88);
      color: rgba(255,255,255,0.65);
      font-size: 14px; line-height: 1.65;
    }
    #camErrorMsg i { font-size: 52px; color: #ff6060; margin-bottom: 18px; display: block; }
    #camErrorMsg strong { color: #ff8080; font-size: 17px; display: block; margin-bottom: 8px; }

    /* ═══════════════════════════════════════════════════════════
       MOBILE GUIDE SCREEN
       Shows before native camera opens on mobile
    ═══════════════════════════════════════════════════════════ */
    .mob-guide-overlay {
      position: fixed; inset: 0; z-index: 1200;
      background: #080810;
      display: flex; flex-direction: column;
      opacity: 0; pointer-events: none;
      transition: opacity 0.22s;
    }
    .mob-guide-overlay.open { opacity: 1; pointer-events: all; }

    /* Top bar */
    .mob-guide-topbar {
      display: flex; align-items: center; justify-content: space-between;
      padding: max(env(safe-area-inset-top,0px),14px) 18px 10px;
      background: linear-gradient(to bottom,rgba(0,0,0,0.6),transparent);
      position: relative; z-index: 2;
    }
    .mob-guide-title {
      font-size: 15px; font-weight: 700; color: #fff;
      display: flex; align-items: center; gap: 8px;
    }
    .mob-guide-close {
      width: 38px; height: 38px; border-radius: 50%;
      background: rgba(255,255,255,0.1); border: 1.5px solid rgba(255,255,255,0.2);
      color: #fff; font-size: 15px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
    }

    /* Canvas area (shows ID frame guide) */
    .mob-guide-canvas-wrap {
      flex: 1; position: relative;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    #mobGuideCanvas {
      width: 100%; height: 100%;
      display: block;
    }

    /* Bottom instruction + button */
    .mob-guide-footer {
      padding: 20px 24px max(env(safe-area-inset-bottom,0px),28px);
      display: flex; flex-direction: column;
      align-items: center; gap: 12px;
      background: linear-gradient(to top,rgba(0,0,0,0.75),transparent);
    }
    .mob-guide-hint {
      font-size: 12px; color: rgba(255,255,255,0.55);
      text-align: center; line-height: 1.5; margin: 0;
    }
    .mob-guide-btn {
      width: 72px; height: 72px; border-radius: 50%;
      background: linear-gradient(135deg,#E90101,#b00000);
      border: 4px solid rgba(255,255,255,0.3);
      color: #fff; font-size: 28px; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 0 0 2px rgba(233,1,1,0.4), 0 6px 28px rgba(233,1,1,0.5);
      transition: transform 0.12s;
    }
    .mob-guide-btn:active { transform: scale(0.88); }
    .idv-card-guide {
      position: relative;
      width: 82%; aspect-ratio: 1.586;
      margin: 16px auto 8px;
      border-radius: 10px;
      background: rgba(0,126,210,0.04);
    }
    /* Corner brackets for upload zone */
    .idv-card-guide::before,
    .idv-card-guide::after,
    .idv-cg-br, .idv-cg-bl {
      content: '';
      position: absolute;
      width: 20px; height: 20px;
      border-color: rgba(0,126,210,0.7);
      border-style: solid;
    }
    .idv-card-guide::before { top:-2px; left:-2px; border-width:2.5px 0 0 2.5px; border-radius:5px 0 0 0; }
    .idv-card-guide::after  { top:-2px; right:-2px; border-width:2.5px 2.5px 0 0; border-radius:0 5px 0 0; }
    .idv-cg-br { bottom:-2px; right:-2px; border-width:0 2.5px 2.5px 0; border-radius:0 0 5px 0; }
    .idv-cg-bl { bottom:-2px; left:-2px;  border-width:0 0 2.5px 2.5px; border-radius:0 0 0 5px; }

    /* ID card silhouette inside the guide */
    .idv-card-silhouette {
      position: absolute; inset: 12px;
      border: 1.5px dashed rgba(0,126,210,0.3);
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      flex-direction: column; gap: 4px;
    }
    .idv-card-silhouette i { font-size: 28px; color: rgba(0,126,210,0.4); }
    .idv-card-silhouette span { font-size: 10px; color: rgba(255,255,255,0.3); font-weight: 500; letter-spacing:.4px; }
    /* ── Upload Zone ID Frame Guide ── */
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

        <!-- ID card frame guide -->
        <div class="idv-card-guide" id="idCardGuide">
          <div class="idv-cg-br"></div>
          <div class="idv-cg-bl"></div>
          <div class="idv-card-silhouette">
            <i class="bi bi-person-vcard" id="uploadIcon"></i>
            <span id="uploadTitle">Place your ID here</span>
          </div>
        </div>

        <div class="idv-upload-sub" id="uploadSub" style="margin-top:4px;">Tap to upload &mdash; JPG, PNG, WebP &bull; Max 10 MB</div>
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
      <!-- Hidden native camera input for mobile guide path -->
      <input type="file" id="mobileCameraInput" accept="image/*" capture="environment"
             style="display:none;" onchange="handleFileSelect(this.files[0])">

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

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- FULL-SCREEN IN-BROWSER ID SCANNER                          -->
<!-- Uses getUserMedia ONLY — never opens native camera app      -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="cam-scanner-overlay" id="camScannerOverlay">

  <!-- Camera feed fills full screen -->
  <video id="camVideo" autoplay playsinline muted></video>

  <!-- Top bar: title + close -->
  <div class="cam-top-bar">
    <div class="cam-top-title">
      <i class="bi bi-camera-video-fill" style="color:#80ccff;"></i>
      Capture ID Photo
    </div>
    <button class="cam-close-btn" id="camCloseBtn" onclick="closeCameraModal()" title="Close">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- Tip pill (shown once camera is live) -->
  <div id="camTipBar">
    <span class="cam-tip-label">📋 Align your ID inside the red frame</span>
  </div>

  <!-- Bottom bar: switch + shutter -->
  <div class="cam-bottom-bar">
    <button class="cam-switch-btn" id="camSwitchBtn" onclick="switchCamera()" disabled title="Switch camera">
      <i class="bi bi-arrow-repeat"></i>
    </button>
    <button class="cam-capture-btn" id="camCaptureBtn" onclick="capturePhoto()" disabled title="Capture">
      <i class="bi bi-camera-fill"></i>
    </button>
    <button class="cam-flash-btn" id="camFlashBtn" onclick="toggleCamFlash()" disabled title="Toggle flash">
      <i class="bi bi-lightning-fill"></i>
    </button>
  </div>

  <!-- Error state (overlaid, shown when getUserMedia fails) -->
  <div id="camErrorMsg" style="display:none;">
    <i class="bi bi-camera-video-off-fill"></i>
    <strong>Camera unavailable</strong>
    <span id="camErrorText">Could not access your camera. Please allow camera permission and try again.</span>
    <button onclick="closeCameraModal()" style="margin-top:24px;padding:12px 28px;border-radius:14px;background:rgba(255,255,255,0.1);border:1.5px solid rgba(255,255,255,0.2);color:#fff;font-size:14px;cursor:pointer;font-family:'Poppins',sans-serif;">Close</button>
  </div>

  <!-- Hidden canvas for photo capture -->
  <canvas id="camCanvas" style="display:none;"></canvas>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- MOBILE GUIDE SCREEN                                        -->
<!-- Draws the scanning frame guide, then launches native camera -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="mob-guide-overlay" id="mobGuideOverlay">

  <!-- Top bar -->
  <div class="mob-guide-topbar">
    <div class="mob-guide-title">
      <i class="bi bi-camera-fill" style="color:#80ccff;"></i>
      ID Scanner Guide
    </div>
    <button class="mob-guide-close" onclick="closeMobGuide()">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <!-- Canvas: shows the scanning frame guide statically -->
  <div class="mob-guide-canvas-wrap">
    <canvas id="mobGuideCanvas"></canvas>
  </div>

  <!-- Footer: instructions + launch button -->
  <div class="mob-guide-footer">
    <p class="mob-guide-hint">
      Tap the button below to open your camera.<br>
      <strong style="color:rgba(255,255,255,0.8);">Align your ID inside the red frame</strong><br>
      then tap the shutter button.
    </p>
    <button class="mob-guide-btn" onclick="launchMobileCamera()" title="Open Camera">
      <i class="bi bi-camera-fill"></i>
    </button>
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

  // ── Canvas scanning frame animation state
  let _canvasRaf  = null;   // requestAnimationFrame handle
  let _scanY      = 0;      // current scan line Y (0-1, relative to frame)
  let _scanDir    = 1;      // 1 = down, -1 = up
  let _cornerPulse = 0;     // for corner glow animation
  let _camFlashOn = false, _camFocusRing = null; // flash + tap-to-focus

  function startCanvasOverlay() {
    // Remove stale canvas if any
    const old = document.getElementById('camOverlayCanvas');
    if (old) old.remove();

    // Inject canvas directly into <body> — sits above ALL hardware layers
    const canvas = document.createElement('canvas');
    canvas.id = 'camOverlayCanvas';
    Object.assign(canvas.style, {
      position: 'fixed', top: '0', left: '0',
      width: '100vw', height: '100vh',
      zIndex: '9999', pointerEvents: 'none', display: 'block'
    });
    document.body.appendChild(canvas);

    // Show tip
    const tipBar = document.getElementById('camTipBar');
    if (tipBar) tipBar.style.display = 'flex';

    _scanY = 0; _scanDir = 1; _cornerPulse = 0;

    function drawFrame() {
      const dpr = window.devicePixelRatio || 1;
      const VW  = window.innerWidth;
      const VH  = window.innerHeight;
      canvas.width  = VW * dpr;
      canvas.height = VH * dpr;
      const ctx = canvas.getContext('2d');
      ctx.scale(dpr, dpr);
      ctx.clearRect(0, 0, VW, VH);

      // Frame: credit-card ratio centred, shifted slightly up
      const fW = Math.min(VW * 0.86, VH * 1.586 * 0.62);
      const fH = fW / 1.586;
      const fX = (VW - fW) / 2;
      const fY = (VH - fH) / 2 - VH * 0.04;
      const r  = 12;

      // Dark vignette
      ctx.fillStyle = 'rgba(0,0,0,0.60)';
      ctx.fillRect(0, 0, VW, VH);

      // Clear window
      ctx.save();
      ctx.globalCompositeOperation = 'destination-out';
      roundRect(ctx, fX, fY, fW, fH, r);
      ctx.fill();
      ctx.restore();

      // Animated scan line (NO shadowBlur — Android Chrome bug causes invisible strokes)
      _scanY += _scanDir * 2.2;
      if (_scanY >= fH - 2) { _scanY = fH - 2; _scanDir = -1; }
      if (_scanY <= 0)       { _scanY = 0;       _scanDir =  1; }
      const sy = fY + _scanY;
      const sg = ctx.createLinearGradient(fX, sy, fX + fW, sy);
      sg.addColorStop(0,   'rgba(255,30,30,0)');
      sg.addColorStop(0.5, 'rgba(255,60,60,1)');
      sg.addColorStop(1,   'rgba(255,30,30,0)');
      ctx.strokeStyle = sg;
      ctx.lineWidth   = 2.5;
      ctx.beginPath();
      ctx.moveTo(fX + 8, sy); ctx.lineTo(fX + fW - 8, sy);
      ctx.stroke();

      // Corner brackets — double-pass: halo then solid (NO shadowBlur)
      _cornerPulse += 0.05;
      const pulse = 0.5 + 0.5 * Math.abs(Math.sin(_cornerPulse));
      const cL = 30;
      const brackets = [
        [fX+r+cL, fY,      fX+r,    fY,      fX,    fY,      fX,    fY+r,    fX,    fY+r+cL],
        [fX+fW-r-cL, fY,   fX+fW-r, fY,      fX+fW, fY,      fX+fW, fY+r,    fX+fW, fY+r+cL],
        [fX,  fY+fH-r-cL,  fX,      fY+fH-r, fX,    fY+fH,   fX+r,  fY+fH,   fX+r+cL, fY+fH],
        [fX+fW, fY+fH-r-cL, fX+fW,  fY+fH-r, fX+fW, fY+fH,   fX+fW-r, fY+fH, fX+fW-r-cL, fY+fH]
      ];
      function drawBrackets(color, lw) {
        ctx.save();
        ctx.strokeStyle = color; ctx.lineWidth = lw; ctx.lineCap = 'round';
        brackets.forEach(p => {
          ctx.beginPath();
          ctx.moveTo(p[0],p[1]); ctx.lineTo(p[2],p[3]);
          ctx.quadraticCurveTo(p[4],p[5],p[6],p[7]); ctx.lineTo(p[8],p[9]);
          ctx.stroke();
        });
        ctx.restore();
      }
      drawBrackets(`rgba(255,40,40,${(0.35+0.25*pulse).toFixed(2)})`, 10); // glow halo
      drawBrackets('#FF2828', 4);                                             // solid line

      // ── Focus ring (tap-to-focus indicator)
      if (_camFocusRing) {
        const elapsed = Date.now() - _camFocusRing.t;
        if (elapsed < 900) {
          const alpha = Math.max(0, 1 - elapsed / 900);
          const sz = 64 + 20 * (1 - alpha);
          ctx.save();
          ctx.strokeStyle = `rgba(255,220,0,${alpha})`;
          ctx.lineWidth = 2;
          ctx.strokeRect(_camFocusRing.x - sz/2, _camFocusRing.y - sz/2, sz, sz);
          const tk = 10;
          ctx.strokeStyle = `rgba(255,255,255,${alpha})`;
          ctx.lineWidth = 2;
          [[-1,-1],[1,-1],[-1,1],[1,1]].forEach(([sx,sy]) => {
            const cx = _camFocusRing.x + sx*(sz/2), cy = _camFocusRing.y + sy*(sz/2);
            ctx.beginPath(); ctx.moveTo(cx, cy-sy*tk); ctx.lineTo(cx, cy); ctx.lineTo(cx+sx*tk, cy); ctx.stroke();
          });
          ctx.restore();
        } else { _camFocusRing = null; }
      }

      _canvasRaf = requestAnimationFrame(drawFrame);
    }

    if (_canvasRaf) cancelAnimationFrame(_canvasRaf);
    drawFrame();
  }

  function stopCanvasOverlay() {
    if (_canvasRaf) { cancelAnimationFrame(_canvasRaf); _canvasRaf = null; }
    // Remove the body-level canvas
    const canvas = document.getElementById('camOverlayCanvas');
    if (canvas) canvas.remove();
    const tipBar = document.getElementById('camTipBar');
    if (tipBar) tipBar.style.display = 'none';
  }

  // Helper: draw rounded rectangle path
  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x+w, y,   x+w, y+r);
    ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x+w, y+h, x+w-r, y+h);
    ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x,   y+h, x,   y+h-r);
    ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x,   y,   x+r, y);
    ctx.closePath();
  }

  // ─────────────────────────────────────────────────────────────────
  // MOBILE GUIDE (guide screen + native camera capture)
  // ─────────────────────────────────────────────────────────────────
  function isMobileDevice() {
    return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
  }

  function openMobGuide() {
    document.getElementById('mobGuideOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
    // Draw after layout settles
    requestAnimationFrame(() => requestAnimationFrame(drawGuideFrame));
  }

  function closeMobGuide() {
    document.getElementById('mobGuideOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }

  function launchMobileCamera() {
    closeMobGuide();
    document.getElementById('mobileCameraInput').click();
  }

  function drawGuideFrame() {
    const canvas = document.getElementById('mobGuideCanvas');
    if (!canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const W   = canvas.offsetWidth  || window.innerWidth;
    const H   = canvas.offsetHeight || Math.round(window.innerHeight * 0.6);
    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, W, H);

    // Background
    const bg = ctx.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#0d0d18');
    bg.addColorStop(1, '#08080f');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    // Frame: credit-card ratio centred
    const fW = Math.min(W * 0.82, H * 1.586 * 0.75);
    const fH = fW / 1.586;
    const fX = (W - fW) / 2;
    const fY = (H - fH) / 2;
    const r  = 12;

    // Dark vignette then punch clear window
    ctx.fillStyle = 'rgba(0,0,0,0.5)';
    ctx.fillRect(0, 0, W, H);
    ctx.save();
    ctx.globalCompositeOperation = 'destination-out';
    roundRect(ctx, fX, fY, fW, fH, r);
    ctx.fill();
    ctx.restore();

    // ID card body inside frame
    ctx.save();
    ctx.fillStyle = 'rgba(20,25,50,0.92)';
    roundRect(ctx, fX, fY, fW, fH, r);
    ctx.fill();
    ctx.restore();

    // Header stripe
    ctx.fillStyle = 'rgba(0,126,210,0.14)';
    ctx.fillRect(fX, fY, fW, fH * 0.28);

    // Placeholder text lines
    ctx.fillStyle = 'rgba(255,255,255,0.09)';
    const lH = fH * 0.06;
    [0,1,2].forEach(i => {
      ctx.fillRect(fX + fW*0.08, fY + fH*0.38 + i*(lH*1.7), fW*0.52, lH);
    });

    // Photo box placeholder
    ctx.strokeStyle = 'rgba(255,255,255,0.12)';
    ctx.lineWidth = 1;
    ctx.strokeRect(fX + fW*0.73, fY + fH*0.26, fW*0.19, fH*0.48);
    ctx.fillStyle = 'rgba(255,255,255,0.04)';
    ctx.fillRect(fX + fW*0.73, fY + fH*0.26, fW*0.19, fH*0.48);

    // Red corner brackets
    ctx.save();
    ctx.strokeStyle = '#E90101';
    ctx.lineWidth   = 4;
    ctx.lineCap     = 'round';
    ctx.shadowColor = 'rgba(233,1,1,0.9)';
    ctx.shadowBlur  = 14;
    const cL = 26;
    // top-left
    ctx.beginPath();
    ctx.moveTo(fX+r+cL, fY); ctx.lineTo(fX+r, fY);
    ctx.quadraticCurveTo(fX, fY, fX, fY+r); ctx.lineTo(fX, fY+r+cL);
    ctx.stroke();
    // top-right
    ctx.beginPath();
    ctx.moveTo(fX+fW-r-cL, fY); ctx.lineTo(fX+fW-r, fY);
    ctx.quadraticCurveTo(fX+fW, fY, fX+fW, fY+r); ctx.lineTo(fX+fW, fY+r+cL);
    ctx.stroke();
    // bottom-left
    ctx.beginPath();
    ctx.moveTo(fX, fY+fH-r-cL); ctx.lineTo(fX, fY+fH-r);
    ctx.quadraticCurveTo(fX, fY+fH, fX+r, fY+fH); ctx.lineTo(fX+r+cL, fY+fH);
    ctx.stroke();
    // bottom-right
    ctx.beginPath();
    ctx.moveTo(fX+fW, fY+fH-r-cL); ctx.lineTo(fX+fW, fY+fH-r);
    ctx.quadraticCurveTo(fX+fW, fY+fH, fX+fW-r, fY+fH); ctx.lineTo(fX+fW-r-cL, fY+fH);
    ctx.stroke();
    ctx.restore();

    // "ALIGN ID HERE" label inside frame
    ctx.save();
    ctx.font = `bold ${Math.max(11, Math.round(fH*0.09))}px Poppins,sans-serif`;
    ctx.fillStyle = 'rgba(255,255,255,0.22)';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('ALIGN ID INSIDE FRAME', fX + fW/2, fY + fH*0.12);
    ctx.restore();
  }

  // ─────────────────────────────────────────────────────────────────
  // DESKTOP IN-BROWSER SCANNER (getUserMedia)
  // ─────────────────────────────────────────────────────────────────
  async function openCameraModal() {
    // Try getUserMedia on ALL devices (mobile + desktop)
    // If it fails on mobile, the catch block falls back to the guide screen

    // ── getUserMedia path
    const overlay    = document.getElementById('camScannerOverlay');
    const videoEl    = document.getElementById('camVideo');
    const errorMsg   = document.getElementById('camErrorMsg');
    const captureBtn = document.getElementById('camCaptureBtn');
    const switchBtn  = document.getElementById('camSwitchBtn');
    const tipBar     = document.getElementById('camTipBar');

    errorMsg.style.display  = 'none';
    videoEl.style.display   = 'block';
    captureBtn.disabled     = true;
    switchBtn.disabled      = true;
    if (tipBar) tipBar.style.display = 'none';
    camDevices     = [];
    camDeviceIndex = 0;

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showCamError('Your browser does not support in-browser camera access. Please use "Choose File" instead.');
      return;
    }

    try {
      camStream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
        audio: false
      });
      videoEl.srcObject = camStream;
      await videoEl.play();
      captureBtn.disabled = false;
      startCanvasOverlay();
      // Check torch support
      try {
        const track = camStream.getVideoTracks()[0];
        const caps  = track.getCapabilities ? track.getCapabilities() : {};
        const fb = document.getElementById('camFlashBtn');
        if (fb && caps.torch) fb.disabled = false;
      } catch(_) {}
      try {
        const allDevices = await navigator.mediaDevices.enumerateDevices();
        camDevices = allDevices.filter(d => d.kind === 'videoinput' && d.deviceId);
        if (camDevices.length > 1) switchBtn.disabled = false;
      } catch (_) {}
    } catch (err) {
      let msg;
      switch (err.name) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
          msg = 'Camera permission denied. Tap the lock icon → Camera → Allow, then try again.';
          break;
        case 'NotFoundError':
          msg = 'No camera detected. Use "Choose File" to upload a photo instead.';
          break;
        case 'NotReadableError':
          msg = 'Camera is in use by another application. Close it and try again.';
          break;
        case 'SecurityError':
          msg = 'Camera blocked (requires HTTPS). Use the Cloudflare tunnel URL.';
          break;
        default:
          msg = `Camera error: ${err.message || err.name}.`;
      }
      if (isMobileDevice()) {
        // getUserMedia failed on mobile — fall back to guide + native camera
        document.getElementById('camScannerOverlay').classList.remove('open');
        document.body.style.overflow = '';
        openMobGuide();
      } else {
        showCamError(msg);
      }
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
    stopCanvasOverlay();
    // Turn off torch before stopping stream
    if (_camFlashOn && camStream) {
      try { camStream.getVideoTracks()[0]?.applyConstraints({ advanced:[{torch:false}] }); } catch(_) {}
    }
    _camFlashOn = false; _camFocusRing = null;
    const fb = document.getElementById('camFlashBtn');
    if (fb) { fb.disabled = true; fb.classList.remove('on'); }
    if (camStream) {
      camStream.getTracks().forEach(t => t.stop());
      camStream = null;
    }
    document.getElementById('camVideo').srcObject = null;
    document.getElementById('camScannerOverlay').classList.remove('open');
    document.body.style.overflow = '';
  }

  function handleBackdropClick(e) { /* no-op: fullscreen scanner has no backdrop */ }

  function showCamError(msg) {
    document.getElementById('camVideo').style.display = 'none';
    document.getElementById('camErrorText').textContent = msg;
    document.getElementById('camErrorMsg').style.display = 'flex';
    stopCanvasOverlay();
  }

  // ── Flash toggle
  async function toggleCamFlash() {
    if (!camStream) return;
    const track = camStream.getVideoTracks()[0];
    if (!track) return;
    _camFlashOn = !_camFlashOn;
    try {
      await track.applyConstraints({ advanced: [{ torch: _camFlashOn }] });
      const fb = document.getElementById('camFlashBtn');
      if (fb) fb.classList.toggle('on', _camFlashOn);
    } catch(e) { _camFlashOn = false; }
  }

  // ── Tap-to-focus
  async function handleCamTapFocus(clientX, clientY) {
    if (!camStream) return;
    const track = camStream.getVideoTracks()[0];
    if (!track) return;
    _camFocusRing = { x: clientX, y: clientY, t: Date.now() };
    try {
      const caps = track.getCapabilities ? track.getCapabilities() : {};
      const adv  = {};
      if (caps.focusMode?.includes('single-shot')) adv.focusMode = 'single-shot';
      else if (caps.focusMode?.includes('manual')) adv.focusMode = 'manual';
      if (caps.pointsOfInterest) {
        const vid  = document.getElementById('camVideo');
        const rect = vid.getBoundingClientRect();
        adv.pointsOfInterest = [{
          x: Math.max(0, Math.min(1, (clientX - rect.left) / rect.width)),
          y: Math.max(0, Math.min(1, (clientY - rect.top)  / rect.height))
        }];
      }
      if (Object.keys(adv).length) await track.applyConstraints({ advanced: [adv] });
    } catch(e) { /* ring still shows */ }
  }

  // Wire tap-to-focus on scanner overlay
  (function() {
    const ov = document.getElementById('camScannerOverlay');
    if (!ov) return;
    ov.addEventListener('click', e => {
      if (e.target.closest('button, .cam-top-bar, .cam-bottom-bar')) return;
      handleCamTapFocus(e.clientX, e.clientY);
    });
    ov.addEventListener('touchend', e => {
      if (e.target.closest('button, .cam-top-bar, .cam-bottom-bar')) return;
      e.preventDefault();
      const t = e.changedTouches[0];
      handleCamTapFocus(t.clientX, t.clientY);
    }, { passive: false });
  })();

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

    // Update upload zone — hide the card guide, show check icon
    uploadZone.classList.add('has-file');
    const guide = document.getElementById('idCardGuide');
    if (guide) {
      guide.innerHTML = '<div class="idv-cg-br"></div><div class="idv-cg-bl"></div>'
        + '<div class="idv-card-silhouette" style="border-color:rgba(0,200,83,0.4);">'
        + '<i class="bi bi-check-circle-fill" style="font-size:28px;color:#00c853;"></i>'
        + '<span style="color:rgba(0,200,83,0.7);">Document selected</span></div>';
    }
    uploadSub.textContent = file.name;
    verifyBtn.disabled = false;
  }

  function removeFile() {
    selectedFile = null;
    previewImg.src = '';
    previewWrap.style.display = 'none';
    uploadZone.classList.remove('has-file');
    // Restore card guide
    const guide = document.getElementById('idCardGuide');
    if (guide) {
      guide.innerHTML = '<div class="idv-cg-br"></div><div class="idv-cg-bl"></div>'
        + '<div class="idv-card-silhouette">'
        + '<i class="bi bi-person-vcard" style="font-size:28px;color:rgba(0,126,210,0.4);"></i>'
        + '<span>Place your ID here</span></div>';
    }
    uploadSub.textContent = 'Tap to upload \u2014 JPG, PNG, WebP \u2022 Max 10 MB';
    verifyBtn.disabled = true;
    document.getElementById('idFileInput').value  = '';
    if (document.getElementById('cameraInput')) document.getElementById('cameraInput').value = '';
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
