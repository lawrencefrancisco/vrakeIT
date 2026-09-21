<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
requireLogin();

$user = getLoggedInUser();
if ($user['role'] !== 'enforcer') {
    header("Location: landing.php");
    exit;
}

// Mark incidents as viewed
$db = getDB();
$db->prepare("UPDATE users SET last_incident_viewed_at = NOW() WHERE id = ?")->execute([$user['id']]);
$user['last_incident_viewed_at'] = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VrakeIT - Live Incidents</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link href="assets/css/style.css" rel="stylesheet">
<style>
  #detailMap { height: 200px; width: 100%; border-radius: 12px; margin-bottom: 12px; border: 1px solid #e0e0e0; z-index: 1;}
  .info-card { background:#f8fafc; border-radius:12px; padding:14px; margin-bottom:12px; }
  .info-card-title { font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:10px; }
  .info-row { margin-bottom:10px; }
  .info-row-label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:3px; }
  .info-row-value { font-size:13px; font-weight:600; color:#0f172a; }
</style>
</head>
<body style="background-color: #f8fafc;">

<header class="app-header">
  <a href="enforcer_landing.php" class="back-btn" style="font-size:22px;"><i class="bi bi-arrow-left"></i></a>
  <span class="header-title">Live Incidents</span>
  <span style="width:32px;"></span>
</header>

<div style="padding:16px 0 0;">
  
  <div style="padding: 0 16px 16px; display: flex; gap: 8px;">
    <div style="flex: 1; position: relative;">
      <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
      <input type="text" id="searchInput" placeholder="Search reference or location..." style="width: 100%; padding: 8px 12px 8px 36px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 13px; outline: none; font-family: Poppins, sans-serif;" oninput="renderReports()">
    </div>
    <button onclick="loadReports()" class="btn-primary-vr" style="width: auto; padding:8px 16px; border-radius:12px; border:none; display:flex; align-items:center; gap:6px; flex-shrink: 0;">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
  </div>

  <div id="loadingState" style="text-align:center;padding:40px;color:var(--muted);">
    <div style="width:40px;height:40px;border:3px solid #e0e0e0;border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px;"></div>
    Loading live incidents...
  </div>

  <div id="emptyState" style="display:none;text-align:center;padding:60px 20px;">
    <i class="bi bi-shield-check" style="font-size:56px;color:#ddd;display:block;margin-bottom:12px;"></i>
    <h5 style="color:var(--muted);font-weight:600;">No Active Incidents</h5>
    <p style="color:#aaa;font-size:14px;">The streets are currently safe and clear.</p>
  </div>

  <div id="reportsList"></div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable" style="margin:auto;max-width:480px;">
    <div class="modal-content" style="border-radius:20px;border:none;">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--blue),#005fa3);color:#fff;border-radius:20px 20px 0 0;">
        <h5 class="modal-title">Incident Details</h5>
        <button type="button" class="btn-close btn-close-white opacity-100" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody" style="font-size:14px;padding:16px;"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('detailModal'));
let allReports = [];
let detailMap = null;

async function loadReports() {
  document.getElementById('loadingState').style.display = 'block';
  document.getElementById('reportsList').innerHTML = '';
  document.getElementById('emptyState').style.display = 'none';

  try {
    const res  = await fetch('api/get_live_reports.php');
    const data = await res.json();
    document.getElementById('loadingState').style.display = 'none';

    if (!data.success || !data.reports.length) {
      document.getElementById('emptyState').style.display = 'block';
      return;
    }

    allReports = data.reports;
    renderReports();
  } catch {
    document.getElementById('loadingState').innerHTML = '<i class="bi bi-wifi-off" style="font-size:40px;color:#ddd;display:block;margin-bottom:8px;"></i>Failed to load reports.';
  }
}

async function generateAISummary(rid) {
  const btn = document.getElementById('btnAiSummarize');
  const textDiv = document.getElementById('aiSummaryText');

  btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:var(--muted);">Analyzing incident details...</span>';

  try {
    const fd = new FormData();
    fd.append('report_id', rid);
    const res = await fetch('api/summarize_incident.php', { method: 'POST', body: fd });
    const d = await res.json();
    btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
    btn.disabled = false;
    textDiv.innerHTML = d.success
      ? `<span style="color:#333;font-weight:500;">${d.summary}</span>`
      : `<span style="color:var(--red);">Error: ${d.message}</span>`;
  } catch {
    btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
    btn.disabled = false;
    textDiv.innerHTML = '<span style="color:var(--red);">Network error occurred.</span>';
  }
}

