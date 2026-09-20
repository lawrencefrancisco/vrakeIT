<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();
requireLogin();

$user = getLoggedInUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VrakeIT - Track Reports</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<header class="app-header">
  <?php $backLink = (($user['role'] ?? 'user') === 'enforcer') ? 'enforcer_landing.php' : 'landing.php'; ?>
  <a href="<?= $backLink ?>" class="back-btn" style="font-size:22px;"><i class="bi bi-arrow-left"></i></a>
  <span class="header-logo">My Reports</span>
  <span style="width:32px;"></span>
</header>

<div style="padding:16px 0 0;">
  
  <!-- NEW: Search and Filter Controls -->
  <div style="padding: 0 16px 16px; display: flex; gap: 8px;">
    <div style="flex: 1; position: relative;">
      <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
      <input type="text" id="searchInput" placeholder="Search reference or location..." style="width: 100%; padding: 8px 12px 8px 36px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 13px; outline: none; font-family: Poppins, sans-serif;" oninput="renderReports()">
    </div>
    <select id="statusFilter" style="padding: 8px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 13px; background: #fff; outline: none; font-family: Poppins, sans-serif;" onchange="renderReports()">
      <option value="all">All Status</option>
      <option value="pending">Pending</option>
      <option value="ongoing">Ongoing</option>
      <option value="escalated">Escalated</option>
      <option value="closed">Closed</option>
    </select>
  </div>
  <!-- END NEW -->

  <div id="loadingState" style="text-align:center;padding:40px;color:var(--muted);">
    <div style="width:40px;height:40px;border:3px solid #e0e0e0;border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px;"></div>
    Loading reports...
  </div>

  <div id="emptyState" style="display:none;text-align:center;padding:60px 20px;">
    <i class="bi bi-clipboard-x" style="font-size:56px;color:var(--red);display:block;margin-bottom:12px;"></i>
    <h5 style="color:var(--muted);font-weight:600;">No Reports Yet</h5>
    <p style="color:#aaa;font-size:14px;">File your first report to see it here.</p>
    <a href="report.php" class="btn-primary-vr d-inline-block" style="padding:12px 24px;text-decoration:none;margin-top:8px;">File a Report</a>
  </div>

  <div id="reportsList"></div>

</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable" style="margin:auto;max-width:480px;">
    <div class="modal-content" style="border-radius:20px;border:none;">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--red),#c20000);color:#fff;border-radius:20px 20px 0 0;">
        <h5 class="modal-title">Report Details</h5>
        <button type="button" class="btn-close btn-close-white opacity-100" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody" style="font-size:14px;"></div>
    </div>
    
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('detailModal'));

// NEW: Global array to store fetched reports
let allReports = []; 

async function loadReports() {
  try {
    const res  = await fetch('api/get_reports.php');
    const data = await res.json();
    document.getElementById('loadingState').style.display = 'none';
    
    if (!data.success || !data.reports.length) {
      document.getElementById('emptyState').style.display = 'block';
      return;
    }
    
    // NEW: Save data and trigger render
    allReports = data.reports;
    renderReports();

  } catch {
    document.getElementById('loadingState').innerHTML = '<i class="bi bi-wifi-off" style="font-size:40px;color:#ddd;display:block;margin-bottom:8px;"></i>Failed to load reports.';
  }
}

//AI summary
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


