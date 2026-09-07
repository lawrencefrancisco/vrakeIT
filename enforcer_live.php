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
</style>
</head>
<body style="background-color: #f8fafc;">

<header class="app-header">
  <a href="enforcer_landing.php" style="color:#fff;font-size:22px;"><i class="bi bi-arrow-left"></i></a>
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
      <div class="modal-body" id="modalBody" style="font-size:14px;"></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('detailModal'));
let allReports = []; 
let detailMap = null;
let detailMarker = null;

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
  
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:var(--muted);">Analyzing incident details...</span>';
  
  try {
    const fd = new FormData();
    fd.append('report_id', rid);
    
    const res = await fetch('api/summarize_incident.php', { method: 'POST', body: fd });
    const d = await res.json();
    
    btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
    btn.disabled = false;
    
    if(d.success) {
      textDiv.innerHTML = `<span style="color:#333; font-weight:500;">${d.summary}</span>`;
    } else {
      textDiv.innerHTML = `<span style="color:var(--red);">Error: ${d.message}</span>`;
    }
  } catch (error) {
    btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
    btn.disabled = false;
    textDiv.innerHTML = `<span style="color:var(--red);">Network error occurred.</span>`;
  }
}

function renderReports() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  
  const filtered = allReports.filter(r => {
    return r.reference_number.toLowerCase().includes(searchTerm) || 
           (r.location_address && r.location_address.toLowerCase().includes(searchTerm)) ||
           (r.first_name && r.first_name.toLowerCase().includes(searchTerm));
  });

  const reportsList = document.getElementById('reportsList');

  if (filtered.length === 0) {
    reportsList.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);">No reports match your search criteria.</div>';
    return;
  }

  const html = filtered.map(r => `
    <div class="report-card" onclick='showDetail(${JSON.stringify(r).replace(/'/g,"&#39;")})' style="cursor:pointer; border-left: 4px solid var(--red);">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
        <div>
          <div class="ref">${r.reference_number}</div>
          <div style="font-size:12px;font-weight:600;color:var(--blue);"><i class="bi bi-person me-1"></i>${r.first_name} ${r.last_name}</div>
        </div>
        ${getStatusBadge(r.status)}
      </div>
      <div class="date"><i class="bi bi-calendar3 me-1"></i>${r.formatted_date}</div>
      ${r.location_address ? `<div style="font-size:12px;color:var(--muted);margin-top:4px;"><i class="bi bi-geo-alt me-1"></i>${r.location_address.substring(0,60)}...</div>` : ''}
      <div style="text-align:right;margin-top:8px;font-size:12px;color:var(--blue);font-weight:600;">View Location & Info &rarr;</div>
    </div>
  `).join('');
  
  reportsList.innerHTML = html;
}

function getStatusBadge(s) {
  const map = {
    pending:   { bg: '#fff3cd', color: '#856404', label: '⚠️ Pending'   },
    reviewing: { bg: '#cfe2ff', color: '#084298', label: '🔍 Reviewing' }
  };
  const c = map[s] || map.pending;
  return `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;background:${c.bg};color:${c.color};">${c.label}</span>`;
}

