<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();

$user = getLoggedInUser();
if (($user['role'] ?? 'user') !== 'user') {
  header('Location: enforcer_landing.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT - Report an Incident</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

  <style>
    :root {
      --primary: #007ED2;
      --primary-light: #e0f2fe;
      --primary-dark: #005fa3;
      --danger: #E90101;
      --danger-light: #fef2f2;
      --success: #00c853;
      --warning: #f59e0b;
      --bg: #f0f4f8;
      --card-bg: rgba(255, 255, 255, 0.92);
      --border: #e5e7eb;
      --text: #1f2937;
      --muted: #6b7280;
      --radius: 1.25rem;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: 5rem;
      position: relative;
      z-index: 0;
    }

    body::after {
      content: '';
      position: fixed;
      bottom: -50px;
      left: 0;
      width: 100%;
      height: 190px;
      background: linear-gradient(to right, #E90101, #007ED2);
      filter: blur(50px);
      opacity: 0.6;
      /* Adjust opacity to make the shadow stronger or softer */
      z-index: -1;
      pointer-events: none;
    }

    /* ─── HEADER ─────────────────────────── */
    .app-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: linear-gradient(rgba(0, 0, 0, 0.15), rgba(0, 0, 0, 0.15)), linear-gradient(to right, rgba(233, 1, 1, 0.85), rgba(0, 126, 210, 0.85));
      backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.6);
      padding: 0.9rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 1px 12px rgba(0, 0, 0, 0.05);
    }

    .app-header .back-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--bg);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--muted);
      font-size: 1.1rem;
      text-decoration: none;
      transition: all 0.2s;
      border: 1px solid var(--border);
    }

    .app-header .back-btn:hover {
      background: var(--primary-light);
      color: var(--primary);
    }

    .app-header h1 {
      font-size: 1rem;
      font-weight: 700;
      color: var(--bg);
    }

    /* ─── PROGRESS ───────────────────────── */
    .progress-wrap {
      padding: 1rem 1.25rem 0;
      max-width: 640px;
      margin: 0 auto;
    }

    .progress-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .progress-meta .step-label {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .progress-meta .flow-label {
      font-size: 0.65rem;
      font-weight: 600;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: var(--bg);
      border: 1px solid var(--border);
      padding: 2px 10px;
      border-radius: 999px;
    }

    .progress-track {
      height: 5px;
      background: var(--border);
      border-radius: 999px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--primary), #00b4ff);
      border-radius: 999px;
      transition: width 0.45s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* ─── LAYOUT ─────────────────────────── */
    .wizard-layout {
      max-width: 1024px;
      margin: 1.25rem auto 0;
      padding: 0 1rem;
      display: flex;
      gap: 1.5rem;
      align-items: flex-start;
    }

    .wizard-main {
      flex: 1;
      min-width: 0;
    }

    /* ─── CARD ───────────────────────────── */
    .wizard-card {
      background: var(--card-bg);
      border-radius: 1.5rem;
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
      padding: 1.75rem 1.5rem;
      backdrop-filter: blur(12px);
    }

    /* ─── STEPS ──────────────────────────── */
    .wizard-step {
      display: none;
      animation: stepIn 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
    }

    .wizard-step.active {
      display: block;
    }

    @keyframes stepIn {
      from {
        opacity: 0;
        transform: translateY(18px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* ─── TYPOGRAPHY ─────────────────────── */
    .step-title {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text);
      line-height: 1.3;
      margin-bottom: 0.4rem;
    }

    .step-sub {
      font-size: 0.82rem;
      color: var(--muted);
      margin-bottom: 1.5rem;
      line-height: 1.55;
    }

    /* ─── CHOICE BUTTONS ─────────────────── */
    .choice-grid {
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
      margin-bottom: 1.25rem;
    }

    .choice-grid.two-col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.65rem;
    }

    .choice-grid.three-col {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.65rem;
    }

    .choice-btn {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      background: #fff;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 1rem 1.15rem;
      cursor: pointer;
      text-align: left;
      transition: all 0.2s ease;
      font-family: 'Poppins', sans-serif;
      width: 100%;
    }

    .choice-btn:hover {
      border-color: var(--primary);
      background: var(--primary-light);
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0, 126, 210, 0.12);
    }

    .choice-btn.danger:hover {
      border-color: var(--danger);
      background: var(--danger-light);
    }

    .choice-btn.selected,
    .choice-btn.active {
      border-color: var(--primary);
      background: var(--primary-light);
      box-shadow: 0 4px 16px rgba(0, 126, 210, 0.15);
    }

    .choice-btn .cb-icon {
      font-size: 1.5rem;
      flex-shrink: 0;
      width: 48px;
      height: 48px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-light);
      color: var(--primary);
      transition: all 0.2s;
    }

    .choice-btn:hover .cb-icon,
    .choice-btn.selected .cb-icon,
    .choice-btn.active .cb-icon {
      background: var(--primary);
      color: #fff;
    }

    .choice-btn .cb-body .cb-title {
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.3;
    }

    .choice-btn .cb-body .cb-desc {
      font-size: 0.73rem;
      color: var(--muted);
      margin-top: 2px;
    }

 /* Card-style choice (vertical stacking by row) */
