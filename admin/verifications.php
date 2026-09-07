<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/layout.php';
startSecureSession();
requireAdminLogin();
$admin = getAdminUser();
$db = getDB();

$verifs = $db->query("SELECT v.*, u.first_name, u.last_name, u.email FROM id_verifications v JOIN users u ON v.user_id=u.id ORDER BY FIELD(v.status,'pending','approved','rejected'), v.created_at DESC LIMIT 100")->fetchAll();

adminHead('ID Verifications');
?>
<body>
<?php adminNav('verifications', $admin); ?>
<div class="main">
<?php adminTopbar('ID Verification Review'); ?>
<div class="page-body">
<div class="section-card">
  <div class="section-header"><span class="section-title-text"><i class="bi bi-shield-check me-2"></i>ID Verification Requests (<?= count($verifs) ?>)</span></div>
  <div style="overflow-x:auto;">
  <table class="data-table">
   <thead><tr><th>User</th><th>ID Type</th><th>Full Name</th><th>Birthdate</th><th>Status</th><th>Submitted</th><th>ID Details</th><th>Selfie</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach($verifs as $v): ?>
    <tr>
      <td>
        <div style="font-weight:600;"><?= htmlspecialchars($v['first_name'].' '.$v['last_name']) ?></div>
        <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($v['email']) ?></div>
      </td>
      <td><?= htmlspecialchars($v['id_type'] ?? '—') ?></td>
      <td><?= htmlspecialchars($v['full_name'] ?? '—') ?></td>
      <td style="font-size:12px;"><?= $v['birthdate'] ? date('M d, Y', strtotime($v['birthdate'])) : '—' ?></td>
      <td><span class="badge-status bs-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span></td>
      <td style="font-size:12px;color:var(--muted);"><?= date('M d, Y', strtotime($v['created_at'])) ?></td>
      <td>
        <button class="btn-admin btn-review" onclick='openVerifModal(<?= htmlspecialchars(json_encode($v), ENT_QUOTES) ?>)'>
          <i class="bi bi-eye"></i> View ID
        </button>
      </td>
      <td>
        <?php if (!empty($v['selfie_file'])): ?>
            <a href="../assets/uploads/<?= htmlspecialchars($v['selfie_file']) ?>" target="_blank" class="btn-admin btn-review"><i class="bi bi-person-bounding-box"></i> View Selfie</a>
        <?php else: ?>
            <span style="font-size:12px;color:var(--muted);">No selfie</span>
        <?php endif; ?>
      </td>
      <td style="display:flex;gap:6px;">
        <?php if($v['status']==='pending'): ?>
        <button class="btn-admin btn-approve" onclick="verifyAction(<?= $v['id'] ?>,'approved')"><i class="bi bi-check2"></i> Approve</button>
        <button class="btn-admin btn-reject"  onclick="verifyAction(<?= $v['id'] ?>,'rejected')"><i class="bi bi-x"></i> Reject</button>
        <?php else: ?>
        <span style="font-size:12px;color:var(--muted);">—</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div></div>

<!-- ══════════════════════════════════════════
     Verification Detail Modal
