<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$where = "WHERE 1=1";
$params = [];
if ($filter === 'good_citizen') $where .= " AND r.flow_type='good_citizen'";
if ($filter === 'pending')      $where .= " AND r.status='pending'";
if ($filter === 'reviewing')    $where .= " AND r.status='reviewing'";
if ($filter === 'verified')     $where .= " AND r.status='verified'";
if ($filter === 'rejected')     $where .= " AND r.status='rejected'";
if ($filter === 'injured')      $where .= " AND r.is_injured=1";
if ($search) { $where .= " AND (r.reference_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)"; $s="%$search%"; $params=[$s,$s,$s]; }

$stmt = $db->prepare("SELECT r.*, u.first_name, u.last_name, u.email, u.account_verified FROM reports r JOIN users u ON r.user_id=u.id $where ORDER BY r.created_at DESC LIMIT 100");
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Fetch enforcers for assignment dropdown
$enforcers = $db->query("SELECT id, first_name, last_name FROM users WHERE role='enforcer' ORDER BY first_name ASC")->fetchAll();

adminHead('Incident Monitoring');
?>
<body>
<?php adminNav('incidents', $admin); ?>
<div class="main">
<?php adminTopbar('Incident Monitoring'); ?>
<div class="page-body">

<div class="section-card mb-4">
  <div class="section-header" style="flex-wrap:wrap;gap:10px;">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php foreach(['all'=>'All','good_citizen'=>'Good Citizen','pending'=>'Pending','reviewing'=>'Reviewing','verified'=>'Verified','rejected'=>'Rejected','injured'=>'With Injury'] as $k=>$v): ?>
      <a href="?filter=<?= $k ?>" class="btn-admin <?= $filter===$k?'btn-primary-admin':'btn-review' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </div>
    <form method="GET" style="display:flex;gap:8px;">
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <input class="search-input" name="search" placeholder="Ref# or name…" value="<?= htmlspecialchars($search) ?>">
      <button type="submit" class="btn-admin btn-primary-admin"><i class="bi bi-search"></i></button>
    </form>
  </div>
</div>

<div class="section-card">
  <div class="section-header">
    <span class="section-title-text"><i class="bi bi-exclamation-triangle me-2"></i>Reports (<?= count($reports) ?>)</span>
  </div>
  <div style="overflow-x:auto;">
  <table class="data-table">
    <thead><tr><th>Ref #</th><th>Reporter</th><th>Type</th><th>Injured</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($reports as $r):
      $typeMap  = ['standard' =>'Standard','contract'=>'Contract','good_citizen'=>'Good Citizen'];
      $typeClr  = ['standard'=>'#f87171','contract'=>'#60b4ff','good_citizen'=>'#4ade80'];
      $ft = $r['flow_type'];
    ?>
    <tr>
      <td><span style="font-family:monospace;color:#60b4ff;font-size:12px;"><?= htmlspecialchars($r['reference_number']) ?></span></td>
      <td>
        <div style="font-weight:600;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
          <?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?>
          <?php if ($r['account_verified']): ?>
          <span class="verified-badge" title="Identity Verified">
            <i class="bi bi-shield-check"></i> Verified
          </span>
          <?php endif; ?>
        </div>
        <div style="font-size:11px;color:rgba(255,255,255,0.35);"><?= htmlspecialchars($r['email']) ?></div>
      </td>
      <td><span style="color:<?= $typeClr[$ft] ?>;font-weight:600;font-size:12px;"><?= $typeMap[$ft] ?></span></td>
      <td><?= $r['is_injured'] ? '<span style="color:#f87171;font-weight:600;">Yes</span>' : '<span style="color:rgba(255,255,255,0.3);">No</span>' ?></td>
      <td>
        <select class="form-dark" style="padding:5px 10px;width:130px;font-size:12px;" onchange="updateStatus(<?= $r['id'] ?>, this.value)">
          <?php foreach(['pending','reviewing','verified','rejected','closed'] as $s): ?>
          <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="font-size:12px;color:rgba(255,255,255,0.4);"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
      <td style="display:flex;gap:6px;">
        <button class="btn-admin btn-review" onclick="viewReport(<?= $r['id'] ?>)" title="View Details"><i class="bi bi-eye"></i></button>
        <?php if($ft==='good_citizen' && in_array($r['status'], ['pending','reviewing'])): ?>
        <button class="btn-admin btn-approve" onclick="approveGC(<?= $r['id'] ?>, <?= $r['user_id'] ?>)" title="Approve Good Citizen — +<?= GOOD_CITIZEN_POINTS ?>pts, set Verified">
          <i class="bi bi-star-fill"></i> +<?= GOOD_CITIZEN_POINTS ?>pts
        </button>
        <?php endif; ?>
        <button class="btn-admin btn-reject" onclick="denyReport(<?= $r['id'] ?>)" title="Close Report"><i class="bi bi-x"></i></button>
      </td> 
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div></div>