// NEW: Function to filter and render HTML
function renderReports() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  const filterStatus = document.getElementById('statusFilter').value;

  const filtered = allReports.filter(r => {
    // Check if reference or address matches search term
    const matchesSearch = r.reference_number.toLowerCase().includes(searchTerm) || 
                          (r.location_address && r.location_address.toLowerCase().includes(searchTerm));
    // Check if status matches dropdown
    const matchesStatus = filterStatus === 'all' || r.status === filterStatus;
    
    return matchesSearch && matchesStatus;
  });

  const reportsList = document.getElementById('reportsList');

  if (filtered.length === 0) {
    reportsList.innerHTML = '<div style="text-align:center;padding:40px;color:var(--muted);">No reports match your search criteria.</div>';
    return;
  }

  const html = filtered.map(r => `
    <div class="report-card" onclick='showDetail(${JSON.stringify(r).replace(/'/g,"&#39;")})' style="cursor:pointer;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
        <div>
          <div class="ref">${r.reference_number}</div>
          <div class="flow-type">${r.flow_label}</div>
          <div style="margin-top:4px;">
            ${ r.reporter_role === 'citizen'
              ? `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#7c3aed;background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.2);padding:2px 8px;border-radius:20px;"><i class="bi bi-eye-fill"></i> Citizen / Witness</span>`
              : `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#1d4ed8;background:rgba(29,78,216,0.08);border:1px solid rgba(29,78,216,0.2);padding:2px 8px;border-radius:20px;"><i class="bi bi-car-front-fill"></i> Driver</span>`
            }
          </div>
        </div>
        ${getStatusBadge(r.status)}
      </div>
      <div class="date"><i class="bi bi-calendar3 me-1"></i>${r.formatted_date}</div>
      ${r.location_address ? `<div style="font-size:12px;color:var(--muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><i class="bi bi-geo-alt me-1"></i>${r.location_address}</div>` : ''}
      ${ r.flow_type === 'good_citizen' && r.status === 'closed' ? `
        <div style="margin-top:10px;padding:8px 12px;border-radius:12px;background:linear-gradient(135deg,rgba(251,191,36,0.15),rgba(74,222,128,0.15));border:1.5px solid rgba(251,191,36,0.5);display:flex;align-items:center;gap:8px;">
          <span style="font-size:20px;">⭐</span>
          <div>
            <div style="font-size:12px;font-weight:700;color:#92400e;">+50 Points Granted!</div>
            <div style="font-size:11px;color:#a16207;">Thank you for being a Good Citizen</div>
          </div>
        </div>` : '' }
      <div style="text-align:right;margin-top:8px;font-size:12px;color:var(--blue);">Tap for details &rarr;</div>
    </div>
  `).join('');
  
  reportsList.innerHTML = html;
}

function getStatusBadge(s) {
  const map = {
    pending:   { bg: '#fff3cd', color: '#856404', icon: 'bi-clock',                    label: 'Pending'                       },
    ongoing:   { bg: '#cfe2ff', color: '#084298', icon: 'bi-arrow-repeat',             label: 'Ongoing'                       },
    escalated: { bg: '#fde8e8', color: '#991b1b', icon: 'bi-exclamation-octagon-fill', label: 'Escalated to Higher Dept.'     },
    closed:    { bg: '#c6ccc9', color: '#0a3622', icon: 'bi-lock-fill',                label: 'Closed'                        },
  };
  const c = map[s] || map.pending;
  return `<span style="display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;background:${c.bg};color:${c.color};"><i class="bi ${c.icon}"></i> ${c.label}</span>`;
}

function showDetail(r) {
  // 1. SMART FALLBACK: Look for the photos under common property names
  const mediaData = r.media_urls || r.photos || r.media || r.attachments; 
  let photosHtml = 'None';
  
  if (mediaData) { 
    let urls = [];
    
    // 2. SMART PARSE: Handle Arrays, JSON strings, or Comma-separated strings
    if (Array.isArray(mediaData)) {
        urls = mediaData;
    } else if (typeof mediaData === 'string' && mediaData.trim().startsWith('[')) {
        try { urls = JSON.parse(mediaData); } catch(e) {}
    } else if (typeof mediaData === 'string') {
        urls = mediaData.split(',');
    }

    // Clean up empty links
    urls = urls.filter(url => url && url.trim() !== '');

    // 3. GENERATE HTML: Add the onclick event to enlarge
  // 3. GENERATE HTML: Add the onclick event to enlarge
    if (urls.length > 0) {
       photosHtml = `<div style="display:flex; gap:6px; flex-wrap:wrap; margin-top:4px;">` + 
                    urls.map(url => {
                        let imagePath = url.trim();
                        
                        // Add the missing 'assets/uploads/' folder to the path
                        if (!imagePath.startsWith('assets/uploads/') && !imagePath.startsWith('http')) {
                            imagePath = 'assets/uploads/' + imagePath;
                        }
                        
                        return `<img src="${imagePath}" onclick="viewFullImage(this.src)" style="width:60px; height:60px; object-fit:cover; border-radius:8px; border: 1px solid #e0e0e0; cursor: pointer;">`;
                    }).join('') + 
                    `</div>`;
    }
  }

  const roleLabel = r.reporter_role === 'citizen'
    ? `<span style="display:inline-flex;align-items:center;gap:5px;color:#7c3aed;font-weight:600;"><i class="bi bi-eye-fill"></i> Citizen / Witness</span>`
    : `<span style="display:inline-flex;align-items:center;gap:5px;color:#1d4ed8;font-weight:600;"><i class="bi bi-car-front-fill"></i> Driver</span>`;

  // Build vehicles HTML
  let vehiclesHtml = '-';
  if (r.vehicles && r.vehicles.length > 0) {
    vehiclesHtml = r.vehicles.map(v =>
      `<div style="margin-bottom:3px;"><strong>${v.vehicle_type}</strong> &mdash; <span style="font-family:monospace;color:var(--red);">${v.plate_number || 'No plate'}</span></div>`
    ).join('');
  }

  // Injury severity and deceased labels
  const severityLabel = r.injury_severity === 'minor'
    ? `<span style="color:#d97706;font-weight:700;"><i class="bi bi-bandaid-fill"></i> Minor Injury</span>`
    : r.injury_severity === 'major'
      ? `<span style="color:#dc2626;font-weight:700;"><i class="bi bi-heartbreak-fill"></i> Major Injury</span>`
      : null;

  const deceasedLabel = r.has_deceased == 1
    ? `<span style="color:#7f1d1d;font-weight:700;"><i class="bi bi-x-octagon-fill"></i> There are fatalities / deceased</span>`
    : r.has_deceased == 0
      ? `<span style="color:#15803d;font-weight:700;"><i class="bi bi-check-circle-fill"></i> Everyone is alive</span>`
      : null;

  const rows = [
    ['Reference', r.reference_number],
    ['Status', r.status.charAt(0).toUpperCase() + r.status.slice(1)],
    ['Type', r.flow_label],
    ['Role', roleLabel],
    ['Submitted', r.formatted_date],
    ['Injured?', r.is_injured ? 'Yes' : 'No'],
    ...(severityLabel  ? [['Injury Severity',  severityLabel]]  : []),
    ...(deceasedLabel  ? [['Deceased Status',  deceasedLabel]]  : []),
    ['Date of Incident', r.incident_date || '-'],
    ['Location', r.location_address || '-'],
    ['Other Parties', r.has_other_parties ? 'Yes' : 'No'],
    ['Weather', r.weather_condition || '-'],
    ['Road Condition', r.road_condition || '-'],
    ['Insurance', r.insurance_type || '-'],
    ['Vehicles', vehiclesHtml],
    ['Photos', photosHtml],
    ['Details', r.event_details || '-']
  ].map(([l, v]) => `<div class="overview-row"><span class="label">${l}</span><span class="value" style="${(l === 'Photos' || l === 'Vehicles' || l === 'Details') ? 'flex-basis: 100%; margin-top: 4px;' : ''}">${v}</span></div>`).join('');
  
  // AI Block:
  const aiHtml = `
    <div style="background:rgba(194, 0, 0, 0.05); border:1px solid rgba(194, 0, 0, 0.2); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px; margin-bottom: 16px;">
      <div style="flex-shrink:0; font-size:24px; color:var(--red);"><i class="bi bi-robot"></i></div>
      <div style="flex-grow:1;">
        <div style="font-size:12px; font-weight:bold; color:var(--red); text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">AI Incident Summary</div>
        <div id="aiSummaryText" style="font-size:13px; color:var(--muted); line-height: 1.4;">Tap the button to generate a quick summary.</div>
      </div>
      <div>
        <button id="btnAiSummarize" class="btn-primary-vr" onclick="generateAISummary(${r.id})" style="padding: 6px 12px; font-size: 12px; white-space:nowrap; border-radius: 8px; text-decoration:none; display:inline-block; border:none;">
          <i class="bi bi-magic"></i> Summarize
        </button>
      </div>
    </div>
  `;

  // Points-granted block for approved Good Citizen reports
  const gcGrantedHtml = (r.flow_type === 'good_citizen' && r.status === 'closed') ? `
    <div style="margin-bottom:16px; padding:16px; border-radius:16px;
                background:linear-gradient(135deg,rgba(251,191,36,0.15),rgba(74,222,128,0.12));
                border:2px solid rgba(251,191,36,0.5);
                display:flex; align-items:center; gap:14px;">
      <div style="font-size:36px; line-height:1;">⭐</div>
      <div>
        <div style="font-size:15px; font-weight:800; color:#92400e; margin-bottom:2px;">+50 Points Granted!</div>
        <div style="font-size:12px; color:#a16207; line-height:1.4;">This Good Citizen report was approved by the admin.<br>50 points have been added to your account.</div>
      </div>
    </div>` : '';

  // CHANGE THE INNER HTML ASSIGNMENT TO INCLUDE aiHtml:
  document.getElementById('modalBody').innerHTML = `<div style="padding:4px 0;">${gcGrantedHtml}${aiHtml}${rows}</div>`;
  modal.show();
}
// Initialize
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
  
  // Hide the detail modal temporarily so it doesn't overlap weirdly
  const detailModalEl = document.getElementById('detailModal');
  if (detailModalEl && detailModalEl.classList.contains('show')) {
      bootstrap.Modal.getInstance(detailModalEl).hide();
      
      // When image viewer closes, reopen the details modal
      document.getElementById('imageViewerModal').addEventListener('hidden.bs.modal', function () {
          bootstrap.Modal.getInstance(detailModalEl).show();
      }, { once: true });
  }
  
  viewerModal.show();
}
</script>

</body>
</html>