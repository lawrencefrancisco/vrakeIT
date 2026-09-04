<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

// Stats
$totalUsers     = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$totalEnforcers = $db->query("SELECT COUNT(*) FROM users WHERE role='enforcer'")->fetchColumn();
$totalReports   = $db->query("SELECT COUNT(*) FROM reports")->fetchColumn();
$pendingReports = $db->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn();
$totalMerchants = $db->query("SELECT COUNT(*) FROM merchants")->fetchColumn();
$pendingMerchants=$db->query("SELECT COUNT(*) FROM merchants WHERE status='pending'")->fetchColumn();
$totalPoints    = $db->query("SELECT COALESCE(SUM(points),0) FROM users WHERE role='user'")->fetchColumn();
$pendingVerifs  = $db->query("SELECT COUNT(*) FROM id_verifications WHERE status='pending'")->fetchColumn();
$gcPending      = $db->query("SELECT COUNT(*) FROM reports WHERE flow_type='good_citizen' AND status='pending'")->fetchColumn();
$vouchersIssued = $db->query("SELECT COUNT(*) FROM vouchers")->fetchColumn();

// Recent reports
$recentReports = $db->query("SELECT r.*, u.first_name, u.last_name FROM reports r JOIN users u ON r.user_id=u.id ORDER BY r.created_at DESC LIMIT 8")->fetchAll();

// Monthly report trend (last 6 months)
$trend = $db->query("SELECT DATE_FORMAT(created_at,'%b %Y') as month, COUNT(*) as cnt FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY created_at ASC")->fetchAll();

// Reports by flow_type
$typeStats = $db->query("SELECT flow_type, COUNT(*) as cnt FROM reports GROUP BY flow_type")->fetchAll();
// User roles
$userRoles = $db->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role")->fetchAll();

adminHead('Dashboard', 'dashboard');
?>
<body>
<?php adminNav('dashboard', $admin); ?>
<div class="main">
<?php adminTopbar('Dashboard Overview'); ?>
<div class="page-body">