══════════════════════════════════════════ -->
<div class="modal fade" id="verifModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:580px;">
    <div class="modal-content" style="background: #ffffff; color: #1e293b; border: none; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);">

      <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding: 24px 28px;">
        <div>
          <h5 class="modal-title" style="font-weight: 700; font-size: 20px; margin: 0; color: #0f172a; letter-spacing: -0.3px;">
            <i class="bi bi-shield-check me-2" style="color: #0ea5e9;"></i>ID Verification Details
          </h5>
          <div id="vm-ref" style="font-size: 12px; color: #64748b; margin-top: 4px; font-family: monospace;"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" style="padding: 28px; background: #f8fafc;">

        <!-- Status Banner -->
        <div id="vm-status-banner"></div>

        <!-- Registrant Info -->
        <div class="vm-section-label"><i class="bi bi-person-fill"></i> Registrant Info</div>
        <div class="vm-block">
          <div class="vm-row"><span class="vm-label">Name</span><span id="vm-user-name" class="vm-val"></span></div>
          <div class="vm-row"><span class="vm-label">Email</span><span id="vm-user-email" class="vm-val"></span></div>
          <div class="vm-row"><span class="vm-label">Registered DOB</span><span id="vm-user-dob" class="vm-val"></span></div>
          <div class="vm-row" style="border:none;"><span class="vm-label">Submitted</span><span id="vm-submitted" class="vm-val"></span></div>
        </div>

        <!-- ID Image -->
        <div id="vm-image-wrap" style="display:none; margin-bottom: 24px;">
          <div class="vm-section-label"><i class="bi bi-person-badge"></i> Submitted ID Image</div>
          <div style="border-radius: 16px; overflow: hidden; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); padding: 12px;">
            <img id="vm-id-image" src="" alt="ID Image"
              style="width: 100%; display: block; max-height: 320px; object-fit: contain; cursor: zoom-in; border-radius: 10px;"
              onclick="window.open(this.src,'_blank')">
          </div>
          <div style="font-size: 12px; color: #94a3b8; text-align: center; margin-top: 10px; font-weight: 500;">Click image to open full size</div>
        </div>

        <!-- OCR Extracted -->
        <div class="vm-section-label"><i class="bi bi-search"></i> OCR Extracted from ID</div>
        <div class="vm-block">
          <div class="vm-row"><span class="vm-label">Document Type</span><span id="vm-doc-type" class="vm-val"></span></div>
          <div class="vm-row">
            <span class="vm-label">Name on ID</span>
            <span id="vm-ocr-name" class="vm-val"></span>
            <span id="vm-name-badge" class="vm-badge"></span>
          </div>
          <div class="vm-row">
            <span class="vm-label">DOB on ID</span>
            <span id="vm-ocr-dob" class="vm-val"></span>
            <span id="vm-dob-badge" class="vm-badge"></span>
          </div>
          <div class="vm-row"><span class="vm-label">Doc Number</span><span id="vm-doc-num" class="vm-val"></span></div>
          <div class="vm-row" style="border:none;"><span class="vm-label">Expiry</span><span id="vm-expiry" class="vm-val"></span></div>
        </div>

        <!-- Confidence Score -->
        <div class="vm-section-label"><i class="bi bi-graph-up"></i> OCR Confidence</div>
        <div class="vm-block">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span id="vm-engine" style="font-size: 13px; font-weight: 600;"></span>
            <span id="vm-conf-pct" style="font-size: 24px; font-weight: 800; letter-spacing: -1px;"></span>
          </div>
          <div style="height: 10px; border-radius: 5px; background: #e2e8f0; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);">
            <div id="vm-conf-bar" style="height: 100%; border-radius: 5px; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); width: 0%;"></div>
          </div>
        </div>

        <!-- Failure Reason -->
        <div id="vm-failure-wrap" style="display:none; margin-top: 24px;">
          <div class="vm-section-label" style="color: #ef4444;"><i class="bi bi-x-circle-fill"></i> Failure Reason</div>
          <div id="vm-fail-reason" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px; font-size: 14px; color: #b91c1c; line-height: 1.6; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.05);"></div>
        </div>

        <!-- Image Hash -->
        <div id="vm-hash-wrap" style="font-size: 11px; color: #94a3b8; font-family: monospace; word-break: break-all; text-align: center; margin-top: 16px;"></div>

      </div>

      <div class="modal-footer" style="border-top: 1px solid #f1f5f9; background: #ffffff; border-bottom-left-radius: 24px; border-bottom-right-radius: 24px; gap: 12px; padding: 20px 28px;" id="vm-footer"></div>
    </div>
  </div>
</div>