.choice-card-grid {
  display: grid;
  grid-template-columns: 1fr; /* Changed from repeat(3, 1fr) */
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

    .choice-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
      background: #fff;
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 0.75rem;
      cursor: pointer;
      font-family: 'Poppins', sans-serif;
      transition: all 0.2s;
      text-align: center;
    }

    .choice-card:hover {
      border-color: var(--primary);
      background: var(--primary-light);
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 126, 210, 0.15);
    }

    .choice-card.active {
      border-color: var(--primary);
      background: var(--primary-light);
    }

    .choice-card .cc-icon {
      font-size: 1.8rem;
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-light);
      color: var(--primary);
      margin-bottom: 6px;
      transition: all 0.2s;
    }

    .choice-card:hover .cc-icon,
    .choice-card.active .cc-icon {
      background: var(--primary);
      color: #fff;
    }

    .choice-card .cc-label {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text);
    }

    .choice-card .cc-sub {
      font-size: 0.65rem;
      color: var(--muted);
    }

    /* ─── ALERT BANNERS ──────────────────── */
    .alert-banner {
      border-radius: var(--radius);
      padding: 1rem 1.15rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
      margin-bottom: 1.25rem;
      font-size: 0.83rem;
      line-height: 1.5;
    }

    .alert-banner.warn {
      background: #fffbeb;
      border: 1.5px solid #fde68a;
    }

    .alert-banner.info {
      background: var(--primary-light);
      border: 1.5px solid #bae6fd;
    }

    .alert-banner.danger {
      background: var(--danger-light);
      border: 1.5px solid #fecaca;
    }

    .alert-banner.success {
      background: #f0fdf4;
      border: 1.5px solid #bbf7d0;
    }

    .alert-banner i {
      font-size: 1.2rem;
      flex-shrink: 0;
      margin-top: 1px;
    }

    .alert-banner .ab-title {
      font-weight: 700;
      margin-bottom: 2px;
    }

    .alert-banner .ab-body {
      color: var(--muted);
    }

    /* ─── CALL BUTTONS ───────────────────── */
    .call-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.75rem;
      margin-bottom: 1.25rem;
    }

    .call-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
      border-radius: var(--radius);
      padding: 1.1rem 0.75rem;
      text-decoration: none;
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 0.82rem;
      text-align: center;
      transition: all 0.2s;
      color: #fff;
    }

    .call-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
      color: #fff;
    }

    .call-btn .ca-icon {
      font-size: 1.6rem;
    }

    .call-btn .ca-label {
      font-size: 0.72rem;
      opacity: 0.85;
      font-weight: 500;
    }

    .call-btn.red {
      background: linear-gradient(135deg, #E90101, #b30000);
    }

    .call-btn.blue {
      background: linear-gradient(135deg, #007ED2, #005fa3);
    }

    .call-btn.amber {
      background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .call-btn.green {
      background: linear-gradient(135deg, #10b981, #059669);
    }

    /* ─── FORM INPUTS ────────────────────── */
    .form-group {
      margin-bottom: 1.1rem;
    }

    .form-label {
      display: block;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 0.4rem;
    }

    .form-control-vr {
      width: 100%;
      border: 2px solid var(--border);
      border-radius: 0.85rem;
      padding: 0.75rem 1rem;
      font-family: 'Poppins', sans-serif;
      font-size: 0.875rem;
      color: var(--text);
      background: #fff;
      transition: border-color 0.2s;
      outline: none;
    }

    .form-control-vr:focus {
      border-color: var(--primary);
    }

    textarea.form-control-vr {
      resize: vertical;
      min-height: 120px;
    }

    /* ─── MAP ────────────────────────────── */
    #mapContainer {
      width: 100%;
      height: 200px;
      border-radius: var(--radius);
      border: 2px solid var(--border);
      overflow: hidden;
      margin-bottom: 0.85rem;
    }

    .address-box {
      background: #f9fafb;
      border: 1px solid var(--border);
      border-radius: 0.85rem;
      padding: 0.85rem 1rem;
      margin-bottom: 1.1rem;
    }

    .address-box .ab-label {
      font-size: 0.68rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 3px;
    }

    .address-box .ab-value {
      font-size: 0.83rem;
      font-weight: 600;
      color: var(--text);
    }

    /* ─── VEHICLE CHIPS ──────────────────── */
    .chip-group {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-bottom: 1.25rem;
    }

    .chip {
      border: 2px solid var(--border);
      background: #fff;
      border-radius: 999px;
      padding: 0.4rem 0.9rem;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      font-family: 'Poppins', sans-serif;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }

    .chip:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: var(--primary-light);
    }

    .chip.active {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary);
    }

    /* ─── VEHICLE DETAIL CARDS ───────────── */
    .vd-card {
      background: #fff;
      border: 1.5px solid #e5e7eb;
      border-radius: 1rem;
      padding: 1rem;
      margin-bottom: 0.75rem;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .vd-card:hover { border-color: var(--primary); box-shadow: 0 2px 12px rgba(233,1,1,0.07); }
    .vd-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem;
    }
    .vd-card-label {
      display: flex;
      align-items: center;
      gap: 0.45rem;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text);
    }
    .vd-card-label .vd-icon {
      font-size: 1.15rem;
      width: 28px;
      height: 28px;
      min-width: 28px;
      background: #fff0f0;
      color: var(--primary);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    /* Count stepper */
    .vd-stepper {
      display: flex;
      align-items: center;
      gap: 0;
      border: 1.5px solid #e0e0e0;
      border-radius: 999px;
      overflow: hidden;
      background: #f9fafb;
    }
    .vd-stepper button {
      background: none;
      border: none;
      width: 30px;
      height: 30px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      color: var(--primary);
      transition: background 0.15s;
      font-family: 'Poppins', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .vd-stepper button:hover { background: rgba(233,1,1,0.08); }
    .vd-stepper span {
      min-width: 28px;
      text-align: center;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--text);
    }
    /* Plate inputs container */
    .vd-plates {
      display: flex;
      flex-direction: column;
      gap: 0.45rem;
      margin-top: 0.6rem;
    }
    .vd-plate-row {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .vd-plate-num {
      width: 22px;
      height: 22px;
      min-width: 22px;
      background: var(--primary);
      color: #fff;
      border-radius: 50%;
      font-size: 0.65rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .vd-plate-input {
      flex: 1;
      border: 1.5px solid #e0e0e0;
      border-radius: 0.6rem;
      padding: 0.45rem 0.75rem;
      font-size: 0.82rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      outline: none;
      background: #fafafa;
      transition: border-color 0.2s;
    }
    .vd-plate-input:focus { border-color: var(--primary); background: #fff; }
    .vd-plate-input::placeholder { text-transform: none; font-weight: 400; letter-spacing: 0; color: #aaa; }

    /* ─── MEDIA UPLOAD ───────────────────── */
    .media-dropzone {
      border: 2px dashed var(--border);
      border-radius: var(--radius);
      padding: 2rem 1rem;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s;
      background: #fafafa;
      margin-bottom: 0.85rem;
    }

    .media-dropzone:hover {
      border-color: var(--primary);
      background: var(--primary-light);
    }

    .media-dropzone i {
      font-size: 2rem;
      color: var(--primary);
      display: block;
      margin-bottom: 0.4rem;
    }

    .media-dropzone .dz-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--primary);
    }

    .media-dropzone .dz-sub {
      font-size: 0.68rem;
      color: var(--muted);
      margin-top: 3px;
    }

    .media-preview {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-bottom: 1rem;
    }

    .media-preview img {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 0.5rem;
      cursor: pointer;
      border: 2px solid #fff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      transition: transform 0.2s;
    }

    .media-preview img:hover {
      transform: scale(1.05);
    }

    /* ─── OVERVIEW TABLE ─────────────────── */
    .overview-table {
      background: #f9fafb;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      overflow: hidden;
      margin-bottom: 1.25rem;
    }

    .ov-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      padding: 0.7rem 1rem;
      border-bottom: 1px solid var(--border);
      font-size: 0.82rem;
    }

    .ov-row:last-child {
      border-bottom: none;
    }

    .ov-row .ov-label {
      color: var(--muted);
      flex-shrink: 0;
    }

    .ov-row .ov-value {
      font-weight: 600;
      text-align: right;
      max-width: 65%;
      word-break: break-word;
    }

    .ov-details-box {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: 0.85rem;
      padding: 1rem;
      font-size: 0.8rem;
      color: var(--text);
      line-height: 1.6;
      white-space: pre-wrap;
      word-break: break-word;
      margin-bottom: 1.25rem;
    }

    /* ─── BUTTONS ────────────────────────── */
    .btn-primary {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.4rem;
      width: 100%;
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: var(--radius);
      padding: 0.95rem 1.25rem;
      font-family: 'Poppins', sans-serif;
      font-size: 0.9rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 0.5rem;
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(0, 126, 210, 0.22);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .btn-primary.danger-btn {
      background: var(--danger);
    }

    .btn-primary.danger-btn:hover {
      background: #b30000;
      box-shadow: 0 6px 18px rgba(233, 1, 1, 0.22);
    }

    .btn-primary.success-btn {
      background: var(--success);
    }

    .btn-outline {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.4rem;
      width: 100%;
      background: transparent;
      color: var(--muted);
      border: 2px solid var(--border);
      border-radius: var(--radius);
      padding: 0.8rem 1.25rem;
      font-family: 'Poppins', sans-serif;
      font-size: 0.85rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 0.35rem;
    }

    .btn-outline:hover {
      border-color: #9ca3af;
      color: var(--text);
      background: #f9fafb;
    }

    .btn-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.5rem;
      margin-top: 0.5rem;
    }

    /* ─── SPECIAL SCREENS ────────────────── */
    .center-screen {
      text-align: center;
      padding: 1.5rem 0;
    }

    .hero-icon {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1rem;
      font-size: 2.2rem;
    }

    .hero-icon.pulse {
      animation: heroPulse 2s infinite;
    }

    @keyframes heroPulse {

      0%,
      100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 currentColor;
      }

      50% {
        transform: scale(1.04);
        box-shadow: 0 0 0 14px transparent;
      }
    }

    .hero-icon.green {
      background: linear-gradient(135deg, #00c853, #009624);
      color: #fff;
    }

    .hero-icon.red {
      background: linear-gradient(135deg, #E90101, #b30000);
      color: #fff;
    }

    .hero-icon.amber {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff;
    }

    .hero-icon.blue {
      background: linear-gradient(135deg, #007ED2, #005fa3);
      color: #fff;
    }

    /* ─── CONTRACT FORM ──────────────────── */
    .contract-card {
      background: #fff;
      border: 2px solid #bae6fd;
      border-radius: var(--radius);
      padding: 1.25rem;
      margin-bottom: 1rem;
    }

    /* ─── CHIP SELECTORS (contract wizard) ── */
    .chip-group {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      margin-bottom: 0.25rem;
    }

    .chip-btn {
      padding: 6px 14px;
      border-radius: 20px;
      border: 1.5px solid #d1d5db;
      background: #f9fafb;
      color: #374151;
      font-family: 'Poppins', sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.18s;
      outline: none;
    }

    .chip-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: var(--primary-light);
    }

    .chip-btn.selected {
      background: var(--primary);
      border-color: var(--primary);
      color: #fff;
    }

    /* cx progress dots */
    .cx-step-dot.active  { background: var(--primary) !important; }
    .cx-step-dot.done    { background: #10b981 !important; }


    .contract-party {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 0;
      border-bottom: 1px solid var(--border);
    }

    .contract-party:last-child {
      border-bottom: none;
    }

    .party-num {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: var(--primary);
      color: #fff;
      font-size: 0.75rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* ─── POINTS BADGE ───────────────────── */
    .points-badge {
      background: linear-gradient(135deg, #007ED2, #00b4ff);
      color: #fff;
      border-radius: var(--radius);
      padding: 1rem 1.25rem;
      text-align: center;
      margin-bottom: 1.25rem;
    }

    .points-badge .pb-points {
      font-size: 2rem;
      font-weight: 800;
    }

    .points-badge .pb-label {
      font-size: 0.78rem;
      opacity: 0.9;
    }

    /* ─── SIDEBAR ────────────────────────── */
    .sidebar {
      width: 260px;
      flex-shrink: 0;
      position: sticky;
      top: 5.5rem;
    }

    @media (max-width: 767px) {
      .sidebar {
        display: none;
      }
    }

    .sidebar-card {
      background: var(--card-bg);
      border-radius: 1.25rem;
      border: 1px solid rgba(255, 255, 255, 0.7);
      padding: 1.25rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    .sidebar-title {
      font-size: 0.8rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }

    .sb-item {
      padding: 0.6rem 0;
      border-bottom: 1px solid var(--border);
    }

    .sb-item:last-child {
      border-bottom: none;
    }

    .sb-item .sb-key {
      font-size: 0.63rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--muted);
      margin-bottom: 2px;
    }

    .sb-item .sb-val {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text);
    }

    .sb-item .sb-val.pending {
      color: #d1d5db;
      font-weight: 400;
      font-style: italic;
    }

    /* ─── SEPARATOR ──────────────────────── */
    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 1rem 0;
    }

    /* ─── LOADING SPINNER ────────────────── */
    .spinner {
      width: 18px;
      height: 18px;
      border: 2px solid rgba(255, 255, 255, 0.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
      display: inline-block;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* ─── BREADCRUMB TRAIL ───────────────── */
    .trail {
      display: flex;
      gap: 0.3rem;
      flex-wrap: wrap;
      margin-bottom: 1.25rem;
    }

    .trail-dot {
      font-size: 0.65rem;
      color: var(--muted);
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 2px 8px;
      font-weight: 600;
    }

    .trail-dot.done {
      background: var(--primary-light);
      border-color: #bae6fd;
      color: var(--primary);
    }

    /* ─── CALMING BANNER ─────────────────── */
    .calm-banner {
      max-width: 640px;
      margin: 1rem auto 0;
      padding: 0 1rem;
    }

    .calm-inner {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      background: rgba(0, 200, 83, 0.10);
      border: 1.5px solid rgba(0, 200, 83, 0.28);
      border-radius: 1rem;
      padding: 0.75rem 1rem;
      backdrop-filter: blur(8px);
      animation: calmFadeIn 0.5s ease forwards;
    }

    @keyframes calmFadeIn {
      from {
        opacity: 0;
        transform: translateY(-6px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .calm-emoji {
      font-size: 1.4rem;
      flex-shrink: 0;
    }

    .calm-text {
      font-size: 0.78rem;
      font-weight: 600;
      color: #1a6636;
      line-height: 1.45;
      flex: 1;
    }
  </style>
</head>

<body>

  <!-- ─── HEADER ──────────────────────────────────────────── -->
  <header class="app-header">
    <a href="landing.php" class="back-btn"><i class="bi bi-arrow-left"></i></a>
    <h1>Report an Incident</h1>
    <button id="translateBtn" onclick="toggleLanguage()" style="
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 999px;
      padding: 4px 12px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.68rem;
      font-weight: 700;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    " onmouseover="this.style.background='var(--primary-light)';this.style.color='var(--primary)';this.style.borderColor='var(--primary)';"
      onmouseout="this.style.background='var(--bg)';this.style.color='var(--muted)';this.style.borderColor='var(--border)';">
      <i class="bi bi-globe"></i> Filipino
    </button>
  </header>

  <!-- ─── PROGRESS ────────────────────────────────────────── -->
  <div class="progress-wrap">
    <div class="progress-meta">
      <span class="step-label" id="stepLabel">Step 1</span>
      <span class="flow-label" id="flowLabel">Getting Started</span>
    </div>
    <div class="progress-track">
      <div class="progress-fill" id="progressBar" style="width: 5%;"></div>
    </div>
  </div>

  <!-- ─── CALMING BANNER ──────────────────────────────────── -->
  <div class="calm-banner" id="calmBanner">
    <div class="calm-inner">
      <span class="calm-emoji" id="calmEmoji"><i class="bi bi-flower1" style="color:#fbbf24;"></i></span>
      <span class="calm-text" id="calmText">Take a deep breath. You're doing the right thing by reporting this calmly.</span>
    </div>
  </div>

  <!-- ─── LAYOUT ───────────────────────────────────────────── -->
  <div class="wizard-layout">
    <div class="wizard-main">
      <div class="wizard-card">

        <!-- ══════════════════════════════════════════════════
             STEP 1 — How many are involved?
        ═══════════════════════════════════════════════════════ -->
        <!-- ══════════════════════════════════════════════════
             STEP 0 — ROLE SELECTION (NEW ENTRY GATE)
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step active" id="step-0-role">
          <div class="alert-banner info" style="margin-bottom:1.25rem;">
            <i class="bi bi-shield-check-fill" style="color:var(--primary);"></i>
            <div>
              <div class="ab-title">You're protected when you report properly. Stay composed and trust the process.</div>
            </div>
          </div>

          <div class="step-title" id="role-title-el">What is your role in this incident?</div>
          <p class="step-sub" id="role-sub-el">Select how you are involved so we can guide you correctly.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="chooseRole('driver', this)" id="btn-role-driver">
              <span class="cc-icon"><i class="bi bi-car-front-fill"></i></span>
              <div class="cc-label" id="role-driver-label">I am a Driver</div>
              <div class="cc-sub" id="role-driver-sub">I was directly involved in the accident</div>
            </button>
            <button class="choice-card" onclick="chooseRole('citizen', this)" id="btn-role-citizen">
              <span class="cc-icon"><i class="bi bi-eye-fill"></i></span>
              <div class="cc-label" id="role-citizen-label">Citizen / Witness</div>
              <div class="cc-sub" id="role-citizen-sub">I witnessed or am reporting on behalf of others</div>
            </button>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             CITIZEN FLOW — Step C1: How many people involved?
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step" id="step-c1">
          <div class="step-title">How many drivers are involved?</div>
          <p class="step-sub">This helps us guide you through the right reporting process.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setCitizenParties('self', this)">
              <span class="cc-icon"><i class="bi bi-person"></i></span>
              <div class="cc-label">One Driver</div>
              <div class="cc-sub">Only me involved</div>
            </button>
            <button class="choice-card" onclick="setCitizenParties('two', this)">
              <span class="cc-icon"><i class="bi bi-people"></i></span>
              <div class="cc-label">Two Drivers</div>
              <div class="cc-sub">Me and another driver</div>
            </button>
            <button class="choice-card" onclick="setCitizenParties('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill"></i></span>
              <div class="cc-label">Three or More Drivers</div>
              <div class="cc-sub">Multiple drivers involved</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- ══════════════════════════════════════════════════
             CITIZEN FLOW — Step C2: How many are injured?
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step" id="step-c2">
          <div class="step-title">Are there any injuries?</div>
          <p class="step-sub">Include everyone at the scene who may be hurt.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setCitizenInjured('none', this)">
              <span class="cc-icon"><i class="bi bi-check-circle-fill" style="color:#10b981;"></i></span>
              <div class="cc-label">No Injuries</div>
              <div class="cc-sub">No one is hurt</div>
            </button>
            <button class="choice-card" onclick="setCitizenInjured('one', this)">
              <span class="cc-icon"><i class="bi bi-person-exclamation"></i></span>
              <div class="cc-label">1 Person</div>
              <div class="cc-sub">One person is injured</div>
            </button>
            <button class="choice-card" onclick="setCitizenInjured('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill" style="color:#e11d48;"></i></span>
              <div class="cc-label">2 or More</div>
              <div class="cc-sub">Multiple people are injured</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- ══════════════════════════════════════════════════
             CITIZEN FLOW — Step C3: How bad are the injuries?
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step" id="step-c-severity">
          <div class="step-title">How bad are the injuries?</div>
          <p class="step-sub">This helps determine the right emergency response.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setCitizenSeverity('major', this)">
              <span class="cc-icon"><i class="bi bi-heartbreak-fill" style="color:#e11d48;"></i></span>
              <div class="cc-label">Major Injury</div>
              <div class="cc-sub">Life-threatening or serious</div>
            </button>
            <button class="choice-card" onclick="setCitizenSeverity('minor', this)">
              <span class="cc-icon"><i class="bi bi-bandaid-fill" style="color:#f59e0b;"></i></span>
              <div class="cc-label">Minor Injury</div>
              <div class="cc-sub">Not life-threatening</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- ══════════════════════════════════════════════════
             CITIZEN FLOW — Step C-Hotline: Call Hotline
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step" id="step-c-hotline">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">There are injured people at the scene. Contact emergency services RIGHT NOW.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToFormFlow('good_citizen')">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             STEP 1 — HOW MANY DRIVERS? (DRIVER FLOW)
        ═══════════════════════════════════════════════════════ -->
        <div class="wizard-step" id="step-1">
          <div class="step-title" id="step1-title-el">How many drivers are involved?</div>
          <p class="step-sub" id="step1-sub-el">This helps us guide you through the right reporting process.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="chooseParties('self', this)">
              <span class="cc-icon"><i class="bi bi-person"></i></span>
              <div class="cc-label">Just Me</div>
              <div class="cc-sub">Solo incident</div>
            </button>
            <button class="choice-card" onclick="chooseParties('two', this)">
              <span class="cc-icon"><i class="bi bi-people"></i></span>
              <div class="cc-label">Two Drivers</div>
              <div class="cc-sub">Me + Another driver</div>
            </button>
            <button class="choice-card" onclick="chooseParties('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill"></i></span>
              <div class="cc-label">Three or More</div>
              <div class="cc-sub">Multi-party accident</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- ══════════════════════════════════════════════════
             SELF FLOW
        ═══════════════════════════════════════════════════════ -->

        <!-- S2 — How many people are injured? (SELF FLOW) -->
        <div class="wizard-step" id="step-s2">
          <div class="step-title">How many people are injured?</div>
          <p class="step-sub">Include yourself and anyone else involved in the incident.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setSelfInjuredCount('none', this)">
              <span class="cc-icon"><i class="bi bi-check-circle-fill"></i></span>
              <div class="cc-label">None</div>
              <div class="cc-sub">No injuries — property damage only</div>
            </button>
            <button class="choice-card" onclick="setSelfInjuredCount('one', this)">
              <span class="cc-icon"><i class="bi bi-person-fill-exclamation"></i></span>
              <div class="cc-label">1 Person</div>
              <div class="cc-sub">One person may be hurt</div>
            </button>
            <button class="choice-card" onclick="setSelfInjuredCount('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill"></i></span>
              <div class="cc-label">2 or More</div>
              <div class="cc-sub">Multiple people are injured</div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- S3 — Are YOU hurt? -->
        <div class="wizard-step" id="step-s3">
          <!-- <div class="alert-banner danger">
            <i class="bi bi-exclamation-triangle-fill" style="color:#E90101;"></i>
            <div>
              <div class="ab-title">Injury Reported</div>
              <div class="ab-body">Please assess your own condition first before proceeding.</div>
            </div>
          </div> -->

          <div class="step-title">Are you personally hurt?</div>
          <p class="step-sub">Your wellbeing is the priority — be honest so we can guide you properly.</p>

          <div class="choice-grid">
            <button class="choice-btn danger" onclick="setSelfHurt(true)">
              <span class="cb-icon"><i class="bi bi-bandaid"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, I am hurt</div>
                <div class="cb-desc">I need medical attention</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setSelfHurt(false)">
              <span class="cb-icon"><i class="bi bi-check2-circle"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, I am not hurt </div>
                <div class="cb-desc">I'm okay physically</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- S-SPEED-DIAL — Emergency contacts (self hurt) -->
        <div class="wizard-step" id="step-s-speed-dial">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">You may be injured. Call for help RIGHT NOW before doing anything else.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToFormFlow()">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>


        <!-- S-ATTENDED — Are you attended by TMO/Police? (self, not hurt) -->
        <div class="wizard-step" id="step-s-attended">
          <div class="step-title">Is a law enforcer attending you?</div>
          <p class="step-sub">Are TMO (Traffic Management Officer) or police present at the scene?</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setSelfAttended(true)">
              <span class="cb-icon"><i class="bi bi-person-badge"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, TMO or Police is here</div>
                <div class="cb-desc">Enforcer is present at the scene</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setSelfAttended(false)">
              <span class="cb-icon"><i class="bi bi-question-circle"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, no enforcer present</div>
                <div class="cb-desc">Nobody has arrived yet</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- S-CALL-TMO — prompt to call TMO (not attended) -->
        <div class="wizard-step" id="step-s-call-tmo">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">No enforcer is present. You must contact TMO or Police before proceeding.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:136" class="call-btn amber" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToStep('step-s-attended')">I've Called — Continue <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>


        <!-- S-DOC-NOTE — Enforcer present, document the scene -->
        <div class="wizard-step" id="step-s-doc-note">
          <div class="alert-banner success">
            <i class="bi bi-shield-check-fill" style="color:#00c853;"></i>
            <div>
              <div class="ab-title">An enforcer is with you — great!</div>
              <div class="ab-body">Stay cooperative and follow their instructions. Take note of the enforcer's ID/badge number if possible.</div>
            </div>
          </div>

          <div class="step-title">Document the scene</div>
          <p class="step-sub">Before filing, make sure to take note of the following items while the enforcer is present:</p>

          <?php foreach (
            [
              ['bi-camera', 'Take photos of vehicle damage, road markings, and surroundings'],
              ['bi-person-badge', 'Note the officer\'s name, badge number, and unit'],
              ['bi-file-earmark-text', 'Request a copy of the official blotter or incident slip'],
              ['bi-clock', 'Record the exact time and confirm with the officer'],
            ] as [$icon, $text]
          ): ?>
            <div style="display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:0.75rem;background:#f9fafb;border-radius:0.85rem;padding:0.85rem;">
              <i class="bi <?= $icon ?>" style="color:var(--primary);margin-top:2px;"></i>
              <span style="font-size:0.8rem;line-height:1.5;color:var(--text);"><?= $text ?></span>
            </div>
          <?php endforeach; ?>

          <button class="btn-primary" onclick="goToFormFlow()"><i class="bi bi-arrow-right"></i> Proceed to File Report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- S-PROPERTY — No injury, property only (Good Citizen) -->
        <div class="wizard-step" id="step-s-property">
          <div class="center-screen">
            <div class="hero-icon green"><i class="bi bi-star-fill"></i></div>
            <div class="step-title">Good Citizen Report</div>
            <p class="step-sub">No injuries involved. Filing this report helps improve road safety!</p>
          </div>

          <div class="points-badge">
            <div class="pb-points">+50 pts</div>
            <div class="pb-label">Earned upon verification of your report</div>
          </div>

          <div class="alert-banner info">
            <i class="bi bi-info-circle-fill" style="color:var(--primary);"></i>
            <div>
              <div class="ab-title">How this works</div>
              <div class="ab-body">Submit your report with photos and a description. Our team will verify it and award your points within 24–48 hours.</div>
            </div>
          </div>

          <button class="btn-primary" onclick="goToFormFlow('good_citizen')"><i class="bi bi-arrow-right"></i> Start Report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>


        <!-- ══════════════════════════════════════════════════
             MULTI-PARTY FLOW (Two / Three+)
        ═══════════════════════════════════════════════════════ -->

        <!-- M1 — How many people are injured? (MULTI FLOW) -->
        <div class="wizard-step" id="step-m1">
          <div class="step-title">How many people are injured?</div>
          <p class="step-sub">Include all parties — drivers, passengers, and bystanders.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setMultiInjuredCount('none', this)">
              <span class="cc-icon"><i class="bi bi-check-circle-fill"></i></span>
              <div class="cc-label">None</div>
              <div class="cc-sub">No injuries — property damage only</div>
            </button>
            <button class="choice-card" onclick="setMultiInjuredCount('one', this)">
              <span class="cc-icon"><i class="bi bi-person-fill-exclamation"></i></span>
              <div class="cc-label">1 Person</div>
              <div class="cc-sub">One person may be hurt</div>
            </button>
            <button class="choice-card" onclick="setMultiInjuredCount('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill"></i></span>
              <div class="cc-label">2 or More</div>
              <div class="cc-sub">Multiple people are injured</div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-NONE-ATTENDED — Three+ parties, no injury: is TMO/Police attending? -->
        <div class="wizard-step" id="step-m-none-attended">
          <div class="alert-banner info" style="margin-bottom:1.1rem;">
            <i class="bi bi-info-circle-fill" style="color:var(--primary);"></i>
            <div>
              <div class="ab-title">Multi-vehicle Accident — No Injuries Reported</div>
              <div class="ab-body">With 3 or more vehicles involved, law enforcement is required to document the scene properly.</div>
            </div>
          </div>

          <div class="step-title">Is TMO or Police attending the scene?</div>
          <p class="step-sub">A law enforcer must be present for a multi-party accident, even without injuries.</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setMultiNoneAttended(true)">
              <span class="cb-icon"><i class="bi bi-person-badge"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, TMO or Police is here</div>
                <div class="cb-desc">Enforcer is present at the scene</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setMultiNoneAttended(false)">
              <span class="cb-icon"><i class="bi bi-telephone-x"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, nobody has arrived yet</div>
                <div class="cb-desc">No law enforcer at the scene</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-NONE-SPEED-DIAL — Three+ parties, no injury, no enforcer: speed dial + file report -->
        <div class="wizard-step" id="step-m-none-speed-dial">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">Multiple vehicles, no enforcer present. Contact TMO or Police RIGHT NOW.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:136" class="call-btn amber" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToFormFlow()">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>


        <!-- M-ATTENDED — Is TMO or police attending? -->
        <div class="wizard-step" id="step-m-attended">
          <!-- <div class="alert-banner danger">
            <i class="bi bi-exclamation-triangle-fill" style="color:#E90101;"></i>
            <div>
              <div class="ab-title">Injury reported — ensure safety first</div>
              <div class="ab-body">Please confirm if a law enforcer is present to assist everyone involved.</div>
            </div>
          </div> -->

          <div class="step-title">Is TMO or Police attending the scene?</div>
          <p class="step-sub">A law enforcer's presence helps document the incident officially.</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setMultiAttended(true)">
              <span class="cb-icon"><i class="bi bi-person-badge"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, enforcer is present</div>
                <div class="cb-desc">TMO or Police is at the scene</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setMultiAttended(false)">
              <span class="cb-icon"><i class="bi bi-telephone-x"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, nobody has arrived</div>
                <div class="cb-desc">No law enforcer yet</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-SPEED-DIAL — Call TMO (not attended, injury present) -->
        <div class="wizard-step" id="step-m-speed-dial">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">There is an injury and no enforcer present. Call for help RIGHT NOW.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToFormFlow()">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>


        <!-- ══════════════════════════════════════════════════
             INJURY SEVERITY BRANCH STEPS (shared for all flows with injury)
        ═══════════════════════════════════════════════════════ -->

        <!-- INJURY-SEVERITY — How bad are the injuries? -->
        <div class="wizard-step" id="step-injury-severity">
          <div class="alert-banner danger" style="margin-bottom:1.25rem;">
            <i class="bi bi-exclamation-triangle-fill" style="color:#E90101;"></i>
            <div>
              <div class="ab-title">Injury Reported</div>
              <div class="ab-body">Please assess the severity carefully. This helps us guide the correct response.</div>
            </div>
          </div>

          <div class="step-title">How bad are the injuries?</div>
          <p class="step-sub">Assess the condition of everyone involved as best you can right now.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setInjurySeverity('minor', this)">
              <span class="cc-icon" style="background:#fffbeb;color:#f59e0b;"><i class="bi bi-bandaid-fill"></i></span>
              <div class="cc-label">Minor Injury</div>
              <div class="cc-sub">Cuts, bruises — no life-threatening condition</div>
            </button>
            <button class="choice-card" onclick="setInjurySeverity('major', this)">
              <span class="cc-icon" style="background:#fef2f2;color:#E90101;"><i class="bi bi-heartbreak-fill"></i></span>
              <div class="cc-label">Major Injury</div>
              <div class="cc-sub">Serious — unconscious, bleeding heavily, or critical</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- INJURY-HOTLINE — Urgent: Call for help now! -->
        <div class="wizard-step" id="step-injury-hotline">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">Someone is injured. Please call for help RIGHT NOW before doing anything else.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="setHotlineChoice(false)">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>

        <!-- MINOR-CALL-TMO — Minor injury, user chose to call TMO first -->
        <div class="wizard-step" id="step-minor-call-tmo">
          <div class="alert-banner danger" style="margin-bottom:1.1rem; border-width:2px;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101; font-size:1.4rem; flex-shrink:0;"></i>
            <div>
              <div class="ab-title" style="font-size:0.85rem; color:#E90101;">⚠ IMMEDIATE ACTION REQUIRED</div>
              <div class="ab-body" style="font-weight:600; color:#7f1d1d;">Minor injury detected. Contact TMO or emergency services RIGHT NOW.</div>
            </div>
          </div>

          <div class="center-screen" style="margin-bottom:0.75rem;">
            <div class="hero-icon red pulse"><i class="bi bi-telephone-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.3rem; line-height:1.3; color:#E90101;">Call Emergency Services Now</div>
            <p class="step-sub" style="font-weight:600; color:#7f1d1d;">Every second counts. Do not delay — call immediately.</p>
          </div>

          <div class="call-grid">
            <a href="tel:136" class="call-btn amber" style="font-size:1rem;">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO 136</strong>
              <span class="ca-label">Traffic Management</span>
            </a>
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>CALL 911</strong>
              <span class="ca-label">National Emergency</span>
            </a>
          </div>

          <hr class="divider">
          <p style="font-size:0.75rem; color:var(--muted); text-align:center; margin-bottom:0.75rem; line-height:1.5;">
            <i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i>
            Only skip if emergency services have <strong>already been called</strong> or are already on the way.
          </p>
          <div class="btn-row" style="margin-top:0;">
            <button class="btn-outline" style="width:100%; margin-bottom:0;" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
            <button class="btn-outline" style="width:100%; margin-bottom:0; color:var(--muted); border-color:#d1d5db;" onclick="goToFormFlow()">Skip, File a Report Anyway <i class="bi bi-arrow-right"></i></button>
          </div>
        </div>


        <!-- INJURY-DECEASED — Are there any deceased? -->
        <div class="wizard-step" id="step-injury-deceased">
          <div class="alert-banner danger" style="margin-bottom:1.25rem;">
            <i class="bi bi-exclamation-octagon-fill" style="color:#E90101;"></i>
            <div>
              <div class="ab-title">Critical Question</div>
              <div class="ab-body">Your answer determines the next steps for this case. Please answer honestly.</div>
            </div>
          </div>
          <div class="step-title">Are there any deceased / fatalities?</div>
          <p class="step-sub">This is a critical factor in how this case will be handled.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setDeceased(true, this)">
              <span class="cc-icon" style="background:#fef2f2;color:#E90101;"><i class="bi bi-exclamation-octagon-fill"></i></span>
              <div class="cc-label">Yes — there are fatalities</div>
              <div class="cc-sub">One or more people have died</div>
            </button>
            <button class="choice-card" onclick="setDeceased(false, this)">
              <span class="cc-icon"><i class="bi bi-check-circle-fill"></i></span>
              <div class="cc-label">No — everyone is alive</div>
              <div class="cc-sub">No deaths at this time</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- INJURY-CONFIRM-REPORT — No deceased: ask if they still want to report -->
        <div class="wizard-step" id="step-injury-confirm-report">
          <div class="center-screen" style="padding-bottom:0;">
            <div class="hero-icon amber pulse" id="injury-confirm-icon"><i class="bi bi-file-earmark-text"></i></div>
            <div class="step-title" id="injury-confirm-title">Do you still want to file a report?</div>
            <p class="step-sub" id="injury-confirm-sub">You can still document this incident for the record — even if it has already been handled.</p>
          </div>

          <div class="alert-banner warn" id="injury-confirm-banner" style="margin-top:1rem;">
            <i class="bi bi-info-circle-fill" style="color:#f59e0b;"></i>
            <div>
              <div class="ab-title" id="injury-confirm-banner-title">Minor Injury Noted</div>
              <div class="ab-body" id="injury-confirm-banner-body">Filing a report creates an official record and may help with insurance or legal processes later.</div>
            </div>
          </div>

          <hr class="divider">
          <button class="btn-primary" onclick="setConfirmReport(true)"><i class="bi bi-file-earmark-text" style="color:#fbbf24;"></i> Yes, I want to file a report</button>
          <button class="btn-outline" onclick="setConfirmReport(false)"><i class="bi bi-x-circle"></i> No, I'm done</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- INJURY-ESCALATE — Immediate escalation (deceased = YES) -->
        <div class="wizard-step" id="step-injury-escalate">
          <div class="center-screen">
            <div class="hero-icon red pulse"><i class="bi bi-exclamation-octagon-fill"></i></div>
            <div class="step-title" style="color:#E90101;">Escalating Immediately</div>
            <p class="step-sub">This case involves fatalities. Emergency responders are being called right away. Do NOT move the vehicles. Stay at the scene and cooperate with authorities.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>Call 911</strong>
              <span class="ca-label">Emergency Hotline</span>
            </a>
            <a href="tel:117" class="call-btn blue">
              <span class="ca-icon"><i class="bi bi-truck-front"></i></span>
              <strong>Call 117</strong>
              <span class="ca-label">Philippine Red Cross</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO Hotline</strong>
              <span class="ca-label">Traffic Mgmt</span>
            </a>
            <a href="tel:7220650" class="call-btn green">
              <span class="ca-icon"><i class="bi bi-car-front-fill"></i></span>
              <strong>PNP Hotline</strong>
              <span class="ca-label">722-0650</span>
            </a>
          </div>

          <div class="alert-banner warn" style="margin-top:1rem;">
            <i class="bi bi-info-circle-fill" style="color:#f59e0b;"></i>
            <div>
              <div class="ab-title">What happens next?</div>
              <div class="ab-body">Emergency responders and a higher office will be notified. A formal investigation will follow. You will be contacted by TMO/Police to complete the report.</div>
            </div>
          </div>
          <a href="landing.php" class="btn-primary" style="text-decoration:none;margin-top:0.5rem;"><i class="bi bi-house-fill"></i> Return to Home</a>
        </div>

        <!-- INJURY-WHO-REPORTS — Who is making this report? (no deceased) -->
        <div class="wizard-step" id="step-injury-who-reports">
          <div class="step-title">Who is making this report?</div>
          <p class="step-sub">This helps us determine the correct process for this injury case.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="setReporter('enforcer', this)">
              <span class="cc-icon"><i class="bi bi-person-badge-fill"></i></span>
              <div class="cc-label">An Enforcer</div>
              <div class="cc-sub">TMO or Police officer at the scene</div>
            </button>
            <button class="choice-card" onclick="setReporter('civilian', this)">
              <span class="cc-icon"><i class="bi bi-person-fill"></i></span>
              <div class="cc-label">Another Person / Civilian</div>
              <div class="cc-sub">Driver, passenger, or bystander</div>
            </button>
          </div>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- INJURY-ENFORCER-REQUIRED — Civilian trying to report serious injury case -->
        <div class="wizard-step" id="step-injury-enforcer-required">
          <div class="center-screen">
            <div class="hero-icon amber"><i class="bi bi-person-badge"></i></div>
            <div class="step-title">An Enforcer Must Be Involved</div>
            <p class="step-sub">For injury cases, a Traffic Management Officer or Police must be officially involved to process the report. This case will be referred to the appropriate office.</p>
          </div>

          <div class="alert-banner warn">
            <i class="bi bi-exclamation-circle-fill" style="color:#f59e0b;"></i>
            <div>
              <div class="ab-title">Case Cannot Be Settled Privately</div>
              <div class="ab-body">Injury cases involving no enforcer are automatically escalated to the higher office for proper handling. Please contact TMO to be assigned an enforcer.</div>
            </div>
          </div>

          <div class="call-grid" style="margin-top:0.75rem;">
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO Hotline</strong>
              <span class="ca-label">Speed Dial 136</span>
            </a>
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>Call 911</strong>
              <span class="ca-label">Emergency</span>
            </a>
          </div>
          <a href="landing.php" class="btn-primary" style="text-decoration:none;margin-top:0.75rem;"><i class="bi bi-house-fill"></i> Return to Home</a>
        </div>

        <!-- M-DOC-NOTE — Enforcer present, document the scene -->
        <div class="wizard-step" id="step-m-doc-note">
          <div class="alert-banner success">
            <i class="bi bi-shield-check-fill" style="color:#00c853;"></i>
            <div>
              <div class="ab-title">Enforcer confirmed — stay calm</div>
              <div class="ab-body">Cooperate with the officer. Do not move vehicles unless instructed.</div>
            </div>
          </div>

          <div class="step-title">Document the scene now</div>
          <p class="step-sub">While waiting or during the assessment, gather the following information:</p>

          <?php foreach (
            [
              ['bi-camera', 'Photograph all vehicles, damage, road signs, and skid marks'],
              ['bi-person-badge', 'Record the enforcer\'s name, badge, and unit'],
              ['bi-people', 'Get names and contact numbers of all parties and witnesses'],
              ['bi-file-earmark-check', 'Request the blotter number or incident slip from the officer'],
            ] as [$icon, $text]
          ): ?>
            <div style="display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:0.75rem;background:#f9fafb;border-radius:0.85rem;padding:0.85rem;">
              <i class="bi <?= $icon ?>" style="color:var(--primary);margin-top:2px;"></i>
              <span style="font-size:0.8rem;line-height:1.5;color:var(--text);"><?= $text ?></span>
            </div>
          <?php endforeach; ?>

          <button class="btn-primary" onclick="goToFormFlow()"><i class="bi bi-arrow-right"></i> Proceed to File Report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-SETTLE — Would they like to settle? (no injury) -->
        <div class="wizard-step" id="step-m-settle">
          <div class="step-title">Would you like to settle this directly?</div>
          <p class="step-sub">Since no one is hurt, you have the option to resolve this between parties without filing a formal report.</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setSettle(true)">
              <span class="cb-icon" style="background:#e0f2fe; color:#0ea5e9;"><i class="bi bi-people-fill"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, settle on our own</div>
                <div class="cb-desc">Agree between involved parties</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setSettle(false)">
              <span class="cb-icon" style="background:#f3f4f6; color:#4b5563;"><i class="bi bi-file-earmark-text-fill"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, file a formal report</div>
                <div class="cb-desc">Document through VrakeIT</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-TALK — Talk about agreement in person -->
        <div class="wizard-step" id="step-m-talk">
          <div class="center-screen">
            <div class="hero-icon blue"><i class="bi bi-handshake-fill" style="color:#22c55e;"></i></div>
            <div class="step-title">Talk it out first</div>
            <p class="step-sub">Have an in-person discussion with the other party. This happens outside the app. Use the guide below to make sure you cover all important points.</p>
          </div>

          <div class="alert-banner warn">
            <i class="bi bi-exclamation-circle-fill" style="color:#f59e0b;"></i>
            <div>
              <div class="ab-title">Important reminder</div>
              <div class="ab-body">Only proceed if all parties have agreed on the terms. Do not sign or commit to anything without understanding the full terms.</div>
            </div>
          </div>

          <!-- ── SETTLEMENT GUIDANCE ───────────────────────── -->
          <style>
            .sg-header {
              display: flex; align-items: center; justify-content: space-between;
              margin: 1.1rem 0 0.65rem;
            }
            .sg-title {
              font-size: 0.82rem; font-weight: 700;
              color: var(--primary); text-transform: uppercase;
              letter-spacing: 0.05em;
              display: flex; align-items: center; gap: 0.4rem;
            }
            .sg-topic {
              display: flex; align-items: flex-start; gap: 0.75rem;
              background: #f9fafb; border: 1.5px solid #e5e7eb;
              border-radius: 0.95rem; padding: 0.85rem 0.9rem;
              margin-bottom: 0.55rem;
            }
            .sg-topic-body { flex: 1; min-width: 0; }
            .sg-topic-title {
              font-size: 0.82rem; font-weight: 700;
              color: var(--primary); line-height: 1.3;
              margin-bottom: 0.2rem;
            }
            .sg-topic-desc {
              font-size: 0.74rem; color: var(--muted);
              line-height: 1.5;
            }
            .sg-topic-icon {
              font-size: 1.25rem; margin-top: 1px; flex-shrink: 0;
            }
          </style>

          <div class="sg-header">
            <div class="sg-title" id="sg-heading"><i class="bi bi-chat-dots-fill"></i> Settlement Discussion Guide</div>
          </div>
          <p id="sg-sub" style="font-size:0.76rem;color:var(--muted);margin-bottom:0.75rem;line-height:1.5;">
            Use these topics as a guide when talking to the other party. Cover as many as possible before agreeing on a settlement.
          </p>

          <div id="sgTopicList"></div>

          <hr class="divider">
          <div class="step-title" style="font-size:1rem; margin-bottom:0.35rem;">Create a written in-system contract?</div>
          <p class="step-sub" style="margin-bottom:0.85rem;">We can help you create a digital agreement that both parties can sign within VrakeIT.</p>

          <button class="btn-primary" onclick="startContractWizard()"><i class="bi bi-file-earmark-ruled"></i> Yes, create a contract</button>
          <button class="btn-outline" onclick="goToStep('step-end-settled')"><i class="bi bi-check2-circle"></i> No, we're done — End</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>


        <!-- ══════════════════════════════════════════════════
             5-STEP CONTRACT WIZARD
        ═══════════════════════════════════════════════════════ -->

        <!-- CX Progress Bar (shown inside all cx steps via JS) -->
        <div id="cx-progress-bar" style="display:none; padding:0 0 0.5rem;">
          <div style="display:flex; align-items:center; justify-content:space-between; gap:4px; margin-bottom:4px;">
            <?php
            $cxSteps = ['What Happened','Parties','Damage','Agreement','Review'];
            foreach ($cxSteps as $i => $label):
            ?>
            <div class="cx-step-dot" id="cx-dot-<?= $i+1 ?>" style="flex:1; height:4px; border-radius:4px; background:#e5e7eb; transition:background 0.3s;"></div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <?php foreach ($cxSteps as $i => $label): ?>
            <div style="font-size:0.6rem; font-weight:600; color:#9ca3af; flex:1; text-align:center;" id="cx-label-<?= $i+1 ?>"><?= $label ?></div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- CX-1: What Happened -->
        <div class="wizard-step" id="step-cx-1">
          <div class="step-title">What happened?</div>
          <p class="step-sub">Describe the incident and select the incident type.</p>

          <div class="form-group">
            <label class="form-label">Incident Type <span style="color:#E90101;">*</span></label>
            <div class="chip-group" id="cx-incident-type-chips">
              <button type="button" class="chip-btn" data-val="rear-end" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Rear-End</button>
              <button type="button" class="chip-btn" data-val="sideswipe" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Side-Swipe</button>
              <button type="button" class="chip-btn" data-val="intersection" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Intersection</button>
              <button type="button" class="chip-btn" data-val="hit-parked" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Hit Parked Vehicle</button>
              <button type="button" class="chip-btn" data-val="motorcycle" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Motorcycle</button>
              <button type="button" class="chip-btn" data-val="pedestrian" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Pedestrian</button>
              <button type="button" class="chip-btn" data-val="other" onclick="selectChip('cx-incident-type-chips',this,'cx_incident_type')">Other</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Incident Description <span style="color:#E90101;">*</span></label>
            <textarea class="form-control-vr" id="cx-description" rows="3" placeholder="Briefly describe what happened: where, when, and how..." oninput="cxAutosave()"></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Do both parties agree on what happened?</label>
            <div class="choice-grid" style="margin-bottom:0;">
              <button class="choice-btn" onclick="setCxVersionsAgree(true)" id="cx-agree-yes">
                <span class="cb-icon"><i class="bi bi-check-circle"></i></span>
                <div class="cb-body"><div class="cb-title">Yes, we agree</div><div class="cb-desc">Same version of events</div></div>
              </button>
              <button class="choice-btn" onclick="setCxVersionsAgree(false)" id="cx-agree-no">
                <span class="cb-icon"><i class="bi bi-chat-left-dots"></i></span>
                <div class="cb-body"><div class="cb-title">No, different versions</div><div class="cb-desc">Each party will write their own</div></div>
              </button>
            </div>
          </div>

          <div id="cx-version-p2-block" style="display:none;">
            <div class="form-group">
              <label class="form-label">Party 2's Version of Events</label>
              <textarea class="form-control-vr" id="cx-version-p2" rows="3" placeholder="Party 2: Describe what happened from your perspective..." oninput="cxAutosave()"></textarea>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Who is at fault?</label>
            <div class="chip-group" id="cx-fault-chips">
              <button type="button" class="chip-btn" data-val="party1" onclick="selectChip('cx-fault-chips',this,'cx_fault')">Party 1</button>
              <button type="button" class="chip-btn" data-val="party2" onclick="selectChip('cx-fault-chips',this,'cx_fault')">Party 2</button>
              <button type="button" class="chip-btn" data-val="shared" onclick="selectChip('cx-fault-chips',this,'cx_fault')">Shared</button>
              <button type="button" class="chip-btn" data-val="undetermined" onclick="selectChip('cx-fault-chips',this,'cx_fault')">Not Determined</button>
            </div>
          </div>

          <button class="btn-primary" onclick="cxGoNext(1)"><i class="bi bi-arrow-right"></i> Next — Parties & Vehicles</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- CX-2: Parties & Vehicles -->
        <div class="wizard-step" id="step-cx-2">
          <div class="step-title">Parties & Vehicles</div>
          <p class="step-sub">Your info is pre-filled. Fill in Party 2's details.</p>

          <div class="contract-card">
            <div style="font-size:0.78rem;font-weight:700;color:var(--primary);margin-bottom:0.75rem;"><i class="bi bi-person-fill"></i> Party 1 — You</div>

            <div class="form-group">
              <label class="form-label">Full Name <span style="color:#E90101;">*</span></label>
              <input type="text" class="form-control-vr" id="cx-p1-name" placeholder="Your full name" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Contact Number <span style="color:#E90101;">*</span></label>
              <input type="tel" class="form-control-vr" id="cx-p1-contact" placeholder="09XX XXX XXXX" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" class="form-control-vr" id="cx-p1-address" placeholder="Street, Barangay, City" oninput="cxAutosave()">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Vehicle Type</label>
                <input type="text" class="form-control-vr" id="cx-p1-vehicle-type" placeholder="e.g. Sedan, SUV" oninput="cxAutosave()">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Plate Number</label>
                <input type="text" class="form-control-vr" id="cx-p1-plate" placeholder="e.g. AAA 1234" oninput="cxAutosave()">
              </div>
            </div>
            <div class="form-group" style="margin-top:0.6rem;">
              <label class="form-label">Driver's License No.</label>
              <input type="text" class="form-control-vr" id="cx-p1-license" placeholder="License number" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Insurance Provider & Policy No.</label>
              <input type="text" class="form-control-vr" id="cx-p1-insurance" placeholder="e.g. Malayan, Policy #12345" oninput="cxAutosave()">
            </div>
          </div>

          <div class="contract-card" style="border-color:rgba(0,200,83,0.3);">
            <div style="font-size:0.78rem;font-weight:700;color:#059669;margin-bottom:0.75rem;"><i class="bi bi-person"></i> Party 2</div>

            <div class="form-group">
              <label class="form-label">VrakeIT Email Address <span style="color:#E90101;">*</span></label>
              <p style="font-size:0.72rem;color:var(--muted);margin-bottom:0.5rem;">Party 2 must have a VrakeIT account. The contract invite will be sent to this email.</p>
              <input type="email" class="form-control-vr" id="cx-p2-email" placeholder="their@email.com" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Full Name <span style="color:#E90101;">*</span></label>
              <input type="text" class="form-control-vr" id="cx-p2-name" placeholder="Other party's full name" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Contact Number</label>
              <input type="tel" class="form-control-vr" id="cx-p2-contact" placeholder="09XX XXX XXXX" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Address</label>
              <input type="text" class="form-control-vr" id="cx-p2-address" placeholder="Street, Barangay, City" oninput="cxAutosave()">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.6rem;">
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Vehicle Type</label>
                <input type="text" class="form-control-vr" id="cx-p2-vehicle-type" placeholder="e.g. Sedan, SUV" oninput="cxAutosave()">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Plate Number</label>
                <input type="text" class="form-control-vr" id="cx-p2-plate" placeholder="e.g. AAA 1234" oninput="cxAutosave()">
              </div>
            </div>
            <div class="form-group" style="margin-top:0.6rem;">
              <label class="form-label">Driver's License No.</label>
              <input type="text" class="form-control-vr" id="cx-p2-license" placeholder="License number" oninput="cxAutosave()">
            </div>
            <div class="form-group">
              <label class="form-label">Insurance Provider & Policy No.</label>
              <input type="text" class="form-control-vr" id="cx-p2-insurance" placeholder="e.g. Malayan, Policy #12345" oninput="cxAutosave()">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Witness (Optional)</label>
            <input type="text" class="form-control-vr" id="cx-witness-name" placeholder="Witness full name" style="margin-bottom:0.5rem;" oninput="cxAutosave()">
            <input type="tel" class="form-control-vr" id="cx-witness-contact" placeholder="Witness contact number" oninput="cxAutosave()">
          </div>

          <button class="btn-primary" onclick="cxGoNext(2)"><i class="bi bi-arrow-right"></i> Next — Damage & Evidence</button>
          <button class="btn-outline" onclick="goToStep('step-cx-1')"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- CX-3: Damage & Evidence -->
        <div class="wizard-step" id="step-cx-3">
          <div class="step-title">Damage & Evidence</div>
          <p class="step-sub">Describe each party's vehicle damage and estimated cost.</p>

          <div class="contract-card">
            <div style="font-size:0.78rem;font-weight:700;color:var(--primary);margin-bottom:0.75rem;"><i class="bi bi-car-front"></i> Party 1 Damage</div>
            <div class="form-group">
              <label class="form-label">Damage Description</label>
              <textarea class="form-control-vr" id="cx-damage-desc-p1" rows="2" placeholder="e.g. Dented rear bumper, cracked tail light" oninput="cxAutosave()"></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Estimated Repair Cost</label>
              <div style="position:relative;">
                <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600;font-size:0.85rem;">₱</span>
                <input type="number" class="form-control-vr" id="cx-damage-cost-p1" placeholder="0.00" style="padding-left:2rem;" oninput="cxAutosave()">
              </div>
            </div>
          </div>

          <div class="contract-card" style="border-color:rgba(0,200,83,0.3);">
            <div style="font-size:0.78rem;font-weight:700;color:#059669;margin-bottom:0.75rem;"><i class="bi bi-car-front"></i> Party 2 Damage</div>
            <div class="form-group">
              <label class="form-label">Damage Description</label>
              <textarea class="form-control-vr" id="cx-damage-desc-p2" rows="2" placeholder="e.g. Scraped front bumper, broken headlight" oninput="cxAutosave()"></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Estimated Repair Cost</label>
              <div style="position:relative;">
                <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600;font-size:0.85rem;">₱</span>
                <input type="number" class="form-control-vr" id="cx-damage-cost-p2" placeholder="0.00" style="padding-left:2rem;" oninput="cxAutosave()">
              </div>
            </div>
          </div>

          <button class="btn-primary" onclick="cxGoNext(3)"><i class="bi bi-arrow-right"></i> Next — The Agreement</button>
          <button class="btn-outline" onclick="goToStep('step-cx-2')"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- CX-4: The Agreement -->
        <div class="wizard-step" id="step-cx-4">
          <div class="step-title">The Agreement</div>
          <p class="step-sub">Define how this incident will be resolved between parties.</p>

          <div class="form-group">
            <label class="form-label">Resolution Type <span style="color:#E90101;">*</span></label>
            <div class="chip-group" id="cx-resolution-chips">
              <button type="button" class="chip-btn" data-val="pay_repair" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">Pay Repair Costs</button>
              <button type="button" class="chip-btn" data-val="shoulder_shop" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">Shoulder at Shop</button>
              <button type="button" class="chip-btn" data-val="cash" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">Cash Payment</button>
              <button type="button" class="chip-btn" data-val="split" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">Split Costs</button>
              <button type="button" class="chip-btn" data-val="insurance" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">Go Through Insurance</button>
              <button type="button" class="chip-btn" data-val="no_comp" onclick="selectChip('cx-resolution-chips',this,'cx_resolution_type')">No Compensation</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Who Pays</label>
            <div class="chip-group" id="cx-who-pays-chips">
              <button type="button" class="chip-btn" data-val="party1" onclick="selectChip('cx-who-pays-chips',this,'cx_who_pays')">Party 1</button>
              <button type="button" class="chip-btn" data-val="party2" onclick="selectChip('cx-who-pays-chips',this,'cx_who_pays')">Party 2</button>
              <button type="button" class="chip-btn" data-val="split" onclick="selectChip('cx-who-pays-chips',this,'cx_who_pays')">Both (Split)</button>
              <button type="button" class="chip-btn" data-val="na" onclick="selectChip('cx-who-pays-chips',this,'cx_who_pays')">N/A</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Agreed Amount</label>
            <div style="position:relative;">
              <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600;font-size:0.85rem;">₱</span>
              <input type="number" class="form-control-vr" id="cx-amount" placeholder="0.00" style="padding-left:2rem;" oninput="cxAutosave()">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Payment Method</label>
            <div class="chip-group" id="cx-payment-method-chips">
              <button type="button" class="chip-btn" data-val="cash" onclick="selectChip('cx-payment-method-chips',this,'cx_payment_method')">Cash</button>
              <button type="button" class="chip-btn" data-val="gcash" onclick="selectChip('cx-payment-method-chips',this,'cx_payment_method')">GCash</button>
              <button type="button" class="chip-btn" data-val="maya" onclick="selectChip('cx-payment-method-chips',this,'cx_payment_method')">Maya</button>
              <button type="button" class="chip-btn" data-val="bank_transfer" onclick="selectChip('cx-payment-method-chips',this,'cx_payment_method')">Bank Transfer</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Payment Schedule</label>
            <div class="chip-group" id="cx-payment-schedule-chips">
              <button type="button" class="chip-btn" data-val="lump_sum" onclick="selectChip('cx-payment-schedule-chips',this,'cx_payment_schedule')">Lump Sum</button>
              <button type="button" class="chip-btn" data-val="installments" onclick="selectChip('cx-payment-schedule-chips',this,'cx_payment_schedule')">Installments</button>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Payment Deadline</label>
            <input type="date" class="form-control-vr" id="cx-payment-deadline" min="<?= date('Y-m-d') ?>" oninput="cxAutosave()">
          </div>

          <div class="form-group">
            <label class="form-label">Agreement Terms <span style="color:#E90101;">*</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:0.6rem;">
              <button type="button" class="chip-btn" style="font-size:0.7rem;" onclick="cxInsertTemplate('Party A will shoulder the repair costs of Party B&#39;s vehicle within 7 days from the date of this agreement.')">Pay repair costs</button>
              <button type="button" class="chip-btn" style="font-size:0.7rem;" onclick="cxInsertTemplate('Both parties agree to split the repair costs equally. Each party shall pay their own portion within 7 days.')">Split equally</button>
              <button type="button" class="chip-btn" style="font-size:0.7rem;" onclick="cxInsertTemplate('Both parties agree that all claims regarding this incident are settled. No further legal action shall be taken.')">Full settlement</button>
              <button type="button" class="chip-btn" style="font-size:0.7rem;" onclick="cxInsertTemplate('Both parties agree to process this claim through their respective insurance providers.')">Insurance route</button>
            </div>
            <textarea class="form-control-vr" id="cx-terms" rows="4" placeholder="e.g. Party A will shoulder repair costs of Party B's vehicle..." oninput="cxAutosave()"></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Escalation Clause <span style="font-size:0.72rem;color:var(--muted);font-weight:400;">(What happens if not followed)</span></label>
            <textarea class="form-control-vr" id="cx-escalation" rows="2" placeholder="e.g. Either party may escalate this to the barangay or file a case with the authorities." oninput="cxAutosave()"></textarea>
          </div>

          <button class="btn-primary" onclick="cxGoNext(4)"><i class="bi bi-arrow-right"></i> Next — Review & Sign</button>
          <button class="btn-outline" onclick="goToStep('step-cx-3')"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- CX-5: Review & Sign -->
        <div class="wizard-step" id="step-cx-5">
          <div class="step-title">Review & Sign</div>
          <p class="step-sub">Check all details carefully before sending the contract invite.</p>

          <!-- Summary preview -->
          <div id="cx-review-preview" style="background:#f8fafc;border:1.5px solid #e5e7eb;border-radius:1rem;padding:1rem;margin-bottom:1rem;font-size:0.8rem;line-height:1.7;"></div>

          <hr class="divider">

          <div class="form-group">
            <label class="form-label">Your Consent <span style="color:#E90101;">*</span></label>
            <div style="display:flex;align-items:flex-start;gap:0.6rem;margin-bottom:0.6rem;">
              <input type="checkbox" id="cx-consent-voluntary" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;accent-color:var(--primary);" onchange="cxCheckConsents()">
              <label for="cx-consent-voluntary" style="font-size:0.8rem;color:var(--text);cursor:pointer;">I am agreeing to this contract <strong>voluntarily</strong> and without pressure from any party.</label>
            </div>
            <div style="display:flex;align-items:flex-start;gap:0.6rem;">
              <input type="checkbox" id="cx-consent-hidden" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;accent-color:var(--primary);" onchange="cxCheckConsents()">
              <label for="cx-consent-hidden" style="font-size:0.8rem;color:var(--text);cursor:pointer;">I understand that <strong>hidden damage or injuries found later</strong> may not be covered by this settlement.</label>
            </div>
          </div>

          <div class="alert-banner info" style="margin-bottom:1rem;">
            <i class="bi bi-envelope-fill" style="color:var(--primary);"></i>
            <div>
              <div class="ab-title">Invite will be sent by email</div>
              <div class="ab-body">Party 2 will receive an email invite to review and confirm this contract on their VrakeIT account. The contract is finalized once they confirm.</div>
            </div>
          </div>

          <button id="cx-submit-btn" class="btn-primary" onclick="submitContract()" disabled>
            <i class="bi bi-send-fill" style="color:#fbbf24;"></i> Submit & Send Invite to Party 2
          </button>
          <button class="btn-outline" onclick="goToStep('step-cx-4')"><i class="bi bi-arrow-left"></i> Back</button>
        </div>



        <!-- FORM — Date & Time -->
        <div class="wizard-step" id="step-form-datetime">
          <div class="step-title">When did this happen?</div>
          <p class="step-sub">Provide the date and time of the incident as accurately as possible.</p>

          <div class="form-group">
            <label class="form-label">Date</label>
            <input type="date" class="form-control-vr" id="incidentDate" max="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group" style="margin-bottom:1.5rem;">
            <label class="form-label">Time</label>
            <input type="time" class="form-control-vr" id="incidentTime">
          </div>

          <button class="btn-primary" onclick="saveDatetime()"><i class="bi bi-arrow-right"></i> Next</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Location -->
        <div class="wizard-step" id="step-form-location">
          <div class="step-title">Where did this happen?</div>
          <p class="step-sub">We've detected your location. Drag the pin to adjust if needed.</p>

          <div id="mapContainer"></div>

          <div class="address-box">
            <div class="ab-label">Detected Address</div>
            <div class="ab-value" id="addressDisplay">Detecting location…</div>
            <input type="hidden" id="locLat">
            <input type="hidden" id="locLng">
            <input type="hidden" id="locAddress">
          </div>

          <button class="btn-outline" onclick="detectLocation()" style="margin-bottom:0.75rem;"><i class="bi bi-geo-alt-fill" style="color:var(--primary);"></i> Re-detect Location</button>
          <button class="btn-primary" onclick="saveLocation()"><i class="bi bi-arrow-right"></i> Next</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Parties & Vehicles (skipped for good citizen) -->
        <div class="wizard-step" id="step-form-parties">
          <div class="step-title">Vehicles involved</div>
          <p class="step-sub">Select all vehicle types at the scene, then enter each plate number.</p>

          <div class="chip-group" id="vehicleChips">
            <?php
            $vehicleIcons = [
              'Car'            => 'bi-car-front-fill',
              'Motorcycle'     => 'bi-scooter',
              'Van'            => 'bi-truck',
              'Truck'          => 'bi-truck-front-fill',
              'Tricycle'       => 'bi-bicycle',
              'E-bike/E-trike' => 'bi-lightning-charge-fill',
              'Jeepney'        => 'bi-bus-front-fill',
              'Bus'            => 'bi-bus-front',
              'Bicycle'        => 'bi-bicycle',
            ];
            foreach ($vehicleIcons as $v => $icon): ?>
              <button class="chip" data-vehicle="<?= $v ?>" data-icon="<?= $icon ?>" onclick="toggleChip(this)"><i class="bi <?= $icon ?>"></i> <?= $v ?></button>
            <?php endforeach; ?>
          </div>

          <div id="vehicleDetailForms"></div>

          <button class="btn-primary" onclick="saveVehicleInfo()"><i class="bi bi-arrow-right"></i> Next</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Weather & Road -->
        <div class="wizard-step" id="step-form-conditions">
          <div class="step-title">Conditions at the time</div>
          <p class="step-sub">Select the weather and road conditions during the incident.</p>

          <div class="form-group">
            <label class="form-label">Weather</label>
            <select class="form-control-vr" id="weatherCond">
              <option value="">Select weather condition…</option>
              <?php foreach (['Clear / Sunny', 'Partly Cloudy', 'Cloudy', 'Light Rain', 'Heavy Rain', 'Thunderstorm', 'Foggy', 'Windy'] as $w): ?>
                <option><?= $w ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="margin-bottom:1.5rem;">
            <label class="form-label">Road Condition</label>
            <select class="form-control-vr" id="roadCond">
              <option value="">Select road condition…</option>
              <?php foreach (['Dry / Good', 'Wet', 'Flooded', 'Under Construction', 'Potholed', 'Slippery', 'Debris on Road'] as $r): ?>
                <option><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <button class="btn-primary" onclick="saveConditions()"><i class="bi bi-arrow-right"></i> Next</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Insurance -->
        <div class="wizard-step" id="step-form-insurance">
          <div class="step-title">Insurance status</div>
          <p class="step-sub">What is the insurance status of your vehicle? Select Unknown if unsure.</p>

          <div class="choice-grid">
            <?php foreach (
              [
                ['<i class="bi bi-question-circle-fill"></i>', 'Unknown', 'unknown', 'Not sure about coverage'],
                ['<i class="bi bi-star-fill"></i>', 'Comprehensive', 'comprehensive', 'Full insurance coverage'],
                ['<i class="bi bi-file-earmark-text-fill"></i>', 'TPL Only', 'tpl', 'Third-party liability only'],
                ['<i class="bi bi-slash-circle-fill"></i>', 'None / Uninsured', 'none', 'No insurance policy'],
              ] as [$icon, $label, $val, $desc]
            ): ?>
              <button class="choice-btn py-2" onclick="selectInsurance('<?= $val ?>', this)">
                <span class="cb-icon"><?= $icon ?></span>
                <div class="cb-body">
                  <div class="cb-title"><?= $label ?></div>
                  <div class="cb-desc"><?= $desc ?></div>
                </div>
              </button>
            <?php endforeach; ?>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Photos / Media -->
        <div class="wizard-step" id="step-form-media">
          <div class="step-title">Add photos or videos</div>
          <p class="step-sub">Visuals help document the incident clearly. This step is optional.</p>

          <div class="media-dropzone" onclick="document.getElementById('mediaInput').click()">
            <i class="bi bi-camera-fill"></i>
            <div class="dz-title">Tap to add photos / videos</div>
            <div class="dz-sub">JPG, PNG, MP4 — Max 20MB each</div>
          </div>
          <input type="file" id="mediaInput" accept="image/*,video/*" multiple class="d-none" onchange="previewMedia(this)">
          <div class="media-preview" id="mediaPreview"></div>

          <button class="btn-primary" onclick="goToStep('step-form-description')"><i class="bi bi-arrow-right"></i> Next</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Description -->
        <div class="wizard-step" id="step-form-description">
          <div class="step-title">Describe what happened</div>
          <p class="step-sub">In your own words, describe the sequence of events leading up to and during the incident.</p>

          <textarea class="form-control-vr" id="eventDetails" rows="7" style="margin-bottom:1.25rem;" placeholder="e.g. I was driving along EDSA when a vehicle sideswiped me from the left lane…"></textarea>

          <button class="btn-primary" onclick="goToOverview()"><i class="bi bi-eye"></i> Review Report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- FORM — Overview -->
        <div class="wizard-step" id="step-form-overview">
          <div class="step-title">Review before submitting</div>
          <p class="step-sub">Make sure everything looks correct. You can go back to edit.</p>

          <div class="overview-table">
            <div class="ov-row"><span class="ov-label">Report Type</span><span class="ov-value" id="ov-type">—</span></div>
            <div class="ov-row"><span class="ov-label">Involved</span><span class="ov-value" id="ov-parties">—</span></div>
            <div class="ov-row"><span class="ov-label">Injury</span><span class="ov-value" id="ov-injured">—</span></div>
            <div class="ov-row"><span class="ov-label">Date & Time</span><span class="ov-value" id="ov-datetime">—</span></div>
            <div class="ov-row"><span class="ov-label">Location</span><span class="ov-value" id="ov-location">—</span></div>
            <div class="ov-row"><span class="ov-label">Weather</span><span class="ov-value" id="ov-weather">—</span></div>
            <div class="ov-row"><span class="ov-label">Road</span><span class="ov-value" id="ov-road">—</span></div>
            <div class="ov-row"><span class="ov-label">Insurance</span><span class="ov-value" id="ov-insurance">—</span></div>
            <div class="ov-row">
              <span class="ov-label">Photos</span>
              <div class="ov-value" id="ov-photos" style="display:flex;flex-wrap:wrap;gap:4px;justify-content:flex-end;">None</div>
            </div>
            <div class="ov-row" id="ov-vehicles-row" style="display:none;">
              <span class="ov-label">Vehicles</span>
              <div class="ov-value" id="ov-vehicles" style="text-align:right;">—</div>
            </div>
          </div>

          <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">What Happened</div>
          <div class="ov-details-box" id="ov-details">—</div>

          <button class="btn-primary" id="submitReportBtn" onclick="submitReport()"><i class="bi bi-send-fill"></i> Submit Report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>


        <!-- ══════════════════════════════════════════════════
             END SCREENS
        ═══════════════════════════════════════════════════════ -->

        <!-- End — No report -->
        <div class="wizard-step" id="step-end-no-report">
          <div class="center-screen">
            <div class="hero-icon green"><i class="bi bi-check-circle"></i></div>
            <div class="step-title">Okay, you're all set</div>
            <p class="step-sub">No report has been filed. Stay safe and drive carefully. You can always come back to file a report later.</p>
          </div>
          <a href="landing.php" class="btn-primary" style="text-decoration:none;"><i class="bi bi-house-fill"></i> Go to Home</a>
        </div>

        <!-- End — Settled -->
        <div class="wizard-step" id="step-end-settled">
          <div class="center-screen">
            <div class="hero-icon green"><i class="bi bi-handshake-fill" style="color:#22c55e;"></i></div>
            <div class="step-title">Settlement noted</div>
            <p class="step-sub">Great — the incident was resolved between parties. No formal report was filed. Drive safely!</p>
          </div>
          <a href="landing.php" class="btn-primary" style="text-decoration:none;"><i class="bi bi-house-fill"></i> Go to Home</a>
        </div>

        <!-- End — Contract Saved -->
        <div class="wizard-step" id="step-end-contract-saved">
          <div class="center-screen">
            <div class="hero-icon green pulse"><i class="bi bi-cloud-check-fill"></i></div>
            <div class="step-title">Contract Saved!</div>
            <p class="step-sub" style="margin-bottom:1.25rem;">The settlement contract has been saved and is accessible to all parties.</p>
          </div>

          <div class="overview-table" style="margin-bottom:1.25rem;">
            <div class="ov-row"><span class="ov-label">Contract Ref</span><span class="ov-value text-primary" id="contractRefNum" style="color:var(--primary);">—</span></div>
            <div class="ov-row"><span class="ov-label">Parties</span><span class="ov-value" id="cs-parties">—</span></div>
            <div class="ov-row"><span class="ov-label">Amount</span><span class="ov-value" id="cs-amount">—</span></div>
            <div class="ov-row"><span class="ov-label">Status</span><span class="ov-value" style="color:#00c853;"><i class="bi bi-check-circle"></i> Agreed & Signed</span></div>
          </div>

          <div class="btn-row">
            <a href="contract.php" class="btn-outline" style="text-decoration:none;"><i class="bi bi-eye"></i> View Contract</a>
            <a href="landing.php" class="btn-primary" style="text-decoration:none;"><i class="bi bi-house-fill"></i> Home</a>
          </div>
        </div>

      </div><!-- /wizard-card -->
    </div><!-- /wizard-main -->

    <!-- ─── SIDEBAR ──────────────────────────────────────────── -->
    <aside class="sidebar">
      <div class="sidebar-card">
        <div class="sidebar-title"><i class="bi bi-card-checklist" style="color:var(--primary);"></i> Live Summary</div>
        <div class="sb-item">
          <div class="sb-key">Parties Involved</div>
          <div class="sb-val" id="ls-parties">—</div>
        </div>
        <div class="sb-item">
          <div class="sb-key">Injury</div>
          <div class="sb-val" id="ls-injured">—</div>
        </div>
        <div class="sb-item">
          <div class="sb-key">Flow Type</div>
          <div class="sb-val" id="ls-flow">—</div>
        </div>
        <div class="sb-item">
          <div class="sb-key">Location</div>
          <div class="sb-val" id="ls-location">—</div>
        </div>
        <div class="sb-item">
          <div class="sb-key">Date & Time</div>
          <div class="sb-val" id="ls-datetime">—</div>
        </div>
        <div class="sb-item">
          <div class="sb-key">Weather</div>
          <div class="sb-val" id="ls-weather">—</div>
        </div>
        <div style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid var(--border);">
          <div style="font-size:0.65rem;color:var(--muted);display:flex;align-items:center;gap:0.3rem;"><i class="bi bi-cloud-check-fill" style="color:var(--primary);"></i> Draft auto-saves every 3s</div>
        </div>
      </div>
    </aside>
  </div>

  <!-- ─── IMAGE VIEWER MODAL ──────────────────────────── -->
  <div class="modal fade" id="imageViewerModal" tabindex="-1" style="z-index:1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content" style="background:transparent;border:none;">
        <div class="modal-header" style="border:none;padding:0;justify-content:flex-end;">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="margin-bottom:8px;filter:invert(1);opacity:1;"></button>
        </div>
        <div class="modal-body" style="padding:0;text-align:center;">
          <img id="fullSizeImage" src="" alt="Full" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.8);">
        </div>
      </div>
    </div>
  </div>

  <!-- ─── TRANSLATION ──────────────────────────────────────── -->
  <script>
    let currentLang = 'en';

    const translations = {
      // Header
      'header-title': {
        en: 'Report an Incident',
        tl: 'Mag-ulat ng Insidente'
      },
      'translate-btn': {
        en: '<i class="bi bi-globe"></i> Filipino',
        tl: '<i class="bi bi-globe"></i> English'
      },

      // Step 0 — Role Selection
      'role-title': {
        en: 'What is your role in this incident?',
        tl: 'Ano ang iyong papel sa insidenteng ito?'
      },
      'role-sub': {
        en: 'Select how you are involved so we can guide you correctly.',
        tl: 'Piliin kung paano ka sangkot para mapatnubayan ka namin nang tama.'
      },
      'role-driver': {
        en: 'I am a Driver',
        tl: 'Ako ay isang Drayber'
      },
      'role-driver-sub': {
        en: 'I was directly involved in the accident',
        tl: 'Direkta akong sangkot sa aksidente'
      },
      'role-citizen': {
        en: 'Citizen / Witness',
        tl: 'Mamamayan / Saksi'
      },
      'role-citizen-sub': {
        en: 'I witnessed or am reporting on behalf of others',
        tl: 'Nasaksihan ko o nag-uulat para sa iba'
      },

      // Step 1
      's1-title': {
        en: 'How many drivers are involved?',
        tl: 'Ilang drayber ang sangkot?'
      },
      's1-sub': {
        en: 'This helps us guide you through the right reporting process.',
        tl: 'Tinutulungan nito kaming gabayan kayo sa tamang proseso ng pag-uulat.'
      },
      's1-just-me': {
        en: 'One Driver',
        tl: 'Isang Drayber'
      },
      's1-just-me-sub': {
        en: 'Only me involved',
        tl: 'Ako lang ang sangkot'
      },
      's1-two': {
        en: 'Two Drivers',
        tl: 'Dalawang Drayber'
      },
      's1-two-sub': {
        en: 'Me + Another driver',
        tl: 'Ako + Isang Drayber Pa'
      },
      's1-multi': {
        en: 'Three or More Drivers',
        tl: 'Tatlo o Higit Pang Drayber'
      },
      's1-multi-sub': {
        en: 'Multiple drivers involved',
        tl: 'Maraming drayber ang sangkot'
      },

      // S2 — How many injured?
      's2-title': {
        en: 'How many people are injured?',
        tl: 'Ilang tao ang nasugatan?'
      },
      's2-sub': {
        en: 'Include yourself and anyone else involved in the incident.',
        tl: 'Isama ang iyong sarili at sinumang sangkot sa insidente.'
      },
      's2-none-t': {
        en: 'None',
        tl: 'Wala'
      },
      's2-one-t': {
        en: '1 Person',
        tl: '1 Tao'
      },
      's2-multi-t': {
        en: '2 or More',
        tl: '2 o Higit Pa'
      },


      // S3
      's3-title': {
        en: 'Are you personally hurt?',
        tl: 'Ikaw ba ay nasugatan?'
      },
      's3-sub': {
        en: 'Your wellbeing is the priority — be honest so we can guide you properly.',
        tl: 'Ang iyong kalusugan ang priyoridad — maging tapat para mapatnubayan ka namin nang tama.'
      },
      's3-yes-t': {
        en: 'Yes, I am hurt',
        tl: 'Oo, nasugatan ako'
      },
      's3-yes-d': {
        en: 'I need medical attention',
        tl: 'Kailangan ko ng medikal na tulong'
      },
      's3-no-t': {
        en: 'No, I am not hurt',
        tl: 'Hindi, hindi ako nasugatan'
      },
      's3-no-d': {
        en: "I'm okay physically",
        tl: 'Ayos naman ang aking katawan'
      },

      // Speed dial (self)
      'ssd-title': {
        en: 'Please call for help first',
        tl: 'Manawagan muna ng tulong'
      },
      'ssd-sub': {
        en: 'Contact emergency services before proceeding with your report.',
        tl: 'Makipag-ugnayan sa emergency services bago magpatuloy sa ulat.'
      },
      'ssd-file': {
        en: 'Do you still want to file a report?',
        tl: 'Gusto mo pa bang mag-ulat?'
      },
      'ssd-file-sub': {
        en: 'You can still document the incident even after calling for help.',
        tl: 'Maaari pa rin kayong mag-dokumenta ng insidente pagkatapos tumawag ng tulong.'
      },
      'ssd-yes': {
        en: 'Yes, I want to file a report',
        tl: 'Oo, gusto kong mag-ulat'
      },
      'ssd-no': {
        en: "No, I'm done",
        tl: 'Hindi, tapos na ako'
      },

      // S-Attended
      'sa-title': {
        en: 'Is a law enforcer attending you?',
        tl: 'May pulis o TMO ba na nakatuon sa inyo?'
      },
      'sa-sub': {
        en: 'Are TMO (Traffic Management Officer) or police present at the scene?',
        tl: 'Naroon ba ang TMO (Traffic Management Officer) o pulis sa lugar?'
      },
      'sa-yes-t': {
        en: 'Yes, TMO or Police is here',
        tl: 'Oo, nandito ang TMO o Pulis'
      },
      'sa-yes-d': {
        en: 'Enforcer is present at the scene',
        tl: 'May nagpapatupad ng batas sa lugar'
      },
      'sa-no-t': {
        en: 'No, no enforcer present',
        tl: 'Hindi, walang nagpapatupad'
      },
      'sa-no-d': {
        en: 'Nobody has arrived yet',
        tl: 'Wala pang dumating'
      },

      // S-Call-TMO
      'stmo-title': {
        en: 'Please contact TMO first',
        tl: 'Makipag-ugnayan muna sa TMO'
      },
      'stmo-sub': {
        en: 'Speed Dial your local Traffic Management Officer or the nearest authority before continuing.',
        tl: 'I-speed dial ang iyong lokal na TMO o pinakamalapit na awtoridad bago magpatuloy.'
      },
      'stmo-cont': {
        en: "I've contacted them — Continue",
        tl: 'Nakipag-ugnayan na ako — Ituloy'
      },

      // S-Doc-note
      'sdn-title': {
        en: 'Document the scene',
        tl: 'I-dokumenta ang eksena'
      },
      'sdn-sub': {
        en: 'Before filing, make sure to take note of the following items while the enforcer is present:',
        tl: 'Bago mag-file, tiyaking catat ang mga sumusunod habang naroroon ang nagpapatupad:'
      },
      'sdn-proceed': {
        en: 'Proceed to File Report',
        tl: 'Magpatuloy sa Pag-file ng Ulat'
      },

      // S-Property (Good Citizen)
      'sp-title': {
        en: 'Good Citizen Report',
        tl: 'Ulat ng Mabuting Mamamayan'
      },
      'sp-sub': {
        en: 'No injuries involved. Filing this report helps improve road safety!',
        tl: 'Walang nasaktan. Ang pag-file ng ulat na ito ay nakakatulong sa kaligtasan sa daan!'
      },
      'sp-start': {
        en: 'Start Report',
        tl: 'Simulan ang Ulat'
      },

      // M1
      'm1-title': {
        en: 'Is anyone involved hurt?',
        tl: 'May nasugatan ba sa mga sangkot?'
      },
      'm1-sub': {
        en: 'This includes yourself, the other party, or any bystanders.',
        tl: 'Kasama rito ang iyong sarili, ang kabilang partido, o sinumang nakasaksi.'
      },
      'm1-yes-t': {
        en: 'Yes, someone is injured',
        tl: 'Oo, may nasugatan'
      },
      'm1-yes-d': {
        en: 'Immediate medical attention may be needed',
        tl: 'Maaaring kailangan ng agarang medikal na tulong'
      },
      'm1-no-t': {
        en: 'No, everyone is safe',
        tl: 'Hindi, ligtas ang lahat'
      },
      'm1-no-d': {
        en: 'No physical injuries',
        tl: 'Walang pisikal na pinsala'
      },

      // M-Attended
      'ma-title': {
        en: 'Is TMO or Police attending the scene?',
        tl: 'Nandoon ba ang TMO o Pulis sa lugar?'
      },
      'ma-sub': {
        en: "A law enforcer's presence helps document the incident officially.",
        tl: 'Ang presensya ng nagpapatupad ay nakakatulong sa opisyal na pagdodokumento.'
      },
      'ma-yes-t': {
        en: 'Yes, enforcer is present',
        tl: 'Oo, mayroong enforcer sa lugar'
      },
      'ma-yes-d': {
        en: 'TMO or Police is at the scene',
        tl: 'Ang TMO o Pulis ay nasa lugar'
      },
      'ma-no-t': {
        en: 'No, nobody has arrived',
        tl: 'Hindi, wala pang dumating'
      },
      'ma-no-d': {
        en: 'No law enforcer yet',
        tl: 'Wala pang nagpapatupad ng batas'
      },

      // M-Speed-dial
      'msd-title': {
        en: 'Call for help immediately',
        tl: 'Tumawag ng tulong agad'
      },
      'msd-sub': {
        en: 'There is an injury and no enforcer present. Please call emergency services now.',
        tl: 'May nasugatan at walang nagpapatupad. Manawagan sa emergency services ngayon.'
      },

      // Form: Date/Time
      'fdt-title': {
        en: 'When did this happen?',
        tl: 'Kailan ito nangyari?'
      },
      'fdt-sub': {
        en: 'Provide the date and time of the incident as accurately as possible.',
        tl: 'Ibigay ang petsa at oras ng insidente nang tumpak hangga\'t maaari.'
      },

      // Form: Location
      'floc-title': {
        en: 'Where did this happen?',
        tl: 'Saan ito nangyari?'
      },
      'floc-sub': {
        en: "We've detected your location. Drag the pin to adjust if needed.",
        tl: 'Na-detect na ang iyong lokasyon. I-drag ang pin upang ayusin kung kinakailangan.'
      },
      'floc-redet': {
        en: 'Re-detect Location',
        tl: 'I-detect Muli ang Lokasyon'
      },

      // Form: Vehicles
      'fveh-title': {
        en: 'Vehicles involved',
        tl: 'Mga sasakyan na sangkot'
      },
      'fveh-sub': {
        en: 'Select all vehicle types at the scene.',
        tl: 'Piliin ang lahat ng uri ng sasakyan sa lugar.'
      },

      // Form: Conditions
      'fcond-title': {
        en: 'Conditions at the time',
        tl: 'Mga kondisyon noong panahon ng insidente'
      },
      'fcond-sub': {
        en: 'Select the weather and road conditions during the incident.',
        tl: 'Piliin ang kondisyon ng panahon at daan noong insidente.'
      },

      // Form: Insurance
      'fins-title': {
        en: 'Insurance status',
        tl: 'Katayuan ng insurance'
      },
      'fins-sub': {
        en: 'What is the insurance status of your vehicle? Select Unknown if unsure.',
        tl: 'Ano ang katayuan ng insurance ng iyong sasakyan? Piliin ang Hindi Alam kung hindi sigurado.'
      },
      'fins-unk-t': {
        en: 'Unknown',
        tl: 'Hindi Alam'
      },
      'fins-unk-d': {
        en: 'Not sure about coverage',
        tl: 'Hindi sigurado sa coverage'
      },
      'fins-comp-t': {
        en: 'Comprehensive',
        tl: 'Komprehensibo'
      },
      'fins-comp-d': {
        en: 'Full insurance coverage',
        tl: 'Buong saklaw ng insurance'
      },
      'fins-tpl-t': {
        en: 'TPL Only',
        tl: 'TPL Lamang'
      },
      'fins-tpl-d': {
        en: 'Third-party liability only',
        tl: 'Third-party liability lamang'
      },
      'fins-none-t': {
        en: 'None / Uninsured',
        tl: 'Wala / Walang Insurance'
      },
      'fins-none-d': {
        en: 'No insurance policy',
        tl: 'Walang patakaran sa insurance'
      },

      // Form: Media
      'fmed-title': {
        en: 'Add photos or videos',
        tl: 'Magdagdag ng larawan o video'
      },
      'fmed-sub': {
        en: 'Visuals help document the incident clearly. This step is optional.',
        tl: 'Ang mga larawan ay nakakatulong sa malinaw na pagdodokumento. Opsyonal ang hakbang na ito.'
      },

      // Form: Description
      'fdesc-title': {
        en: 'Describe what happened',
        tl: 'Ilarawan ang nangyari'
      },
      'fdesc-sub': {
        en: 'In your own words, describe the sequence of events leading up to and during the incident.',
        tl: 'Sa iyong sariling salita, ilarawan ang pagkakasunod-sunod ng mga pangyayari bago at sa panahon ng insidente.'
      },
      'fdesc-ph': {
        en: 'e.g. I was driving along EDSA when a vehicle sideswiped me from the left lane…',
        tl: 'hal. Nagmamaneho ako sa EDSA nang may sasakyang sumagasaw sa akin mula sa kaliwang linya…'
      },

      // Form: Overview
      'fov-title': {
        en: 'Review before submitting',
        tl: 'Suriin bago isumite'
      },
      'fov-sub': {
        en: 'Make sure everything looks correct. You can go back to edit.',
        tl: 'Tiyaking tama ang lahat. Maaari kang bumalik upang i-edit.'
      },
      'fov-submit': {
        en: 'Submit Report',
        tl: 'Isumite ang Ulat'
      },

      // M-TALK
      'mtalk-title': {
        en: 'Talk it out first',
        tl: 'Kausapin muna'
      },
      'mtalk-sub': {
        en: 'Have an in-person discussion with the other party. This happens outside the app. Use the guide below to make sure you cover all important points.',
        tl: 'Makipag-usap nang personal sa kabilang partido. Ito ay nangyayari sa labas ng app. Gamitin ang gabay sa ibaba upang matiyak na matalakay ang lahat ng mahahalagang punto.'
      },
      'mtalk-warn-title': {
        en: 'Important reminder',
        tl: 'Mahalagang paalala'
      },
      'mtalk-warn-body': {
        en: 'Only proceed if all parties have agreed on the terms. Do not sign or commit to anything without understanding the full terms.',
        tl: 'Magpatuloy lamang kung sumang-ayon na ang lahat ng partido sa mga kondisyon. Huwag pumirma o mangako ng anuman nang hindi naiintindihan ang buong kasunduan.'
      },
      'mtalk-sg-heading': {
        en: '💬 Settlement Discussion Guide',
        tl: '💬 Gabay sa Talakayan ng Kasunduan'
      },
      'mtalk-sg-sub': {
        en: 'Use these topics as a guide when talking to the other party. Cover as many as possible before agreeing on a settlement.',
        tl: 'Gamitin ang mga paksang ito bilang gabay sa pakikipag-usap sa kabilang partido. Talakayan ang pinakamarami bago sumang-ayon sa kasunduan.'
      },
      'mtalk-contract-title': {
        en: 'Create a written in-system contract?',
        tl: 'Gumawa ng nakasulat na kontrata sa sistema?'
      },
      'mtalk-contract-sub': {
        en: "We can help you create a digital agreement that both parties can sign within VrakeIT.",
        tl: 'Maaari kaming tumulong sa paggawa ng digital na kasunduan na maaaring pirmahan ng parehong partido sa loob ng VrakeIT.'
      },
      'mtalk-btn-contract': {
        en: 'Yes, create a contract',
        tl: 'Oo, gumawa ng kontrata'
      },
      'mtalk-btn-done': {
        en: "No, we're done — End",
        tl: 'Hindi, tapos na kami — Tapusin'
      },

      // Generic buttons
      'btn-next': {
        en: 'Next',
        tl: 'Susunod'
      },
      'btn-back': {
        en: 'Back',
        tl: 'Bumalik'
      },
    };

    // Map: [selector, key, attribute ('text' or 'placeholder')]
    const translationMap = [
      // Header
      ['h1', 'header-title', 'text'],
      ['#translateBtn', 'translate-btn', 'html'],

      // Step 0 — Role Selection
      ['#role-title-el', 'role-title', 'text'],
      ['#role-sub-el', 'role-sub', 'text'],
      ['#role-driver-label', 'role-driver', 'text'],
      ['#role-driver-sub', 'role-driver-sub', 'text'],
      ['#role-citizen-label', 'role-citizen', 'text'],
      ['#role-citizen-sub', 'role-citizen-sub', 'text'],

      // Step 1
      ['#step-1 .step-title', 's1-title', 'text'],
      ['#step-1 .step-sub', 's1-sub', 'text'],
      ['#step-1 .choice-card:nth-child(1) .cc-label', 's1-just-me', 'text'],
      ['#step-1 .choice-card:nth-child(1) .cc-sub', 's1-just-me-sub', 'text'],
      ['#step-1 .choice-card:nth-child(2) .cc-label', 's1-two', 'text'],
      ['#step-1 .choice-card:nth-child(2) .cc-sub', 's1-two-sub', 'text'],
      ['#step-1 .choice-card:nth-child(3) .cc-label', 's1-multi', 'text'],
      ['#step-1 .choice-card:nth-child(3) .cc-sub', 's1-multi-sub', 'text'],

      // S2
      ['#step-s2 .step-title', 's2-title', 'text'],
      ['#step-s2 .step-sub', 's2-sub', 'text'],
      ['#step-s2 .choice-card:nth-child(1) .cc-label', 's2-none-t', 'text'],
      ['#step-s2 .choice-card:nth-child(2) .cc-label', 's2-one-t', 'text'],
      ['#step-s2 .choice-card:nth-child(3) .cc-label', 's2-multi-t', 'text'],
      ['#step-s2 .choice-btn:nth-child(2) .cb-title', 's2-no-t', 'text'],
      ['#step-s2 .choice-btn:nth-child(2) .cb-desc', 's2-no-d', 'text'],

      // S3
      ['#step-s3 .step-title', 's3-title', 'text'],
      ['#step-s3 .step-sub', 's3-sub', 'text'],
      ['#step-s3 .choice-btn:nth-child(1) .cb-title', 's3-yes-t', 'text'],
      ['#step-s3 .choice-btn:nth-child(1) .cb-desc', 's3-yes-d', 'text'],
      ['#step-s3 .choice-btn:nth-child(2) .cb-title', 's3-no-t', 'text'],
      ['#step-s3 .choice-btn:nth-child(2) .cb-desc', 's3-no-d', 'text'],

      // Speed dial self
      ['#step-s-speed-dial .step-title', 'ssd-title', 'text'],
      ['#step-s-speed-dial .step-sub', 'ssd-sub', 'text'],
      ['#step-s-speed-dial hr + .step-title', 'ssd-file', 'text'],
      ['#step-s-speed-dial hr + .step-title + p', 'ssd-file-sub', 'text'],

      // S-attended
      ['#step-s-attended .step-title', 'sa-title', 'text'],
      ['#step-s-attended .step-sub', 'sa-sub', 'text'],
      ['#step-s-attended .choice-btn:nth-child(1) .cb-title', 'sa-yes-t', 'text'],
      ['#step-s-attended .choice-btn:nth-child(1) .cb-desc', 'sa-yes-d', 'text'],
      ['#step-s-attended .choice-btn:nth-child(2) .cb-title', 'sa-no-t', 'text'],
      ['#step-s-attended .choice-btn:nth-child(2) .cb-desc', 'sa-no-d', 'text'],

      // S-call-tmo
      ['#step-s-call-tmo .step-title', 'stmo-title', 'text'],
      ['#step-s-call-tmo .step-sub', 'stmo-sub', 'text'],

      // S-doc-note
      ['#step-s-doc-note .step-title:not(.alert-banner .ab-title)', 'sdn-title', 'text'],
      ['#step-s-doc-note > p.step-sub', 'sdn-sub', 'text'],

      // S-property
      ['#step-s-property .step-title', 'sp-title', 'text'],
      ['#step-s-property .step-sub', 'sp-sub', 'text'],

      // M1
      ['#step-m1 .step-title', 'm1-title', 'text'],
      ['#step-m1 .step-sub', 'm1-sub', 'text'],
      ['#step-m1 .choice-btn:nth-child(1) .cb-title', 'm1-yes-t', 'text'],
      ['#step-m1 .choice-btn:nth-child(1) .cb-desc', 'm1-yes-d', 'text'],
      ['#step-m1 .choice-btn:nth-child(2) .cb-title', 'm1-no-t', 'text'],
      ['#step-m1 .choice-btn:nth-child(2) .cb-desc', 'm1-no-d', 'text'],

      // M-attended
      ['#step-m-attended .step-title', 'ma-title', 'text'],
      ['#step-m-attended .step-sub', 'ma-sub', 'text'],
      ['#step-m-attended .choice-btn:nth-child(1) .cb-title', 'ma-yes-t', 'text'],
      ['#step-m-attended .choice-btn:nth-child(1) .cb-desc', 'ma-yes-d', 'text'],
      ['#step-m-attended .choice-btn:nth-child(2) .cb-title', 'ma-no-t', 'text'],
      ['#step-m-attended .choice-btn:nth-child(2) .cb-desc', 'ma-no-d', 'text'],

      // M-speed-dial
      ['#step-m-speed-dial .step-title', 'msd-title', 'text'],
      ['#step-m-speed-dial .step-sub', 'msd-sub', 'text'],

      // Form steps
      ['#step-form-datetime .step-title', 'fdt-title', 'text'],
      ['#step-form-datetime .step-sub', 'fdt-sub', 'text'],
      ['#step-form-location .step-title', 'floc-title', 'text'],
      ['#step-form-location .step-sub', 'floc-sub', 'text'],
      ['#step-form-parties .step-title', 'fveh-title', 'text'],
      ['#step-form-parties .step-sub', 'fveh-sub', 'text'],
      ['#step-form-conditions .step-title', 'fcond-title', 'text'],
      ['#step-form-conditions .step-sub', 'fcond-sub', 'text'],
      ['#step-form-insurance .step-title', 'fins-title', 'text'],
      ['#step-form-insurance .step-sub', 'fins-sub', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(1) .cb-title', 'fins-unk-t', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(1) .cb-desc', 'fins-unk-d', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(2) .cb-title', 'fins-comp-t', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(2) .cb-desc', 'fins-comp-d', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(3) .cb-title', 'fins-tpl-t', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(3) .cb-desc', 'fins-tpl-d', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(4) .cb-title', 'fins-none-t', 'text'],
      ['#step-form-insurance .choice-btn:nth-child(4) .cb-desc', 'fins-none-d', 'text'],
      ['#step-form-media .step-title', 'fmed-title', 'text'],
      ['#step-form-media .step-sub', 'fmed-sub', 'text'],
      ['#step-form-description .step-title', 'fdesc-title', 'text'],
      ['#step-form-description .step-sub', 'fdesc-sub', 'text'],
      ['#eventDetails', 'fdesc-ph', 'placeholder'],
      ['#step-form-overview .step-title', 'fov-title', 'text'],
      ['#step-form-overview .step-sub', 'fov-sub', 'text'],
      // M-TALK static text
      ['#step-m-talk .center-screen .step-title', 'mtalk-title', 'text'],
      ['#step-m-talk .center-screen .step-sub', 'mtalk-sub', 'text'],
      ['#step-m-talk .alert-banner .ab-title', 'mtalk-warn-title', 'text'],
      ['#step-m-talk .alert-banner .ab-body', 'mtalk-warn-body', 'text'],
      ['#step-m-talk hr + .step-title', 'mtalk-contract-title', 'text'],
      ['#step-m-talk hr + .step-title + p', 'mtalk-contract-sub', 'text'],
    ];

    function toggleLanguage() {
      currentLang = currentLang === 'en' ? 'tl' : 'en';
      applyTranslations();
      // Re-render the settlement guide in the new language if it's been built
      if (document.getElementById('sgTopicList').children.length > 0) {
        buildSettlementGuide();
      }
      // Update sg-heading and sg-sub directly (dynamic elements)
      const sgH = document.getElementById('sg-heading');
      const sgS = document.getElementById('sg-sub');
      if (sgH) sgH.innerHTML = translations['mtalk-sg-heading'][currentLang];
      if (sgS) sgS.textContent = translations['mtalk-sg-sub'][currentLang];
    }

    function applyTranslations() {
      translationMap.forEach(([selector, key, attr]) => {
        const t = translations[key];
        if (!t) return;
        document.querySelectorAll(selector).forEach(el => {
          if (attr === 'text') el.textContent = t[currentLang];
          else if (attr === 'html') el.innerHTML = t[currentLang];
          else if (attr === 'placeholder') el.placeholder = t[currentLang];
        });
      });
      // Also update the calming banner language
      refreshCalmBanner();
    }

    // ══════════════════════════════════════════════════════════
    //  CALMING MESSAGES
    // ══════════════════════════════════════════════════════════
    const calmMessages = [{
        emoji: '<i class="bi bi-flower1" style="color:#fbbf24;"></i>',
        en: 'Take a deep breath. You\'re doing the right thing by reporting this calmly.',
        tl: 'Huminga nang malalim. Ginagawa mo ang tamang bagay sa pag-uulat nang mahinahon.'
      },
      {
        emoji: '<i class="bi bi-heart-fill" style="color:#22c55e;"></i>',
        en: 'Accidents happen to everyone. Stay calm — this form will guide you step by step.',
        tl: 'Nangyayari ang aksidente sa lahat. Manatiling kalmado — gagabayan ka ng form na ito.'
      },
      {
        emoji: '<i class="bi bi-feather" style="color:#a78bfa;"></i>',
        en: 'No need to rush. Take your time — your safety and clarity matter most right now.',
        tl: 'Hindi kailangang magmadali. Mag-ingat — ang iyong kaligtasan at kalinawan ang pinakamahalaga.'
      },
      {
        emoji: '<i class="bi bi-handshake-fill" style="color:#22c55e;"></i>',
        en: 'Keep it civil — the other party is also a person. Together, this can be resolved.',
        tl: 'Manatiling maayos — tao rin ang kabilang partido. Sama-sama, maaari itong maayos.'
      },
      {
        emoji: '<i class="bi bi-person-arms-up" style="color:#60a5fa;"></i>',
        en: 'Anger makes things harder. A calm report leads to a faster, fairer resolution.',
        tl: 'Nagpapalubha ang galit ng sitwasyon. Ang mahinahong ulat ay humahantong sa mas mabilis na solusyon.'
      },
      {
        emoji: '<i class="bi bi-cloud-sun-fill" style="color:#fbbf24;"></i>',
        en: 'You\'re safe now. Focus on the facts — leave the frustration behind for a moment.',
        tl: 'Ligtas ka na ngayon. Tumutok sa mga katotohanan — iwanan muna ang pagkabigo.'
      },
      {
        emoji: '<i class="bi bi-heart-fill" style="color:#fbbf24;"></i>',
        en: 'Road rage only escalates things. Your calm response shows real strength of character.',
        tl: 'Ang road rage ay nagpapalala lamang. Ang iyong mahinahong tugon ay nagpapakita ng tunay na lakas.'
      },
      {
        emoji: '<i class="bi bi-shield-fill-check" style="color:#60b4ff;"></i>',
        en: 'You\'re protected when you report properly. Stay composed and trust the process.',
        tl: 'Protektado ka kapag nag-ulat ka nang tama. Manatiling panatag at magtiwala sa proseso.'
      },
      {
        emoji: '<i class="bi bi-flower2" style="color:#4ade80;"></i>',
        en: 'This too shall pass. Filing a proper report is the quickest path to moving forward.',
        tl: 'Lalipas din ito. Ang pag-file ng wastong ulat ang pinakamabilis na paraan para sumulong.'
      },
      {
        emoji: '<i class="bi bi-sun-fill" style="color:#fbbf24;"></i>',
        en: 'Everyone on the road is trying to get somewhere safely — including you. Stay kind.',
        tl: 'Lahat sa daan ay nagsisikap na makarating nang ligtas — kasama ka. Manatiling mabait.'
      },
    ];

    let lastCalmIndex = -1;

    function getRandomCalmMessage() {
      let idx;
      do {
        idx = Math.floor(Math.random() * calmMessages.length);
      } while (idx === lastCalmIndex);
      lastCalmIndex = idx;
      return calmMessages[idx];
    }

    function refreshCalmBanner() {
      const msg = calmMessages[lastCalmIndex >= 0 ? lastCalmIndex : 0];
      document.getElementById('calmEmoji').innerHTML = msg.emoji;
      document.getElementById('calmText').textContent = msg[currentLang];
    }

    function rotateCalmBanner() {
      const banner = document.getElementById('calmBanner');
      const inner = banner.querySelector('.calm-inner');
      // Fade out, swap, fade in
      inner.style.transition = 'opacity 0.3s';
      inner.style.opacity = '0';
      setTimeout(() => {
        const msg = getRandomCalmMessage();
        document.getElementById('calmEmoji').innerHTML = msg.emoji;
        document.getElementById('calmText').textContent = msg[currentLang];
        inner.style.opacity = '1';
      }, 300);
    }
  </script>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // ══════════════════════════════════════════════════════════
    //  STATE
    // ══════════════════════════════════════════════════════════
    const state = {
      role: '',         // 'driver' | 'citizen'
      parties: '',     // 'self' | 'two' | 'multiple'
      injured_count: '', // 'none' | 'one' | 'multiple'
      injury_severity: '', // 'minor' | 'major'
      has_injury: null,
      self_hurt: null,
      self_attended: null,
      multi_attended: null,
      settle: null,
      flow_type: '', // 'standard' | 'good_citizen' | 'contract'
      incident_date: '',
      incident_time: '',
      location_lat: null,
      location_lng: null,
      location_address: '',
      vehicle_types: [],
      vehicle_counts: [],
      plate_numbers: [],
      weather_condition: '',
      road_condition: '',
      insurance_type: '',
      event_details: '',
      // Contract
      contract_party1_name: '',
      contract_party1_contact: '',
      contract_party2_name: '',
      contract_party2_contact: '',
      contract_terms: '',
      contract_amount: '',
      contract_description: ''
    };

    const stepHistory = ['step-0-role'];
    let mediaFiles = [];

    // ─── Progress map ────────────────────────────────────────
    const STEP_NUM = {
      'step-1': 1,
      'step-s2': 2,
      'step-s3': 3,
      'step-s-speed-dial': 4,
      'step-s-attended': 3,
      'step-s-call-tmo': 4,
      'step-s-doc-note': 4,
      'step-s-property': 3,
      'step-m1': 2,
      'step-m-attended': 3,
      'step-m-speed-dial': 4,
      'step-m-doc-note': 4,
      'step-m-settle': 3,
      'step-m-talk': 4,
      'step-m-contract-form': 5,
      'step-m-contract-review': 6,
      'step-m-contract-revise': 6,
      'step-form-datetime': 5,
      'step-form-location': 6,
      'step-form-parties': 7,
      'step-form-conditions': 8,
      'step-form-insurance': 9,
      'step-form-media': 10,
      'step-form-description': 11,
      'step-form-overview': 12,
      'step-end-no-report': 13,
      'step-end-settled': 13,
      'step-end-contract-saved': 13,
    };
    const MAX_STEPS = 13;

    function showStep(id) {
      document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
      const el = document.getElementById(id);
      if (!el) return;
      el.classList.add('active');
      el.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });

      const num = STEP_NUM[id] || 1;
      document.getElementById('progressBar').style.width = Math.round((num / MAX_STEPS) * 100) + '%';
      document.getElementById('stepLabel').textContent = `Step ${num} of ${MAX_STEPS}`;

      // Rotate calming message on each step
      rotateCalmBanner();

      // Init map when entering location step
      if (id === 'step-form-location') initMap();

      // Init vehicle detail forms when entering parties step
      if (id === 'step-form-parties') updateVehicleDetails();
    }

    function goToStep(id) {
      stepHistory.push(id);
      showStep(id);
    }

    function goBack() {
      if (stepHistory.length > 1) {
        stepHistory.pop();
        showStep(stepHistory[stepHistory.length - 1]);
      }
    }

    function isMultipleParties() {
      return state.parties === 'multiple';
    }

    function isTwoParties() {
      return state.parties === 'two';
    }

    // ══════════════════════════════════════════════════════════
    //  STEP 0 — ROLE SELECTION
    // ══════════════════════════════════════════════════════════
    function chooseRole(role, btn) {
      document.querySelectorAll('#step-0-role .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.role = role;
      const labels = { driver: 'Driver', citizen: 'Citizen/Witness' };
      document.getElementById('flowLabel').textContent = labels[role] || '';
      syncSidebar();
      setTimeout(() => {
        if (role === 'driver') {
          goToStep('step-1');          // Driver: choose how many drivers
        } else {
          // Citizen: follow CitizenFlow — ask how many people are involved
          state.parties = 'citizen';   // Tag so form & submit know the flow
          goToStep('step-c1');
        }
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  CITIZEN FLOW — Step C1: How many people are involved?
    // ══════════════════════════════════════════════════════════
    function setCitizenParties(count, btn) {
      document.querySelectorAll('#step-c1 .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.citizen_party_count = count;
      syncSidebar();
      setTimeout(() => {
        // All choices → ask how many are injured, same flow for 1, 2, or 3+ drivers
        goToStep('step-c2');
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  CITIZEN FLOW — Step C2: How many are injured?
    // ══════════════════════════════════════════════════════════
    function setCitizenInjured(count, btn) {
      document.querySelectorAll('#step-c2 .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.injured_count = count;
      state.has_injury = (count !== 'none');
      syncSidebar();
      setTimeout(() => {
        if (count === 'none') {
          // No injuries → go directly to good-citizen report
          goToFormFlow('good_citizen');
        } else {
          // Injuries present → ask severity
          goToStep('step-c-severity');
        }
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  CITIZEN FLOW — Step C3: How bad are the injuries?
    // ══════════════════════════════════════════════════════════
    function setCitizenSeverity(severity, btn) {
      document.querySelectorAll('#step-c-severity .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.injury_severity = severity;
      syncSidebar();
      setTimeout(() => {
        // Use the same emergency hotline screen as the driver flow
        goToStep('step-injury-hotline');
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  STEP 1 — PARTIES (DRIVER FLOW)
    // ══════════════════════════════════════════════════════════
    function chooseParties(type, btn) {
      document.querySelectorAll('#step-1 .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.parties = type;
      const labels = {
        self: 'Solo Driver',
        two: 'Two Drivers',
        multiple: 'Multi-Party'
      };
      document.getElementById('flowLabel').textContent = labels[type] || '';
      syncSidebar();
      setTimeout(() => {
        if (state.parties === 'self') {
          goToStep('step-s-attended');   // Solo: first check if TMO/Police is attending
        } else {
          goToStep('step-m1');           // Multi: how many injured?
        }
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  CITIZEN FLOW — "Are you hurt?" reused from step-s3
    // ══════════════════════════════════════════════════════════
    function setSelfHurt(hurt) {
      state.self_hurt = hurt;
      syncSidebar();
      if (state.role === 'citizen') {
        // Citizen: if hurt call for help, then proceed to good citizen report form
        if (hurt) {
          goToStep('step-s-speed-dial');
        } else {
          goToStep('step-s-property');  // Good Citizen report
        }
      } else {
        // Driver Self flow:
        // YES — hurt → Speed Dial → "Do you still wish to report?"
        // NO  — not hurt → Good Citizen report (+50 pts)
        goToStep(hurt ? 'step-s-speed-dial' : 'step-s-property');
      }
    }

    // ══════════════════════════════════════════════════════════
    //  DRIVER SELF FLOW — How many injured?
    // ══════════════════════════════════════════════════════════
    function setSelfInjuredCount(count, btn) {
      document.querySelectorAll('#step-s2 .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.injured_count = count;
      state.has_injury = (count !== 'none');
      syncSidebar();
      setTimeout(() => {
        if (count === 'none') {
          // Solo driver, no injury — go straight to report form
          goToFormFlow('good_citizen');
        } else {
          // Solo driver with injury — go to severity check
          goToStep('step-injury-severity');
        }
      }, 180);
    }

    // Legacy compat (speed-dial → report-form button still works)
    function setSelfInjury(injured) {
      state.has_injury = injured;
      syncSidebar();
      goToStep(injured ? 'step-injury-severity' : 'step-s-property');
    }

    function setSelfAttended(attended) {
      state.self_attended = attended;
      syncSidebar();
      if (state.parties === 'self') {
        // Driver Self flow:
        // YES — attended by TMO/Police → go straight to file the report
        // NO  — not attended → ask if they are hurt
        goToStep(attended ? 'step-s-doc-note' : 'step-s3');
      } else {
        // Legacy / multi path
        goToStep(attended ? 'step-s-doc-note' : 'step-s3');
      }
    }

    // ══════════════════════════════════════════════════════════
    //  DRIVER MULTI FLOW — How many injured?
    // ══════════════════════════════════════════════════════════
    function setMultiInjuredCount(count, btn) {
      document.querySelectorAll('#step-m1 .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.injured_count = count;
      state.has_injury = (count !== 'none');
      syncSidebar();
      setTimeout(() => {
        if (count === 'none') {
          // Three or more parties with no injury — must ask about TMO/Police
          if (state.parties === 'multiple') {
            goToStep('step-m-none-attended');
          } else {
            // Two parties, no injury — offer settlement or formal report
            goToStep('step-m-settle');
          }
        } else {
          // Injury present — assess severity
          goToStep('step-injury-severity');
        }
      }, 180);
    }

    // Legacy compat
    function setMultiInjury(injured) {
      state.has_injury = injured;
      syncSidebar();
      goToStep(injured ? 'step-injury-severity' : 'step-m-settle');
    }

    function setMultiAttended(attended) {
      state.multi_attended = attended;
      syncSidebar();
      goToStep(attended ? 'step-m-doc-note' : 'step-m-speed-dial');
    }

    // Three+ parties, no injury — TMO/Police attending check
    function setMultiNoneAttended(attended) {
      state.multi_attended = attended;
      syncSidebar();
      if (attended) {
        // Enforcer present — proceed directly to report form
        goToFormFlow();
      } else {
        // No enforcer — show speed dial + option to just file report
        goToStep('step-m-none-speed-dial');
      }
    }

    // ══════════════════════════════════════════════════════════
    //  INJURY BRANCH
    //  Minor:  Severity → Hotline → (No: form | Yes: Call TMO → form)
    //  Major:  Severity → Hotline → Deceased → (Fatal: escalate | Alive: confirm → reporter → form)
    // ══════════════════════════════════════════════════════════
    function setInjurySeverity(severity, btn) {
      document.querySelectorAll('#step-injury-severity .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.injury_severity = severity;
      syncSidebar();
      // Both minor and major lead to the hotline question first
      setTimeout(() => goToStep('step-injury-hotline'), 180);
    }

    function setHotlineChoice(called) {
      if (state.injury_severity === 'minor') {
        // Minor injury: skip the deceased check entirely
        if (called) {
          // User wants to call TMO first — show the TMO call screen
          goToStep('step-minor-call-tmo');
        } else {
          // User skips calling — go straight to the report form
          goToFormFlow();
        }
      } else {
        // Major injury: keep the original flow (deceased check)
        goToStep('step-injury-deceased');
      }
    }

    function setDeceased(deceased, btn) {
      document.querySelectorAll('#step-injury-deceased .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      setTimeout(() => {
        if (deceased) {
          // Fatal — escalate immediately
          goToStep('step-injury-escalate');
        } else {
          // No deceased — for minor OR major injury without fatalities,
          // ask if they still want to file a report.
          const severity = state.injury_severity; // 'minor' | 'major'

          // Update the confirm-report step dynamically based on severity
          const title  = document.getElementById('injury-confirm-title');
          const sub    = document.getElementById('injury-confirm-sub');
          const btitle = document.getElementById('injury-confirm-banner-title');
          const bbody  = document.getElementById('injury-confirm-banner-body');
          const icon   = document.getElementById('injury-confirm-icon');
          const banner = document.getElementById('injury-confirm-banner');

          if (severity === 'minor') {
            icon.className  = 'hero-icon amber pulse';
            banner.className = 'alert-banner warn';
            banner.querySelector('i').style.color = '#f59e0b';
            btitle.textContent = 'Minor Injury Noted';
            bbody.textContent  = 'Even for minor injuries, filing a report creates an official record that can help with insurance or legal matters later.';
          } else {
            // major
            icon.className  = 'hero-icon red pulse';
            banner.className = 'alert-banner danger';
            banner.querySelector('i').style.color = '#E90101';
            btitle.textContent = 'Major Injury Noted';
            bbody.textContent  = 'This is a serious incident. Filing an official report is strongly recommended to protect all parties involved.';
          }

          goToStep('step-injury-confirm-report');
        }
      }, 180);
    }

    function setConfirmReport(wantsToReport) {
      if (wantsToReport) {
        // Go straight to the report form
        goToFormFlow();
      } else {
        // User does not want to file — end session
        goToStep('step-end-no-report');
      }
    }

    function setReporter(type, btn) {
      document.querySelectorAll('#step-injury-who-reports .choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      setTimeout(() => {
        if (type === 'enforcer') {
          // Enforcer is reporting — proceed to the report form
          goToFormFlow();
        } else {
          // Civilian reporting injury case without enforcer — cannot continue
          goToStep('step-injury-enforcer-required');
        }
      }, 180);
    }

    function setSettle(settle) {
      state.settle = settle;
      if (settle) {
        buildSettlementGuide();
        goToStep('step-m-talk');
      } else {
        goToFormFlow();
      }
    }

    function buildSettlementGuide() {
      // ── Base topics (always shown) — bilingual ─────────────
      const topics = [
        {
          icon: '<i class="bi bi-camera" style="color:#60a5fa;"></i>',
          en: { title: 'Damage Assessment', desc: 'Inspect and agree on the extent of vehicle or property damage on both sides. Take photos for reference.' },
          tl: { title: 'Pagtatasa ng Pinsala', desc: 'Suriin at sumang-ayon sa lawak ng pinsala sa sasakyan o ari-arian ng magkabilang panig. Kumuha ng mga larawan para sa sanggunian.' }
        },
        {
          icon: '<i class="bi bi-clipboard2-check" style="color:#a78bfa;"></i>',
          en: { title: 'Responsibility Acknowledgment', desc: 'Discuss and acknowledge who was at fault or if responsibility is shared between parties.' },
          tl: { title: 'Pagkilala sa Pananagutan', desc: 'Talakayin at kilalanin kung sino ang may kasalanan o kung ibinabahagi ng magkabilang panig ang pananagutan.' }
        },
        {
          icon: '<i class="bi bi-telephone-fill" style="color:#fbbf24;"></i>',
          en: { title: 'Contact Information Exchange', desc: 'Share full names, mobile numbers, and addresses with each other for follow-up purposes.' },
          tl: { title: 'Pagpapalitan ng Impormasyon', desc: 'Ibahagi ang buong pangalan, numero ng telepono, at address ng bawat partido para sa follow-up.' }
        },
        {
          icon: '<i class="bi bi-cash-stack" style="color:#4ade80;"></i>',
          en: { title: 'Payment or Repair Agreement', desc: 'Agree on the repair method — cash payment, repair-in-kind, or through a preferred shop — and the payment deadline.' },
          tl: { title: 'Kasunduan sa Bayad o Pagkukumpuni', desc: 'Sumang-ayon sa paraan ng pagkukumpuni — cash payment, pagkukumpuni sa napiling shop — at sa takdang petsa ng bayad.' }
        },
        {
          icon: '<i class="bi bi-handshake-fill" style="color:#22c55e;"></i>',
          en: { title: 'Settlement Terms', desc: 'Agree on specific terms — who pays for what, timelines for payment or repairs, and any conditions.' },
          tl: { title: 'Mga Tuntunin ng Kasunduan', desc: 'Sumang-ayon sa mga tiyak na tuntunin — sino ang magbabayad ng ano, takdang panahon ng bayad o pagkukumpuni, at iba pang kondisyon.' }
        },
      ];

      // ── Conditional: injury present ────────────────────────
      if (state.has_injury) {
        topics.splice(1, 0, {
          icon: '<i class="bi bi-hospital" style="color:#ef4444;"></i>',
          en: { title: 'Medical Expense Agreement', desc: 'Discuss who will shoulder medical costs, hospital bills, or rehabilitation expenses for the injured party.' },
          tl: { title: 'Kasunduan sa Gastos sa Medikal', desc: 'Talakayin kung sino ang magnanagot ng gastos sa medikal, bayad sa ospital, o rehabilitasyon ng nasugatan.' }
        });
      }

      // ── Conditional: insurance present ────────────────────
      if (state.insurance_type && state.insurance_type !== 'none' && state.insurance_type !== 'unknown') {
        topics.push({
          icon: '<i class="bi bi-shield-check" style="color:#60a5fa;"></i>',
          en: { title: 'Insurance Discussion', desc: "Discuss whether an insurance claim will be filed and which party's policy applies (TPL, comprehensive, etc.)." },
          tl: { title: 'Talakayan ng Insurance', desc: 'Talakayin kung magfa-file ng insurance claim at kung anong polisiya ang naaangkop (TPL, komprehensibo, atbp.).' }
        });
      }

      // ── Conditional: multi-party / witnesses ──────────────
      if (state.parties === 'multiple' || (state.vehicle_types && state.vehicle_types.length > 2)) {
        topics.push({
          icon: '<i class="bi bi-people" style="color:#a78bfa;"></i>',
          en: { title: 'Witness Confirmation', desc: 'Identify any bystanders or witnesses present. Collect their contact information in case it is needed later.' },
          tl: { title: 'Pagpapatunay ng mga Saksi', desc: 'Tukuyin ang mga nakasaksi o testigo. Kolektahin ang kanilang impormasyon sa pakikipag-ugnayan kung kailangan sa hinaharap.' }
        });
      }

      // ── Always last ────────────────────────────────────────
      topics.push({
        icon: '<i class="bi bi-file-earmark-text" style="color:#fbbf24;"></i>',
        en: { title: 'Written Agreement / Contract', desc: "Decide if you will formalize the settlement in writing via VrakeIT's digital contract or a handwritten document." },
        tl: { title: 'Nakasulat na Kasunduan / Kontrata', desc: 'Magpasya kung pormal na isusulat ang kasunduan sa pamamagitan ng digital na kontrata ng VrakeIT o isang kamay na nakasulat na dokumento.' }
      });

      // ── Render (static, display only) ────────────────────
      const list = document.getElementById('sgTopicList');
      const lang = (typeof currentLang !== 'undefined') ? currentLang : 'en';
      list.innerHTML = '';

      topics.forEach(t => {
        const el = document.createElement('div');
        el.className = 'sg-topic';
        el.innerHTML =
          '<span class="sg-topic-icon">' + t.icon + '</span>' +
          '<div class="sg-topic-body">' +
            '<div class="sg-topic-title">' + t[lang].title + '</div>' +
            '<div class="sg-topic-desc">' + t[lang].desc + '</div>' +
          '</div>';
        list.appendChild(el);
      });
    }

    // ══════════════════════════════════════════════════════════
    //  CONTRACT
    // ══════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════
    //  5-STEP CONTRACT WIZARD (cx) STATE & HELPERS
    // ══════════════════════════════════════════════════════════

    const CX_STEPS = ['step-cx-1','step-cx-2','step-cx-3','step-cx-4','step-cx-5'];
    let cx = {};            // contract wizard state
    let cxExistingRef = ''; // if continuing a draft
    let cxSaveTimer   = null;

    // Called by "Yes, create a contract" button in step-m-talk
    function startContractWizard() {
      // Reset state, pre-fill Party 1 from logged-in user
      cx = {
        cx_incident_type: '', cx_fault: '',
        cx_versions_agree: true,
        cx_resolution_type: '', cx_who_pays: '',
        cx_payment_method: '', cx_payment_schedule: '',
      };
      cxExistingRef = '';

      // Restore draft if one exists
      const saved = localStorage.getItem('cx_draft');
      if (saved) {
        try {
          const d = JSON.parse(saved);
          cx = Object.assign(cx, d);
          cxExistingRef = d.existing_ref || '';
          // Re-apply chip states after brief render delay
          setTimeout(cxRestoreChips, 100);
        } catch(e) {}
      }

      // Pre-fill Party 1 from profile
      const p1name  = document.getElementById('cx-p1-name');
      const p1phone = document.getElementById('cx-p1-contact');
      if (p1name  && !p1name.value)  p1name.value  = cx.party1_name  || '<?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>';
      if (p1phone && !p1phone.value) p1phone.value = cx.party1_contact || '<?= htmlspecialchars($user['phone'] ?? '') ?>';

      // Show progress bar
      document.getElementById('cx-progress-bar').style.display = 'block';
      cxUpdateProgress(1);
      goToStep('step-cx-1');
    }

    // ── Chip selector ─────────────────────────────────────────
    function selectChip(groupId, btn, stateKey) {
      document.querySelectorAll(`#${groupId} .chip-btn`).forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      cx[stateKey] = btn.dataset.val;
      cxAutosave();
    }

    function cxRestoreChips() {
      const map = {
        'cx-incident-type-chips': 'cx_incident_type',
        'cx-fault-chips':         'cx_fault',
        'cx-resolution-chips':    'cx_resolution_type',
        'cx-who-pays-chips':      'cx_who_pays',
        'cx-payment-method-chips':'cx_payment_method',
        'cx-payment-schedule-chips':'cx_payment_schedule',
      };
      Object.entries(map).forEach(([gId, key]) => {
        const val = cx[key];
        if (!val) return;
        const btn = document.querySelector(`#${gId} .chip-btn[data-val="${val}"]`);
        if (btn) btn.classList.add('selected');
      });
      // Restore text fields
      const fields = {
        'cx-description': cx.description, 'cx-version-p2': cx.version_p2,
        'cx-p1-name': cx.party1_name, 'cx-p1-contact': cx.party1_contact,
        'cx-p1-address': cx.party1_address, 'cx-p1-vehicle-type': cx.party1_vehicle_type,
        'cx-p1-plate': cx.party1_plate, 'cx-p1-license': cx.party1_license,
        'cx-p1-insurance': cx.party1_insurance,
        'cx-p2-email': cx.party2_email, 'cx-p2-name': cx.party2_name,
        'cx-p2-contact': cx.party2_contact, 'cx-p2-address': cx.party2_address,
        'cx-p2-vehicle-type': cx.party2_vehicle_type, 'cx-p2-plate': cx.party2_plate,
        'cx-p2-license': cx.party2_license, 'cx-p2-insurance': cx.party2_insurance,
        'cx-witness-name': cx.witness_name, 'cx-witness-contact': cx.witness_contact,
        'cx-damage-desc-p1': cx.damage_desc_p1, 'cx-damage-cost-p1': cx.damage_cost_p1,
        'cx-damage-desc-p2': cx.damage_desc_p2, 'cx-damage-cost-p2': cx.damage_cost_p2,
        'cx-amount': cx.amount, 'cx-payment-deadline': cx.payment_deadline,
        'cx-terms': cx.terms, 'cx-escalation': cx.escalation_clause,
      };
      Object.entries(fields).forEach(([id, val]) => {
        const el = document.getElementById(id);
        if (el && val != null) el.value = val;
      });
      // Restore version-p2 block visibility
      if (cx.cx_versions_agree === false) {
        const block = document.getElementById('cx-version-p2-block');
        if (block) block.style.display = 'block';
      }
    }

    // ── Progress bar ──────────────────────────────────────────
    function cxUpdateProgress(activeStep) {
      for (let i = 1; i <= 5; i++) {
        const dot   = document.getElementById(`cx-dot-${i}`);
        const label = document.getElementById(`cx-label-${i}`);
        if (!dot) continue;
        dot.className = 'cx-step-dot';
        if (i < activeStep)       { dot.classList.add('done');   label.style.color = '#10b981'; }
        else if (i === activeStep){ dot.classList.add('active');  label.style.color = 'var(--primary)'; }
        else                      {                               label.style.color = '#9ca3af'; }
      }
    }

    // Show/hide progress bar when entering/leaving CX steps
    const _origGoToStep = goToStep;
    goToStep = function(id) {
      _origGoToStep(id);
      const bar = document.getElementById('cx-progress-bar');
      if (!bar) return;
      const idx = CX_STEPS.indexOf(id);
      if (idx >= 0) {
        bar.style.display = 'block';
        cxUpdateProgress(idx + 1);
      } else {
        bar.style.display = 'none';
      }
    };

    // ── "Do both parties agree?" toggle ───────────────────────
    function setCxVersionsAgree(agree) {
      cx.cx_versions_agree = agree;
      const block = document.getElementById('cx-version-p2-block');
      block.style.display = agree ? 'none' : 'block';
      // Highlight chosen button
      document.getElementById('cx-agree-yes').classList.toggle('choice-btn--active', agree);
      document.getElementById('cx-agree-no').classList.toggle('choice-btn--active', !agree);
      cxAutosave();
    }

    // ── Template insert ───────────────────────────────────────
    function cxInsertTemplate(text) {
      const el = document.getElementById('cx-terms');
      if (!el) return;
      el.value = text;
      cx.terms = text;
      cxAutosave();
    }

    // ── Auto-save to localStorage ─────────────────────────────
    function cxAutosave() {
      clearTimeout(cxSaveTimer);
      cxSaveTimer = setTimeout(() => {
        cxCollectFields();
        cx.existing_ref = cxExistingRef;
        localStorage.setItem('cx_draft', JSON.stringify(cx));
      }, 1500);
    }

    function cxCollectFields() {
      const g = id => (document.getElementById(id) || {}).value || '';
      cx.description        = g('cx-description');
      cx.version_p2         = g('cx-version-p2');
      cx.party1_name        = g('cx-p1-name');
      cx.party1_contact     = g('cx-p1-contact');
      cx.party1_address     = g('cx-p1-address');
      cx.party1_vehicle_type= g('cx-p1-vehicle-type');
      cx.party1_plate       = g('cx-p1-plate');
      cx.party1_license     = g('cx-p1-license');
      cx.party1_insurance   = g('cx-p1-insurance');
      cx.party2_email       = g('cx-p2-email');
      cx.party2_name        = g('cx-p2-name');
      cx.party2_contact     = g('cx-p2-contact');
      cx.party2_address     = g('cx-p2-address');
      cx.party2_vehicle_type= g('cx-p2-vehicle-type');
      cx.party2_plate       = g('cx-p2-plate');
      cx.party2_license     = g('cx-p2-license');
      cx.party2_insurance   = g('cx-p2-insurance');
      cx.witness_name       = g('cx-witness-name');
      cx.witness_contact    = g('cx-witness-contact');
      cx.damage_desc_p1     = g('cx-damage-desc-p1');
      cx.damage_cost_p1     = g('cx-damage-cost-p1');
      cx.damage_desc_p2     = g('cx-damage-desc-p2');
      cx.damage_cost_p2     = g('cx-damage-cost-p2');
      cx.amount             = g('cx-amount');
      cx.payment_deadline   = g('cx-payment-deadline');
      cx.terms              = g('cx-terms');
      cx.escalation_clause  = g('cx-escalation');
    }

    // ── Step validation + navigation ──────────────────────────
    function cxGoNext(step) {
      cxCollectFields();
      if (step === 1) {
        if (!cx.cx_incident_type) { showCxError('Please select an incident type.'); return; }
        if (!cx.description.trim()) { showCxError('Please describe what happened.'); return; }
        goToStep('step-cx-2');
      } else if (step === 2) {
        if (!cx.party1_name.trim()) { showCxError('Please enter your full name.'); return; }
        if (!cx.party1_contact.trim()) { showCxError('Please enter your contact number.'); return; }
        if (!cx.party2_email.trim()) { showCxError('Party 2\'s VrakeIT email is required to send the invite.'); return; }
        if (!cx.party2_name.trim()) { showCxError('Please enter Party 2\'s full name.'); return; }
        goToStep('step-cx-3');
      } else if (step === 3) {
        goToStep('step-cx-4');
      } else if (step === 4) {
        if (!cx.cx_resolution_type) { showCxError('Please select a resolution type.'); return; }
        if (!cx.terms.trim()) { showCxError('Please enter the agreement terms.'); return; }
        // Build review preview
        buildCxReviewPreview();
        goToStep('step-cx-5');
      }
    }

    function showCxError(msg) {
      // Brief shake + toast
      const btn = event.target.closest('button');
      if (btn) { btn.style.animation='none'; setTimeout(()=>{btn.style.animation='';},10); }
      showToast(msg, 'error');
    }

    // ── Review preview builder ────────────────────────────────
    function buildCxReviewPreview() {
      const resLabels = {
        pay_repair:'Pay Repair Costs', shoulder_shop:'Shoulder at Shop',
        cash:'Cash Payment', split:'Split Costs',
        insurance:'Go Through Insurance', no_comp:'No Compensation',
      };
      const payLabels = { cash:'Cash', gcash:'GCash', maya:'Maya', bank_transfer:'Bank Transfer' };
      const schLabels = { lump_sum:'Lump Sum', installments:'Installments' };
      const faultLabels = { party1:'Party 1', party2:'Party 2', shared:'Shared', undetermined:'Not Determined' };
      const incLabels = {
        'rear-end':'Rear-End','sideswipe':'Side-Swipe','intersection':'Intersection',
        'hit-parked':'Hit Parked Vehicle','motorcycle':'Motorcycle',
        'pedestrian':'Pedestrian','other':'Other'
      };

      const row = (l, v) => v ? `<div style="display:flex;gap:0.5rem;margin-bottom:0.4rem;"><span style="font-weight:700;color:#6b7280;min-width:120px;font-size:0.76rem;">${l}</span><span style="font-size:0.8rem;color:#111;">${v}</span></div>` : '';

      let html = `
        <div style="font-size:0.72rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">📋 What Happened</div>
        ${row('Type', incLabels[cx.cx_incident_type] || cx.cx_incident_type)}
        ${row('Fault', faultLabels[cx.cx_fault] || '—')}
        ${row('Description', cx.description)}
        <hr style="border:none;border-top:1px solid #f3f4f6;margin:10px 0;">
        <div style="font-size:0.72rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">👤 Parties</div>
        ${row('Party 1', cx.party1_name + (cx.party1_contact ? ' · ' + cx.party1_contact : ''))}
        ${row('Vehicle 1', [cx.party1_vehicle_type, cx.party1_plate].filter(Boolean).join(' · '))}
        ${row('Party 2', cx.party2_name + (cx.party2_email ? ' · ' + cx.party2_email : ''))}
        ${row('Vehicle 2', [cx.party2_vehicle_type, cx.party2_plate].filter(Boolean).join(' · '))}
        <hr style="border:none;border-top:1px solid #f3f4f6;margin:10px 0;">
        <div style="font-size:0.72rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">🤝 Agreement</div>
        ${row('Resolution', resLabels[cx.cx_resolution_type] || '—')}
        ${cx.amount ? row('Amount', '₱' + parseFloat(cx.amount).toLocaleString()) : ''}
        ${row('Payment', payLabels[cx.cx_payment_method] || '')}
        ${row('Schedule', schLabels[cx.cx_payment_schedule] || '')}
        ${cx.payment_deadline ? row('Deadline', cx.payment_deadline) : ''}
        <div style="margin-top:8px;padding:8px 10px;background:#f0f7ff;border-left:3px solid var(--primary);border-radius:4px;font-size:0.78rem;color:#374151;line-height:1.5;">${cx.terms || '—'}</div>
        ${cx.escalation_clause ? `<div style="margin-top:6px;padding:6px 10px;background:#fef2f2;border-left:3px solid #dc2626;border-radius:4px;font-size:0.76rem;color:#991b1b;line-height:1.5;"><strong>Escalation:</strong> ${cx.escalation_clause}</div>` : ''}
      `;
      document.getElementById('cx-review-preview').innerHTML = html;
    }

    // ── Consent checkboxes ────────────────────────────────────
    function cxCheckConsents() {
      const v = document.getElementById('cx-consent-voluntary')?.checked;
      const h = document.getElementById('cx-consent-hidden')?.checked;
      const btn = document.getElementById('cx-submit-btn');
      if (btn) btn.disabled = !(v && h);
    }

    // ── Final submit ──────────────────────────────────────────
    async function submitContract() {
      const btn = document.getElementById('cx-submit-btn');
      btn.disabled = true;
      btn.innerHTML = '<span style="width:16px;height:16px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:8px;"></span>Submitting...';

      cxCollectFields();
      const fd = new FormData();
      fd.append('action', 'submit');
      fd.append('existing_ref', cxExistingRef);
      // Serialize all cx fields
      const fieldMap = {
        incident_type: cx.cx_incident_type, fault: cx.cx_fault,
        versions_agree: cx.cx_versions_agree ? '1' : '0',
        version_p2: cx.version_p2, description: cx.description,
        party1_name: cx.party1_name, party1_contact: cx.party1_contact,
        party1_address: cx.party1_address, party1_vehicle_type: cx.party1_vehicle_type,
        party1_plate: cx.party1_plate, party1_license: cx.party1_license,
        party1_insurance: cx.party1_insurance,
        party2_email: cx.party2_email, party2_name: cx.party2_name,
        party2_contact: cx.party2_contact, party2_address: cx.party2_address,
        party2_vehicle_type: cx.party2_vehicle_type, party2_plate: cx.party2_plate,
        party2_license: cx.party2_license, party2_insurance: cx.party2_insurance,
        witness_name: cx.witness_name, witness_contact: cx.witness_contact,
        damage_desc_p1: cx.damage_desc_p1, damage_cost_p1: cx.damage_cost_p1,
        damage_desc_p2: cx.damage_desc_p2, damage_cost_p2: cx.damage_cost_p2,
        resolution_type: cx.cx_resolution_type, who_pays: cx.cx_who_pays,
        amount: cx.amount, payment_method: cx.cx_payment_method,
        payment_schedule: cx.cx_payment_schedule,
        payment_deadline: cx.payment_deadline, escalation_clause: cx.escalation_clause,
        terms: cx.terms,
        consent_voluntary: '1', consent_hidden_damage: '1',
      };
      Object.entries(fieldMap).forEach(([k,v]) => { if (v != null) fd.append(k, v); });

      try {
        const res  = await fetch('api/submit_contract.php', { method:'POST', body:fd });
        const data = await res.json();

        if (data.success) {
          // Clear draft from localStorage
          localStorage.removeItem('cx_draft');
          cxExistingRef = data.reference_number;

          // Update end screen
          const refEl = document.getElementById('contractRefNum');
          if (refEl) refEl.textContent = data.reference_number;
          const psEl  = document.getElementById('cs-parties');
          if (psEl) psEl.textContent = `${cx.party1_name} & ${cx.party2_name}`;
          goToStep('step-end-contract-saved');
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send-fill" style="color:#fbbf24;"></i> Submit & Send Invite to Party 2';
          showToast(data.message || 'Failed to submit. Please try again.', 'error');
        }
      } catch(e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill" style="color:#fbbf24;"></i> Submit & Send Invite to Party 2';
        showToast('Network error. Please check your connection.', 'error');
      }
    }

    // ── Legacy stubs (kept for backward compat, now unused) ───
    function saveContractForm() { startContractWizard(); }
    function setContractAgree() {}


    // ══════════════════════════════════════════════════════════
    //  ROUTE TO FORM FLOW
    // ══════════════════════════════════════════════════════════
    function goToFormFlow(overrideFlow) {
      // Citizens/witnesses ALWAYS get good citizen flow (eligible for points)
      // regardless of whether there were injuries or not.
      const effectiveFlow = (state.role === 'citizen') ? 'good_citizen' : (overrideFlow || 'standard');
      state.flow_type = effectiveFlow;
      document.getElementById('flowLabel').textContent = effectiveFlow === 'good_citizen' ? 'Good Citizen 🌟' : 'Filing Report';
      syncSidebar();
      goToStep('step-form-datetime');
    }

    // ══════════════════════════════════════════════════════════
    //  FORM STEPS
    // ══════════════════════════════════════════════════════════
    function saveDatetime() {
      const d = document.getElementById('incidentDate').value;
      const t = document.getElementById('incidentTime').value;
      if (!d || !t) {
        alert('Please set both date and time.');
        return;
      }
      state.incident_date = d;
      state.incident_time = t;
      syncSidebar();
      goToStep('step-form-location');
    }

    function saveLocation() {
      if (!state.location_lat) {
        alert('Please wait for location detection or allow GPS access.');
        return;
      }
      // Skip parties step for solo/good-citizen; else include it
      goToStep('step-form-parties');
    }

    // Vehicle chips
    function toggleChip(btn) {
      btn.classList.toggle('active');
      updateVehicleDetails();
    }

    function updateVehicleDetails() {
      const chips = [...document.querySelectorAll('#vehicleChips .chip.active')];
      const container = document.getElementById('vehicleDetailForms');
      if (chips.length === 0) { container.innerHTML = ''; return; }

      // Preserve existing counts when re-rendering
      const prevCounts = {};
      document.querySelectorAll('.vd-card').forEach(card => {
        const v = card.dataset.vehicleKey;
        const cnt = parseInt(card.querySelector('.vd-count-val')?.textContent) || 1;
        prevCounts[v] = cnt;
      });

      container.innerHTML = chips.map((chip, i) => {
        const v    = chip.dataset.vehicle;
        const icon = chip.dataset.icon || 'bi-car-front-fill';
        const cnt  = prevCounts[v] || 1;
        const plates = Array.from({ length: cnt }, (_, j) => `
          <div class="vd-plate-row">
            <div class="vd-plate-num">${j + 1}</div>
            <input class="vd-plate-input" id="vp_${i}_${j}" type="text" placeholder="e.g. ABC 1234" maxlength="12">
          </div>
        `).join('');
        return `
          <div class="vd-card" data-vehicle-key="${v}" data-chip-index="${i}">
            <div class="vd-card-header">
              <div class="vd-card-label">
                <span class="vd-icon"><i class="bi ${icon}"></i></span>
                <span>${v}</span>
              </div>
              <div class="vd-stepper">
                <button type="button" onclick="stepCount(this, -1, ${i})" aria-label="Decrease">−</button>
                <span class="vd-count-val">${cnt}</span>
                <button type="button" onclick="stepCount(this, 1, ${i})" aria-label="Increase">+</button>
              </div>
            </div>
            <div class="vd-plates" id="vd_plates_${i}">
              ${plates}
            </div>
          </div>`;
      }).join('');
    }

    function stepCount(btn, delta, chipIdx) {
      const card     = btn.closest('.vd-card');
      const valEl    = card.querySelector('.vd-count-val');
      const platesEl = document.getElementById(`vd_plates_${chipIdx}`);
      const chip     = document.querySelectorAll('#vehicleChips .chip.active')[chipIdx];
      const icon     = chip ? chip.dataset.icon : 'bi-car-front-fill';
      let cnt = parseInt(valEl.textContent) + delta;
      if (cnt < 1) cnt = 1;
      if (cnt > 20) cnt = 20;
      valEl.textContent = cnt;

      // Preserve existing plate values
      const existingVals = [...platesEl.querySelectorAll('.vd-plate-input')].map(el => el.value);
      platesEl.innerHTML = Array.from({ length: cnt }, (_, j) => `
        <div class="vd-plate-row">
          <div class="vd-plate-num">${j + 1}</div>
          <input class="vd-plate-input" id="vp_${chipIdx}_${j}" type="text" placeholder="e.g. ABC 1234" maxlength="12" value="${existingVals[j] || ''}">
        </div>
      `).join('');
    }

    function saveVehicleInfo() {
      const chips = [...document.querySelectorAll('#vehicleChips .chip.active')];
      if (!chips.length) {
        alert('Please select at least one vehicle type.');
        return;
      }
      state.vehicle_types  = [];
      state.vehicle_counts = [];
      state.plate_numbers  = [];
      chips.forEach((chip, i) => {
        const card = document.querySelector(`.vd-card[data-chip-index="${i}"]`);
        const cnt  = card ? parseInt(card.querySelector('.vd-count-val')?.textContent) || 1 : 1;
        const plates = Array.from({ length: cnt }, (_, j) => (document.getElementById(`vp_${i}_${j}`)?.value || '').trim().toUpperCase()).join(', ');
        state.vehicle_types.push(chip.dataset.vehicle);
        state.vehicle_counts.push(cnt);
        state.plate_numbers.push(plates);
      });
      goToStep('step-form-conditions');
    }

    function saveConditions() {
      const w = document.getElementById('weatherCond').value;
      const r = document.getElementById('roadCond').value;
      if (!w || !r) {
        alert('Please select both weather and road condition.');
        return;
      }
      state.weather_condition = w;
      state.road_condition = r;
      syncSidebar();
      // Citizens/witnesses don't need to declare insurance — skip that step
      if (state.role === 'citizen') {
        state.insurance_type = '';
        goToStep('step-form-media');
      } else {
        goToStep('step-form-insurance');
      }
    }

    function selectInsurance(val, btn) {
      document.querySelectorAll('#step-form-insurance .choice-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      state.insurance_type = val;
      setTimeout(() => goToStep('step-form-media'), 220);
    }

    // ── Media ──
    function previewMedia(input) {
      for (const f of input.files) mediaFiles.push(f);
      const container = document.getElementById('mediaPreview');
      container.innerHTML = '';
      mediaFiles.forEach(file => {
        const url = URL.createObjectURL(file);
        const img = document.createElement('img');
        img.src = url;
        img.onclick = () => viewFullImage(url);
        container.appendChild(img);
      });
    }

    function viewFullImage(src) {
      document.getElementById('fullSizeImage').src = src;
      new bootstrap.Modal(document.getElementById('imageViewerModal')).show();
    }

    // ── Overview ──
    function goToOverview() {
      const desc = document.getElementById('eventDetails').value.trim();
      if (!desc) {
        alert('Please describe what happened.');
        return;
      }
      state.event_details = desc;

      const partyLabels = {
        self: 'Solo (One Driver)',
        two: 'Two Drivers',
        multiple: 'Three or More Drivers'
      };
      document.getElementById('ov-type').innerHTML = state.flow_type === 'good_citizen' ? '<i class="bi bi-star-fill" style="color:#fbbf24;"></i> Good Citizen' : '📋 Standard Report';
      document.getElementById('ov-parties').textContent = partyLabels[state.parties] || '—';
      document.getElementById('ov-injured').textContent = state.has_injury ? 'Yes' : 'No';
      document.getElementById('ov-datetime').textContent = state.incident_date ? `${state.incident_date} @ ${state.incident_time}` : '—';
      document.getElementById('ov-location').textContent = state.location_address || '—';
      document.getElementById('ov-weather').textContent = state.weather_condition || '—';
      document.getElementById('ov-road').textContent = state.road_condition || '—';
      document.getElementById('ov-insurance').textContent = state.insurance_type || '—';
      document.getElementById('ov-details').textContent = desc;

      // Photos thumbnail strip
      const ovPhotos = document.getElementById('ov-photos');
      if (mediaFiles.length > 0) {
        ovPhotos.innerHTML = '';
        mediaFiles.forEach(f => {
          const url = URL.createObjectURL(f);
          const img = document.createElement('img');
          img.src = url;
          img.style.cssText = 'width:40px;height:40px;object-fit:cover;border-radius:6px;cursor:pointer;';
          img.onclick = () => viewFullImage(url);
          ovPhotos.appendChild(img);
        });
      } else {
        ovPhotos.textContent = 'None';
      }

      // Vehicles summary
      const ovVehiclesRow = document.getElementById('ov-vehicles-row');
      const ovVehicles    = document.getElementById('ov-vehicles');
      if (state.vehicle_types && state.vehicle_types.length > 0) {
        ovVehiclesRow.style.display = '';
        ovVehicles.innerHTML = state.vehicle_types.map((v, i) => {
          const plate = state.plate_numbers[i] || 'No plate';
          return `<div style="margin-bottom:2px;"><strong>${v}</strong> &mdash; <span style="font-family:monospace;color:var(--primary);">${plate}</span></div>`;
        }).join('');
      } else {
        ovVehiclesRow.style.display = 'none';
      }

      goToStep('step-form-overview');
    }

    // ─── SUBMIT ──────────────────────────────────────────────
    async function submitReport() {
      const btn = document.getElementById('submitReportBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span> Submitting…';

      const fd = new FormData();
      fd.append('flow_type', state.flow_type);
      fd.append('reporter_role', state.role || 'driver'); // 'driver' | 'citizen'
      fd.append('parties', state.parties);
      // Derive has_other_parties from state.parties
      fd.append('has_other_parties', (state.parties === 'two' || state.parties === 'multiple') ? 1 : 0);
      fd.append('is_injured', state.has_injury ? 1 : 0);
      fd.append('injured_count', state.injured_count || 'none');
      fd.append('injury_severity', state.injury_severity || '');
      fd.append('has_deceased', state.has_deceased === true ? 1 : (state.has_deceased === false ? 0 : ''));
      fd.append('self_hurt', state.self_hurt ?? '');
      fd.append('self_attended', state.self_attended ?? '');
      fd.append('multi_attended', state.multi_attended ?? '');

      // Derive enforcer_type from attended flags:
      // If user said a law enforcer is present, set enforcer_type to 'tmo_police'
      const enforcerPresent = state.self_attended === true || state.multi_attended === true;
      fd.append('enforcer_type', enforcerPresent ? 'tmo_police' : '');

      fd.append('incident_date', state.incident_date);
      fd.append('incident_time', state.incident_time);
      fd.append('location_lat', state.location_lat || '');
      fd.append('location_lng', state.location_lng || '');
      fd.append('location_address', state.location_address);
      state.vehicle_types.forEach((v, i) => {
        fd.append('vehicle_types[]', v);
        fd.append('vehicle_counts[]', state.vehicle_counts[i] || 1);
        fd.append('plate_numbers[]', state.plate_numbers[i] || '');
      });
      fd.append('weather_condition', state.weather_condition);
      fd.append('road_condition', state.road_condition);
      fd.append('insurance_type', state.insurance_type);
      fd.append('event_details', state.event_details);
      mediaFiles.forEach(f => fd.append('media[]', f));

      try {
        const res = await fetch('api/submit_report.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        if (data.success) {
          showSuccessModal(data.reference_number, data.points_earned || 0, data.total_points || 0);
          mediaFiles = [];
        } else {
          alert('Error: ' + (data.message || 'Unknown error'));
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Report';
        }
      } catch {
        alert('Connection error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Report';
      }
    }

    function showSuccessModal(refNum, ptsEarned, totalPts) {
      const isGC = ptsEarned > 0;
      document.body.insertAdjacentHTML('beforeend', `
    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px);">
      <div style="background:#fff;border-radius:24px;padding:2rem 1.5rem;max-width:360px;width:100%;text-align:center;animation:stepIn .35s ease;">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#00c853,#009624);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2.2rem;"><i class="bi bi-check-circle"></i></div>
        <div style="font-size:1.25rem;font-weight:800;margin-bottom:0.5rem;">Report Submitted!</div>
        <div style="background:#f5f5f5;border-radius:12px;padding:0.75rem;margin-bottom:0.85rem;">
          <div style="font-size:0.68rem;color:#888;">Reference Number</div>
          <div style="font-size:1.1rem;font-weight:700;color:#E90101;">${refNum}</div>
        </div>
        ${isGC ? `<div style="background:#e0f2fe;border-radius:12px;padding:0.85rem;margin-bottom:0.85rem;">
          <div style="font-size:1.4rem;font-weight:800;color:#007ED2;">+${ptsEarned} pts</div>
          <div style="font-size:0.75rem;color:#555;">Total: ${totalPts} points</div>
        </div>` : ''}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">
          <a href="track.php" style="background:#f0f0f0;color:#333;border-radius:12px;padding:0.75rem;text-decoration:none;font-weight:600;font-size:0.82rem;">Track Report</a>
          <a href="landing.php" style="background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;border-radius:12px;padding:0.75rem;text-decoration:none;font-weight:600;font-size:0.82rem;">Go Home</a>
        </div>
      </div>
    </div>`);
    }

    // ══════════════════════════════════════════════════════════
    //  MAP
    // ══════════════════════════════════════════════════════════
    let map, mapMarker;

    function initMap() {
      if (map) {
        setTimeout(() => map.invalidateSize(), 100);
        return;
      }
      const VALENZUELA_CENTER = [14.7050, 120.9850];

      // Valenzuela City boundary — accurate vertices (clockwise)
      // Exact city bounds: N 14.7700 | S 14.6400 | E 121.0500 | W 120.9300
      const VALENZUELA_POLY = [
        // ─ Wawang Pulo westward protrusion (westernmost tip) ───────
        [14.7250, 120.9380],  // Wawang Pulo SW approach
        [14.7290, 120.9310],  // Wawang Pulo far-west tip
        [14.7350, 120.9305],  // Wawang Pulo west mid
        [14.7410, 120.9345],  // Wawang Pulo NW shoulder
        [14.7445, 120.9435],  // Wawang Pulo north / Coloong join
        [14.7465, 120.9530],  // Coloong west edge
        // ─ Northern boundary (W → E) ───────────────────────────────
        [14.7505, 120.9580],  // Coloong / Tagalag N
        [14.7550, 120.9660],  // Malanday north
        [14.7595, 120.9755],  // Lingunan NW
        [14.7640, 120.9870],  // Lingunan / Punturin N
        [14.7670, 120.9990],  // Punturin north apex
        [14.7695, 121.0110],  // Bignay west
        [14.7700, 121.0230],  // Bignay north (northernmost)
        [14.7675, 121.0370],  // Bignay NE / Lawang Bato N
        [14.7620, 121.0450],  // Lawang Bato north
        // ─ Eastern boundary (N → S) ────────────────────────────────
        [14.7510, 121.0490],  // Lawang Bato east
        [14.7400, 121.0500],  // Canumay East (easternmost)
        [14.7300, 121.0475],  // Canumay East mid
        [14.7210, 121.0420],  // Bagbaguin east
        [14.7100, 121.0340],  // Ugong east
        [14.7000, 121.0250],  // Mapulang Lupa / Ugong transition
        [14.6945, 121.0165],  // Paso de Blas east
        // ─ Southern boundary (E → W) ───────────────────────────────
        [14.6905, 121.0070],  // Gen. T. De Leon SE
        [14.6750, 120.9990],  // Gen. T. De Leon south (expanded)
        [14.6720, 120.9910],  // Parada / Marulas E (expanded)
        [14.6700, 120.9845],  // Marulas south (expanded southernmost)
        [14.6715, 120.9750],  // Karuhatan south (expanded)
        [14.6735, 120.9640],  // Malinta south (expanded)
        [14.6760, 120.9565],  // Malinta / Rincon SW
        // ─ Western boundary (S → N) ────────────────────────────────
        [14.6935, 120.9530],  // Rincon west
        [14.7020, 120.9505],  // Polo west
        [14.7115, 120.9480],  // Balangkas west
        [14.7200, 120.9455],  // Malanday SW
        [14.7235, 120.9420],  // south approach to Wawang Pulo
      ];

      // Bounds matching exact city extents (with tiny padding)
      const VALENZUELA_BOUNDS = L.latLngBounds(
        L.latLng(14.6380, 120.9270), // SW — slightly beyond S/W city edge
        L.latLng(14.7720, 121.0530)  // NE — slightly beyond N/E city edge
      );
      map = L.map('mapContainer', {
        center: VALENZUELA_CENTER,
        zoom: 14,
        minZoom: 13,
        maxZoom: 19,
        maxBounds: VALENZUELA_BOUNDS,
        maxBoundsViscosity: 1.0
      });

      map.setMaxBounds(VALENZUELA_BOUNDS);

      // ── BASE TILE LAYER (full color) ─────────────────────────
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
      }).addTo(map);

      // ── BORDER HIGHLIGHT ─────────────────────────────────────
      L.polygon(VALENZUELA_POLY, {
        color: '#007ED2',
        weight: 3,
        dashArray: '8 5',
        fill: false,
        interactive: false
      }).addTo(map);

      // ── DARK MASK: inverted polygon covers everything OUTSIDE Valenzuela ──
      const WORLD_OUTER = [
        [90, -180], [90, 180], [-90, 180], [-90, -180]
      ];
      L.polygon([WORLD_OUTER, VALENZUELA_POLY], {
        color: 'transparent',
        weight: 0,
        fillColor: '#0d0d1a',
        fillOpacity: 0.85,
        fillRule: 'evenodd',
        interactive: false
      }).addTo(map);

      // ── DRAGGABLE MARKER ─────────────────────────────────────
      mapMarker = L.marker(VALENZUELA_CENTER, { draggable: true }).addTo(map);
      mapMarker.on('dragend', e => reverseGeocode(e.target.getLatLng()));

      detectLocation();

    }

    function detectLocation() {
      document.getElementById('addressDisplay').textContent = 'Detecting location…';
      if (!navigator.geolocation) {
        document.getElementById('addressDisplay').textContent = 'Geolocation not supported.';
        return;
      }
      navigator.geolocation.getCurrentPosition(pos => {
        const {
          latitude: lat,
          longitude: lng
        } = pos.coords;
        map.setView([lat, lng], 16);
        mapMarker.setLatLng([lat, lng]);
        reverseGeocode({
          lat,
          lng
        });
      }, () => {
        document.getElementById('addressDisplay').textContent = 'Could not detect. Drag the pin manually.';
      });
    }

    async function reverseGeocode({
      lat,
      lng
    }) {
      state.location_lat = lat;
      state.location_lng = lng;
      try {
        const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        const data = await res.json();
        const addr = data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        state.location_address = addr;
        document.getElementById('addressDisplay').textContent = addr;
        document.getElementById('locLat').value = lat;
        document.getElementById('locLng').value = lng;
        document.getElementById('locAddress').value = addr;
        syncSidebar();
      } catch {
        state.location_address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        document.getElementById('addressDisplay').textContent = state.location_address;
      }
    }

    // ══════════════════════════════════════════════════════════
    //  SIDEBAR SYNC
    // ══════════════════════════════════════════════════════════
    function syncSidebar() {
      const partyLabels = {
        self: 'Solo',
        two: 'Two Drivers',
        multiple: 'Three or More Drivers'
      };
      const flowLabels = {
        standard: 'Standard Report',
        good_citizen: 'Good Citizen <i class="bi bi-star-fill"></i>',
        contract: 'Contract Settlement'
      };

      document.getElementById('ls-parties').textContent = partyLabels[state.parties] || '—';
      document.getElementById('ls-injured').textContent = state.has_injury === null ? '—' : (state.has_injury ? 'Yes' : 'No');
      document.getElementById('ls-flow').textContent = flowLabels[state.flow_type] || '—';
      document.getElementById('ls-location').textContent = state.location_address || '—';
      document.getElementById('ls-datetime').textContent = state.incident_date ? `${state.incident_date} ${state.incident_time}` : '—';
      document.getElementById('ls-weather').textContent = state.weather_condition || '—';
    }

    // ══════════════════════════════════════════════════════════
    //  AUTO-SAVE & INIT
    // ══════════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
      // Default date/time
      const now = new Date();
      document.getElementById('incidentDate').value = now.toISOString().split('T')[0];
      document.getElementById('incidentTime').value = now.toTimeString().slice(0, 5);

      // Init first calming message
      rotateCalmBanner();

      // Auto-save draft
      setInterval(() => {
        try {
          const draft = {
            ...state,
            event_details: document.getElementById('eventDetails')?.value || ''
          };
          localStorage.setItem('vrakeit_incident_draft', JSON.stringify(draft));
        } catch (e) {}
      }, 3000);

      // Sidebar sync loop
      setInterval(syncSidebar, 800);
    });
  </script>
</body>

</html>