<!-- Stats Grid -->
<div class="row g-3 mb-4">
  <?php
  $stats = [
    ['icon'=>'👤','bg'=>'rgba(0,126,210,.2)','num'=>number_format($totalUsers),'lbl'=>'Standard Users','delta'=>'','color'=>'#60b4ff'],
    ['icon'=>'👮','bg'=>'rgba(251,191,36,.15)','num'=>number_format($totalEnforcers),'lbl'=>'Law Enforcers','delta'=>'','color'=>'#fbbf24'],
    ['icon'=>'📋','bg'=>'rgba(74,222,128,.15)','num'=>number_format($totalReports),'lbl'=>'Total Reports','delta'=>$pendingReports.' pending','color'=>'#4ade80'],
    ['icon'=>'🏪','bg'=>'rgba(167,139,250,.15)','num'=>number_format($totalMerchants),'lbl'=>'Merchants','delta'=>$pendingMerchants.' awaiting approval','color'=>'#a78bfa'],
    ['icon'=>'⭐','bg'=>'rgba(251,191,36,.15)','num'=>number_format($totalPoints),'lbl'=>'Points in Circulation','delta'=>'','color'=>'#fbbf24'],
    ['icon'=>'🛡️','bg'=>'rgba(248,113,113,.15)','num'=>number_format($pendingVerifs),'lbl'=>'Pending Verifications','delta'=>'Needs review','color'=>'#f87171'],
    ['icon'=>'🌟','bg'=>'rgba(0,126,210,.2)','num'=>number_format($gcPending),'lbl'=>'Good Citizen Pending','delta'=>'Approve to grant 50pts','color'=>'#60b4ff'],
    ['icon'=>'🎫','bg'=>'rgba(74,222,128,.15)','num'=>number_format($vouchersIssued),'lbl'=>'Vouchers Issued','delta'=>'','color'=>'#4ade80'],
  ];
  foreach($stats as $s): ?>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:<?= $s['bg'] ?>;"><span><?= $s['icon'] ?></span></div>
      <div class="stat-num" style="color:<?= $s['color'] ?>"><?= $s['num'] ?></div>
      <div class="stat-lbl"><?= $s['lbl'] ?></div>
      <?php if($s['delta']): ?><div class="stat-delta" style="color:rgba(255,255,255,0.35);"><?= htmlspecialchars($s['delta']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="section-card">
      <div class="section-header"><span class="section-title-text"><i class="bi bi-lightning-fill me-2" style="color:#fbbf24;"></i>Quick Actions</span></div>
      <div style="padding:18px;display:flex;flex-wrap:wrap;gap:10px;">
        <a href="incidents.php?filter=good_citizen" class="btn-admin btn-approve"><i class="bi bi-star-fill"></i> Approve GC Reports (+50pts)</a>
        <a href="verifications.php" class="btn-admin btn-review"><i class="bi bi-shield-check"></i> Review ID Verifications</a>
        <a href="merchants.php?filter=pending" class="btn-admin btn-review"><i class="bi bi-shop"></i> Approve Merchants</a>
        <a href="users.php" class="btn-admin btn-primary-admin"><i class="bi bi-people"></i> Manage Users</a>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- NEW: LIVE INCIDENT MAP SECTION START       -->
<!-- ========================================== -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  /* Custom Marker Colors */
  .marker-pending { filter: hue-rotate(150deg); }   /* Red */
  .marker-reviewing { filter: hue-rotate(20deg); }  /* Orange */
  .marker-closed { filter: hue-rotate(250deg); }    /* Green */
</style>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="section-card">
      <div class="section-header">
        <span class="section-title-text"><i class="bi bi-map-fill me-2" style="color:#60b4ff;"></i>Live Incident Map (Valenzuela)</span>
      </div>
      <div style="position: relative; height: 450px; border-radius: 0 0 12px 12px; overflow: hidden;">
        
        <!-- Floating Legend -->
        <div style="position: absolute; top: 15px; right: 15px; z-index: 1000; background: rgba(30, 30, 45, 0.95); padding: 10px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); color: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
          <div style="font-size: 12px; font-weight: 600; margin-bottom: 5px; color: #a78bfa;">Status Legend</div>
          <div style="font-size: 11px;"><span style="color: #f87171; font-size: 16px; vertical-align: middle;">●</span> Pending</div>
          <div style="font-size: 11px;"><span style="color: #fbbf24; font-size: 16px; vertical-align: middle;">●</span> Reviewing</div>
          <div style="font-size: 11px;"><span style="color: #4ade80; font-size: 16px; vertical-align: middle;">●</span> Closed</div>
        </div>

        <!-- The Map -->
        <div id="adminMap" style="height: 100%; width: 100%;"></div>

      </div>
    </div>
  </div>
</div>
<!-- ========================================== -->
<!-- NEW: LIVE INCIDENT MAP SECTION END         -->
<!-- ========================================== -->

<!-- Analytics Charts Grid -->
<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="section-card h-100">
      <div class="section-header">
        <span class="section-title-text"><i class="bi bi-graph-up me-2"></i>Incident Trend (Last 6 Months)</span>
      </div>
      <div style="padding:18px;">
        <canvas id="trendChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="section-card h-100">
      <div class="section-header">
        <span class="section-title-text"><i class="bi bi-pie-chart-fill me-2"></i>Reports by Type</span>
      </div>
      <div style="padding:18px; display:flex; justify-content:center;">
        <canvas id="typeChart" height="200" style="max-height: 250px;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Recent Reports -->
<div class="section-card">
  <div class="section-header">
    <span class="section-title-text"><i class="bi bi-file-text me-2"></i>Recent Reports</span>
    <a href="incidents.php" class="btn-admin btn-review">View All</a>
  </div>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr>
      <th>Ref #</th><th>User</th><th>Type</th><th>Status</th><th>Date</th><th>Action</th>
    </tr></thead>
    <tbody>
    <?php foreach($recentReports as $r): ?>
    <tr>
      <td><span style="font-family:monospace;color:#60b4ff;"><?= htmlspecialchars($r['reference_number']) ?></span></td>
      <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
      <td><?php
        $labels = ['standard'=>'Standard','good_citizen'=>'Good Citizen'];
        $colors = ['standard'=>'#f87171','good_citizen'=>'#4ade80'];
        $ft = $r['flow_type'];
        echo "<span style='color:{$colors[$ft]};font-weight:600;font-size:12px;'>{$labels[$ft]}</span>";
      ?></td>
      <td><span class="badge-status bs-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
      <td style="color:rgba(255,255,255,0.45);font-size:12px;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
      <td>
        <?php if($r['flow_type']==='good_citizen' && in_array($r['status'], ['pending','reviewing'])): ?>
        <button class="btn-admin btn-approve" onclick="approveGC(<?= $r['id'] ?>, <?= $r['user_id'] ?>)"
                title="Grant +50pts &amp; set Verified">
          <i class="bi bi-star-fill"></i> +50pts
        </button>
        <?php else: ?>
        <a href="incidents.php" class="btn-admin btn-review"><i class="bi bi-eye"></i> View</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div><!-- /page-body -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>

// ==========================================
// LIVE MAP LOGIC
// ==========================================
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
const map = L.map('adminMap', {
  center: VALENZUELA_CENTER,
  zoom: 13,
  minZoom: 13,
  maxZoom: 19,
  maxBounds: VALENZUELA_BOUNDS,
  maxBoundsViscosity: 1.0
});
map.setMaxBounds(VALENZUELA_BOUNDS);

// ── BASE TILE LAYER (full color) ───────────────────────────
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19,
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

// ── BORDER HIGHLIGHT ────────────────────────────────────────
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



let markerGroup = L.layerGroup().addTo(map);

function getCustomIcon(status) {
  let colorClass = 'marker-closed';
  if (status === 'pending') colorClass = 'marker-pending';
  if (status === 'reviewing') colorClass = 'marker-reviewing';

  return L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
    className: colorClass 
  });
}