function showDetail(r) {
  const mediaData = r.media_urls || r.photos || r.media || r.attachments; 
  let photosHtml = '<span style="color:var(--muted)">No photos attached</span>';
  
  if (mediaData) { 
    let urls = [];
    if (Array.isArray(mediaData)) {
        urls = mediaData;
    } else if (typeof mediaData === 'string' && mediaData.trim().startsWith('[')) {
        try { urls = JSON.parse(mediaData); } catch(e) {}
    } else if (typeof mediaData === 'string') {
        urls = mediaData.split(',');
    }
    urls = urls.filter(url => url && url.trim() !== '');

    if (urls.length > 0) {
       photosHtml = `<div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">` + 
                    urls.map(url => {
                        let imagePath = url.trim();
                        if (!imagePath.startsWith('assets/uploads/') && !imagePath.startsWith('http')) {
                            imagePath = 'assets/uploads/' + imagePath;
                        }
                        return `<img src="${imagePath}" onclick="viewFullImage(this.src)" style="width:60px; height:60px; object-fit:cover; border-radius:8px; border: 1px solid #e0e0e0; cursor: pointer;">`;
                    }).join('') + `</div>`;
    }
  }

  const rows = [
    ['Reference', r.reference_number],
    ['Submitted By', r.first_name + ' ' + r.last_name],
    ['Injured?', r.is_injured ? '<span style="color:var(--red);font-weight:bold;">Yes - Needs Medic</span>' : 'No'],
    ['Date of Incident', r.incident_date || '-'],
    ['Location', r.location_address || '-'],
    ['Other Parties', r.has_other_parties ? 'Yes' : 'No'],
    ['Weather / Road', (r.weather_condition || '-') + ' / ' + (r.road_condition || '-')],
    ['Photos', photosHtml],
    ['Details', r.event_details || '-']
  ].map(([l, v]) => `<div class="overview-row"><span class="label">${l}</span><span class="value" style="${l === 'Photos' ? 'flex-basis: 100%; margin-top: 4px;' : ''}">${v}</span></div>`).join('');
  
  const mapHtml = `<div id="detailMap"></div>`;

  const gmapsUrl = r.location_lat ? `https://www.google.com/maps/dir/?api=1&destination=${r.location_lat},${r.location_lng}` : '#';
  const gmapsBtnHtml = r.location_lat ? `
    <a href="${gmapsUrl}" target="_blank" style="display:flex; justify-content:center; align-items:center; gap:8px; padding:12px; margin-bottom:16px; border-radius:12px; background:linear-gradient(135deg, #007ED2, #005fa3); color:#fff; text-decoration:none; font-weight:600; box-shadow: 0 4px 15px rgba(0, 126, 210, 0.3);">
      <i class="bi bi-geo-alt-fill" style="font-size:16px;"></i> Open in Google Maps
    </a>` : '';

  const aiHtml = `
    <div style="background:rgba(0, 126, 210, 0.05); border:1px solid rgba(0, 126, 210, 0.2); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px; margin-bottom: 16px;">
      <div style="flex-shrink:0; font-size:24px; color:#007ED2;"><i class="bi bi-robot"></i></div>
      <div style="flex-grow:1;">
        <div style="font-size:12px; font-weight:bold; color:#007ED2; text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">AI Incident Summary</div>
        <div id="aiSummaryText" style="font-size:13px; color:var(--muted); line-height: 1.4;">Tap the button to generate a quick summary.</div>
      </div>
      <div>
        <button id="btnAiSummarize" class="btn-primary-vr" onclick="generateAISummary(${r.id})" style="padding: 6px 12px; font-size: 12px; white-space:nowrap; border-radius: 8px; text-decoration:none; display:inline-block; border:none; background: #007ED2;">
          <i class="bi bi-magic"></i> Summarize
        </button>
      </div>
    </div>
  `;

  document.getElementById('modalBody').innerHTML = `<div style="padding:4px 0;">${aiHtml}${mapHtml}${gmapsBtnHtml}${rows}</div>`;
  
  modal.show();

  // Initialize Map after modal is shown so it renders correctly
  document.getElementById('detailModal').addEventListener('shown.bs.modal', function initMap() {
    document.getElementById('detailModal').removeEventListener('shown.bs.modal', initMap);
    
    if (detailMap) {
      detailMap.remove();
    }
    
    let lat = r.location_lat ? parseFloat(r.location_lat) : 14.5995;
    let lng = r.location_lng ? parseFloat(r.location_lng) : 120.9842;
    
    detailMap = L.map('detailMap').setView([lat, lng], 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(detailMap);
    
    // Custom marker icon
    const redIcon = L.icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    if (r.location_lat && r.location_lng) {
      detailMarker = L.marker([lat, lng], {icon: redIcon}).addTo(detailMap);
      detailMarker.bindPopup(`<b>Incident Location</b><br>${r.location_address || ''}`).openPopup();
    }
    
    // Invalidate size to fix leaflet grey tiles issue in modals
    setTimeout(() => { detailMap.invalidateSize(); }, 200);
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
          bootstrap.Modal.getInstance(detailModalEl).show();
      }, { once: true });
  }
  
  viewerModal.show();
}
</script>

</body>
</html>