<style>
.vm-section-label {
  font-size: 12px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 1px; color: #64748b; margin-bottom: 12px; display: flex; align-items: center; gap: 6px;
}
.vm-block {
  background: #ffffff; border-radius: 16px;
  padding: 18px 20px; margin-bottom: 24px;
  border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.vm-row {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 10px 0; border-bottom: 1px solid #f1f5f9;
  font-size: 14px; flex-wrap: nowrap;
}
.vm-label {
  color: #64748b; font-size: 12px; font-weight: 600;
  min-width: 130px; text-transform: uppercase; letter-spacing: 0.5px;
  padding-top: 2px;
}
.vm-val { font-weight: 600; flex: 1; color: #0f172a; word-break: break-word; min-width: 0; }
.vm-val.empty { color: #94a3b8; font-weight: 400; font-style: italic; }
.vm-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 12px; white-space: nowrap; flex-shrink: 0; margin-left: auto; }
.vm-badge.match { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.vm-badge.nomatch { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const _verifModal = new bootstrap.Modal(document.getElementById('verifModal'));

function openVerifModal(v) {
  // Reference
  document.getElementById('vm-ref').textContent = v.ocr_reference ? 'Ref: ' + v.ocr_reference : '';

  // Status banner
  const bannerStyles = {
    pending:  { bg:'#fffbeb',  border:'#fde68a', color:'#d97706', icon:'<i class="bi bi-hourglass-split"></i>', label:'Pending Review'  },
    approved: { bg:'#f0fdf4',  border:'#bbf7d0', color:'#16a34a', icon:'<i class="bi bi-shield-check"></i>', label:'Approved'        },
    rejected: { bg:'#fef2f2', border:'#fecaca',color:'#dc2626', icon:'<i class="bi bi-x-circle"></i>', label:'Rejected'        },
  };
  const s = bannerStyles[v.status] || { bg:'#f1f5f9', border:'#e2e8f0', color:'#64748b', icon:'<i class="bi bi-question-circle-fill"></i>', label: v.status ? ucFirst(v.status) : 'Unknown' };
  const banner = document.getElementById('vm-status-banner');
  banner.style.cssText = `background:${s.bg};border:1px solid ${s.border};color:${s.color};border-radius:16px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;font-weight:700;font-size:15px;box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);`;
  banner.innerHTML = `<span style="font-size:20px;">${s.icon}</span> <span style="letter-spacing:0.2px;">${s.label}</span>`;

  // Registrant info
  document.getElementById('vm-user-name').textContent  = (v.first_name + ' ' + v.last_name).trim() || '—';
  document.getElementById('vm-user-email').textContent = v.email || '—';
  document.getElementById('vm-user-dob').textContent   = v.birthdate ? fmtDate(v.birthdate) : '—';
  document.getElementById('vm-submitted').textContent  = v.created_at ? fmtDate(v.created_at) : '—';

  // OCR extracted fields
  setVal('vm-doc-type',  v.id_type);
  setVal('vm-ocr-name',  v.ocr_extracted_name);
  setVal('vm-ocr-dob',   v.ocr_extracted_dob);
  setVal('vm-doc-num',   v.ocr_extracted_doc_number);
  setVal('vm-expiry',    v.ocr_extracted_expiry);

  // Match badges
  setBadge('vm-name-badge', v.ocr_name_match);
  setBadge('vm-dob-badge', v.ocr_dob_match);

  // Confidence score
  const pct = v.ocr_confidence_score ? Math.round(parseFloat(v.ocr_confidence_score) * 100) : 0;
  const confColor = pct >= 75 ? '#4ade80' : pct >= 50 ? '#fbbf24' : '#f87171';
  document.getElementById('vm-conf-pct').textContent = pct + '%';
  document.getElementById('vm-conf-pct').style.color = confColor;
  document.getElementById('vm-conf-bar').style.width  = pct + '%';
  document.getElementById('vm-conf-bar').style.background = confColor;

  // OCR engine
  const engineEl = document.getElementById('vm-engine');
  if (v.ocr_engine) {
    const label = v.ocr_engine === 'tesseract' ? '<i class="bi bi-pc-display"></i> Tesseract (Local)'
                : v.ocr_engine === 'gemini_vision' ? '<i class="bi bi-robot"></i> Gemini Vision (AI)'
                : v.ocr_engine.toUpperCase();
    engineEl.innerHTML = label;
  } else {
    // Default to Gemini since it's the primary engine now
    engineEl.innerHTML = '<i class="bi bi-robot"></i> Gemini Vision (AI)';
  }
  engineEl.style.color = '#0ea5e9';

  // Failure reason
  const failWrap = document.getElementById('vm-failure-wrap');
  if (v.ocr_failure_reason) {
    failWrap.style.display = 'block';
    
    let niceReason = v.ocr_failure_reason
      .replace(/name_mismatch/g, '<strong>Name Mismatch:</strong> The name on the ID does not match the registered user.<br>')
      .replace(/dob_mismatch/g, '<strong>DOB Mismatch:</strong> The date of birth on the ID does not match the registered user.<br>')
      .replace(/ \(score=([0-9.]+)\)/, '<br><span style="font-size:12px;color:#ef4444;opacity:0.8;">Internal Match Score: $1</span>');
      
    document.getElementById('vm-fail-reason').innerHTML = niceReason;
  } else {
    failWrap.style.display = 'none';
  }

  // Image hash (fingerprint)
  document.getElementById('vm-hash-wrap').textContent = v.image_hash ? 'SHA-256: ' + v.image_hash : '';

  // ID image preview
  const imgWrap = document.getElementById('vm-image-wrap');
  const imgEl   = document.getElementById('vm-id-image');
  if (v.id_file) {
    imgEl.src = 'serve_id_image.php?vid=' + encodeURIComponent(v.id);
    imgWrap.style.display = 'block';
  } else {
    imgEl.src = '';
    imgWrap.style.display = 'none';
  }

  // Footer buttons
  const footer = document.getElementById('vm-footer');
  if (v.status === 'pending') {
    footer.innerHTML = `
      <button class="btn-admin btn-review" data-bs-dismiss="modal" style="border-radius:12px; padding:10px 24px; font-weight:600;">Close</button>
      <button class="btn-admin btn-reject" onclick="verifyAction(${v.id},'rejected');_verifModal.hide()" style="border-radius:12px; padding:10px 20px; font-weight:600;">
        <i class="bi bi-x"></i> Reject
      </button>
      <button class="btn-admin btn-approve" onclick="verifyAction(${v.id},'approved');_verifModal.hide()" style="border-radius:12px; padding:10px 20px; font-weight:600;">
        <i class="bi bi-check2"></i> Approve
      </button>`;
  } else {
    footer.innerHTML = `<button class="btn-admin btn-review" data-bs-dismiss="modal" style="border-radius:12px; padding:10px 24px; font-weight:600;">Close</button>`;
  }

  _verifModal.show();
}

/* ── Helpers ── */
function setVal(id, val) {
  const el = document.getElementById(id);
  if (val) { el.textContent = val; el.classList.remove('empty'); }
  else      { el.textContent = 'Not extracted'; el.classList.add('empty'); }
}

function setBadge(id, raw) {
  const el = document.getElementById(id);
  if (raw === null || raw === undefined || raw === '') {
    el.textContent = ''; el.className = 'vm-badge';
  } else if (parseInt(raw) === 1) {
    el.innerHTML = '<i class="bi bi-check"></i> Match';    el.className = 'vm-badge match';
  } else {
    el.innerHTML = '<i class="bi bi-x"></i> Mismatch'; el.className = 'vm-badge nomatch';
  }
}

function fmtDate(str) {
  if (!str) return '—';
  const d = new Date(str);
  return isNaN(d) ? str : d.toLocaleDateString('en-PH', {year:'numeric',month:'short',day:'numeric'});
}
function ucFirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }

/* ── Approve / Reject ── */
async function verifyAction(vid, status) {
  if (!confirm('Confirm: ' + status + ' this verification?')) return;
  const fd = new FormData();
  fd.append('action','verify_id'); fd.append('verif_id',vid); fd.append('status',status);
  const res = await fetch('api/admin_action.php',{method:'POST',body:fd});
  const data = await res.json();
  if (data.success) location.reload(); else alert(data.message);
}
</script>
</body></html>

