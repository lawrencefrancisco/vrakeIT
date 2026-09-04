<?php
// =====================================================================
// verify.php — OCR-Based Identity Verification
// Central verification page for all users (new + returning).
// Automatically updates account to Verified on OCR success.
// =====================================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();

// Prevent bfcache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$user = getLoggedInUser();
if (!$user) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Enforcers don't use this page
if (($user['role'] ?? 'user') !== 'user') {
    header('Location: ' . BASE_URL . '/enforcer_landing.php');
    exit;
}

$verStatus  = $user['verification_status'] ?? 'Unverified';

// Pull last rejection reason for context (portal source)
$db = getDB();
$lastRej = null;
if ($verStatus === 'Unverified' || $verStatus === 'Rejected') {
    $stmt = $db->prepare(
        "SELECT ocr_failure_reason, created_at FROM id_verifications
         WHERE user_id = ? ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $lastRej = $stmt->fetch();
}

// Session DOB from registration flow (might not exist for returning users)
$sessionDob = $_SESSION['reg_birthdate'] ?? '';
$justRegistered = !empty($_SESSION['just_registered']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT – Verify Your Identity</title>
  <meta name="description" content="Verify your identity with a government-issued ID or driver's license to unlock all VrakeIT features.">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    /* ════════════════════════════════
       VERIFY PAGE — DESIGN SYSTEM
    ════════════════════════════════ */
    :root {
      --vr-red:        #E90101;
      --vr-red-glow:   rgba(233,1,1,0.25);
      --vr-blue:       #007ED2;
      --vr-blue-glow:  rgba(0,126,210,0.25);
      --vr-green:      #00c853;
      --vr-green-glow: rgba(0,200,83,0.25);
      --vr-dark:       #0d0d14;
      --vr-card:       rgba(255,255,255,0.06);
      --vr-border:     rgba(255,255,255,0.10);
      --vr-text:       #f0f0f8;
      --vr-muted:      rgba(240,240,248,0.55);
    }

    html, body { height: 100%; }
    body {
      background: linear-gradient(145deg, #0d0d18 0%, #111126 50%, #0a0a12 100%);
      font-family: 'Poppins', sans-serif;
      color: var(--vr-text);
      min-height: 100vh;
      background-attachment: fixed;
    }

    /* ── HEADER ── */
    .vfy-header {
      position: sticky;
      top: 0;
      z-index: 50;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 20px;
      background: rgba(13,13,20,0.85);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--vr-border);
    }
    .vfy-header-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 18px;
      font-weight: 700;
      color: #fff;
      text-decoration: none;
    }
    .vfy-header-logo span.dot { color: var(--vr-red); }

    /* ── MAIN CONTAINER ── */
    .vfy-container {
      max-width: 520px;
      margin: 0 auto;
      padding: 24px 16px 60px;
    }

    /* ── STATUS BADGE ── */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.4px;
    }
    .status-pill.unverified { background: rgba(233,1,1,0.18); color: #ff6b6b; border: 1px solid rgba(233,1,1,0.35); }
    .status-pill.verified   { background: rgba(0,200,83,0.18); color: #4dde8a; border: 1px solid rgba(0,200,83,0.35); }
    .status-pill.pending    { background: rgba(255,193,7,0.18); color: #ffc107; border: 1px solid rgba(255,193,7,0.35); }

    /* ── HERO BANNER ── */
    .vfy-hero {
      background: linear-gradient(135deg, rgba(233,1,1,0.18) 0%, rgba(0,126,210,0.12) 100%);
      border: 1px solid var(--vr-border);
      border-radius: 20px;
      padding: 28px 24px;
      text-align: center;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }
    .vfy-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at 50% -20%, rgba(233,1,1,0.12) 0%, transparent 70%);
      pointer-events: none;
    }
    .vfy-hero-icon {
      width: 72px; height: 72px;
      background: linear-gradient(135deg, var(--vr-red), #b00000);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
      font-size: 32px;
      box-shadow: 0 8px 32px var(--vr-red-glow);
    }
    .vfy-hero h1 {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    .vfy-hero p {
      font-size: 13px;
      color: var(--vr-muted);
      line-height: 1.6;
    }
    .vfy-steps {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 18px;
      flex-wrap: wrap;
    }
    .vfy-step-chip {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      color: var(--vr-muted);
      padding: 4px 10px;
      border-radius: 12px;
      background: rgba(255,255,255,0.07);
      border: 1px solid var(--vr-border);
    }
    .vfy-step-chip i { color: var(--vr-blue); }

    /* ── CARD ── */
    .vfy-card {
      background: var(--vr-card);
      backdrop-filter: blur(12px);
      border: 1px solid var(--vr-border);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 16px;
    }
    .vfy-section-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--vr-muted);
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .vfy-section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--vr-border);
    }

    /* ── DOB FIELD (for returning users) ── */
    .vfy-dob-wrap { display: none; }
    .vfy-dob-wrap.show { display: block; }
    .vfy-input {
      background: rgba(255,255,255,0.07);
      border: 1.5px solid var(--vr-border);
      border-radius: 12px;
      color: var(--vr-text);
      padding: 12px 16px;
      font-family: 'Poppins', sans-serif;
      font-size: 14px;
      width: 100%;
      transition: border-color 0.2s;
    }
    .vfy-input:focus {
      outline: none;
      border-color: var(--vr-blue);
      background: rgba(0,126,210,0.08);
    }
    .vfy-input-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: var(--vr-muted);
      margin-bottom: 6px;
    }

    /* ── UPLOAD ZONE ── */
    .vfy-drop-zone {
      border: 2px dashed rgba(0,126,210,0.45);
      border-radius: 16px;
      padding: 36px 20px;
      text-align: center;
      cursor: pointer;
      background: rgba(0,126,210,0.05);
      transition: all 0.25s ease;
      position: relative;
    }
    .vfy-drop-zone:hover, .vfy-drop-zone.dragover {
      border-color: var(--vr-blue);
      background: rgba(0,126,210,0.12);
      transform: translateY(-2px);
    }
    .vfy-drop-zone.has-file {
      border-color: var(--vr-green);
      background: rgba(0,200,83,0.07);
    }
    .vfy-drop-icon {
      width: 56px; height: 56px;
      background: rgba(0,126,210,0.15);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 14px;
      font-size: 26px;
      color: var(--vr-blue);
      transition: all 0.25s;
    }
    .vfy-drop-zone.has-file .vfy-drop-icon {
      background: rgba(0,200,83,0.15);
      color: var(--vr-green);
    }
    .vfy-drop-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
    .vfy-drop-sub   { font-size: 12px; color: var(--vr-muted); }

    /* Camera row */
    .vfy-capture-row {
      display: flex;
      gap: 10px;
      margin-top: 14px;
    }
    .vfy-btn-ghost {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      padding: 11px;
      border-radius: 12px;
      border: 1.5px solid var(--vr-border);
      background: rgba(255,255,255,0.05);
      color: var(--vr-text);
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: 'Poppins', sans-serif;
      transition: all 0.2s;
    }
    .vfy-btn-ghost:hover {
      background: rgba(255,255,255,0.1);
      border-color: rgba(255,255,255,0.25);
    }
    .vfy-btn-ghost i { font-size: 16px; }
    .vfy-btn-ghost.camera-btn i { color: var(--vr-blue); }
    .vfy-btn-ghost.file-btn   i { color: #a78bfa; }

    /* Preview */
    .vfy-preview-wrap {
      margin-top: 14px;
      border-radius: 14px;
      overflow: hidden;
      position: relative;
      display: none;
    }
    .vfy-preview-wrap.show { display: block; }
    .vfy-preview-img {
      width: 100%;
      max-height: 220px;
      object-fit: cover;
      display: block;
    }
    .vfy-preview-remove {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 32px; height: 32px;
      border-radius: 50%;
      background: rgba(233,1,1,0.85);
      border: none;
      color: #fff;
      font-size: 14px;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    .vfy-preview-remove:hover { background: var(--vr-red); }

    /* ── TIPS ── */
    .vfy-tips {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }
    .vfy-tip {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--vr-border);
      border-radius: 12px;
      padding: 12px;
      font-size: 12px;
      color: var(--vr-muted);
      display: flex;
      gap: 8px;
      align-items: flex-start;
    }
    .vfy-tip i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
    .vfy-tip.good i { color: var(--vr-green); }
    .vfy-tip.bad  i { color: var(--vr-red); }

    /* ── ALERT ── */
    .vfy-alert {
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 13px;
      display: none;
      margin-bottom: 16px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    .vfy-alert.danger  { background: rgba(233,1,1,0.12); border: 1px solid rgba(233,1,1,0.30); color: #ff8a8a; }
    .vfy-alert.success { background: rgba(0,200,83,0.12); border: 1px solid rgba(0,200,83,0.30); color: #4dde8a; }
    .vfy-alert.warning { background: rgba(255,193,7,0.12); border: 1px solid rgba(255,193,7,0.30); color: #ffc107; }
    .vfy-alert.info    { background: rgba(0,126,210,0.12); border: 1px solid rgba(0,126,210,0.30); color: #60b8ff; }
    .vfy-alert i { font-size: 17px; flex-shrink: 0; margin-top: 1px; }

    /* ── SUBMIT BUTTON ── */
    .vfy-submit-btn {
      width: 100%;
      padding: 16px;
      border-radius: 14px;
      border: none;
      background: linear-gradient(135deg, var(--vr-red) 0%, #b00000 100%);
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: all 0.25s;
      box-shadow: 0 6px 24px var(--vr-red-glow);
      letter-spacing: 0.3px;
    }
    .vfy-submit-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 10px 32px rgba(233,1,1,0.4);
    }
    .vfy-submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    /* ── PROGRESS OVERLAY ── */
    .vfy-progress-overlay {
      position: fixed;
      inset: 0;
      background: rgba(13,13,20,0.90);
      backdrop-filter: blur(8px);
      z-index: 1000;
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 24px;
      text-align: center;
      padding: 40px;
    }
    .vfy-progress-overlay.show { display: flex; }
    .vfy-progress-ring {
      width: 90px; height: 90px;
      border-radius: 50%;
      border: 3px solid rgba(255,255,255,0.08);
      border-top-color: var(--vr-blue);
      border-right-color: var(--vr-red);
      animation: vfy-spin 1s linear infinite;
    }
    @keyframes vfy-spin { to { transform: rotate(360deg); } }
    .vfy-progress-steps { display: flex; flex-direction: column; gap: 10px; max-width: 300px; }
    .vfy-progress-step {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      color: var(--vr-muted);
      transition: color 0.3s;
    }
    .vfy-progress-step.active  { color: var(--vr-text); }
    .vfy-progress-step.done    { color: var(--vr-green); }
    .vfy-progress-step.current { color: var(--vr-blue); animation: vfy-pulse 1.5s ease-in-out infinite; }
    @keyframes vfy-pulse { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
    .vfy-step-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }
    .vfy-progress-step.done .vfy-step-dot::before { content: '✓'; font-size: 10px; }

    /* ── RESULT STATES ── */
    .vfy-result-card {
      text-align: center;
      padding: 40px 24px;
    }
    .vfy-result-icon {
      width: 96px; height: 96px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 44px;
      margin: 0 auto 20px;
    }
    .vfy-result-icon.success {
      background: radial-gradient(circle, rgba(0,200,83,0.25) 0%, rgba(0,200,83,0.05) 70%);
      border: 2px solid rgba(0,200,83,0.35);
      box-shadow: 0 0 40px var(--vr-green-glow);
      animation: vfy-pop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
    }
    .vfy-result-icon.error {
      background: radial-gradient(circle, rgba(233,1,1,0.20) 0%, rgba(233,1,1,0.05) 70%);
      border: 2px solid rgba(233,1,1,0.35);
      box-shadow: 0 0 40px var(--vr-red-glow);
    }
    @keyframes vfy-pop {
      0%   { transform: scale(0.5); opacity: 0; }
      100% { transform: scale(1);   opacity: 1; }
    }
    .vfy-result-title { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
    .vfy-result-msg   { font-size: 14px; color: var(--vr-muted); line-height: 1.6; margin-bottom: 24px; }
    .vfy-result-ref   { font-size: 11px; color: var(--vr-muted); margin-bottom: 20px; font-family: monospace; }

    /* ── ALREADY VERIFIED ── */
    .vfy-verified-wrap {
      text-align: center;
      padding: 60px 24px;
    }
    .vfy-verified-badge {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: rgba(0,200,83,0.12);
      border: 1.5px solid rgba(0,200,83,0.30);
      border-radius: 20px;
      padding: 10px 24px;
      font-size: 14px;
      font-weight: 700;
      color: #4dde8a;
      margin-bottom: 20px;
    }
    .vfy-shield-big {
      width: 100px; height: 100px;
      background: radial-gradient(circle, rgba(0,200,83,0.20) 0%, transparent 70%);
      border: 2px solid rgba(0,200,83,0.30);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 48px;
      margin: 0 auto 24px;
      box-shadow: 0 0 50px rgba(0,200,83,0.20);
      animation: vfy-glow-pulse 3s ease-in-out infinite;
    }
    @keyframes vfy-glow-pulse {
      0%,100% { box-shadow: 0 0 50px rgba(0,200,83,0.20); }
      50%      { box-shadow: 0 0 70px rgba(0,200,83,0.35); }
    }

    /* ── PENDING ── */
    .vfy-pending-wrap { text-align: center; padding: 50px 24px; }
    .vfy-pending-icon {
      width: 90px; height: 90px;
      border-radius: 50%;
      border: 2px solid rgba(255,193,7,0.35);
      display: flex; align-items: center; justify-content: center;
      font-size: 40px;
      margin: 0 auto 20px;
      background: rgba(255,193,7,0.08);
      animation: vfy-pending-spin 8s linear infinite;
    }
    @keyframes vfy-pending-spin { to { border-color: rgba(0,126,210,0.35) rgba(255,193,7,0.35) rgba(0,200,83,0.35) rgba(233,1,1,0.35); } }



    /* ── BACK LINK ── */
    .vfy-back-link {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--vr-muted);
      font-size: 13px;
      text-decoration: none;
      padding: 12px 0;
      transition: color 0.2s;
    }
    .vfy-back-link:hover { color: var(--vr-text); }

    /* ── SPINNER ── */
    .spinner-sm {
      display: inline-block;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: vfy-spin 0.7s linear infinite;
      vertical-align: middle;
    }

    /* ── OCR RESULTS PANEL ── */
    .ocr-results-panel {
      background: rgba(255,255,255,0.04);
      border: 1px solid var(--vr-border);
      border-radius: 16px;
      padding: 18px;
      margin-bottom: 16px;
      animation: vfy-pop 0.4s cubic-bezier(0.175,0.885,0.32,1.275) both;
    }
    .ocr-results-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--vr-muted);
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .ocr-field-row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 9px 0;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      font-size: 13px;
    }
    .ocr-field-row:last-child { border-bottom: none; }
    .ocr-field-label {
      color: var(--vr-muted);
      font-size: 11px;
      font-weight: 600;
      min-width: 90px;
      padding-top: 2px;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }
    .ocr-field-value {
      flex: 1;
      font-weight: 600;
      word-break: break-word;
    }
    .ocr-field-value.empty { color: var(--vr-muted); font-weight: 400; font-style: italic; }
    .ocr-match-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 10px;
      white-space: nowrap;
      margin-left: 6px;
    }
    .ocr-match-badge.match   { background: rgba(0,200,83,0.18); color: #4dde8a; }
    .ocr-match-badge.no-match { background: rgba(233,1,1,0.18);  color: #ff8a8a; }
    .ocr-match-badge.neutral { background: rgba(255,255,255,0.08); color: var(--vr-muted); }
    .ocr-confidence-bar {
      height: 4px;
      border-radius: 2px;
      background: rgba(255,255,255,0.08);
      overflow: hidden;
      margin-top: 10px;
    }
    .ocr-confidence-fill {
      height: 100%;
      border-radius: 2px;
      transition: width 0.8s ease;
    }
    .ocr-engine-badge {
      font-size: 10px;
      color: var(--vr-muted);
      margin-top: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    /* failure reason box */
    .ocr-fail-reason {
      background: rgba(233,1,1,0.08);
      border: 1px solid rgba(233,1,1,0.22);
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 12px;
      color: #ff8a8a;
      margin-top: 10px;
      line-height: 1.6;
    }
    .ocr-fail-reason strong { display: block; margin-bottom: 4px; font-size: 13px; }

    /* ── RESPONSIVE ── */
    @media (max-width: 480px) {
      .vfy-tips { grid-template-columns: 1fr; }
      .vfy-hero h1 { font-size: 19px; }
      .ocr-field-label { min-width: 70px; }
    }
  </style>
</head>
<body>

  <!-- ── HEADER ── -->
  <header class="vfy-header">
    <a href="landing.php" class="vfy-header-logo">
      <img src="assets/img/system_logo.png" alt="VrakeIT" style="height:28px;border-radius:6px;">
      Vrake<span class="dot">IT</span>
    </a>
    <?php if ($verStatus === 'Verified'): ?>
      <span class="status-pill verified"><i class="bi bi-patch-check-fill"></i> Verified</span>
    <?php elseif ($verStatus === 'Pending'): ?>
      <span class="status-pill pending"><i class="bi bi-hourglass-split"></i> Pending</span>
    <?php else: ?>
      <span class="status-pill unverified"><i class="bi bi-shield-exclamation"></i> Unverified</span>
    <?php endif; ?>
  </header>

  <!-- ═══════════════════════════════════════════════ -->
  <!--   STATE: ALREADY VERIFIED                       -->
  <!-- ═══════════════════════════════════════════════ -->
  <?php if ($verStatus === 'Verified'): ?>
  <div class="vfy-container">
    <div class="vfy-verified-wrap">
      <div class="vfy-shield-big">🛡️</div>
      <div class="vfy-verified-badge">
        <i class="bi bi-patch-check-fill"></i> Identity Verified
      </div>
      <h2 style="font-size:20px;font-weight:700;margin-bottom:10px;">Your account is verified!</h2>
      <p style="color:var(--vr-muted);font-size:14px;line-height:1.7;margin-bottom:28px;">
        Your identity has already been confirmed. You have full access to all VrakeIT features including filing reports and earning Good Citizen points.
      </p>
      <?php if (!empty($user['verified_at'])): ?>
      <div style="font-size:12px;color:var(--vr-muted);margin-bottom:24px;">
        Verified on <?= date('F j, Y', strtotime($user['verified_at'])) ?>
        <?php if ($user['verification_method']): ?>
          · via <?= htmlspecialchars(str_replace('_',' ', $user['verification_method'])) ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <a href="landing.php" class="vfy-submit-btn" style="text-decoration:none;max-width:280px;display:inline-flex;">
        <i class="bi bi-house-fill"></i> Back to Home
      </a>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!--   STATE: PENDING REVIEW (manual/legacy)         -->
  <!-- ═══════════════════════════════════════════════ -->
  <?php elseif ($verStatus === 'Pending'): ?>
  <div class="vfy-container">
    <div class="vfy-pending-wrap">
      <div class="vfy-pending-icon">⏳</div>
      <h2 style="font-size:20px;font-weight:700;margin-bottom:10px;color:#ffc107;">Verification Under Review</h2>
      <p style="color:var(--vr-muted);font-size:14px;line-height:1.7;margin-bottom:28px;">
        Your identity verification is currently being reviewed. This usually takes up to 1–3 business days. You'll be notified once a decision is made.
      </p>
      <a href="landing.php" class="vfy-back-link" style="justify-content:center;">
        <i class="bi bi-arrow-left"></i> Back to Home
      </a>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════ -->
  <!--   STATE: UPLOAD FORM (Unverified / Rejected)    -->
  <!-- ═══════════════════════════════════════════════ -->
  <?php else: ?>

  <!-- Processing Overlay -->
  <div class="vfy-progress-overlay" id="progressOverlay">
    <div class="vfy-progress-ring"></div>
    <div>
      <div style="font-size:17px;font-weight:700;margin-bottom:6px;">Verifying Your Identity</div>
      <div style="font-size:13px;color:var(--vr-muted);">Please wait, do not close this page.</div>
    </div>
    <div class="vfy-progress-steps">
      <div class="vfy-progress-step" id="pstep1">
        <div class="vfy-step-dot"></div> Uploading document…
      </div>
      <div class="vfy-progress-step" id="pstep2">
        <div class="vfy-step-dot"></div> Reading document with OCR…
      </div>
      <div class="vfy-progress-step" id="pstep3">
        <div class="vfy-step-dot"></div> Validating identity…
      </div>
      <div class="vfy-progress-step" id="pstep4">
        <div class="vfy-step-dot"></div> Finalizing…
      </div>
    </div>
  </div>

  <div class="vfy-container">

    <!-- Hidden file inputs -->
    <input type="file" id="fileCamera" accept="image/*" capture="environment" style="display:none;">
    <input type="file" id="fileUpload" accept="image/jpeg,image/png,image/webp" style="display:none;">

    <!-- ── Previous failure notice ── -->
    <?php if ($lastRej && $verStatus === 'Unverified'): ?>
    <div class="vfy-alert warning" style="display:flex;" id="lastFailAlert">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>
        <strong>Previous attempt failed.</strong><br>
        We could not verify your identity. Please ensure your document is clear, valid, and that the information matches your registered account details.
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Alert box ── -->
    <div class="vfy-alert danger" id="alertBox" style="display:none;">
      <i class="bi bi-exclamation-circle-fill" id="alertIcon"></i>
      <div id="alertMsg"></div>
    </div>

    <!-- ── Hero Banner ── -->
    <div class="vfy-hero">
      <div class="vfy-hero-icon"><i class="bi bi-patch-check"></i></div>
      <h1>Verify Your Identity</h1>
      <p>To unlock all VrakeIT features, upload a clear photo of your government-issued ID or driver's license. Our system will automatically verify your identity.</p>
      <div class="vfy-steps">
        <div class="vfy-step-chip"><i class="bi bi-upload"></i> Upload ID</div>
        <div class="vfy-step-chip"><i class="bi bi-cpu"></i> OCR Scan</div>
        <div class="vfy-step-chip"><i class="bi bi-check2-circle"></i> Auto Verify</div>
      </div>
    </div>

    <!-- ── Date of Birth (only shown if not in session) ── -->
    <?php if (!$sessionDob): ?>
    <div class="vfy-card">
      <div class="vfy-section-label"><i class="bi bi-calendar3" style="color:var(--vr-blue);"></i> Your Date of Birth</div>
      <p style="font-size:12px;color:var(--vr-muted);margin-bottom:12px;">
        Enter your date of birth so we can match it against your ID document.
      </p>
      <label for="dobInput" class="vfy-input-label">Date of Birth</label>
      <input type="date"
             id="dobInput"
             class="vfy-input"
             max="<?= date('Y-m-d', strtotime('-16 years')) ?>"
             required>
    </div>
    <?php else: ?>
    <input type="hidden" id="dobInput" value="<?= htmlspecialchars($sessionDob) ?>">
    <?php endif; ?>

    <!-- ── Upload Card ── -->
    <div class="vfy-card">
      <div class="vfy-section-label"><i class="bi bi-card-image" style="color:var(--vr-blue);"></i> Government-Issued ID</div>
      <p style="font-size:12px;color:var(--vr-muted);margin-bottom:16px;">
        Accepted: PhilSys / National ID, Driver's License, Passport, SSS, GSIS, PhilHealth, Voter's ID, PRC, UMID, and other government-issued IDs.
      </p>

      <!-- Drop zone -->
      <div class="vfy-drop-zone" id="dropZone">
        <div class="vfy-drop-icon"><i class="bi bi-cloud-upload" id="dropIcon"></i></div>
        <div class="vfy-drop-title" id="dropTitle">Drag & drop your ID here</div>
        <div class="vfy-drop-sub" id="dropSub">JPG, PNG, WebP — max 10 MB</div>
      </div>

      <!-- Capture / Upload buttons -->
      <div class="vfy-capture-row">
        <button class="vfy-btn-ghost camera-btn" id="cameraBtnEl" type="button">
          <i class="bi bi-camera-fill"></i> Use Camera
        </button>
        <button class="vfy-btn-ghost file-btn" id="uploadBtnEl" type="button">
          <i class="bi bi-folder2-open"></i> Browse File
        </button>
      </div>

      <!-- Preview -->
      <div class="vfy-preview-wrap" id="previewWrap">
        <img id="previewImg" class="vfy-preview-img" src="" alt="ID Preview">
        <button class="vfy-preview-remove" id="removeImgBtn" type="button">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>

    <!-- ── Photo Tips ── -->
    <div class="vfy-card">
      <div class="vfy-section-label"><i class="bi bi-lightbulb" style="color:#fbbf24;"></i> Photo Tips</div>
      <div class="vfy-tips">
        <div class="vfy-tip good"><i class="bi bi-check-circle-fill"></i> Ensure the entire ID is visible in the frame</div>
        <div class="vfy-tip good"><i class="bi bi-check-circle-fill"></i> Use good lighting — avoid shadows</div>
        <div class="vfy-tip bad" ><i class="bi bi-x-circle-fill"></i> Don't cover any part of the ID with fingers</div>
        <div class="vfy-tip bad" ><i class="bi bi-x-circle-fill"></i> Avoid blurry, cropped, or edited photos</div>
      </div>
    </div>

    <!-- ── Submit ── -->
    <button class="vfy-submit-btn" id="submitBtn">
      <i class="bi bi-shield-check"></i> Verify My Identity
    </button>

    <!-- Back link -->
    <a href="landing.php" class="vfy-back-link">
      <i class="bi bi-arrow-left"></i> Back to Home
    </a>

  </div><!-- /vfy-container -->

  <!-- ═══════════════════════════════════════════════ -->
  <!--   RESULT OVERLAY (injected by JS)               -->
  <!-- ═══════════════════════════════════════════════ -->
  <div class="vfy-progress-overlay" id="resultOverlay" style="display:none;flex-direction:column;overflow-y:auto;padding:20px 16px;">
    <div style="max-width:520px;width:100%;">
      <!-- Status card -->
      <div class="vfy-card" style="margin-bottom:12px;">
        <div class="vfy-result-card" id="resultContent"></div>
      </div>
      <!-- OCR Detected Fields -->
      <div class="vfy-card" id="ocrFieldsCard" style="display:none;">
        <div class="ocr-results-title"><i class="bi bi-cpu" style="color:var(--vr-blue);"></i> What We Detected on Your ID</div>
        <div id="ocrFieldsBody"></div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <script>
  // ══════════════════════════════════════════
  //  verify.php — Client-side logic
  // ══════════════════════════════════════════

  let selectedFile = null;

  // ── DOM refs ──
  const dropZone    = document.getElementById('dropZone');
  const dropIcon    = document.getElementById('dropIcon');
  const dropTitle   = document.getElementById('dropTitle');
  const dropSub     = document.getElementById('dropSub');
  const previewWrap = document.getElementById('previewWrap');
  const previewImg  = document.getElementById('previewImg');
  const removeBtn   = document.getElementById('removeImgBtn');
  const submitBtn   = document.getElementById('submitBtn');
  const alertBox    = document.getElementById('alertBox');
  const alertMsg    = document.getElementById('alertMsg');
  const alertIcon   = document.getElementById('alertIcon');
  const fileCamera  = document.getElementById('fileCamera');
  const fileUpload  = document.getElementById('fileUpload');
  const progressOverlay = document.getElementById('progressOverlay');
  const resultOverlay   = document.getElementById('resultOverlay');

  // Only wire up if on the upload form state
  if (dropZone) {

    // ── Drag & Drop ──
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      const f = e.dataTransfer.files[0];
      if (f) applyFile(f);
    });
    dropZone.addEventListener('click', () => fileUpload.click());

    // ── Camera button ──
    document.getElementById('cameraBtnEl').addEventListener('click', e => {
      e.stopPropagation();
      fileCamera.click();
    });

    // ── Browse button ──
    document.getElementById('uploadBtnEl').addEventListener('click', e => {
      e.stopPropagation();
      fileUpload.click();
    });

    // ── File inputs ──
    fileCamera.addEventListener('change', () => { if (fileCamera.files[0]) applyFile(fileCamera.files[0]); });
    fileUpload.addEventListener('change', () => { if (fileUpload.files[0]) applyFile(fileUpload.files[0]); });

    // ── Remove preview ──
    if (removeBtn) removeBtn.addEventListener('click', clearFile);

    // ── Submit ──
    if (submitBtn && !submitBtn.disabled) {
      submitBtn.addEventListener('click', handleSubmit);
    }
  }

  function applyFile(file) {
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
      showAlert('Invalid file type. Please upload a JPG, PNG, or WebP image.', 'danger');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      showAlert('File is too large. Maximum allowed size is 10 MB.', 'danger');
      return;
    }
    selectedFile = file;
    const url = URL.createObjectURL(file);
    previewImg.src = url;
    previewWrap.classList.add('show');
    dropZone.classList.add('has-file');
    dropIcon.className = 'bi bi-check-circle-fill';
    dropTitle.textContent = file.name;
    dropSub.textContent   = (file.size / 1024).toFixed(0) + ' KB — click to change';
    hideAlert();
  }

  function clearFile() {
    selectedFile = null;
    previewImg.src = '';
    previewWrap.classList.remove('show');
    dropZone.classList.remove('has-file');
    dropIcon.className = 'bi bi-cloud-upload';
    dropTitle.textContent = 'Drag & drop your ID here';
    dropSub.textContent   = 'JPG, PNG, WebP — max 10 MB';
    fileCamera.value = '';
    fileUpload.value = '';
  }

  function showAlert(msg, type = 'danger') {
    if (!alertBox) return;
    alertBox.className = `vfy-alert ${type}`;
    alertBox.style.display = 'flex';
    alertIcon.className = type === 'danger'
      ? 'bi bi-exclamation-circle-fill'
      : (type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-info-circle-fill');
    alertMsg.innerHTML = msg;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function hideAlert() {
    if (alertBox) alertBox.style.display = 'none';
  }

  // ── Progress steps ──
  const psteps = ['pstep1','pstep2','pstep3','pstep4'];
  let currentStep = 0;
  let stepInterval = null;

  function startProgress() {
    currentStep = 0;
    psteps.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.className = 'vfy-progress-step';
    });
    const el = document.getElementById(psteps[0]);
    if (el) el.className = 'vfy-progress-step current';
    progressOverlay.classList.add('show');

    stepInterval = setInterval(() => {
      if (currentStep < psteps.length - 1) {
        const cur = document.getElementById(psteps[currentStep]);
        if (cur) cur.className = 'vfy-progress-step done';
        currentStep++;
        const next = document.getElementById(psteps[currentStep]);
        if (next) next.className = 'vfy-progress-step current';
      }
    }, 2200);
  }

  function stopProgress() {
    clearInterval(stepInterval);
    progressOverlay.classList.remove('show');
    psteps.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.className = 'vfy-progress-step';
    });
  }

  // ── Show result overlay ──
  function showResult(success, title, msg, ref, attemptsLeft, extractedFields, failureDetail) {
    stopProgress();
    const content = document.getElementById('resultContent');
    if (!content) return;

    if (success) {
      content.innerHTML = `
        <div class="vfy-result-icon success">🎉</div>
        <div class="vfy-result-title" style="color:#4dde8a;">${title}</div>
        <div class="vfy-result-msg">${msg}</div>
        ${ref ? `<div class="vfy-result-ref">Reference: ${ref}</div>` : ''}
        <a href="landing.php" class="vfy-submit-btn" style="text-decoration:none;background:linear-gradient(135deg,#00c853,#007a32);box-shadow:0 6px 24px rgba(0,200,83,0.25);max-width:300px;margin:0 auto;">
          <i class="bi bi-house-fill"></i> Continue to Home
        </a>`;
    } else {
      const attemptsHtml = attemptsLeft > 0
        ? `<button class="vfy-submit-btn" onclick="retryVerification()" style="max-width:280px;margin:0 auto 12px;"><i class="bi bi-arrow-counterclockwise"></i> Try Again</button>`
        : `<div style="font-size:12px;color:var(--vr-muted);margin-bottom:16px;">No attempts remaining. Please contact support.</div>`;
      content.innerHTML = `
        <div class="vfy-result-icon error">❌</div>
        <div class="vfy-result-title" style="color:#ff8a8a;">${title}</div>
        <div class="vfy-result-msg">${msg}</div>
        ${attemptsHtml}
        <a href="landing.php" class="vfy-back-link" style="justify-content:center;margin-top:4px;">
          <i class="bi bi-arrow-left"></i> Back to Home
        </a>`;
    }

    // ── Render OCR detected fields panel ──
    if (extractedFields) renderOcrFields(extractedFields, failureDetail, success);

    resultOverlay.style.display = 'flex';
    resultOverlay.scrollTop = 0;
  }

  function matchBadge(matched) {
    if (matched === null || matched === undefined) return '';
    return matched
      ? `<span class="ocr-match-badge match"><i class="bi bi-check-lg"></i> Matches</span>`
      : `<span class="ocr-match-badge no-match"><i class="bi bi-x-lg"></i> Mismatch</span>`;
  }

  function renderOcrFields(f, failureDetail, success) {
    const card   = document.getElementById('ocrFieldsCard');
    const body   = document.getElementById('ocrFieldsBody');
    if (!card || !body) return;

    const confColor = f.confidence >= 70 ? '#4dde8a' : f.confidence >= 45 ? '#ffc107' : '#ff8a8a';
    const engineLabel = f.ocr_engine === 'tesseract'
      ? '<i class="bi bi-cpu-fill"></i> Tesseract (local)'
      : f.ocr_engine === 'gemini'
        ? '<i class="bi bi-stars"></i> Gemini AI'
        : '<i class="bi bi-question-circle"></i> Unknown';

    let rows = '';

    // Document type
    rows += `<div class="ocr-field-row">
      <span class="ocr-field-label">Document</span>
      <span class="ocr-field-value ${f.doc_type ? '' : 'empty'}">${f.doc_type || 'Not detected'}</span>
      <span class="ocr-match-badge neutral"><i class="bi bi-info-circle"></i></span>
    </div>`;

    // Name
    rows += `<div class="ocr-field-row">
      <span class="ocr-field-label">Name</span>
      <span class="ocr-field-value ${f.name ? '' : 'empty'}">${f.name || 'Not detected'}</span>
      ${matchBadge(f.name_match)}
    </div>`;

    // Date of Birth
    rows += `<div class="ocr-field-row">
      <span class="ocr-field-label">Date of Birth</span>
      <span class="ocr-field-value ${f.dob ? '' : 'empty'}">${f.dob || 'Not detected'}</span>
      ${matchBadge(f.dob_match)}
    </div>`;

    // ID Number
    if (f.doc_number) {
      rows += `<div class="ocr-field-row">
        <span class="ocr-field-label">ID Number</span>
        <span class="ocr-field-value">${f.doc_number}</span>
        <span class="ocr-match-badge neutral"><i class="bi bi-info-circle"></i> Info</span>
      </div>`;
    }

    // Expiry
    if (f.expiry) {
      rows += `<div class="ocr-field-row">
        <span class="ocr-field-label">Expiry</span>
        <span class="ocr-field-value">${f.expiry}</span>
        <span class="ocr-match-badge neutral"><i class="bi bi-calendar"></i></span>
      </div>`;
    }

    // Confidence bar
    rows += `
      <div class="ocr-confidence-bar" style="margin-top:12px;">
        <div class="ocr-confidence-fill" style="width:${f.confidence}%;background:${confColor};"></div>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;margin-top:4px;">
        <span style="color:var(--vr-muted);">OCR Confidence</span>
        <span style="color:${confColor};font-weight:700;">${f.confidence}%</span>
      </div>
      <div class="ocr-engine-badge">${engineLabel}</div>`;

    // Failure detail box
    if (!success && failureDetail) {
      let why = '';
      if (failureDetail.reason === 'identity_mismatch') {
        const issues = [];
        if (!failureDetail.name_match)  issues.push('❌ Name on ID does not match your registered name');
        if (!failureDetail.dob_match)   issues.push('❌ Date of birth on ID does not match your registration');
        if (failureDetail.name_match && failureDetail.dob_match) issues.push('❌ Identity could not be confirmed');
        why = issues.join('<br>');
      } else if (failureDetail.reason === 'document_invalid') {
        why = '❌ ' + (failureDetail.detail || 'Document validation failed');
      }
      if (why) {
        rows += `<div class="ocr-fail-reason"><strong>Why it failed:</strong>${why}</div>`;
      }
    }

    body.innerHTML = rows;
    card.style.display = 'block';
  }

  function retryVerification() {
    resultOverlay.style.display = 'none';
    document.getElementById('ocrFieldsCard').style.display = 'none';
    clearFile();
    hideAlert();
  }

  // ── Main submit handler ──
  async function handleSubmit() {
    hideAlert();

    const dobInput = document.getElementById('dobInput');
    if (dobInput && dobInput.type === 'date' && !dobInput.value) {
      showAlert('Please enter your date of birth.', 'danger');
      dobInput.focus();
      return;
    }

    if (!selectedFile) {
      showAlert('Please upload or capture a photo of your ID first.', 'danger');
      return;
    }

    submitBtn.disabled = true;
    startProgress();

    const fd = new FormData();
    fd.append('id_image', selectedFile);
    fd.append('source', 'portal');
    if (dobInput) fd.append('birthdate', dobInput.value);

    try {
      const res  = await fetch('api/ocr_verify_id.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        psteps.forEach(id => {
          const el = document.getElementById(id);
          if (el) el.className = 'vfy-progress-step done';
        });
        await new Promise(r => setTimeout(r, 600));
        showResult(
          true,
          'Identity Verified!',
          'Your identity has been successfully verified. Your account now has full access to all VrakeIT features.',
          data.reference || '',
          data.remaining_attempts ?? 0,
          data.extracted_fields || null,
          null
        );
      } else {
        showResult(
          false,
          'Verification Failed',
          data.message || 'We could not verify your identity. Please make sure your document is clear and valid.',
          '',
          data.remaining_attempts ?? 0,
          data.extracted_fields || null,
          data.failure_detail || null
        );
        submitBtn.disabled = (data.remaining_attempts ?? 0) <= 0;
      }
    } catch (err) {
      stopProgress();
      showAlert('A connection error occurred. Please check your internet connection and try again.', 'danger');
      submitBtn.disabled = false;
    }
  }

  // Guard against bfcache
  window.addEventListener('pageshow', e => { if (e.persisted) window.location.reload(); });
  </script>

</body>
</html>