<!-- Modal for Viewing Report -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:#1e1e2d; color:#fff; border: 1px solid rgba(255,255,255,0.1); border-radius:12px;">
      <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
        <h5 class="modal-title"><i class="bi bi-card-text me-2"></i>Report Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reportModalBody" style="min-height: 200px;">
        <div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div></div>
      </div>
      <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.05);">
        <button type="button" class="btn-admin btn-review" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const enforcersList = <?= json_encode($enforcers) ?>;

function getAdminStatusBadge(s) {
  const cfg = {
    pending:   { cls: 'bs-pending',   icon: '⚠️', label: 'Pending'   },
    reviewing: { cls: 'bs-reviewing', icon: '🔍', label: 'Reviewing' },
    verified:  { cls: 'bs-verified',  icon: '✅', label: 'Verified'  },
    rejected:  { cls: 'bs-rejected',  icon: '❌', label: 'Rejected'  },
    closed:    { cls: 'bs-closed',    icon: '🔒', label: 'Closed'    },
  };
  const c = cfg[s] || { cls: 'bs-pending', icon: '⚠️', label: s || 'N/A' };
  return `<span class="badge-status ${c.cls}" style="font-size:12px;">${c.icon} ${c.label}</span>`;
}

async function adminPost(data) {
  const fd = new FormData();
  for(const k in data) fd.append(k, data[k]);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  return await res.json();
}
async function approveGC(rid, uid) {
  if (!confirm('Approve this Good Citizen report?\n\nThis will:\n• Grant +' + <?= GOOD_CITIZEN_POINTS ?> + ' points to the reporter\n• Set status to ✅ Verified')) return;
  const d = await adminPost({action:'approve_gc', report_id:rid, user_id:uid});
  if (d.success) { alert('✅ ' + d.message); location.reload(); } else alert(d.message);
}
async function updateStatus(rid, status) {
  const d = await adminPost({action:'update_report_status', report_id:rid, status});
  if (d.success) {
    // Reload to reflect any auto-granted points (e.g. GC → verified)
    location.reload();
  } else {
    alert(d.message);
  }
}
async function denyReport(rid) {
  if(!confirm('Mark report as closed?'))return;
  const d = await adminPost({action:'update_report_status',report_id:rid,status:'closed'});
  if(d.success) location.reload(); else alert(d.message);
}

async function saveAdminManagement(rid) {
  const status = document.getElementById('adminManageStatus').value;
  const enforcerId = document.getElementById('adminManageEnforcer').value;
  const notes = document.getElementById('adminManageNotes').value;
  
  const btn = document.getElementById('btnSaveManage');
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
  btn.disabled = true;
  
  const d = await adminPost({
    action: 'manage_report',
    report_id: rid,
    status: status,
    assigned_enforcer_id: enforcerId,
    admin_notes: notes
  });
  
  if(d.success) {
    alert('✅ ' + d.message);
    location.reload();
  } else {
    alert('Error: ' + d.message);
    btn.innerHTML = '<i class="bi bi-save"></i> Save Changes';
    btn.disabled = false;
  }
}

async function generateAISummary(rid) {
  const btn = document.getElementById('btnAiSummarize');
  const textDiv = document.getElementById('aiSummaryText');
  
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:rgba(255,255,255,0.5);">Analyzing incident details...</span>';
  
  const d = await adminPost({ action: 'summarize_incident', report_id: rid });
  
  btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
  btn.disabled = false;
  
  if(d.success) {
    textDiv.innerHTML = `<span style="color:#fff;">${d.summary}</span>`;
  } else {
    textDiv.innerHTML = `<span style="color:#f87171;">Error: ${d.message}</span>`;
  }
}

