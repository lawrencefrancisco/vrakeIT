<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
startSecureSession();
requireLogin();

$user = getLoggedInUser();

$role = $user['role'] ?? 'user';

// If they are an enforcer, let them stay.
if ($role === 'enforcer') {
    // Do nothing, they belong here.
} 
// If they are a standard user, send them to their dashboard.
elseif ($role === 'user') {
    header('Location: landing.php');
    exit;
} 
// If they are anything else (like an 'admin'), send them back to login for now.
else {
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT Enforcer — Report an Incident</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    :root {
      --blue: #007ED2;
      --blue-light: #e0f2fe;
      --blue-dark: #005fa3;
      --red: #E90101;
      --red-light: #fef2f2;
      --green: #00a854;
      --amber: #f59e0b;
      --bg: #f3f6f9;
      --surface: #ffffff;
      --border: #e2e8f0;
      --border-focus: #007ED2;
      --text: #0f172a;
      --muted: #64748b;
      --label: #334155;
      --radius: 12px;
      --radius-lg: 16px;
      --shadow: 0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Poppins', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: 6rem;
      font-size: 15px;
      position: relative;
      z-index: 0;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      z-index: -2;
      background-image: url('assets/img/bg-house.png');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      opacity: 0.35;
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
      z-index: -1;
      pointer-events: none;
    }

    /* ─── HEADER ─── */
    .app-header {
      position: sticky; top: 0; z-index: 200;
      background: linear-gradient(to right, rgba(233, 1, 1, 0.8), rgba(0, 126, 210, 0.8));
      backdrop-filter: blur(16px);
      border-bottom: 1px solid rgba(255,255,255,0.6);
      padding: 0.9rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between;
      box-shadow: 0 1px 12px rgba(0,0,0,0.05);
    }
    .back-btn {
      width: 38px; height: 38px; border-radius: 50%;
      background: var(--bg); border: 1px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      color: var(--muted); font-size: 1.1rem; text-decoration: none;
      transition: all 0.2s;
    }
    .back-btn:hover { background: var(--blue-light); color: var(--blue); }
    .header-title { font-size: 1rem; font-weight: 700; color: #fff; }

    /* ─── LAYOUT ─── */
    .page-wrap {
      max-width: 780px;
      margin: 0 auto;
      padding: 1.75rem 1rem 3rem;
    }

    /* ─── SECTION CARDS ─── */
    .form-section {
      background: rgba(255, 255, 255, 0.92);
      border-radius: 1.5rem;
      border: 1px solid rgba(255, 255, 255, 0.7);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06);
      margin-bottom: 1.25rem;
      overflow: hidden;
      backdrop-filter: blur(12px);
      transition: box-shadow 0.2s;
    }
    .form-section:focus-within {
      box-shadow: 0 0 0 2px rgba(0,126,210,0.15), var(--shadow);
    }
    .section-header {
      padding: 1.25rem 1.25rem 0.5rem;
      border-bottom: none;
      display: flex; align-items: center; gap: 0.65rem;
      background: transparent;
    }
    .section-icon {
      width: 32px; height: 32px; border-radius: 8px;
      background: var(--blue-light);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.95rem; flex-shrink: 0;
    }
    .section-icon.red { background: var(--red-light); }
    .section-icon.amber { background: #fffbeb; }
    .section-icon.green { background: #f0fdf4; }
    .section-title { font-size: 1.1rem; font-weight: 700; color: var(--text); line-height: 1.3; }
    .section-sub { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }
    .section-body { padding: 1.25rem; }

    /* ─── REQUIRED BADGE ─── */
    .req { color: var(--red); margin-left: 2px; }

    /* ─── LABELS ─── */
    .field-label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--label);
      margin-bottom: 0.4rem;
      letter-spacing: 0.01em;
    }

    /* ─── FORM CONTROLS ─── */
    .fld {
      width: 100%;
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: var(--radius);
      padding: 0.65rem 0.9rem;
      font-family: 'Poppins', sans-serif;
      font-size: 0.875rem;
      color: var(--text);
      background: #fff;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
      appearance: none; -webkit-appearance: none;
    }
    .fld:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(0,126,210,0.1); }
    textarea.fld { resize: vertical; min-height: 110px; }
    select.fld { background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 0.65rem center; background-size: 1.2em; padding-right: 2.4rem; }

    /* ─── ROW GRID ─── */
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }
    @media (max-width: 540px) {
      .row-2, .row-3 { grid-template-columns: 1fr; }
    }
    .field-group { margin-bottom: 1rem; }
    .field-group:last-child { margin-bottom: 0; }

    /* ─── TOGGLE CHIPS ─── */
    .chip-row { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.4rem; }
    .chip-opt {
      border: 1px solid rgba(0,0,0,0.08);
      background: #fff;
      border-radius: 999px;
      padding: 0.35rem 0.8rem;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s;
      font-family: 'Poppins', sans-serif;
      color: var(--muted);
      user-select: none;
    }
    .chip-opt:hover { border-color: var(--blue); color: var(--blue); }
    .chip-opt.on { border-color: var(--blue); background: var(--blue-light); color: var(--blue); }

    /* ─── RADIO ROWS ─── */
    .radio-stack { display: flex; flex-direction: column; gap: 0.5rem; }
    .radio-card {
      display: flex; align-items: center; gap: 0.75rem;
      border: 1px solid rgba(0,0,0,0.08);
      border-radius: var(--radius);
      padding: 0.75rem 1rem;
      cursor: pointer;
      transition: all 0.15s;
      background: #fff;
      user-select: none;
    }
    .radio-card:hover { border-color: var(--blue); background: var(--blue-light); }
    .radio-card.on { border-color: var(--blue); background: var(--blue-light); }
    .radio-card.on-red { border-color: var(--red); background: var(--red-light); }
    .radio-card input[type=radio] { display: none; }
    .radio-dot {
      width: 18px; height: 18px; border-radius: 50%;
      border: 2px solid var(--border);
      flex-shrink: 0; position: relative;
      transition: border-color 0.15s;
    }
    .radio-card.on .radio-dot { border-color: var(--blue); }
    .radio-card.on .radio-dot::after {
      content: ''; width: 8px; height: 8px; border-radius: 50%;
      background: var(--blue); position: absolute;
      top: 50%; left: 50%; transform: translate(-50%, -50%);
    }
    .radio-card.on-red .radio-dot { border-color: var(--red); }
    .radio-card.on-red .radio-dot::after {
      content: ''; width: 8px; height: 8px; border-radius: 50%;
      background: var(--red); position: absolute;
      top: 50%; left: 50%; transform: translate(-50%, -50%);
    }
    .rc-icon { font-size: 1.3rem; }
    .rc-title { font-size: 0.85rem; font-weight: 700; color: var(--text); }
    .rc-sub { font-size: 0.7rem; color: var(--muted); }

    .radio-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    @media (max-width: 480px) { .radio-row { grid-template-columns: 1fr; } }

    /* ─── MAP ─── */
    #mapContainer {
      width: 100%; height: 220px;
      border-radius: var(--radius);
      border: 1px solid rgba(0,0,0,0.08);
      overflow: hidden; margin-bottom: 0.75rem;
    }
    .address-pill {
      background: #f8fafc; border: 1px solid var(--border);
      border-radius: var(--radius); padding: 0.6rem 0.85rem;
      margin-bottom: 0.75rem; display: flex; gap: 0.5rem; align-items: flex-start;
    }
    .address-pill i { color: var(--blue); margin-top: 2px; font-size: 0.9rem; flex-shrink: 0; }
    .ap-val { font-size: 0.8rem; font-weight: 600; color: var(--text); }
    .ap-hint { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }

    /* ─── VEHICLE CHIPS ─── */
    .chip-group-ep {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-bottom: 1.25rem;
    }
    .chip-ep {
      border: 2px solid rgba(0,0,0,0.08);
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
      color: var(--muted);
    }
    .chip-ep:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-light); }
    .chip-ep.active { border-color: var(--blue); background: var(--blue-light); color: var(--blue); }

    /* ─── VEHICLE DETAIL CARDS ─── */
    .vd-card-ep {
      background: #fff;
      border: 1.5px solid #e5e7eb;
      border-radius: 1rem;
      padding: 1rem;
      margin-bottom: 0.75rem;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .vd-card-ep:hover { border-color: var(--blue); box-shadow: 0 2px 12px rgba(0,126,210,0.07); }
    .vd-card-header-ep { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
    .vd-card-label-ep { display: flex; align-items: center; gap: 0.45rem; font-size: 0.85rem; font-weight: 700; color: var(--text); }
    .vd-icon-ep { font-size: 1.15rem; width: 28px; height: 28px; min-width: 28px; background: var(--blue-light); color: var(--blue); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
    .vd-stepper-ep { display: flex; align-items: center; border: 1.5px solid #e0e0e0; border-radius: 999px; overflow: hidden; background: #f9fafb; }
    .vd-stepper-ep button { background: none; border: none; width: 30px; height: 30px; font-size: 1rem; font-weight: 700; cursor: pointer; color: var(--blue); display: flex; align-items: center; justify-content: center; font-family: 'Poppins', sans-serif; transition: background 0.15s; }
    .vd-stepper-ep button:hover { background: rgba(0,126,210,0.08); }
    .vd-stepper-ep span { min-width: 28px; text-align: center; font-size: 0.82rem; font-weight: 700; color: var(--text); }
    .vd-plates-ep { display: flex; flex-direction: column; gap: 0.45rem; margin-top: 0.6rem; }
    .vd-plate-row-ep { display: flex; align-items: center; gap: 0.5rem; }
    .vd-plate-num-ep { width: 22px; height: 22px; min-width: 22px; background: var(--blue); color: #fff; border-radius: 50%; font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
    .vd-plate-input-ep { flex: 1; border: 1.5px solid #e0e0e0; border-radius: 0.6rem; padding: 0.45rem 0.75rem; font-size: 0.82rem; font-family: 'Poppins', sans-serif; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; outline: none; background: #fafafa; transition: border-color 0.2s; }
    .vd-plate-input-ep:focus { border-color: var(--blue); background: #fff; }
    .vd-plate-input-ep::placeholder { text-transform: none; font-weight: 400; letter-spacing: 0; color: #aaa; }

    /* ─── MEDIA UPLOAD ─── */
    .dropzone {
      border: 1.5px dashed var(--border); border-radius: var(--radius);
      padding: 1.75rem 1rem; text-align: center; cursor: pointer;
      transition: all 0.15s; background: #fafbfc;
    }
    .dropzone:hover { border-color: var(--blue); background: var(--blue-light); }
    .dropzone i { font-size: 1.75rem; color: var(--blue); display: block; margin-bottom: 0.35rem; }
    .dz-t { font-size: 0.82rem; font-weight: 700; color: var(--blue); }
    .dz-s { font-size: 0.68rem; color: var(--muted); margin-top: 2px; }
    .media-thumbs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.75rem; }
    .media-thumbs img { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 2px solid #fff; box-shadow: 0 1px 6px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.15s; }
    .media-thumbs img:hover { transform: scale(1.06); }

    /* ─── ALERT BANNER ─── */
    .alert-strip {
      border-radius: var(--radius); padding: 0.75rem 1rem;
      display: flex; gap: 0.6rem; align-items: flex-start;
      font-size: 0.8rem; line-height: 1.5; margin-bottom: 1rem;
    }
    .alert-strip i { font-size: 1rem; flex-shrink: 0; margin-top: 2px; }
    .alert-strip.warn { background: #fffbeb; border: 1px solid #fde68a; }
    .alert-strip.info { background: var(--blue-light); border: 1px solid #bae6fd; }
    .alert-strip.success { background: #f0fdf4; border: 1px solid #bbf7d0; }
    .alert-strip.danger { background: var(--red-light); border: 1px solid #fecaca; }
    .as-title { font-weight: 700; margin-bottom: 2px; }
    .as-body { color: var(--muted); }

    /* ─── CALL BUTTONS ─── */
    .call-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; margin: 0.75rem 0; }
    .call-btn {
      display: flex; flex-direction: column; align-items: center; gap: 4px;
      border-radius: var(--radius); padding: 0.85rem 0.5rem;
      text-decoration: none; font-family: 'Poppins', sans-serif;
      font-weight: 700; font-size: 0.78rem; text-align: center;
      color: #fff; transition: transform 0.15s, box-shadow 0.15s;
    }
    .call-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,0.2); color: #fff; }
    .call-btn i { font-size: 1.3rem; }
    .call-btn .ca-sub { font-size: 0.65rem; opacity: 0.85; font-weight: 500; }
    .call-btn.red { background: linear-gradient(135deg,#E90101,#b30000); }
    .call-btn.blue { background: linear-gradient(135deg,#007ED2,#005fa3); }
    .call-btn.amber { background: linear-gradient(135deg,#f59e0b,#d97706); }
    .call-btn.green-btn { background: linear-gradient(135deg,#10b981,#059669); }

    /* ─── CONDITIONAL SECTIONS ─── */
    .cond-block {
      overflow: hidden; max-height: 0;
      opacity: 0; transition: max-height 0.35s ease, opacity 0.25s ease, margin 0.2s;
      margin-bottom: 0;
    }
    .cond-block.visible {
      max-height: 2000px; opacity: 1; margin-bottom: 1.25rem;
    }

    /* ─── DIVIDER ─── */
    .fdiv { border: none; border-top: 1px solid var(--border); margin: 1rem 0; }

    /* ─── SUBMIT AREA ─── */
    .submit-wrap {
      position: sticky; bottom: 0; z-index: 100;
      background: rgba(255,255,255,0.97); backdrop-filter: blur(16px);
      border-top: 1px solid var(--border); padding: 1rem 1.25rem;
      box-shadow: 0 -2px 16px rgba(0,0,0,0.06);
    }
    .submit-inner { max-width: 780px; margin: 0 auto; display: flex; gap: 0.75rem; align-items: center; }
    .btn-submit {
      flex: 1; background: linear-gradient(135deg, var(--blue), var(--blue-dark)); color: #fff; box-shadow: 0 4px 16px rgba(0, 126, 210, 0.3); border: none;
      border: none; border-radius: var(--radius); padding: 0.8rem 1.25rem;
      font-family: 'Poppins', sans-serif; font-size: 0.9rem; font-weight: 700;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.4rem;
      transition: background 0.15s, transform 0.15s;
    }
    .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0, 126, 210, 0.4); }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    .points-hint { font-size: 0.72rem; color: var(--muted); text-align: right; line-height: 1.4; flex-shrink: 0; }
    .pts { font-size: 0.88rem; font-weight: 700; color: var(--blue); display: block; }

    .spinner {
      width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.4);
      border-top-color: #fff; border-radius: 50%; animation: spin 0.6s linear infinite; display: inline-block;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ─── CURRENCY INPUT ─── */
    .currency-wrap { position: relative; }
    .currency-wrap .peso { position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--muted); font-weight: 600; font-size: 0.85rem; pointer-events: none; }
    .currency-wrap .fld { padding-left: 1.75rem; }

    /* ─── SECTION NUMBERS ─── */
    .sec-num {
      width: 26px; height: 26px; border-radius: 50%; background: var(--blue);
      color: #fff; font-size: 0.75rem; font-weight: 700;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    /* ─── CONTRACT PARTIES ─── */
    .party-row { display: flex; gap: 0.5rem; align-items: flex-start; margin-bottom: 0.75rem; }
    .party-num { width: 26px; height: 26px; border-radius: 50%; background: var(--blue); color: #fff; font-size: 0.72rem; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 0.15rem; }
    .party-fields { flex: 1; display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    @media (max-width: 480px) { .party-fields { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<!-- ─── HEADER ─── -->
<header class="app-header">
  <a href="landing.php" class="back-btn"><i class="bi bi-arrow-left"></i></a>
  <span class="header-title">Report an Incident</span>
  <span style="width:36px;"></span>
</header>

<div class="page-wrap">

  <!-- ══════════════════════════════════════════
       SECTION 1 — PARTIES INVOLVED
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">1</div>
      <div>
        <div class="section-title">Who is involved?</div>
        <div class="section-sub">Select all that apply to this incident</div>
      </div>
    </div>
    <div class="section-body">
      <div class="field-label">Number of parties <span class="req">*</span></div>
      <div class="radio-row" id="partiesGroup">
        <label class="radio-card" onclick="selectParties('self', this)">
          <input type="radio" name="parties" value="self">
          <div class="radio-dot"></div>
          <span class="rc-icon"><i class="bi bi-person-fill" style="color:var(--blue); font-size: 1.3rem;"></i></span>
          <div><div class="rc-title">1 Driver</div><div class="rc-sub">Solo incident</div></div>
        </label>
        <label class="radio-card" onclick="selectParties('two', this)">
          <input type="radio" name="parties" value="two">
          <div class="radio-dot"></div>
          <span class="rc-icon"><i class="bi bi-people-fill" style="color:var(--blue); font-size: 1.3rem;"></i></span>
          <div><div class="rc-title">Two Drivers</div><div class="rc-sub">Two vehicles involved</div></div>
        </label>
        <label class="radio-card" onclick="selectParties('multiple', this)">
          <input type="radio" name="parties" value="multiple">
          <div class="radio-dot"></div>
          <span class="rc-icon"><i class="bi bi-people-fill" style="color:var(--blue); font-size: 1.3rem;"></i></span>
          <div><div class="rc-title">Three or More</div><div class="rc-sub">Multi-vehicle incident</div></div>
        </label>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 2 — INJURY
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">2</div>
      <div>
        <div class="section-title">Injuries & Emergency</div>
        <div class="section-sub">Prioritize safety before filing</div>
      </div>
    </div>
    <div class="section-body">

      <div class="field-group">
        <div class="field-label">Was anyone injured? <span class="req">*</span></div>
        <div class="radio-row">
          <label class="radio-card" onclick="setInjury(true, this)">
            <input type="radio" name="injury" value="1">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-bandaid-fill" style="color:var(--red); font-size: 1.3rem;"></i></span>
            <div><div class="rc-title">Yes, someone is injured</div><div class="rc-sub">Medical attention needed</div></div>
          </label>
          <label class="radio-card" onclick="setInjury(false, this)">
            <input type="radio" name="injury" value="0">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-check-circle-fill" style="color:var(--green); font-size: 1.3rem;"></i></span>
            <div><div class="rc-title">No injuries</div><div class="rc-sub">Property/vehicle damage only</div></div>
          </label>
        </div>
      </div>

      <!-- How many injured? -->
      <div class="cond-block" id="block-injured-count">
        <div class="fdiv"></div>
        <div class="field-label">How many people are injured? <span class="req">*</span></div>
        <div class="radio-row" style="grid-template-columns:1fr 1fr 1fr;">
          <label class="radio-card" onclick="setInjuredCount('none', this)">
            <input type="radio" name="injured_count" value="none">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-check-circle-fill" style="color:var(--green); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">None</div><div class="rc-sub">Property damage only</div></div>
          </label>
          <label class="radio-card" onclick="setInjuredCount('one', this)">
            <input type="radio" name="injured_count" value="one">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-person-fill-exclamation" style="color:var(--amber); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">1 Person</div><div class="rc-sub">One person may be hurt</div></div>
          </label>
          <label class="radio-card" onclick="setInjuredCount('multiple', this)">
            <input type="radio" name="injured_count" value="multiple">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-people-fill" style="color:var(--red); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">2 or More</div><div class="rc-sub">Multiple people injured</div></div>
          </label>
        </div>
      </div>

      <!-- Injury severity -->
      <div class="cond-block" id="block-severity">
        <div class="fdiv"></div>
        <div class="field-label">How severe are the injuries? <span class="req">*</span></div>
        <div class="radio-row">
          <label class="radio-card" onclick="setSeverity('minor', this)">
            <input type="radio" name="injury_severity" value="minor">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-bandaid-fill" style="color:var(--amber); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">Minor</div><div class="rc-sub">Cuts, bruises, limping</div></div>
          </label>
          <label class="radio-card" onclick="setSeverity('major', this)">
            <input type="radio" name="injury_severity" value="major">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-heartbreak-fill" style="color:var(--red); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">Major / Critical</div><div class="rc-sub">Unconscious, broken bones</div></div>
          </label>
        </div>
      </div>

      <!-- Deceased -->
      <div class="cond-block" id="block-deceased">
        <div class="fdiv"></div>
        <div class="field-label">Are there any fatalities / deceased? <span class="req">*</span></div>
        <div class="radio-row">
          <label class="radio-card" onclick="setDeceased(true, this)">
            <input type="radio" name="has_deceased" value="1">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-x-octagon-fill" style="color:var(--red); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">Yes, there are deceased</div><div class="rc-sub">Fatality at the scene</div></div>
          </label>
          <label class="radio-card" onclick="setDeceased(false, this)">
            <input type="radio" name="has_deceased" value="0">
            <div class="radio-dot"></div>
            <span class="rc-icon"><i class="bi bi-check-circle-fill" style="color:var(--green); font-size:1.3rem;"></i></span>
            <div><div class="rc-title">No, everyone is alive</div><div class="rc-sub">No fatalities</div></div>
          </label>
        </div>
      </div>

      <!-- Emergency speed dial -->
      <div class="cond-block" id="block-emergency">
        <div class="fdiv"></div>
        <div class="alert-strip danger">
          <i class="bi bi-exclamation-triangle-fill" style="color:var(--red);"></i>
          <div>
            <div class="as-title">Injuries reported — contact emergency services</div>
            <div class="as-body">Call for additional medical support if needed. You can still complete the report.</div>
          </div>
        </div>
        <div class="call-grid">
          <a href="tel:911" class="call-btn red"><i class="bi bi-telephone-fill"></i> Call 911 <span class="ca-sub">Emergency Hotline</span></a>
          <a href="tel:117" class="call-btn blue"><i class="bi bi-heart-pulse-fill"></i> Call 117 <span class="ca-sub">Philippine Red Cross</span></a>
          <a href="tel:163" class="call-btn amber"><i class="bi bi-fire"></i> BFP 163 <span class="ca-sub">Fire Bureau</span></a>
          <a href="tel:7220650" class="call-btn green-btn"><i class="bi bi-shield-fill"></i> PNP 722-0650 <span class="ca-sub">Police Hotline</span></a>
        </div>
      </div>
    </div>
  </div>


  <!-- ══════════════════════════════════════════
       SECTION 4 — SETTLEMENT (multi-party, no injury)
  ═══════════════════════════════════════════════ -->


  <!-- ══════════════════════════════════════════
       SECTION 3 — DATE & TIME
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num" id="sec-num-datetime">3</div>
      <div>
        <div class="section-title">Date & Time</div>
        <div class="section-sub">When did the incident occur?</div>
      </div>
    </div>
    <div class="section-body">
      <div class="row-2">
        <div class="field-group">
          <label class="field-label" for="incidentDate">Date <span class="req">*</span></label>
          <input type="date" class="fld" id="incidentDate" max="<?= date('Y-m-d', strtotime('+8 hours', strtotime(gmdate('Y-m-d H:i:s')))) ?>" value="<?= date('Y-m-d', strtotime('+8 hours', strtotime(gmdate('Y-m-d H:i:s')))) ?>">
        </div>
        <div class="field-group">
          <label class="field-label" for="incidentTime">Time <span class="req">*</span></label>
          <input type="time" class="fld" id="incidentTime">
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 4 — LOCATION
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">4</div>
      <div>
        <div class="section-title">Location</div>
        <div class="section-sub">Where did the incident happen?</div>
      </div>
    </div>
    <div class="section-body">
      <div id="mapContainer"></div>
      <div class="address-pill">
        <i class="bi bi-geo-alt-fill"></i>
        <div>
          <div class="ap-val" id="addressDisplay">Detecting location…</div>
          <div class="ap-hint">Drag the pin to adjust if needed</div>
        </div>
      </div>
      <input type="hidden" id="locLat">
      <input type="hidden" id="locLng">
      <input type="hidden" id="locAddress">
      <button type="button" onclick="detectLocation()" style="background:none;border:1.5px solid var(--border);border-radius:var(--radius);padding:0.5rem 0.85rem;font-family: 'Poppins', sans-serif;font-size:0.78rem;font-weight:600;color:var(--blue);cursor:pointer;display:flex;align-items:center;gap:0.35rem;">
        <i class="bi bi-geo-alt-fill"></i> Re-detect My Location
      </button>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 7 — VEHICLES
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">5</div>
      <div>
        <div class="section-title">Vehicles Involved</div>
        <div class="section-sub">Select all vehicle types at the scene</div>
      </div>
    </div>
    <div class="section-body">
      <div class="field-label">Vehicle types <span class="req">*</span></div>
      <div class="chip-group-ep" id="vehicleChipsEP">
        <?php
        $vehicleIconsEP = [
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
        foreach ($vehicleIconsEP as $v => $icon): ?>
          <button class="chip-ep" data-vehicle="<?= $v ?>" data-icon="<?= $icon ?>" onclick="toggleChipEP(this)">
            <i class="bi <?= $icon ?>"></i> <?= $v ?>
          </button>
        <?php endforeach; ?>
      </div>
      <div id="vehicleDetailFormsEP"></div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 8 — CONDITIONS
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">6</div>
      <div>
        <div class="section-title">Weather & Road Conditions</div>
        <div class="section-sub">At the time of the incident</div>
      </div>
    </div>
    <div class="section-body">
      <div class="row-2">
        <div class="field-group">
          <label class="field-label" for="weatherCond">Weather <span class="req">*</span></label>
          <select class="fld" id="weatherCond">
            <option value="">Select…</option>
            <?php foreach (['Clear / Sunny','Partly Cloudy','Cloudy','Light Rain','Heavy Rain','Thunderstorm','Foggy','Windy'] as $w): ?>
              <option><?= $w ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field-group">
          <label class="field-label" for="roadCond">Road Condition <span class="req">*</span></label>
          <select class="fld" id="roadCond">
            <option value="">Select…</option>
            <?php foreach (['Dry / Good','Wet','Flooded','Under Construction','Potholed','Slippery','Debris on Road'] as $r): ?>
              <option><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 6b — DAMAGE CATEGORY
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">7</div>
      <div>
        <div class="section-title">Damage Assessment</div>
        <div class="section-sub">What type of damage occurred?</div>
      </div>
    </div>
    <div class="section-body">
      <div class="field-group">
        <label class="field-label" for="damageCategory">Damage Category</label>
        <select class="fld" id="damageCategory">
          <option value="">Select…</option>
          <?php foreach ([
            'Minor Vehicle Damage',
            'Major Vehicle Damage',
            'Total Loss / Write-off',
            'Property / Infrastructure Damage',
            'Multiple Vehicles Damaged',
            'No Visible Damage',
          ] as $d): ?>
            <option><?= $d ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 9 — INSURANCE
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">8</div>
      <div>
        <div class="section-title">Insurance Status</div>
        <div class="section-sub">For vehicle — select Unknown if unsure</div>
      </div>
    </div>
    <div class="section-body">
      <div class="radio-row">
        <?php foreach ([
          ['<i class="bi bi-question-circle-fill" style="color:var(--blue); font-size: 1.3rem;"></i>','Unknown','unknown','Not sure about coverage'],
          ['<i class="bi bi-star-fill" style="color:var(--blue); font-size: 1.3rem;"></i>','Comprehensive','comprehensive','Full insurance coverage'],
          ['<i class="bi bi-file-earmark-text-fill" style="color:var(--blue); font-size: 1.3rem;"></i>','TPL Only','tpl','Third-party liability only'],
          ['<i class="bi bi-slash-circle-fill" style="color:var(--muted); font-size: 1.3rem;"></i>','Uninsured','none','No insurance policy'],
        ] as [$icon,$label,$val,$desc]): ?>
          <label class="radio-card" onclick="selectInsurance('<?=$val?>', this)">
            <input type="radio" name="insurance" value="<?=$val?>">
            <div class="radio-dot"></div>
            <span class="rc-icon"><?=$icon?></span>
            <div><div class="rc-title"><?=$label?></div><div class="rc-sub"><?=$desc?></div></div>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 10 — PHOTOS & VIDEOS
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">9</div>
      <div>
        <div class="section-title">Photos & Videos</div>
        <div class="section-sub">Optional — visuals strengthen your report</div>
      </div>
    </div>
    <div class="section-body">
      <div class="dropzone" onclick="document.getElementById('mediaInput').click()">
        <i class="bi bi-camera-fill"></i>
        <div class="dz-t">Tap to add photos or videos</div>
        <div class="dz-s">JPG, PNG, MP4 — Max 20MB each</div>
      </div>
      <input type="file" id="mediaInput" accept="image/*,video/*" multiple style="display:none;" onchange="handleMedia(this)">
      <div class="media-thumbs" id="mediaThumbs"></div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════
       SECTION 11 — DESCRIPTION
  ═══════════════════════════════════════════════ -->
  <div class="form-section">
    <div class="section-header">
      <div class="sec-num">10</div>
      <div>
        <div class="section-title">What Happened</div>
        <div class="section-sub">Describe the sequence of events in your own words</div>
      </div>
    </div>
    <div class="section-body">
      <textarea class="fld" id="eventDetails" rows="7" placeholder="e.g. I was driving along EDSA northbound when a vehicle sideswiped me from the left lane. I immediately pulled to the shoulder…"></textarea>
      <div style="font-size:0.68rem;color:var(--muted);margin-top:0.4rem;text-align:right;">
        <span id="charCount">0</span> characters
      </div>
    </div>
  </div>

</div><!-- /page-wrap -->

<!-- ─── STICKY SUBMIT ─── -->
<div class="submit-wrap">
  <div class="submit-inner">
    <button class="btn-submit" id="submitBtn" onclick="submitReport()">
      <i class="bi bi-send-fill"></i> Submit Report
    </button>
  </div>
</div>

<!-- ─── IMAGE VIEWER ─── -->
<div class="modal fade" id="imageModal" tabindex="-1" style="z-index:1060;">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:transparent;border:none;">
      <div class="modal-header" style="border:none;padding:0 0 8px;justify-content:flex-end;">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="filter:invert(1);opacity:1;"></button>
      </div>
      <div class="modal-body" style="padding:0;text-align:center;">
        <img id="fullImg" src="" alt="Full size" style="max-width:100%;max-height:80vh;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.8);">
      </div>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── STATE ──────────────────────────────────────────────
const S = {
  parties: '',
  has_injury: null,
  injured_count: '',   // 'none' | 'one' | 'multiple'
  injury_severity: '', // 'minor' | 'major'
  has_deceased: null,
  self_hurt: null,
  enforcer_present: null,
  settle: null,
  flow_type: 'standard',
  insurance_type: '',
};
let mediaFiles = [];
let map, mapMarker;

// ── SECTION 1 — PARTIES ──────────────────────────────
function selectParties(val, el) {
  document.querySelectorAll('#partiesGroup .radio-card').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.parties = val;
  updateConditionals();
}

// ── SECTION 2 — INJURY ──────────────────────────────
function setInjury(val, el) {
  document.querySelectorAll('label[onclick^="setInjury"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.has_injury = val;
  // Reset sub-fields when toggling injury
  if (!val) { S.injured_count = 'none'; S.injury_severity = ''; S.has_deceased = null; }
  updateConditionals();
}

function setInjuredCount(val, el) {
  document.querySelectorAll('label[onclick^="setInjuredCount"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.injured_count = val;
  updateConditionals();
}

function setSeverity(val, el) {
  document.querySelectorAll('label[onclick^="setSeverity"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.injury_severity = val;
  updateConditionals();
}

function setDeceased(val, el) {
  document.querySelectorAll('label[onclick^="setDeceased"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.has_deceased = val;
  updateConditionals();
}

// ── SECTION 3 — ENFORCER ────────────────────────────
function setEnforcer(val, el) {
  document.querySelectorAll('label[onclick^="setEnforcer"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.enforcer_present = val;
  updateConditionals();
}

// ── SECTION 4 — SETTLE ──────────────────────────────
function setSettle(val, el) {
  document.querySelectorAll('label[onclick^="setSettle"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.settle = val;
  updateConditionals();
}

// ── INSURANCE ────────────────────────────────────────
function selectInsurance(val, el) {
  document.querySelectorAll('label[onclick^="selectInsurance"]').forEach(c => c.classList.remove('on'));
  el.classList.add('on');
  S.insurance_type = val;
}

// ── CONDITIONALS ENGINE ──────────────────────────────
function show(id) { document.getElementById(id)?.classList.add('visible'); }
function hide(id) { document.getElementById(id)?.classList.remove('visible'); }

function updateConditionals() {
  const solo     = S.parties === 'self';
  const multi    = S.parties === 'two' || S.parties === 'multiple';
  const injured  = S.has_injury === true;
  const notInj   = S.has_injury === false;
  const hasCount = S.injured_count && S.injured_count !== 'none';

  // Injured count question: whenever there are injuries
  injured ? show('block-injured-count') : hide('block-injured-count');

  // Severity: when at least 1 injured
  (injured && hasCount) ? show('block-severity') : hide('block-severity');

  // Deceased: when severity is set
  (injured && hasCount && S.injury_severity) ? show('block-deceased') : hide('block-deceased');

  // Emergency dial: when injuries present
  injured ? show('block-emergency') : hide('block-emergency');

  S.flow_type = 'standard';

  // TMO call prompt: enforcer NOT present
  S.enforcer_present === false ? show('block-calltmo') : hide('block-calltmo');
  S.enforcer_present === true  ? show('block-enforcertips') : hide('block-enforcertips');

  // Settlement: multi-party with no injury
  multi && notInj ? show('block-settlement') : hide('block-settlement');

  // Contract fields: settlement chosen
  S.settle === true ? show('block-contract') : hide('block-contract');
}

// ── VEHICLES ────────────────────────────────────────
function toggleChipEP(btn) {
  btn.classList.toggle('active');
  updateVehicleDetailsEP();
}

function updateVehicleDetailsEP() {
  const chips = [...document.querySelectorAll('#vehicleChipsEP .chip-ep.active')];
  const container = document.getElementById('vehicleDetailFormsEP');
  if (!chips.length) { container.innerHTML = ''; return; }

  // Preserve existing counts when re-rendering
  const prevCounts = {};
  document.querySelectorAll('.vd-card-ep').forEach(card => {
    const v   = card.dataset.vehicleKey;
    const cnt = parseInt(card.querySelector('.vd-count-val-ep')?.textContent) || 1;
    prevCounts[v] = cnt;
  });

  container.innerHTML = chips.map((chip, i) => {
    const v    = chip.dataset.vehicle;
    const icon = chip.dataset.icon || 'bi-car-front-fill';
    const cnt  = prevCounts[v] || 1;
    const plates = Array.from({ length: cnt }, (_, j) => `
      <div class="vd-plate-row-ep">
        <div class="vd-plate-num-ep">${j + 1}</div>
        <input class="vd-plate-input-ep" id="vp_ep_${i}_${j}" type="text" placeholder="e.g. ABC 1234" maxlength="12">
      </div>`).join('');
    return `
      <div class="vd-card-ep" data-vehicle-key="${v}" data-chip-index="${i}">
        <div class="vd-card-header-ep">
          <div class="vd-card-label-ep">
            <span class="vd-icon-ep"><i class="bi ${icon}"></i></span>
            <span>${v}</span>
          </div>
          <div class="vd-stepper-ep">
            <button type="button" onclick="stepCountEP(this, -1, ${i})" aria-label="Decrease">−</button>
            <span class="vd-count-val-ep">${cnt}</span>
            <button type="button" onclick="stepCountEP(this, 1, ${i})" aria-label="Increase">+</button>
          </div>
        </div>
        <div class="vd-plates-ep" id="vd_plates_ep_${i}">${plates}</div>
      </div>`;
  }).join('');
}

function stepCountEP(btn, delta, chipIdx) {
  const card     = btn.closest('.vd-card-ep');
  const valEl    = card.querySelector('.vd-count-val-ep');
  const platesEl = document.getElementById(`vd_plates_ep_${chipIdx}`);
  const chip     = document.querySelectorAll('#vehicleChipsEP .chip-ep.active')[chipIdx];
  const icon     = chip ? chip.dataset.icon : 'bi-car-front-fill';
  let cnt = parseInt(valEl.textContent) + delta;
  if (cnt < 1) cnt = 1;
  if (cnt > 20) cnt = 20;
  valEl.textContent = cnt;

  const existingVals = [...platesEl.querySelectorAll('.vd-plate-input-ep')].map(el => el.value);
  platesEl.innerHTML = Array.from({ length: cnt }, (_, j) => `
    <div class="vd-plate-row-ep">
      <div class="vd-plate-num-ep">${j + 1}</div>
      <input class="vd-plate-input-ep" id="vp_ep_${chipIdx}_${j}" type="text" placeholder="e.g. ABC 1234" maxlength="12" value="${existingVals[j] || ''}">
    </div>`).join('');
}

// ── MEDIA ────────────────────────────────────────────
function handleMedia(input) {
  for (const f of input.files) mediaFiles.push(f);
  const container = document.getElementById('mediaThumbs');
  container.innerHTML = '';
  mediaFiles.forEach(file => {
    const url = URL.createObjectURL(file);
    const img = document.createElement('img');
    img.src = url;
    img.onclick = () => { document.getElementById('fullImg').src = url; new bootstrap.Modal(document.getElementById('imageModal')).show(); };
    container.appendChild(img);
  });
}

// ── CHAR COUNT ───────────────────────────────────────
document.getElementById('eventDetails').addEventListener('input', function() {
  document.getElementById('charCount').textContent = this.value.length;
});

// ── MAP ──────────────────────────────────────────────
function initMap() {
  if (map) return;
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
    attribution: '© OpenStreetMap contributors',
    maxZoom: 18
  }).addTo(map);

  // ── BORDER HIGHLIGHT ─────────────────────────────────────
  L.polygon(VALENZUELA_POLY, {
    color: '#007ED2',
    weight: 3,
    dashArray: '8 5',
    fill: false,
    interactive: false
  }).addTo(map);

  // ── DARK MASK: inverted donut polygon — outside = dark, inside = city ──
  // fillRule 'evenodd' is REQUIRED for the hole to render correctly in Leaflet
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
  if (!navigator.geolocation) { document.getElementById('addressDisplay').textContent = 'Geolocation not supported.'; return; }
  navigator.geolocation.getCurrentPosition(pos => {
    const { latitude: lat, longitude: lng } = pos.coords;
    map.setView([lat, lng], 16);
    mapMarker.setLatLng([lat, lng]);
    reverseGeocode({ lat, lng });
  }, () => {
    document.getElementById('addressDisplay').textContent = 'Could not detect. Drag the pin manually.';
  });
}

async function reverseGeocode({ lat, lng }) {
  document.getElementById('locLat').value = lat;
  document.getElementById('locLng').value = lng;
  try {
    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
    const data = await res.json();
    const addr = data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    document.getElementById('addressDisplay').textContent = addr;
    document.getElementById('locAddress').value = addr;
  } catch {
    const addr = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    document.getElementById('addressDisplay').textContent = addr;
    document.getElementById('locAddress').value = addr;
  }
}

// ── VALIDATION ───────────────────────────────────────
function validate() {
  const errors = [];
  if (!S.parties) errors.push('Please select how many people are involved.');
  if (S.has_injury === null) errors.push('Please indicate whether anyone was injured.');
  if (!document.getElementById('incidentDate').value) errors.push('Please enter the incident date.');
  if (!document.getElementById('incidentTime').value) errors.push('Please enter the incident time.');
  if (!document.getElementById('locLat').value) errors.push('Please allow location access or drag the map pin.');
  if (!document.querySelectorAll('#vehicleChipsEP .chip-ep.active').length) errors.push('Please select at least one vehicle type.');
  if (!document.getElementById('weatherCond').value) errors.push('Please select a weather condition.');
  if (!document.getElementById('roadCond').value) errors.push('Please select a road condition.');
  if (!S.insurance_type) errors.push('Please select your insurance status.');
  if (!document.getElementById('eventDetails').value.trim()) errors.push('Please describe what happened.');

  // Contract fields if settling
  if (S.settle) {
    if (!document.getElementById('contractTerms').value.trim()) errors.push('Please enter the agreement terms.');
    if (!document.getElementById('party1Name').value.trim() || !document.getElementById('party2Name').value.trim()) errors.push('Please enter both party names for the contract.');
  }

  return errors;
}

// ── SUBMIT ───────────────────────────────────────────
async function submitReport() {
  const errs = validate();
  if (errs.length) { alert('Please fix the following:\n\n• ' + errs.join('\n• ')); return; }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Submitting…';

  // Standard report — collect active chips as DOM elements
  const selected = [...document.querySelectorAll('#vehicleChipsEP .chip-ep.active')];
  const fd = new FormData();
  fd.append('flow_type', S.flow_type);
  fd.append('reporter_role', 'driver');
  fd.append('parties', S.parties);
  fd.append('has_other_parties', (S.parties === 'two' || S.parties === 'multiple') ? 1 : 0);
  fd.append('is_injured', S.has_injury ? 1 : 0);
  fd.append('injured_count', S.injured_count || 'none');
  fd.append('injury_severity', S.injury_severity || '');
  fd.append('has_deceased', S.has_deceased === true ? 1 : (S.has_deceased === false ? 0 : ''));
  fd.append('self_hurt', S.self_hurt ?? '');
  fd.append('enforcer_present', S.enforcer_present ?? '');
  fd.append('enforcer_type', 'TMO');
  fd.append('incident_date', document.getElementById('incidentDate').value);
  fd.append('incident_time', document.getElementById('incidentTime').value);
  fd.append('location_lat', document.getElementById('locLat').value);
  fd.append('location_lng', document.getElementById('locLng').value);
  fd.append('location_address', document.getElementById('locAddress').value);
  fd.append('damage_category', document.getElementById('damageCategory').value);
  selected.forEach((chip, i) => {
    const v    = chip.dataset.vehicle;
    const card = document.querySelector(`.vd-card-ep[data-chip-index="${i}"]`);
    const cnt  = card ? parseInt(card.querySelector('.vd-count-val-ep')?.textContent) || 1 : 1;
    // Each vehicle gets its own row — collect individual plate inputs
    Array.from({ length: cnt }, (_, j) => {
      const plate = (document.getElementById(`vp_ep_${i}_${j}`)?.value || '').trim().toUpperCase();
      fd.append('vehicle_types[]', v);
      fd.append('vehicle_counts[]', 1);
      fd.append('plate_numbers[]', plate);
    });
  });
  fd.append('weather_condition', document.getElementById('weatherCond').value);
  fd.append('road_condition', document.getElementById('roadCond').value);
  fd.append('insurance_type', S.insurance_type);
  fd.append('event_details', document.getElementById('eventDetails').value);
  mediaFiles.forEach(f => fd.append('media[]', f));

  try {
    const res = await fetch('api/submit_enforcer_report.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showSuccess(data.reference_number, data.points_earned || 0, data.total_points || 0);
      mediaFiles = [];
    } else {
      alert('Error: ' + (data.message || 'Unknown error'));
      btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Report';
    }
  } catch {
    showError();
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-send-fill"></i> Submit Report';
  }
}

function showSuccess(ref, pts, total) {
  document.body.insertAdjacentHTML('beforeend', `
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(10px);">
    <div style="background:#fff;border-radius:24px;padding:2rem 1.5rem;max-width:360px;width:100%;text-align:center;">
      <div style="width:72px;height:72px;background:linear-gradient(135deg,#00a854,#007a3d);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:2rem;"><i class="bi bi-check-circle-fill" style="color:var(--green); font-size: 1.3rem;"></i></div>
      <div style="font-size:1.2rem;font-weight:700;margin-bottom:0.5rem;">Report Submitted!</div>
      <div style="background:#f8fafc;border-radius:10px;padding:0.7rem;margin-bottom:0.75rem;">
        <div style="font-size:0.68rem;color:#888;font-family:'DM Mono',monospace;">REFERENCE NUMBER</div>
        <div style="font-size:1rem;font-weight:700;color:#E90101;font-family:'DM Mono',monospace;">${ref}</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-top:0.5rem;">
        <a href="track.php" style="background:#f1f5f9;color:#334155;border-radius:10px;padding:0.7rem;text-decoration:none;font-weight:600;font-size:0.82rem;font-family: 'Poppins', sans-serif;">Track Report</a>
        <a href="enforcer_landing.php" style="background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;border-radius:10px;padding:0.7rem;text-decoration:none;font-weight:600;font-size:0.82rem;font-family: 'Poppins', sans-serif;">Go Home</a>
      </div>
    </div>
  </div>`);
}

function showError() {
  document.body.insertAdjacentHTML('beforeend', `
  <div style="position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:1100;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(10px);">
    <div style="background:#fff;border-radius:24px;padding:2rem 1.5rem;max-width:340px;width:100%;text-align:center;">
      <div style="font-size:2.5rem;margin-bottom:0.75rem;color:var(--amber);"><i class="bi bi-exclamation-triangle-fill"></i></div>
      <div style="font-size:1rem;font-weight:700;margin-bottom:0.5rem;">Connection Error</div>
      <div style="font-size:0.82rem;color:#64748b;margin-bottom:1.25rem;">Could not submit your report. Please check your connection and try again.</div>
      <button onclick="this.closest('[style]').remove()" style="width:100%;background:#007ED2;color:#fff;border:none;border-radius:12px;padding:0.8rem;font-family: 'Poppins', sans-serif;font-size:0.9rem;font-weight:700;cursor:pointer;">Dismiss</button>
    </div>
  </div>`);
}

// ── INIT ─────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const now = new Date();
  document.getElementById('incidentDate').value = now.toISOString().split('T')[0];
  document.getElementById('incidentTime').value = now.toTimeString().slice(0,5);
  initMap();
  updateConditionals();
});
</script>
</body>
</html>