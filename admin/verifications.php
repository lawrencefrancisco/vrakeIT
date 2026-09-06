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
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:520px;">
    <div class="modal-content" style="background:var(--card-bg);border:1px solid var(--muted);border-radius:20px;color:var(--text);">

      <div class="modal-header" style="border-bottom:1px solid var(--muted);padding:20px 24px;">
        <div>
          <h5 class="modal-title" style="font-weight:700;font-size:16px;margin:0;">
            <i class="bi bi-shield-check me-2" style="color:#60b4ff;"></i>ID Verification Details
          </h5>
          <div id="vm-ref" style="font-size:11px;color:var(--muted);margin-top:3px;font-family:monospace;"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" style="padding:24px;">

        <!-- Status Banner -->
        <div id="vm-status-banner" style="border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:13px;"></div>

        <!-- Registrant Info -->
        <div class="vm-section-label"><i class="bi bi-person-fill"></i> Registrant Info</div>
        <div class="vm-block">
          <div class="vm-row"><span class="vm-label">Name</span><span id="vm-user-name" class="vm-val"></span></div>
          <div class="vm-row"><span class="vm-label">Email</span><span id="vm-user-email" class="vm-val"></span></div>
          <div class="vm-row"><span class="vm-label">Registered DOB</span><span id="vm-user-dob" class="vm-val"></span></div>
          <div class="vm-row" style="border:none;"><span class="vm-label">Submitted</span><span id="vm-submitted" class="vm-val"></span></div>
        </div>

        <!-- ID Image -->
        <div id="vm-image-wrap" style="display:none;margin-bottom:18px;">
          <div class="vm-section-label"><i class="bi bi-person-badge-fill"></i> Submitted ID Image</div>
          <div style="border-radius:12px;overflow:hidden;background:var(--bg);border:1px solid var(--muted);">
            <img id="vm-id-image" src="" alt="ID Image"
              style="width:100%;display:block;max-height:240px;object-fit:contain;cursor:zoom-in;"
              onclick="window.open(this.src,'_blank')">
          </div>
          <div style="font-size:10px;color:var(--muted);text-align:center;margin-top:5px;">Click image to open full size</div>
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

        <!-- OCR Confidence -->
        <div class="vm-section-label"><i class="bi bi-bar-chart-fill"></i> OCR Confidence</div>
        <div class="vm-block">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <span id="vm-engine" style="font-size:12px;"></span>
            <span id="vm-conf-pct" style="font-size:20px;font-weight:800;"></span>
          </div>
          <div style="height:8px;border-radius:4px;background:var(--muted);overflow:hidden;">
            <div id="vm-conf-bar" style="height:100%;border-radius:4px;transition:width 0.8s ease;width:0%;"></div>
          </div>
        </div>

        <!-- Failure Reason -->
        <div id="vm-failure-wrap" style="display:none;margin-top:16px;">
          <div class="vm-section-label" style="color:rgba(248,113,113,0.7);"><i class="bi bi-x-circle-fill"></i> Failure Reason</div>
          <div id="vm-fail-reason" style="background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2);border-radius:12px;padding:14px;font-size:13px;color:#f87171;line-height:1.6;margin-bottom:18px;"></div>
        </div>

        <!-- Image Hash -->
        <div id="vm-hash-wrap" style="font-size:10px;color:var(--muted);font-family:monospace;word-break:break-all;text-align:center;margin-top:4px;"></div>

      </div>

      <div class="modal-footer" style="border-top:1px solid var(--muted);gap:8px;padding:16px 24px;" id="vm-footer"></div>
    </div>
  </div>
</div>

<style>
.vm-section-label {
  font-size:11px;font-weight:700;text-transform:uppercase;
  letter-spacing:1px;color:var(--muted);margin-bottom:10px;
}
.vm-block {
  background:var(--muted);border-radius:12px;
  padding:14px;margin-bottom:18px;
}
.vm-row {
  display:flex;align-items:center;gap:10px;
  padding:8px 0;border-bottom:1px solid var(--muted);
  font-size:13px;flex-wrap:wrap;
}
.vm-label {
  color:var(--muted);font-size:11px;font-weight:600;
  min-width:120px;text-transform:uppercase;letter-spacing:0.4px;
}
.vm-val          { font-weight:600;flex:1; }
.vm-val.empty    { color:var(--muted);font-weight:400;font-style:italic; }
.vm-badge        { font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;white-space:nowrap; }
.vm-badge.match  { background:rgba(74,222,128,0.18);color:#4ade80; }
.vm-badge.nomatch{ background:rgba(248,113,113,0.18);color:#f87171; }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const _verifModal = new bootstrap.Modal(document.getElementById('verifModal'));

function openVerifModal(v) {
  // Reference
  document.getElementById('vm-ref').textContent = v.ocr_reference ? 'Ref: ' + v.ocr_reference : '';

  // Status banner
  const bannerStyles = {
    pending:  { bg:'rgba(251,191,36,0.12)',  border:'rgba(251,191,36,0.3)',  color:'#fbbf24', icon:'<i class="bi bi-hourglass-split"></i>', label:'Pending Review'  },
    approved: { bg:'rgba(74,222,128,0.12)',  border:'rgba(74,222,128,0.3)', color:'#4ade80', icon:'<i class="bi bi-check-circle-fill"></i>', label:'Approved'        },
    rejected: { bg:'rgba(248,113,113,0.12)', border:'rgba(248,113,113,0.3)',color:'#f87171', icon:'<i class="bi bi-x-circle-fill"></i>', label:'Rejected'        },
  };
  const s = bannerStyles[v.status] || { bg:'var(--muted)', border:'var(--muted)', color:'var(--muted)', icon:'<i class="bi bi-question-circle-fill"></i>', label: v.status ? ucFirst(v.status) : 'Unknown' };
  const banner = document.getElementById('vm-status-banner');
  banner.style.cssText = `background:${s.bg};border:1px solid ${s.border};color:${s.color};border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:13px;`;
  banner.innerHTML = `<span style="font-size:18px;">${s.icon}</span> ${s.label}`;

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
                : v.ocr_engine === 'gemini'    ? '<i class="bi bi-robot"></i> Gemini Vision (AI)'
                : v.ocr_engine.toUpperCase();
    engineEl.innerHTML = label;
    engineEl.style.color = '#60b4ff';
  } else {
    engineEl.textContent = 'Engine unknown';
    engineEl.style.color = 'var(--muted)';
  }

  // Failure reason
  const failWrap = document.getElementById('vm-failure-wrap');
  if (v.ocr_failure_reason) {
    failWrap.style.display = 'block';
    document.getElementById('vm-fail-reason').textContent = v.ocr_failure_reason;
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
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      <button class="btn-admin btn-reject" onclick="verifyAction(${v.id},'rejected');_verifModal.hide()">
        <i class="bi bi-x"></i> Reject
      </button>
      <button class="btn-admin btn-approve" onclick="verifyAction(${v.id},'approved');_verifModal.hide()">
        <i class="bi bi-check2"></i> Approve
      </button>`;
  } else {
    footer.innerHTML = `<button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>`;
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