let reportModalInstance = null;
async function viewReport(rid) {
  if (!reportModalInstance) {
    reportModalInstance = new bootstrap.Modal(document.getElementById('reportModal'));
  }
  
  const modalBody = document.getElementById('reportModalBody');
  modalBody.innerHTML = '<div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div></div>';
  reportModalInstance.show();

  const d = await adminPost({action:'get_report', report_id:rid});
  
  // 1. SAFELY GRAB THE REPORT: Handles both common jsonResponse formats
  const r = d.report || (d.data && d.data.report);

  if (!d.success || !r) {
    modalBody.innerHTML = `<div class="alert alert-danger">${d.message || 'Failed to load report details.'}</div>`;
    return;
  }

  // 2. FIX IMAGE PATHS: Pointing to the correct assets folder
  let mediaHtml = '<span style="color:rgba(255,255,255,0.4);">No media provided</span>';
  if (r.media && r.media.length > 0) {
    mediaHtml = '<div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">';
    r.media.forEach(m => {
      // Ensure the path uses the correct assets folder
      const imgPath = m.file_path.includes('assets/') ? `../${m.file_path}` : `../assets/uploads/${m.file_path}`;
      if (m.file_type === 'image') {
        mediaHtml += `<a href="${imgPath}" target="_blank"><img src="${imgPath}" style="height:150px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); object-fit:cover;"></a>`;
      } else if (m.file_type === 'video') {
        mediaHtml += `<video src="${imgPath}" controls style="height:150px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);"></video>`;
      }
    });
    mediaHtml += '</div>';
  }

  let vehiclesHtml = '<span style="color:#cbd5e1;">N/A</span>';
  if (r.vehicles && r.vehicles.length > 0) {
    vehiclesHtml = '<ul style="list-style:none; padding:0; margin:0;">';
    r.vehicles.forEach(v => {
      vehiclesHtml += `<li style="background:rgba(255,255,255,0.05); padding:8px 12px; border-radius:6px; margin-bottom:5px; font-size:13px;">
        <span style="font-weight:bold;">${v.vehicle_type}</span> 
        <span style="margin:0 8px; color:rgba(255,255,255,0.3);">|</span> 
        <span style="font-family:monospace; color:#60b4ff;">${v.plate_number || 'No Plate'}</span>
        ${v.vehicle_count > 1 ? ` <span style="margin:0 8px; color:rgba(255,255,255,0.3);">|</span> Qty: ${v.vehicle_count}` : ''}
      </li>`;
    });
    vehiclesHtml += '</ul>';
  }
  
  const descText = r.event_details || r.damage_category || '';
  const descHtml = descText ? descText.replace(/\n/g, '<br>') : '<span class="text-muted">No details provided</span>';

  // 3. PARSE EMERGENCY SERVICES: Safely read the JSON string stored in the database
  let emergencySvcs = '<span style="color:#cbd5e1;">N/A</span>';
  if (r.emergency_services && r.emergency_services !== 'null') {
     try {
         let parsed = JSON.parse(r.emergency_services);
         if (Array.isArray(parsed)) emergencySvcs = parsed.join(', ').toUpperCase();
     } catch(e) { 
         emergencySvcs = r.emergency_services; 
     }
  }
  
  // 4. ADD ALL MISSING HTML FIELDS
  modalBody.innerHTML = `
    <div class="row g-4">
      <div class="col-12">
        <div id="aiSummaryContainer" style="background:rgba(96, 180, 255, 0.1); border:1px solid rgba(96, 180, 255, 0.3); border-radius:8px; padding:15px; display:flex; align-items:center; gap:15px;">
          <div style="flex-shrink:0; font-size:24px; color:#60b4ff;"><i class="bi bi-robot"></i></div>
          <div style="flex-grow:1;">
            <div style="font-size:12px; font-weight:bold; color:#60b4ff; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">AI Incident Summary</div>
            <div id="aiSummaryText" style="font-size:14px; color:rgba(255,255,255,0.8);">Click the button to generate a 1-sentence summary of this report.</div>
          </div>
          <div>
            <button id="btnAiSummarize" class="btn-admin btn-primary-admin" onclick="generateAISummary(${r.id})" style="white-space:nowrap;">
              <i class="bi bi-magic"></i> Summarize
            </button>
          </div>
        </div>
      </div>

      <div class="col-md-6">
       <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Reference Number</p>
        <h5 style="color:#60b4ff; font-family:monospace; margin:0;">${r.reference_number}</h5>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Reported On</p>
        <h6 style="margin:0;">${r.created_at_fmt}</h6>
      </div>

      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Reporter</p>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
          <div style="position:relative; flex-shrink:0;">
            <img src="${r.avatar_url}" alt="${r.reporter_name}"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                 style="width:48px; height:48px; border-radius:50%; object-fit:cover;
                        border:2px solid rgba(96,180,255,0.4); background:#1e1e2d;">
            <div style="display:none; width:48px; height:48px; border-radius:50%;
                        background:rgba(96,180,255,0.15); border:2px solid rgba(96,180,255,0.4);
                        align-items:center; justify-content:center; font-weight:700;
                        font-size:16px; color:#60b4ff;">
              ${(r.reporter_name||'?').split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase()}
            </div>
          </div>
          <div>
            <h6 style="margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
              ${r.reporter_name}
              ${r.assigned_enforcer_id !== null ? '<span style="font-size:10px;color:rgba(255,255,255,0.4);font-weight:400;">— ENFORCER</span>' : ''}
            </h6>
            ${r.account_verified == 1 ? `<span class="verified-badge-lg" title="This user has a verified identity"><i class="bi bi-shield-check"></i> Identity Verified</span>` : ''}
            <div style="font-size:12px; color:rgba(255,255,255,0.5); margin-top:2px;">${r.email} ${r.phone ? '• ' + r.phone : ''}</div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Status / Flow Type</p>
        <h6 style="margin:0;">
          ${getAdminStatusBadge(r.status)}
          <span style="display:inline-block; padding:3px 8px; border-radius:4px; background:rgba(96, 180, 255, 0.1); color:#60b4ff; font-size:11px; margin-left:5px;">${(r.flow_type || '').replace('_', ' ').toUpperCase()}</span>
        </h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.1); margin:0;"></div>
      
      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Incident Date & Time</p>
        <h6 style="margin:0;">${r.incident_date ? r.incident_date + ' ' + (r.incident_time||'') : '<span class="text-muted">Not provided</span>'}</h6>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Location</p>
        <h6 style="margin:0;">${r.location_address || '<span class="text-muted">Not provided</span>'}</h6>
      </div>
      
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Weather / Road</p>
        <h6 style="margin:0;">${r.weather_condition || 'N/A'} <span style="color:rgba(255, 255, 255, 0.73)">|</span> ${r.road_condition || 'N/A'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Injured Persons?</p>
        <h6 style="margin:0;">${r.is_injured == 1 ? '<span style="color:#f87171;"><i class="bi bi-exclamation-circle-fill me-1"></i> Yes</span>' : '<span style="color:rgba(255, 255, 255, 0.73);">No</span>'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Other Parties</p>
        <h6 style="margin:0;">${r.has_other_parties == 1 ? '<span style="color:#f87171;">Yes</span>' : '<span style="color:rgba(255, 255, 255, 0.73);">No</span>'}</h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.1); margin:0;"></div>

      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Law Enforcer</p>
        <h6 style="margin:0;">${r.enforcer_type ? r.enforcer_type.toUpperCase() : '<span style="color:#cbd5e1;">N/A</span>'}</h6>
       
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Emergency Services</p>
        <h6 style="margin:0;">${emergencySvcs}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Insurance Type</p>
        <h6 style="margin:0;">${r.insurance_type ? r.insurance_type.toUpperCase() : '<span style="color:#cbd5e1;">N/A</span>'}</h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.1); margin:0;"></div>

      <div class="col-12">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Vehicles Involved</p>
        ${vehiclesHtml}
      </div>
      
      <div class="col-12">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Event Details</p>
        <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:8px; border:1px solid rgba(255, 255, 255, 0.73); font-size:14px; line-height:1.5;">
          ${descHtml}
        </div>
      </div>
      <div class="col-12">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Photo/Video Evidence</p>
        <div class="text-center" style="background:rgba(0,0,0,0.2); padding:15px; border-radius:8px; border:1px solid rgba(255, 255, 255, 0.73);">
          ${mediaHtml}
        </div>
      </div>

      
  `;
}
</script>
</body></html>