async function loadAdminMap() {
  try {
    const fd = new FormData();
    fd.append('action', 'get_map_reports');

    const response = await fetch('api/admin_action.php', { // Ensure this matches your file name
      method: 'POST',
      body: fd
    });
    
    const data = await response.json();

    if (data.success && data.reports) {
      markerGroup.clearLayers(); 

      data.reports.forEach(report => {
        if (report.latitude && report.longitude) {
          const marker = L.marker([report.latitude, report.longitude], {
            icon: getCustomIcon(report.status)
          }).addTo(markerGroup);

          const isInjuredText = parseInt(report.is_injured) === 1 ? '<span style="color:#f87171; font-weight:bold;">Yes</span>' : 'No';

          const popupHtml = `
            <div style="font-family: 'Inter', sans-serif; min-width: 180px; color: #333;">
              <strong style="color: #007ED2; font-size: 14px;">Ref: ${report.reference_number}</strong><br>
              <span style="font-size: 12px;">Type: <strong>${report.flow_type.replace('_', ' ').toUpperCase()}</strong></span><br>
              <span style="font-size: 12px;">Status: <strong>${report.status.toUpperCase()}</strong></span><br>
              <span style="font-size: 12px;">Injuries: ${isInjuredText}</span><br>
              <hr style="margin: 8px 0; border-color: #ddd;">
              <span style="font-size: 11px; color: #666;">${report.location_address}</span><br>
              <a href="incidents.php?id=${report.id}" style="display:inline-block; margin-top:8px; padding: 4px 8px; background: #60b4ff; color: #fff; text-decoration: none; border-radius: 4px; font-size: 11px;">View Full Report</a>
            </div>
          `;
          marker.bindPopup(popupHtml);
        }
      });
    }
  } catch (error) {
    console.error("Failed to load map data:", error);
  }
}

// Initialize map data and set auto-refresh
loadAdminMap();
setInterval(loadAdminMap, 30000); // Refreshes every 30 seconds
// ==========================================

async function approveGC(reportId, userId) {
  if (!confirm('Approve this Good Citizen report?\n\nThis will:\n• Grant +50 points to the reporter\n• Set status to ✅ Verified')) return;
  const fd = new FormData();
  fd.append('report_id', reportId);
  fd.append('user_id', userId);
  fd.append('action', 'approve_gc');
  const res = await fetch('api/admin_action.php', {method:'POST', body:fd});
  const data = await res.json();
  if (data.success) { alert('✅ ' + data.message); location.reload(); }
  else alert('Error: ' + data.message);
}

// Chart Configurations
document.addEventListener('DOMContentLoaded', function() {
  Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
  Chart.defaults.font.family = "'Inter', sans-serif";
  
  // Prepare trend data
  const trendData = <?= json_encode($trend) ?>;
  const labels = trendData.map(d => d.month);
  const values = trendData.map(d => parseInt(d.cnt));

  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Reports Filed',
        data: values,
        borderColor: '#60b4ff',
        backgroundColor: 'rgba(96, 180, 255, 0.2)',
        borderWidth: 3,
        tension: 0.4,
        fill: true,
        pointBackgroundColor: '#1e1e2d',
        pointBorderColor: '#60b4ff',
        pointBorderWidth: 2,
        pointRadius: 4
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
        x: { grid: { display: false } }
      }
    }
  });

  // Prepare type data
  const typeStats = <?= json_encode($typeStats) ?>;
  const typeMap = { 'contract': 'Contract', 'standard': 'Standard', 'good_citizen': 'Good Citizen' };
  const tLabels = typeStats.map(d => typeMap[d.flow_type] || d.flow_type);
  const tVals = typeStats.map(d => parseInt(d.cnt));
  const tColors = typeStats.map(d => {
    if(d.flow_type === 'standard') return '#f87171';
    if(d.flow_type === 'contract') return '#60b4ff';
    if(d.flow_type === 'good_citizen') return '#4ade80';
    return '#a78bfa';
  });

  new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
      labels: tLabels,
      datasets: [{
        data: tVals,
        backgroundColor: tColors,
        borderWidth: 0,
        hoverOffset: 4
      }]
    },
    options: {
      responsive: true,
      cutout: '70%',
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
      }
    }
  });
});
</script>
</body></html>