function renderReports() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  const filtered = allReports.filter(r =>
    r.reference_number.toLowerCase().includes(searchTerm) ||
    (r.location_address && r.location_address.toLowerCase().includes(searchTerm)) ||
    (r.first_name && r.first_name.toLowerCase().includes(searchTerm))
  );

  const reportsList = document.getElementById('reportsList');

  if (!filtered.length) {
    reportsList.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);">No reports match your search criteria.</div>';
    return;
  }

  reportsList.innerHTML = filtered.map(r => {
    const injuryBadge = r.is_injured == 1
      ? `<span style="background:#fee2e2;color:#ef4444;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;"><i class="bi bi-exclamation-circle-fill me-1"></i>Injured</span>`
      : '';
    const addr = r.location_address ? r.location_address.substring(0, 55) + (r.location_address.length > 55 ? '…' : '') : '';
    return `
    <div class="report-card" onclick='showDetail(${JSON.stringify(r).replace(/'/g,"&#39;")})' style="cursor:pointer; border-left: 4px solid var(--red);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
        <div>
          <div class="ref">${r.reference_number}</div>
          <div style="font-size:12px;font-weight:600;color:var(--blue);"><i class="bi bi-person me-1"></i>${r.first_name} ${r.last_name}</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
          ${getStatusBadge(r.status)}
          ${injuryBadge}
        </div>
      </div>
      <div class="date"><i class="bi bi-calendar3 me-1"></i>${r.formatted_date}</div>
      ${addr ? `<div style="font-size:12px;color:var(--muted);margin-top:4px;"><i class="bi bi-geo-alt me-1"></i>${addr}</div>` : ''}
      <div style="text-align:right;margin-top:8px;font-size:12px;color:var(--blue);font-weight:600;">View Details &rarr;</div>
    </div>`;
  }).join('');
}

function getStatusBadge(s) {
  const map = {
    pending:   { bg: '#fff3cd', color: '#856404', label: '⚠️ Pending'   },
    reviewing: { bg: '#cfe2ff', color: '#084298', label: '🔍 Reviewing' },
    ongoing:   { bg: '#d1fae5', color: '#065f46', label: '🟢 Ongoing'   },
    closed:    { bg: '#f1f5f9', color: '#64748b', label: '✅ Closed'     },
    escalated: { bg: '#fee2e2', color: '#991b1b', label: '🚨 Escalated' },
  };
  const c = map[s] || map.pending;
  return `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;background:${c.bg};color:${c.color};">${c.label}</span>`;
}

// ── Helper badge builder ─────────────────────────────────────
function badge(text, bg, border, color, icon) {
  const ico = icon ? `<i class="bi bi-${icon} me-1"></i>` : '';
  return `<span style="background:${bg};border:1px solid ${border};color:${color};padding:2px 9px;border-radius:6px;font-size:12px;font-weight:700;">${ico}${text}</span>`;
}

function infoRow(label, html) {
  return `<div class="info-row"><div class="info-row-label">${label}</div><div class="info-row-value">${html}</div></div>`;
}

