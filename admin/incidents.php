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
if ($filter === 'citizen')      $where .= " AND r.reporter_role='citizen'";
if ($filter === 'driver')       $where .= " AND (r.reporter_role='driver' OR r.reporter_role IS NULL)";
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
      <?php foreach(['all'=>'All','citizen'=>'Citizen/Witness','driver'=>'Driver','good_citizen'=>'Good Citizen','pending'=>'Pending','reviewing'=>'Reviewing','verified'=>'Verified','rejected'=>'Rejected','injured'=>'With Injury'] as $k=>$v): ?>
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
    <thead><tr><th>Ref #</th><th>Reporter</th><th>Role</th><th>Type</th><th>Injured</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
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
        <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['email']) ?></div>
      </td>
      <td>
        <?php
          $rRole = $r['reporter_role'] ?? 'driver';
          $roleLabel = $rRole === 'citizen' ? 'Citizen/Witness' : 'Driver';
          $roleColor = $rRole === 'citizen' ? '#a78bfa' : '#60b4ff';
          $roleIcon  = $rRole === 'citizen' ? 'bi-eye-fill' : 'bi-car-front-fill';
        ?>
        <span style="color:<?= $roleColor ?>;font-weight:600;font-size:12px;">
          <i class="bi <?= $roleIcon ?>"></i> <?= $roleLabel ?>
        </span>
      </td>
      <td><span style="color:<?= $typeClr[$ft] ?>;font-weight:600;font-size:12px;"><?= $typeMap[$ft] ?></span></td>
      <td><?= $r['is_injured'] ? '<span style="color:#f87171;font-weight:600;">Yes</span>' : '<span style="color:var(--muted);">No</span>' ?></td>
      <td>
        <select class="form-dark" style="padding:5px 10px;width:130px;font-size:12px;" onchange="updateStatus(<?= $r['id'] ?>, this.value)">
          <?php foreach(['pending','reviewing','verified','rejected','closed'] as $s): ?>
          <option value="<?= $s ?>" <?= $r['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
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
    <div class="modal-content" style="background:#ffffff; color:#333333; border: 1px solid #e2e8f0; border-radius:12px; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
      <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; background: #f8fafc; border-radius: 12px 12px 0 0;">
        <h5 class="modal-title" style="color:#0f172a; font-weight: 600;"><i class="bi bi-card-text me-2"></i>Report Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="reportModalBody" style="min-height: 200px;">
        <div class="text-center mt-5"><div class="spinner-border text-primary" role="status"></div></div>
      </div>
      <div class="modal-footer" style="border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 12px 12px;">
        <button type="button" class="btn-admin btn-review" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<!-- Action Confirm Modal -->
<div class="modal fade" id="actionConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content" style="background: linear-gradient(145deg, #0f172a, #1e293b); color:#f8fafc; border: 1px solid rgba(255,255,255,0.08); border-radius:24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
      <div class="modal-body" style="padding: 32px 24px 24px; text-align: center;">
        <div id="confirmModalIcon" style="font-size:48px; color:#38bdf8; margin-bottom:16px; line-height:1;"><i class="bi bi-question-circle"></i></div>
        <h5 id="confirmModalTitle" style="font-weight:700; font-size:18px; margin-bottom:12px; color:#f8fafc;">Confirm Action</h5>
        <div id="confirmModalText" style="font-size:14px; color:#94a3b8; line-height:1.6; margin-bottom:24px;">Are you sure you want to proceed?</div>
        
        <div style="display:flex; gap:12px; justify-content:center;">
          <button type="button" class="btn-admin btn-review" data-bs-dismiss="modal" style="border-radius:12px; padding:10px 24px; font-weight:600; flex:1;">Cancel</button>
          <button type="button" class="btn-admin btn-primary-admin" id="confirmModalBtn" style="border-radius:12px; padding:10px 24px; font-weight:600; flex:1; border:none; box-shadow:0 4px 15px rgba(56,189,248,0.2);">Confirm</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Alert Modal -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content" style="background: linear-gradient(145deg, #0f172a, #1e293b); color:#f8fafc; border: 1px solid rgba(255,255,255,0.08); border-radius:24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
      <div class="modal-body" style="padding: 32px 24px 24px; text-align: center;">
        <div id="alertModalIcon" style="font-size:48px; color:#10b981; margin-bottom:16px; line-height:1;"><i class="bi bi-check-circle"></i></div>
        <h5 id="alertModalTitle" style="font-weight:700; font-size:18px; margin-bottom:12px; color:#f8fafc;">Success</h5>
        <div id="alertModalText" style="font-size:14px; color:#94a3b8; line-height:1.6; margin-bottom:24px;">Action completed successfully.</div>
        
        <button type="button" class="btn-admin btn-primary-admin" data-bs-dismiss="modal" id="alertModalBtn" style="border-radius:12px; padding:10px 32px; font-weight:600; border:none; box-shadow:0 4px 15px rgba(16,185,129,0.2);">OK</button>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

function showCustomConfirm(title, text, iconHtml, confirmText, confirmClass, onConfirm) {
  document.getElementById('confirmModalTitle').innerHTML = title;
  document.getElementById('confirmModalText').innerHTML = text;
  document.getElementById('confirmModalIcon').innerHTML = iconHtml;
  
  const btn = document.getElementById('confirmModalBtn');
  btn.innerHTML = confirmText;
  btn.className = 'btn-admin ' + confirmClass;
  
  // Set the shadow color based on the button class
  if (confirmClass.includes('btn-approve') || confirmClass.includes('btn-primary-admin')) {
    btn.style.boxShadow = '0 4px 15px rgba(56,189,248,0.2)';
  } else if (confirmClass.includes('btn-reject')) {
    btn.style.boxShadow = '0 4px 15px rgba(248,113,113,0.2)';
  } else {
    btn.style.boxShadow = 'none';
  }
  
  const modalEl = document.getElementById('actionConfirmModal');
  const modal = new bootstrap.Modal(modalEl);
  
  // Remove old event listeners by cloning
  const newBtn = btn.cloneNode(true);
  btn.parentNode.replaceChild(newBtn, btn);
  
  newBtn.addEventListener('click', () => {
    modal.hide();
    onConfirm();
  });
  
  modal.show();
}

function showCustomAlert(title, text, iconHtml, isSuccess, onClose) {
  document.getElementById('alertModalTitle').innerHTML = title;
  document.getElementById('alertModalText').innerHTML = text;
  document.getElementById('alertModalIcon').innerHTML = iconHtml;
  
  const btn = document.getElementById('alertModalBtn');
  if(isSuccess) {
    document.getElementById('alertModalIcon').style.color = '#10b981';
    btn.style.boxShadow = '0 4px 15px rgba(16,185,129,0.2)';
    btn.className = 'btn-admin btn-approve';
  } else {
    document.getElementById('alertModalIcon').style.color = '#f87171';
    btn.style.boxShadow = '0 4px 15px rgba(248,113,113,0.2)';
    btn.className = 'btn-admin btn-reject';
  }
  
  const modalEl = document.getElementById('alertModal');
  const modal = new bootstrap.Modal(modalEl);
  
  const newBtn = btn.cloneNode(true);
  btn.parentNode.replaceChild(newBtn, btn);
  
  // Also handle modal close event (clicking outside)
  modalEl.addEventListener('hidden.bs.modal', function handler() {
    modalEl.removeEventListener('hidden.bs.modal', handler);
    if(onClose) onClose();
  });
  
  newBtn.addEventListener('click', () => {
    modal.hide(); // this triggers hidden.bs.modal
  });
  
  modal.show();
}

const enforcersList = <?= json_encode($enforcers) ?>;

function getAdminStatusBadge(s) {
  const cfg = {
    pending:   { cls: 'bs-pending',   icon: '<i class="bi bi-exclamation-triangle"></i>', label: 'Pending'   },
    reviewing: { cls: 'bs-reviewing', icon: '🔍', label: 'Reviewing' },
    verified:  { cls: 'bs-verified',  icon: '<i class="bi bi-check-circle"></i>', label: 'Verified'  },
    rejected:  { cls: 'bs-rejected',  icon: '❌', label: 'Rejected'  },
    closed:    { cls: 'bs-closed',    icon: '🔒', label: 'Closed'    },
  };
  const c = cfg[s] || { cls: 'bs-pending', icon: '<i class="bi bi-exclamation-triangle"></i>', label: s || 'N/A' };
  return `<span class="badge-status ${c.cls}" style="font-size:12px;">${c.icon} ${c.label}</span>`;
}

async function adminPost(data) {
  const fd = new FormData();
  for(const k in data) fd.append(k, data[k]);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  return await res.json();
}

function approveGC(rid, uid) {
  const points = <?= GOOD_CITIZEN_POINTS ?>;
  const msg = `This will:<br>
  <div style="text-align:left; display:inline-block; margin-top:8px;">
    • Grant <span style="color:#fbbf24; font-weight:bold;">+${points} points</span> to the reporter<br>
    • Set status to <span style="color:#10b981;"><i class="bi bi-check-circle-fill"></i> Verified</span>
  </div>`;
  
  showCustomConfirm('Approve Report?', msg, '<i class="bi bi-star-fill" style="color:#fbbf24;"></i>', '<i class="bi bi-check2"></i> Approve', 'btn-approve', async () => {
    const d = await adminPost({action:'approve_gc', report_id:rid, user_id:uid});
    if (d.success) {
      showCustomAlert('Approved!', d.message, '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
    } else {
      showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    }
  });
}

function updateStatus(rid, status) {
  showCustomConfirm('Update Status?', 'Are you sure you want to change the status?', '<i class="bi bi-arrow-repeat" style="color:#38bdf8;"></i>', 'Update Status', 'btn-primary-admin', async () => {
      const d = await adminPost({action:'update_report_status', report_id:rid, status});
      if (d.success) {
        showCustomAlert('Updated!', 'Status has been updated successfully.', '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
      } else {
        showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
      }
  });
}

function denyReport(rid) {
  showCustomConfirm('Close Report?', 'Are you sure you want to mark this report as closed?', '<i class="bi bi-x-circle-fill" style="color:#f87171;"></i>', '<i class="bi bi-x"></i> Close Report', 'btn-reject', async () => {
    const d = await adminPost({action:'update_report_status',report_id:rid,status:'closed'});
    if(d.success) {
        showCustomAlert('Closed', 'Report has been closed successfully.', '<i class="bi bi-check-circle-fill"></i>', true, () => location.reload());
    } else {
        showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    }
  });
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
    alert('<i class="bi bi-check-circle"></i> ' + d.message);
    location.reload();
  } else {
    showCustomAlert('Error', d.message, '<i class="bi bi-x-circle-fill"></i>', false);
    btn.innerHTML = '<i class="bi bi-save"></i> Save Changes';
    btn.disabled = false;
  }
}

async function generateAISummary(rid) {
  const btn = document.getElementById('btnAiSummarize');
  const textDiv = document.getElementById('aiSummaryText');
  
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:var(--muted);">Analyzing incident details...</span>';
  
  const d = await adminPost({ action: 'summarize_incident', report_id: rid });
  
  btn.innerHTML = '<i class="bi bi-magic"></i> Summarize';
  btn.disabled = false;
  
  if(d.success) {
    textDiv.innerHTML = `<span style="color:var(--text);">${d.summary}</span>`;
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
    modalBody.innerHTML = `
    <div class="row g-4">
      <div class="col-12">
        <div id="aiSummaryContainer" style="background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), rgba(14, 165, 233, 0.03)); border:1px solid rgba(56, 189, 248, 0.2); border-radius:16px; padding:20px; display:flex; align-items:center; gap:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
          <div style="flex-shrink:0; font-size:32px; color:#38bdf8; background:rgba(56,189,248,0.1); width:64px; height:64px; display:flex; align-items:center; justify-content:center; border-radius:16px; border:1px solid rgba(56,189,248,0.2);"><i class="bi bi-robot"></i></div>
          <div style="flex-grow:1;">
            <div style="font-size:12px; font-weight:700; color:#38bdf8; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:4px;">AI Incident Summary</div>
            <div id="aiSummaryText" style="font-size:14px; color:#94a3b8; line-height:1.5;">Click the button to generate an intelligent 1-sentence summary of this report.</div>
          </div>
          <div>
            <button id="btnAiSummarize" class="btn-admin btn-primary-admin" onclick="generateAISummary(${r.id})" style="white-space:nowrap; border-radius:12px; padding:10px 20px; font-weight:600; box-shadow:0 4px 15px rgba(56,189,248,0.2);">
              <i class="bi bi-magic"></i> Summarize
            </button>
          </div>
        </div>
      </div>

      <div class="col-md-6">
       <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Reference Number</p>
        <h5 style="color:#f8fafc; font-family:'Courier New', monospace; font-weight:700; margin:0; letter-spacing:1px;">${r.reference_number}</h5>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Reported On</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${r.created_at_fmt}</h6>
      </div>

      <div class="col-md-6">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Reporter</p>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:6px;">
          <div style="position:relative; flex-shrink:0;">
            <img src="${r.avatar_url}" alt="${r.reporter_name}"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';"
                 style="width:48px; height:48px; border-radius:12px; object-fit:cover;
                        border:1px solid rgba(255,255,255,0.1); background:#1e293b; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
            <div style="display:none; width:48px; height:48px; border-radius:12px;
                        background: linear-gradient(135deg, #1e293b, #0f172a); border:1px solid rgba(255,255,255,0.1);
                        align-items:center; justify-content:center; font-weight:700;
                        font-size:16px; color:#e2e8f0; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
              ${(r.reporter_name||'?').split(' ').map(n=>n[0]).join('').slice(0,2).toUpperCase()}
            </div>
          </div>
          <div>
            <h6 style="margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap; color:#f8fafc; font-weight:700;">
              ${r.reporter_name}
              ${r.assigned_enforcer_id !== null ? '<span style="font-size:10px;color:#94a3b8;font-weight:600;background:rgba(255,255,255,0.05);padding:2px 6px;border-radius:4px;">ENFORCER</span>' : ''}
            </h6>
            ${r.account_verified == 1 ? `<span class="verified-badge-lg" style="background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;" title="Identity Verified"><i class="bi bi-shield-check"></i> Verified</span>` : ''}
            <div style="font-size:12px; color:#94a3b8; margin-top:2px;">${r.email} ${r.phone ? '• ' + r.phone : ''}</div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Status / Flow Type</p>
        <h6 style="margin:0; display:flex; align-items:center; gap:8px;">
          ${getAdminStatusBadge(r.status)}
          <span style="display:inline-block; padding:4px 10px; border-radius:8px; background:rgba(96, 180, 255, 0.1); border:1px solid rgba(96, 180, 255, 0.2); color:#60b4ff; font-size:11px; font-weight:700;">${(r.flow_type || '').replace('_', ' ').toUpperCase()}</span>
        </h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.08); margin:8px 0;"></div>
      
      <div class="col-md-6">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Incident Date & Time</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${r.incident_date ? r.incident_date + ' ' + (r.incident_time||'') : '<span style="color:#64748b;font-weight:400;">Not provided</span>'}</h6>
      </div>
      <div class="col-md-6">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Location</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600; line-height:1.4;">${r.location_address || '<span style="color:#64748b;font-weight:400;">Not provided</span>'}</h6>
      </div>
      
      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Weather / Road</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${r.weather_condition || 'N/A'} <span style="color:rgba(255,255,255,0.1)">|</span> ${r.road_condition || 'N/A'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Injured Persons?</p>
        <h6 style="margin:0; font-weight:600;">${r.is_injured == 1 ? '<span style="color:#ef4444;"><i class="bi bi-exclamation-circle-fill me-1"></i> Yes</span>' : '<span style="color:#94a3b8;font-weight:400;">No</span>'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Other Parties</p>
        <h6 style="margin:0; font-weight:600;">${r.has_other_parties == 1 ? '<span style="color:#ef4444;">Yes</span>' : '<span style="color:#94a3b8;font-weight:400;">No</span>'}</h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.08); margin:8px 0;"></div>

      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Law Enforcer</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${r.enforcer_type ? r.enforcer_type.toUpperCase() : '<span style="color:#64748b;font-weight:400;">N/A</span>'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Emergency Services</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${emergencySvcs}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Insurance Type</p>
        <h6 style="margin:0; color:#e2e8f0; font-weight:600;">${r.insurance_type ? r.insurance_type.toUpperCase() : '<span style="color:#64748b;font-weight:400;">N/A</span>'}</h6>
      </div>

      <div class="col-12"><hr style="border-color:rgba(255,255,255,0.08); margin:8px 0;"></div>

      <div class="col-12">
        <p class="mb-2" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Vehicles Involved</p>
        ${vehiclesHtml.replace(/background:rgba\(255,255,255,0.05\)/g, 'background:rgba(15,23,42,0.4);border:1px solid rgba(255,255,255,0.05);box-shadow:inset 0 2px 10px rgba(0,0,0,0.1);')}
      </div>
      
      <div class="col-12">
        <p class="mb-2" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Event Details</p>
        <div style="background:rgba(15,23,42,0.4); padding:20px; border-radius:16px; border:1px solid rgba(255,255,255,0.05); font-size:14px; line-height:1.6; color:#e2e8f0; box-shadow:inset 0 2px 10px rgba(0,0,0,0.1);">
          ${descHtml}
        </div>
      </div>
      <div class="col-12">
        <p class="mb-2" style="color: #64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">Photo/Video Evidence</p>
        <div class="text-center" style="background:rgba(15,23,42,0.4); padding:24px; border-radius:16px; border:1px solid rgba(255,255,255,0.05); box-shadow:inset 0 2px 10px rgba(0,0,0,0.1);">
          ${mediaHtml}
        </div>
      </div>
    </div>
  `;
    return;
  }

  // 2. FIX IMAGE PATHS: Pointing to the correct assets folder
  let mediaHtml = '<span style="color:var(--muted);">No media provided</span>';
  if (r.media && r.media.length > 0) {
    mediaHtml = '<div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">';
    r.media.forEach(m => {
      // Ensure the path uses the correct assets folder
      const imgPath = m.file_path.includes('assets/') ? `../${m.file_path}` : `../assets/uploads/${m.file_path}`;
      if (m.file_type === 'image') {
        mediaHtml += `<a href="${imgPath}" target="_blank"><img src="${imgPath}" style="height:150px; border-radius:8px; border:1px solid var(--muted); object-fit:cover;"></a>`;
      } else if (m.file_type === 'video') {
        mediaHtml += `<video src="${imgPath}" controls style="height:150px; border-radius:8px; border:1px solid var(--muted);"></video>`;
      }
    });
    mediaHtml += '</div>';
  }

  let vehiclesHtml = '<span style="color:#cbd5e1;">N/A</span>';
  if (r.vehicles && r.vehicles.length > 0) {
    vehiclesHtml = '<ul style="list-style:none; padding:0; margin:0;">';
    r.vehicles.forEach(v => {
      vehiclesHtml += `<li style="background:var(--muted); padding:8px 12px; border-radius:6px; margin-bottom:5px; font-size:13px;">
        <span style="font-weight:bold;">${v.vehicle_type}</span> 
        <span style="margin:0 8px; color:var(--muted);">|</span> 
        <span style="font-family:monospace; color:#60b4ff;">${v.plate_number || 'No Plate'}</span>
        ${v.vehicle_count > 1 ? ` <span style="margin:0 8px; color:var(--muted);">|</span> Qty: ${v.vehicle_count}` : ''}
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
            <div id="aiSummaryText" style="font-size:14px; color:var(--muted);">Click the button to generate a 1-sentence summary of this report.</div>
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
                        border:2px solid rgba(96,180,255,0.4); background:var(--card-bg);">
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
              ${r.assigned_enforcer_id !== null ? '<span style="font-size:10px;color:var(--muted);font-weight:400;">— ENFORCER</span>' : ''}
            </h6>
            ${r.account_verified == 1 ? `<span class="verified-badge-lg" title="This user has a verified identity"><i class="bi bi-shield-check"></i> Identity Verified</span>` : ''}
            <div style="font-size:12px; color:var(--muted); margin-top:2px;">${r.email} ${r.phone ? '• ' + r.phone : ''}</div>
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

      <div class="col-md-6">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Reporter Role</p>
        <h6 style="margin:0;">
          ${ r.reporter_role === 'citizen'
            ? '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:rgba(167,139,250,0.1);border:1px solid rgba(167,139,250,0.3);color:#a78bfa;font-size:12px;font-weight:600;"><i class=\'bi bi-eye-fill\'></i> Citizen / Witness</span>'
            : '<span style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:6px;background:rgba(96,180,255,0.1);border:1px solid rgba(96,180,255,0.3);color:#60b4ff;font-size:12px;font-weight:600;"><i class=\'bi bi-car-front-fill\'></i> Driver</span>'
          }
        </h6>
      </div>

      <div class="col-12"><hr style="border-color:var(--muted); margin:0;"></div>
      
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
        <h6 style="margin:0;">${r.weather_condition || 'N/A'} <span style="color:var(--muted)">|</span> ${r.road_condition || 'N/A'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Injured Persons?</p>
        <h6 style="margin:0;">${r.is_injured == 1 ? '<span style="color:#f87171;"><i class="bi bi-exclamation-circle-fill me-1"></i> Yes</span>' : '<span style="color:var(--muted);">No</span>'}</h6>
      </div>
      <div class="col-md-4">
        <p class="mb-1" style="color: #007ED2; font-size:12px; text-transform:uppercase; letter-spacing:1px;">Other Parties</p>
        <h6 style="margin:0;">${r.has_other_parties == 1 ? '<span style="color:#f87171;">Yes</span>' : '<span style="color:var(--muted);">No</span>'}</h6>
      </div>

      <div class="col-12"><hr style="border-color:var(--muted); margin:0;"></div>

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

      <div class="col-12"><hr style="border-color:var(--muted); margin:0;"></div>

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
