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
    }

    .chip:hover {
      border-color: var(--primary);
      color: var(--primary);
    }

    .chip.active {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary);
    }

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
        <div class="wizard-step active" id="step-1">
          <div class="step-title">How many people are involved?</div>
          <p class="step-sub">This helps us guide you through the right reporting process.</p>

          <div class="choice-card-grid">
            <button class="choice-card" onclick="chooseParties('self', this)">
              <span class="cc-icon"><i class="bi bi-person"></i></span>
              <div class="cc-label">Just Me</div>
              <div class="cc-sub">Solo incident</div>
            </button>
            <button class="choice-card" onclick="chooseParties('two', this)">
              <span class="cc-icon"><i class="bi bi-people"></i></span>
              <div class="cc-label">Two People</div>
              <div class="cc-sub">Me + Another</div>
            </button>
            <button class="choice-card" onclick="chooseParties('multiple', this)">
              <span class="cc-icon"><i class="bi bi-people-fill"></i></span>
              <div class="cc-label">Three or More</div>
              <div class="cc-sub">Multi-party</div>
            </button>
          </div>
        </div>

        <!-- ══════════════════════════════════════════════════
             SELF FLOW
        ═══════════════════════════════════════════════════════ -->

        <!-- S2 — Any injury or accident? -->
        <div class="wizard-step" id="step-s2">
          <div class="step-title">Was there an injury or accident?</div>
          <p class="step-sub">Tell us what happened — injury, collision, or property damage?</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setSelfInjury(true)">
              <span class="cb-icon"><i class="bi bi-truck-front"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes — there was an injury</div>
                <div class="cb-desc">Someone may be hurt</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setSelfInjury(false)">
              <span class="cb-icon"><i class="bi bi-car-front"></i></span>
              <div class="cb-body">
                <div class="cb-title">No — property or vehicle damage only</div>
                <div class="cb-desc">No injuries involved</div>
              </div>
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
          <div class="center-screen">
            <div class="hero-icon red pulse"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="step-title" style="margin-bottom:0.4rem;">Please call for help first</div>
            <p class="step-sub">Contact emergency services before proceeding with your report.</p>
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
            <a href="tel:163" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-fire"></i></span>
              <strong>BFP 163</strong>
              <span class="ca-label">Fire Bureau</span>
            </a>
            <a href="tel:7220650" class="call-btn green">
              <span class="ca-icon"><i class="bi bi-car-front-fill"></i></span>
              <strong>PNP Hotline</strong>
              <span class="ca-label">722-0650</span>
            </a>
          </div>

          <hr class="divider">
          <div class="step-title" style="font-size:1rem; margin-bottom:0.4rem;">Do you still want to file a report?</div>
          <p class="step-sub" style="margin-bottom:0.85rem;">You can still document the incident even after calling for help.</p>
          <button class="btn-primary" onclick="goToFormFlow()"><i class="bi bi-file-earmark-text" style="color:#fbbf24;"></i> Yes, I want to file a report</button>
          <button class="btn-outline" onclick="goToStep('step-end-no-report')"><i class="bi bi-x-circle"></i> No, I'm done</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
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
          <div class="center-screen">
            <div class="hero-icon amber pulse"><i class="bi bi-stoplights"></i></div>
            <div class="step-title">Please contact TMO first</div>
            <p class="step-sub">Speed Dial your local Traffic Management Officer or the nearest authority before continuing.</p>
          </div>

          <div class="call-grid">
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>TMO Hotline</strong>
              <span class="ca-label">Speed Dial</span>
            </a>
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>911</strong>
              <span class="ca-label">Emergency</span>
            </a>
          </div>

          <button class="btn-primary" onclick="goToStep('step-s-attended')"><i class="bi bi-check-circle"></i> I've contacted them — Continue</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
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

        <!-- M1 — Is someone hurt? -->
        <div class="wizard-step" id="step-m1">
          <div class="step-title">Is anyone involved hurt?</div>
          <p class="step-sub">This includes yourself, the other party, or any bystanders.</p>

          <div class="choice-grid">
            <button class="choice-btn" onclick="setMultiInjury(true)">
              <span class="cb-icon"><i class="bi bi-truck-front"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, someone is injured</div>
                <div class="cb-desc">Immediate medical attention may be needed</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setMultiInjury(false)">
              <span class="cb-icon"><i class="bi bi-check-circle"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, everyone is safe</div>
                <div class="cb-desc">No physical injuries</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
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
          <div class="center-screen">
            <div class="hero-icon red pulse"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="step-title">Call for help immediately</div>
            <p class="step-sub">There is an injury and no enforcer present. Please call emergency services now.</p>
          </div>

          <div class="call-grid">
            <a href="tel:911" class="call-btn red">
              <span class="ca-icon"><i class="bi bi-telephone-fill" style="color:#fbbf24;"></i></span>
              <strong>Call 911</strong>
              <span class="ca-label">Emergency Hotline</span>
            </a>
            <a href="tel:136" class="call-btn amber">
              <span class="ca-icon"><i class="bi bi-stoplights"></i></span>
              <strong>Speed Dial TMO</strong>
              <span class="ca-label">Traffic Mgmt</span>
            </a>
            <a href="tel:117" class="call-btn blue">
              <span class="ca-icon"><i class="bi bi-truck-front"></i></span>
              <strong>Call 117</strong>
              <span class="ca-label">Red Cross</span>
            </a>
            <a href="tel:7220650" class="call-btn green">
              <span class="ca-icon"><i class="bi bi-car-front-fill"></i></span>
              <strong>PNP Hotline</strong>
              <span class="ca-label">722-0650</span>
            </a>
          </div>

          <div class="step-title" style="font-size:1rem; margin-bottom:0.4rem;">Do you still want to file a report?</div>
          <p class="step-sub" style="margin-bottom:0.85rem;">You can still document the incident even after calling for help.</p>
          <button class="btn-primary" onclick="goToFormFlow()"><i class="bi bi-file-earmark-text" style="color:#fbbf24;"></i> Yes, I want to file a report</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
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
              <span class="cb-icon"><i class="bi bi-handshake-fill"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, settle on our own</div>
                <div class="cb-desc">Agree between involved parties</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setSettle(false)">
              <span class="cb-icon"><i class="bi bi-card-checklist"></i></span>
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

          <button class="btn-primary" onclick="goToStep('step-m-contract-form')"><i class="bi bi-file-earmark-ruled"></i> Yes, create a contract</button>
          <button class="btn-outline" onclick="goToStep('step-end-settled')"><i class="bi bi-check2-circle"></i> No, we're done — End</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-CONTRACT-FORM — Contract details -->
        <div class="wizard-step" id="step-m-contract-form">
          <div class="step-title">Create a Settlement Contract</div>
          <p class="step-sub">Fill in the details of the agreement. Both parties will be able to review before signing.</p>

          <div class="form-group">
            <label class="form-label">Incident Description</label>
            <textarea class="form-control-vr" id="contractIncidentDesc" rows="3" placeholder="Briefly describe what happened..."></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Agreement Terms</label>
            <textarea class="form-control-vr" id="contractTerms" rows="4" placeholder="e.g. Party A will shoulder repair costs of Party B's vehicle..."></textarea>
          </div>

          <div class="contract-card">
            <div style="font-size:0.78rem;font-weight:700;color:var(--primary);margin-bottom:0.75rem;"><i class="bi bi-people-fill"></i> Parties Involved</div>

            <div class="contract-party">
              <div class="party-num">1</div>
              <div style="flex:1">
                <input type="text" class="form-control-vr" id="party1Name" placeholder="Your name / First party" style="margin-bottom:0.4rem;">
                <input type="text" class="form-control-vr" id="party1Contact" placeholder="Contact number">
              </div>
            </div>
            <div class="contract-party">
              <div class="party-num">2</div>
              <div style="flex:1">
                <input type="text" class="form-control-vr" id="party2Name" placeholder="Other party's name" style="margin-bottom:0.4rem;">
                <input type="text" class="form-control-vr" id="party2Contact" placeholder="Contact number">
              </div>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Agreed Amount (if any)</label>
            <div style="position:relative;">
              <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--muted);font-weight:600;font-size:0.85rem;">₱</span>
              <input type="number" class="form-control-vr" id="contractAmount" placeholder="0.00" style="padding-left:2rem;">
            </div>
          </div>

          <button class="btn-primary" onclick="saveContractForm()"><i class="bi bi-arrow-right"></i> Next — Review Contract</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-CONTRACT-REVIEW — Does party agree? -->
        <div class="wizard-step" id="step-m-contract-review">
          <div class="step-title">Review Settlement Contract</div>
          <p class="step-sub">Share this summary with the other party for their review.</p>

          <div class="overview-table" style="margin-bottom:1rem;">
            <div class="ov-row"><span class="ov-label">Party 1</span><span class="ov-value" id="cr-party1">—</span></div>
            <div class="ov-row"><span class="ov-label">Party 2</span><span class="ov-value" id="cr-party2">—</span></div>
            <div class="ov-row"><span class="ov-label">Amount</span><span class="ov-value" id="cr-amount">—</span></div>
          </div>

          <div style="font-size:0.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.4rem;">Agreed Terms</div>
          <div class="ov-details-box" id="cr-terms">—</div>

          <hr class="divider">
          <div class="step-title" style="font-size:1rem; margin-bottom:0.35rem;">Does the other party agree?</div>

          <div class="choice-grid" style="margin-bottom:0.85rem;">
            <button class="choice-btn" onclick="setContractAgree(true)">
              <span class="cb-icon"><i class="bi bi-check-circle"></i></span>
              <div class="cb-body">
                <div class="cb-title">Yes, all parties agree</div>
                <div class="cb-desc">Save the contract</div>
              </div>
            </button>
            <button class="choice-btn" onclick="setContractAgree(false)">
              <span class="cb-icon"><i class="bi bi-pencil-square"></i></span>
              <div class="cb-body">
                <div class="cb-title">No, needs revision</div>
                <div class="cb-desc">Edit the terms</div>
              </div>
            </button>
          </div>

          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>

        <!-- M-CONTRACT-REVISE — Revision note -->
        <div class="wizard-step" id="step-m-contract-revise">
          <div class="center-screen">
            <div class="hero-icon amber"><i class="bi bi-pencil-fill"></i></div>
            <div class="step-title">Let's revise the contract</div>
            <p class="step-sub">Go back and update the terms to reflect what both parties can agree on. Take your time.</p>
          </div>

          <button class="btn-primary" onclick="goToStep('step-m-contract-form')"><i class="bi bi-pencil-square"></i> Edit Contract</button>
          <button class="btn-outline" onclick="goBack()"><i class="bi bi-arrow-left"></i> Back</button>
        </div>


        <!-- ══════════════════════════════════════════════════
             SHARED REPORT FORM STEPS
        ═══════════════════════════════════════════════════════ -->

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
          <p class="step-sub">Select all vehicle types at the scene.</p>

          <div class="chip-group" id="vehicleChips">
            <?php foreach (['Car', 'Motorcycle', 'Van', 'Truck', 'Tricycle', 'E-bike/E-trike', 'Jeepney', 'Bus', 'Bicycle'] as $v): ?>
              <button class="chip" data-vehicle="<?= $v ?>" onclick="toggleChip(this)"><?= $v ?></button>
            <?php endforeach; ?>
          </div>

          <div id="vehicleDetailForms" class="mb-3"></div>

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

      // Step 1
      's1-title': {
        en: 'How many people are involved?',
        tl: 'Ilang tao ang sangkot?'
      },
      's1-sub': {
        en: 'This helps us guide you through the right reporting process.',
        tl: 'Tinutulungan nito kaming gabayan kayo sa tamang proseso ng pag-uulat.'
      },
      's1-just-me': {
        en: 'Just Me',
        tl: 'Ako Lang'
      },
      's1-just-me-sub': {
        en: 'Solo incident',
        tl: 'Nag-iisang insidente'
      },
      's1-two': {
        en: 'Two People',
        tl: 'Dalawang Tao'
      },
      's1-two-sub': {
        en: 'Me + Another',
        tl: 'Ako + Isa Pa'
      },
      's1-multi': {
        en: 'Three or More',
        tl: 'Tatlo o Higit Pa'
      },
      's1-multi-sub': {
        en: 'Multi-party',
        tl: 'Maraming partido'
      },

      // S2
      's2-title': {
        en: 'Was there an injury or accident?',
        tl: 'May nasaktan o aksidente ba?'
      },
      's2-sub': {
        en: 'Tell us what happened — injury, collision, or property damage?',
        tl: 'Sabihin sa amin ang nangyari — pinsala, banggaan, o pinsala sa ari-arian?'
      },
      's2-yes-t': {
        en: 'Yes — there was an injury',
        tl: 'Oo — may nasaktan'
      },
      's2-yes-d': {
        en: 'Someone may be hurt',
        tl: 'Maaaring may nasaktan'
      },
      's2-no-t': {
        en: 'No — property or vehicle damage only',
        tl: 'Hindi — pinsala sa sasakyan o ari-arian lamang'
      },
      's2-no-d': {
        en: 'No injuries involved',
        tl: 'Walang nasaktan'
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
      ['#step-s2 .choice-btn:nth-child(1) .cb-title', 's2-yes-t', 'text'],
      ['#step-s2 .choice-btn:nth-child(1) .cb-desc', 's2-yes-d', 'text'],
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
      parties: '', // 'self' | 'two' | 'multiple'
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

    const stepHistory = ['step-1'];
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
    //  STEP 1 — PARTIES
    // ══════════════════════════════════════════════════════════
    function chooseParties(type, btn) {
      document.querySelectorAll('.choice-card').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.parties = type;
      const labels = {
        self: 'Solo',
        two: 'Two Parties',
        multiple: 'Multi-Party'
      };
      document.getElementById('flowLabel').textContent = labels[type] || '';
      syncSidebar();
      setTimeout(() => {
        if (state.parties === 'self') {
          goToStep('step-s-attended');
        } else if (state.parties === 'two') {
          goToStep('step-m1');
        } else {
          goToStep('step-m-attended');
        }
      }, 180);
    }

    // ══════════════════════════════════════════════════════════
    //  SELF FLOW
    // ══════════════════════════════════════════════════════════
    function setSelfInjury(injured) {
      state.has_injury = injured;
      syncSidebar();
      if (injured) {
        goToStep('step-s3');
      } else {
        goToStep('step-s-property');
      }
    }

    function setSelfHurt(hurt) {
      state.self_hurt = hurt;
      syncSidebar();
      goToStep(hurt ? 'step-s-speed-dial' : 'step-s-property');
    }

    function setSelfAttended(attended) {
      state.self_attended = attended;
      syncSidebar();
      goToStep(attended ? 'step-s-doc-note' : 'step-s3');
    }

    // ══════════════════════════════════════════════════════════
    //  MULTI FLOW
    // ══════════════════════════════════════════════════════════
    function setMultiInjury(injured) {
      state.has_injury = injured;
      syncSidebar();
      if (injured && isTwoParties()) {
        goToStep('step-m-attended');
      } else if (injured) {
        goToStep('step-m-speed-dial');
      } else {
        goToStep('step-m-settle');
      }
    }

    function setMultiAttended(attended) {
      state.multi_attended = attended;
      syncSidebar();
      goToStep(attended ? 'step-m-doc-note' : 'step-m-speed-dial');
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
    function saveContractForm() {
      const desc = document.getElementById('contractIncidentDesc').value.trim();
      const terms = document.getElementById('contractTerms').value.trim();
      const p1name = document.getElementById('party1Name').value.trim();
      const p2name = document.getElementById('party2Name').value.trim();
      if (!terms || !p1name || !p2name) {
        alert('Please fill in at least the terms and both party names.');
        return;
      }
      state.contract_party1_name = p1name;
      state.contract_party1_contact = document.getElementById('party1Contact').value.trim();
      state.contract_party2_name = p2name;
      state.contract_party2_contact = document.getElementById('party2Contact').value.trim();
      state.contract_terms = terms;
      state.contract_amount = document.getElementById('contractAmount').value.trim();
      state.contract_description = desc;

      // Populate review
      document.getElementById('cr-party1').textContent = `${p1name} ${state.contract_party1_contact ? '('+state.contract_party1_contact+')' : ''}`;
      document.getElementById('cr-party2').textContent = `${p2name} ${state.contract_party2_contact ? '('+state.contract_party2_contact+')' : ''}`;
      document.getElementById('cr-amount').textContent = state.contract_amount ? '₱' + parseFloat(state.contract_amount).toLocaleString() : 'Not specified';
      document.getElementById('cr-terms').textContent = terms;
      goToStep('step-m-contract-review');
    }

    async function setContractAgree(agree) {
      if (!agree) {
        goToStep('step-m-contract-revise');
        return;
      }
      // Submit contract
      const fd = new FormData();
      fd.append('type', 'contract');
      fd.append('party1_name', state.contract_party1_name);
      fd.append('party1_contact', state.contract_party1_contact);
      fd.append('party2_name', state.contract_party2_name);
      fd.append('party2_contact', state.contract_party2_contact);
      fd.append('terms', state.contract_terms);
      fd.append('amount', state.contract_amount);
      fd.append('parties', state.parties);
      fd.append('description', state.contract_description);

      try {
        const res = await fetch('api/submit_contract.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();
        const ref = data.reference_number;
        document.getElementById('contractRefNum').textContent = ref;
        document.getElementById('cs-parties').textContent = `${state.contract_party1_name} & ${state.contract_party2_name}`;
        document.getElementById('cs-amount').textContent = state.contract_amount ? '₱' + parseFloat(state.contract_amount).toLocaleString() : '—';
        goToStep('step-end-contract-saved');
      } catch {
        document.body.insertAdjacentHTML('beforeend', `
        <div id="contractErrorModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px);">
          <div style="background:#fff;border-radius:24px;padding:2rem 1.5rem;max-width:340px;width:100%;text-align:center;animation:stepIn .35s ease;">
            <div style="width:72px;height:72px;background:linear-gradient(135deg,#E90101,#b30000);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2rem;">⚠️</div>
            <div style="font-size:1.1rem;font-weight:800;margin-bottom:0.5rem;">An error occurred</div>
            <div style="font-size:0.82rem;color:#6b7280;margin-bottom:1.25rem;">Could not save the contract. Please check your connection and try again.</div>
            <button onclick="document.getElementById('contractErrorModal').remove()" style="width:100%;background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;border:none;border-radius:14px;padding:0.85rem;font-family:'Poppins',sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;">Try Again</button>
          </div>
        </div>
      `);
      }
    }

    // ══════════════════════════════════════════════════════════
    //  ROUTE TO FORM FLOW
    // ══════════════════════════════════════════════════════════
    function goToFormFlow(overrideFlow) {
      state.flow_type = overrideFlow || 'standard';
      document.getElementById('flowLabel').textContent = overrideFlow === 'good_citizen' ? 'Good Citizen 🌟' : 'Filing Report';
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
      const selected = [...document.querySelectorAll('.chip.active')].map(c => c.dataset.vehicle);
      const container = document.getElementById('vehicleDetailForms');
      container.innerHTML = selected.length === 0 ? '' : selected.map((v, i) => `
      <div style="background:#f9fafb;border:1px solid var(--border);border-radius:0.85rem;padding:0.9rem;margin-bottom:0.6rem;">
        <div style="font-size:0.78rem;font-weight:700;margin-bottom:0.6rem;color:var(--text);">${v}</div>
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:0.5rem;">
          <div>
            <label style="font-size:0.65rem;color:var(--muted);display:block;margin-bottom:3px;">Count</label>
            <input type="number" class="form-control-vr" id="vc_count_${i}" min="1" max="20" value="1" style="padding:0.5rem 0.65rem;">
          </div>
          <div>
            <label style="font-size:0.65rem;color:var(--muted);display:block;margin-bottom:3px;">Plate(s)</label>
            <input type="text" class="form-control-vr" id="vc_plate_${i}" placeholder="e.g. ABC 1234" style="padding:0.5rem 0.65rem;">
          </div>
        </div>
      </div>
    `).join('');
    }

    function saveVehicleInfo() {
      const selected = [...document.querySelectorAll('.chip.active')].map(c => c.dataset.vehicle);
      if (!selected.length) {
        alert('Please select at least one vehicle type.');
        return;
      }
      state.vehicle_types = selected;
      state.vehicle_counts = selected.map((_, i) => document.getElementById(`vc_count_${i}`)?.value || '1');
      state.plate_numbers = selected.map((_, i) => document.getElementById(`vc_plate_${i}`)?.value || '');
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
      goToStep('step-form-insurance');
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
        self: 'Solo (Just Me)',
        two: 'Two People',
        multiple: 'Three or More'
      };
      document.getElementById('ov-type').textContent = state.flow_type === 'good_citizen' ? '<i class="bi bi-star-fill"></i> Good Citizen' : '📋 Standard Report';
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

      goToStep('step-form-overview');
    }

    // ─── SUBMIT ──────────────────────────────────────────────
    async function submitReport() {
      const btn = document.getElementById('submitReportBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span> Submitting…';

      const fd = new FormData();
      fd.append('flow_type', state.flow_type);
      fd.append('parties', state.parties);
      // Derive has_other_parties from state.parties
      fd.append('has_other_parties', (state.parties === 'two' || state.parties === 'multiple') ? 1 : 0);
      fd.append('is_injured', state.has_injury ? 1 : 0);
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
        [14.6870, 120.9990],  // Gen. T. De Leon south
        [14.6845, 120.9910],  // Parada / Marulas E
        [14.6820, 120.9845],  // Marulas south (southernmost)
        [14.6825, 120.9750],  // Karuhatan south (Tullahan River)
        [14.6845, 120.9640],  // Malinta south (Tullahan River)
        [14.6870, 120.9565],  // Malinta / Rincon SW
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
        two: 'Two Parties',
        multiple: 'Three or More'
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