function showDetail(r) {
  // ── Photos ───────────────────────────────────────────────────
  const photoArr = Array.isArray(r.photos) ? r.photos.filter(Boolean) : [];
  let photosHtml = '<span style="color:#94a3b8;font-style:italic;font-size:13px;">No photos attached</span>';
  if (photoArr.length) {
    photosHtml = `<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;">` +
      photoArr.map(p => {
        let src = p.trim();
        if (!src.startsWith('http') && !src.startsWith('assets/')) src = 'assets/uploads/' + src;
        return `<img src="${src}" onclick="viewFullImage(this.src)" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e0e0e0;cursor:pointer;">`;
      }).join('') + '</div>';
  }

  // ── Role / Flow ──────────────────────────────────────────────
  const roleHtml = r.reporter_role === 'citizen'
    ? badge('Citizen / Witness', '#f0fdf4', '#bbf7d0', '#16a34a', 'eye-fill')
    : r.reporter_role === 'enforcer'
      ? badge('Enforcer', '#d1fae5', '#a7f3d0', '#059669', 'shield-fill')
      : badge('Driver', '#eff6ff', '#bfdbfe', '#2563eb', 'person-fill');

  const flowHtml = r.flow_type === 'good_citizen'
    ? badge('Good Citizen ⭐', '#fefce8', '#fde68a', '#d97706', 'star-fill')
    : badge('Standard Report', '#f1f5f9', '#e2e8f0', '#64748b');

  // ── Parties ──────────────────────────────────────────────────
  let partiesHtml;
  if      (r.parties === 'self')     partiesHtml = badge('Solo / No Other Parties',  '#f1f5f9','#e2e8f0','#64748b','person');
  else if (r.parties === 'two')      partiesHtml = badge('2 Drivers',                '#eff6ff','#bfdbfe','#1d4ed8','people');
  else if (r.parties === 'multiple') partiesHtml = badge('Three or More',             '#f5f3ff','#ddd6fe','#7c3aed','people-fill');
  else partiesHtml = r.has_other_parties
    ? badge('Yes', '#fee2e2','#fca5a5','#ef4444')
    : badge('No',  '#f1f5f9','#e2e8f0','#64748b');

  // ── Injury ───────────────────────────────────────────────────
  const injHtml = r.is_injured == 1
    ? badge('YES — Injuries Reported','#fee2e2','#fca5a5','#ef4444','exclamation-circle-fill')
    : badge('No Injuries','#f1f5f9','#e2e8f0','#64748b','check-circle');

  let injCountHtml = '';
  if (r.injured_count === 'none')         injCountHtml = infoRow('Injured Count', badge('None','#f1f5f9','#e2e8f0','#64748b','check-circle'));
  else if (r.injured_count === 'one')     injCountHtml = infoRow('Injured Count', badge('1 Person','#fef3c7','#fde68a','#d97706','person-fill-exclamation'));
  else if (r.injured_count === 'multiple')injCountHtml = infoRow('Injured Count', badge('2 or More','#fee2e2','#fca5a5','#ef4444','people-fill'));

  let sevHtml = '';
  if (r.injury_severity) {
    sevHtml = infoRow('Injury Severity', r.injury_severity === 'minor'
      ? badge('Minor','#fef3c7','#fde68a','#d97706','bandaid-fill')
      : badge('Major / Critical','#fee2e2','#fca5a5','#ef4444','heartbreak-fill'));
  }

  let decHtml = '';
  if (r.injury_severity && r.has_deceased !== null && r.has_deceased !== undefined && r.has_deceased !== '') {
    decHtml = infoRow('Fatalities', r.has_deceased == 1
      ? badge('Deceased Reported','#fee2e2','#fca5a5','#ef4444','x-octagon-fill')
      : badge('Everyone Alive','#dcfce7','#bbf7d0','#16a34a','check-circle-fill'));
  }

  // ── Enforcer ─────────────────────────────────────────────────
  let formattedEnforcer = r.enforcer_type || '';
  if (formattedEnforcer) {
    if (formattedEnforcer.toUpperCase() === 'TMO_POLICE') {
      formattedEnforcer = 'TMO / Police';
    } else {
      formattedEnforcer = formattedEnforcer.replace(/_/g, ' / ').toUpperCase();
    }
  }

  const enfHtml = formattedEnforcer
    ? badge(formattedEnforcer, '#e0f2fe', '#bae6fd', '#0284c7', 'shield-shaded')
    : '<span style="color:#94a3b8;font-style:italic;">None reported</span>';

  // ── Map & Navigation ──────────────────────────────────────────
  const gmapsUrl = r.location_lat ? `https://www.google.com/maps/dir/?api=1&destination=${r.location_lat},${r.location_lng}` : '#';
  const navBtn = r.location_lat
    ? `<a href="${gmapsUrl}" target="_blank" style="display:flex;justify-content:center;align-items:center;gap:8px;padding:12px;margin-bottom:14px;border-radius:12px;background:linear-gradient(135deg,#007ED2,#005fa3);color:#fff;text-decoration:none;font-weight:600;font-size:13px;box-shadow:0 4px 15px rgba(0,126,210,.3);">
         <i class="bi bi-geo-alt-fill" style="font-size:16px;"></i> Open in Google Maps
       </a>` : '';

  // ── AI Summary ────────────────────────────────────────────────
  const aiHtml = `
    <div style="background:rgba(0,126,210,.05);border:1px solid rgba(0,126,210,.2);border-radius:12px;padding:12px;display:flex;align-items:center;gap:12px;margin-bottom:14px;">
      <div style="flex-shrink:0;font-size:22px;color:#007ED2;"><i class="bi bi-robot"></i></div>
      <div style="flex-grow:1;">
        <div style="font-size:11px;font-weight:700;color:#007ED2;text-transform:uppercase;letter-spacing:1px;margin-bottom:2px;">AI Incident Summary</div>
        <div id="aiSummaryText" style="font-size:12px;color:var(--muted);line-height:1.5;">Tap the button to generate a quick summary.</div>
      </div>
      <button id="btnAiSummarize" onclick="generateAISummary(${r.id})" style="padding:6px 12px;font-size:12px;white-space:nowrap;border-radius:8px;border:none;background:#007ED2;color:#fff;font-weight:600;flex-shrink:0;">
        <i class="bi bi-magic"></i> Summarize
      </button>
    </div>`;

  // ── Assemble modal ────────────────────────────────────────────
  document.getElementById('modalBody').innerHTML = `
    <div style="padding:4px 0;">
      ${aiHtml}
      <div id="detailMap" style="height:200px;width:100%;border-radius:12px;margin-bottom:12px;border:1px solid #e0e0e0;"></div>
      ${navBtn}

      <div class="info-card">
        <div class="info-card-title">Report Overview</div>
        ${infoRow('Reference #', `<span style="font-family:monospace;font-size:14px;color:#E90101;font-weight:800;">${r.reference_number}</span>`)}
        ${infoRow('Submitted By', `<i class="bi bi-person me-1" style="color:#007ED2;"></i>${r.first_name} ${r.last_name}`)}
        ${infoRow('Role', roleHtml)}
        ${infoRow('Report Type', flowHtml)}
        ${infoRow('Status', getStatusBadge(r.status))}
        ${infoRow('Submitted', `<i class="bi bi-clock me-1" style="color:#64748b;"></i>${r.formatted_date}`)}
        ${r.incident_date ? infoRow('Incident Date / Time', `<i class="bi bi-calendar3 me-1" style="color:#64748b;"></i>${r.incident_date}${r.incident_time ? ' at ' + r.incident_time : ''}`) : ''}
      </div>

      <div class="info-card">
        <div class="info-card-title">Location</div>
        ${infoRow('Address', `<i class="bi bi-geo-alt-fill me-1" style="color:#ef4444;"></i>${r.location_address || '<span style="color:#94a3b8;font-style:italic;">Not provided</span>'}`)}
        ${infoRow('Weather / Road', `${r.weather_condition || '-'} &nbsp;/&nbsp; ${r.road_condition || '-'}`)}
      </div>

      <div class="info-card">
        <div class="info-card-title">Parties &amp; Injuries</div>
        ${infoRow('Parties Involved', partiesHtml)}
        ${infoRow('Injuries', injHtml)}
        ${injCountHtml}
        ${sevHtml}
        ${decHtml}
        ${infoRow('Law Enforcer / Authority', enfHtml)}
      </div>

      ${r.damage_category ? `<div class="info-card">
        <div class="info-card-title">Damage</div>
        ${infoRow('Damage Category', r.damage_category)}
      </div>` : ''}

      <div class="info-card">
        <div class="info-card-title">Description</div>
        <p style="font-size:13px;color:#0f172a;line-height:1.6;margin:0;">${r.event_details || '<span style="color:#94a3b8;font-style:italic;">No description provided.</span>'}</p>
      </div>

      ${photoArr.length ? `<div class="info-card">
        <div class="info-card-title">Photos / Media (${photoArr.length})</div>
        ${photosHtml}
      </div>` : ''}
    </div>`;

  modal.show();

  // Init map after modal is visible
  document.getElementById('detailModal').addEventListener('shown.bs.modal', function initMap() {
    document.getElementById('detailModal').removeEventListener('shown.bs.modal', initMap);
    if (detailMap) { detailMap.remove(); detailMap = null; }

    const lat = r.location_lat ? parseFloat(r.location_lat) : 14.5995;
    const lng = r.location_lng ? parseFloat(r.location_lng) : 120.9842;

    detailMap = L.map('detailMap').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(detailMap);

    if (r.location_lat && r.location_lng) {
      const redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25,41], iconAnchor: [12,41], popupAnchor: [1,-34], shadowSize: [41,41]
      });
      L.marker([lat, lng], {icon: redIcon}).addTo(detailMap)
        .bindPopup(`<b>Incident Location</b><br>${r.location_address || ''}`).openPopup();
    }
    setTimeout(() => detailMap.invalidateSize(), 200);
  });
}

loadReports();
</script>

<!-- Full Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" style="z-index: 1060;">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background: transparent; border: none;">
      <div class="modal-header" style="border: none; padding: 0; justify-content: flex-end;">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="margin-bottom: 8px; filter: invert(1); opacity: 1;"></button>
      </div>
      <div class="modal-body" style="padding: 0; text-align: center;">
        <img id="fullSizeImage" src="" alt="Full Size" style="max-width: 100%; max-height: 80vh; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.8);">
      </div>
    </div>
  </div>
</div>

<script>
function viewFullImage(src) {
  document.getElementById('fullSizeImage').src = src;
  const viewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));
  const detailModalEl = document.getElementById('detailModal');
  if (detailModalEl && detailModalEl.classList.contains('show')) {
    bootstrap.Modal.getInstance(detailModalEl).hide();
    document.getElementById('imageViewerModal').addEventListener('hidden.bs.modal', function () {
      bootstrap.Modal.getInstance(detailModalEl)?.show();
    }, { once: true });
  }
  viewerModal.show();
}
</script>

</body>
</html>
