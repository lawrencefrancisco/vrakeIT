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
<title>VrakeIT - My Contracts</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<header class="app-header">
  <?php $backLink = (($user['role'] ?? 'user') === 'enforcer') ? 'enforcer_landing.php' : 'landing.php'; ?>
  <a href="<?= $backLink ?>" style="color:#fff;font-size:22px;"><i class="bi bi-arrow-left"></i></a>
  <span class="header-logo">My Contracts</span>
  <span style="width:32px;"></span>
</header>

<div style="padding:16px 0 0;">

  <!-- Search -->
  <div style="padding: 0 16px 16px;">
    <div style="position: relative;">
      <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted);"></i>
      <input type="text" id="searchInput" placeholder="Search reference or party name..." style="width: 100%; padding: 8px 12px 8px 36px; border: 2px solid #e0e0e0; border-radius: 12px; font-size: 13px; outline: none; font-family: Poppins, sans-serif;" oninput="renderContracts()">
    </div>
  </div>

  <div id="loadingState" style="text-align:center;padding:40px;color:var(--muted);">
    <div style="width:40px;height:40px;border:3px solid #e0e0e0;border-top-color:var(--red);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px;"></div>
    Loading contracts...
  </div>

  <div id="emptyState" style="display:none;text-align:center;padding:60px 20px;">
    <i class="bi bi-file-earmark-ruled" style="font-size:56px;color:#ddd;display:block;margin-bottom:12px;"></i>
    <h5 style="color:var(--muted);font-weight:600;">No Contracts Yet</h5>
    <p style="color:#aaa;font-size:14px;">Settlement contracts will appear here once created.</p>
    <a href="report.php" class="btn-primary-vr d-inline-block" style="padding:12px 24px;text-decoration:none;margin-top:8px;">File a Report</a>
  </div>

  <div id="contractsList"></div>

</div>

<!-- ── Detail Modal ─────────────────────────────────────── -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable" style="margin:auto;max-width:480px;">
    <div class="modal-content" style="border-radius:20px;border:none;">
      <div class="modal-header" style="background:linear-gradient(135deg,var(--red),#c20000);color:#fff;border-radius:20px 20px 0 0;">
        <h5 class="modal-title">Contract Details</h5>
        <button type="button" class="btn-close btn-close-white opacity-100" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalBody" style="font-size:14px;"></div>
      <div class="modal-footer" id="modalFooter" style="border-top:1px solid #f0f0f0;padding:12px 16px;border-radius:0 0 20px 20px;"></div>
    </div>
  </div>
</div>

<!-- ── Settled Confirmation Modal ───────────────────────── -->
<div class="modal fade" id="confirmModal" tabindex="-1" style="z-index:1060;">
  <div class="modal-dialog modal-dialog-centered" style="max-width:340px;margin:auto;">
    <div class="modal-content" style="border-radius:20px;border:none;">
      <div class="modal-body" style="text-align:center;padding:2rem 1.5rem;">
        <div style="width:64px;height:64px;background:linear-gradient(135deg,#00c853,#009624);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.8rem;">🤝</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:0.4rem;">Mark as Settled?</div>
        <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1.25rem;">This will update the contract status to <strong>Settled</strong>. This action cannot be undone.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <button onclick="closeConfirm()" style="padding:10px;border:2px solid #e0e0e0;border-radius:12px;background:#fff;font-family:Poppins,sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;color:#6b7280;">Cancel</button>
          <button id="confirmSettleBtn" onclick="confirmSettle()" style="padding:10px;border:none;border-radius:12px;background:linear-gradient(135deg,#00c853,#009624);color:#fff;font-family:Poppins,sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;">Yes, Settle</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Disputed Confirmation Modal ──────────────────────── -->
<div class="modal fade" id="disputeModal" tabindex="-1" style="z-index:1060;">
  <div class="modal-dialog modal-dialog-centered" style="max-width:340px;margin:auto;">
    <div class="modal-content" style="border-radius:20px;border:none;">
      <div class="modal-body" style="text-align:center;padding:2rem 1.5rem;">
        <div style="width:64px;height:64px;background:linear-gradient(135deg,#E90101,#b30000);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.8rem;">⚠️</div>
        <div style="font-size:1rem;font-weight:800;margin-bottom:0.4rem;">Mark as Disputed?</div>
        <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1.25rem;">This will flag the contract as <strong>Disputed</strong>. This action cannot be undone.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
          <button onclick="closeDispute()" style="padding:10px;border:2px solid #e0e0e0;border-radius:12px;background:#fff;font-family:Poppins,sans-serif;font-size:0.85rem;font-weight:600;cursor:pointer;color:#6b7280;">Cancel</button>
          <button id="confirmDisputeBtn" onclick="confirmDispute()" style="padding:10px;border:none;border-radius:12px;background:linear-gradient(135deg,#E90101,#b30000);color:#fff;font-family:Poppins,sans-serif;font-size:0.85rem;font-weight:700;cursor:pointer;">Yes, Dispute</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const detailModal  = new bootstrap.Modal(document.getElementById('detailModal'));
const confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
const disputeModal = new bootstrap.Modal(document.getElementById('disputeModal'));

let allContracts      = [];
let activeContractRef = null;

// ── Load ──────────────────────────────────────────────────
async function loadContracts() {
  try {
    const res  = await fetch('api/get_contracts.php');
    const data = await res.json();
    document.getElementById('loadingState').style.display = 'none';

    if (!data.success || !data.contracts.length) {
      document.getElementById('emptyState').style.display = 'block';
      return;
    }

    allContracts = data.contracts;
    renderContracts();

  } catch {
    document.getElementById('loadingState').innerHTML =
      '<i class="bi bi-wifi-off" style="font-size:40px;color:#ddd;display:block;margin-bottom:8px;"></i>Failed to load contracts.';
  }
}

// ── Render list ───────────────────────────────────────────
function renderContracts() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();

  const filtered = allContracts.filter(c =>
    c.reference_number.toLowerCase().includes(searchTerm) ||
    (c.party1_name && c.party1_name.toLowerCase().includes(searchTerm)) ||
    (c.party2_name && c.party2_name.toLowerCase().includes(searchTerm))
  );

  const contractsList = document.getElementById('contractsList');

  if (filtered.length === 0) {
    contractsList.innerHTML =
      '<div style="text-align:center;padding:40px;color:var(--muted);">No contracts match your search.</div>';
    return;
  }

  contractsList.innerHTML = filtered.map(c => `
    <div class="report-card" onclick='showDetail(${JSON.stringify(c).replace(/'/g,"&#39;")})' style="cursor:pointer;">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
        <div>
          <div class="ref">${c.reference_number}</div>
          <div class="flow-type"><i class="bi bi-file-earmark-ruled me-1"></i>Settlement Contract</div>
        </div>
        ${getStatusBadge(c.status)}
      </div>

      <div style="display:flex;align-items:center;gap:6px;margin:8px 0 4px;font-size:12px;color:var(--muted);">
        <i class="bi bi-people-fill" style="color:var(--blue);"></i>
        <span style="font-weight:600;color:#333;">${c.party1_name || '—'}</span>
        <span style="color:#ccc;">⇄</span>
        <span style="font-weight:600;color:#333;">${c.party2_name || '—'}</span>
      </div>

      ${c.amount && parseFloat(c.amount) > 0
        ? `<div style="font-size:12px;color:var(--muted);margin-top:2px;">
             <i class="bi bi-cash-coin me-1" style="color:#10b981;"></i>
             Agreed Amount: <strong style="color:#10b981;">₱${parseFloat(c.amount).toLocaleString()}</strong>
           </div>`
        : ''}

      <div class="date" style="margin-top:6px;"><i class="bi bi-calendar3 me-1"></i>${c.formatted_date}</div>
      <div style="text-align:right;margin-top:8px;font-size:12px;color:var(--blue);">Tap for details &rarr;</div>
    </div>
  `).join('');
}

// ── Status badge ──────────────────────────────────────────
function getStatusBadge(s) {
  const map = {
    settled:     '#0a3622:#d1e7dd',
    not_settled: '#856404:#fff3cd',
    disputed:    '#842029:#f8d7da',
  };
  const [color, bg] = (map[s] || map.not_settled).split(':');
  const labels = { settled: 'Settled', not_settled: 'Not Settled', disputed: 'Disputed' };
  const label  = labels[s] ?? (s.charAt(0).toUpperCase() + s.slice(1));
  return `<span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;background:${bg};color:${color};">${label}</span>`;
}

function statusLabel(s) {
  const labels = { settled: 'Settled', not_settled: 'Not Settled', disputed: 'Disputed' };
  return labels[s] ?? (s.charAt(0).toUpperCase() + s.slice(1));
}

// ── Show detail modal ─────────────────────────────────────
// ── Show detail modal ─────────────────────────────────────
function showDetail(c) {
  activeContractRef = c.reference_number;

  const amountDisplay = (c.amount && parseFloat(c.amount) > 0)
    ? '₱' + parseFloat(c.amount).toLocaleString()
    : 'Not specified';

  const rows = [
    ['Reference',     c.reference_number],
    ['Status',        statusLabel(c.status)],
    ['Submitted',     c.formatted_date],
    ['Party 1',       `${c.party1_name || '—'}${c.party1_contact ? ' · ' + c.party1_contact : ''}`],
    ['Party 2',       `${c.party2_name || '—'}${c.party2_contact ? ' · ' + c.party2_contact : ''}`],
    ['Parties',       c.parties || '—'],
    ['Agreed Amount', amountDisplay],
    ['Incident',      c.description || '—'],
    ['Terms',         c.terms || '—'],
  ].map(([l, v]) =>
    `<div class="overview-row">
       <span class="label">${l}</span>
       <span class="value">${v}</span>
     </div>`
  ).join('');

  // AI Block
  const aiHtml = `
    <div style="background:rgba(194, 0, 0, 0.05); border:1px solid rgba(194, 0, 0, 0.2); border-radius:12px; padding:15px; display:flex; align-items:center; gap:12px; margin-bottom: 16px;">
      <div style="flex-shrink:0; font-size:24px; color:var(--red);"><i class="bi bi-robot"></i></div>
      <div style="flex-grow:1;">
        <div style="font-size:12px; font-weight:bold; color:var(--red); text-transform:uppercase; letter-spacing:1px; margin-bottom:2px;">AI Contract Summary</div>
        <div id="aiSummaryText" style="font-size:13px; color:var(--muted); line-height: 1.4;">Tap the button to generate a quick summary.</div>
      </div>
      <div>
        <button id="btnAiSummarize" class="btn-primary-vr" onclick="generateAISummary('${c.reference_number}')" style="padding: 6px 12px; font-size: 12px; white-space:nowrap; border-radius: 8px; text-decoration:none; display:inline-block; border:none;">
          <i class="bi bi-magic"></i> Summarize
        </button>
      </div>
    </div>
  `;

  document.getElementById('modalBody').innerHTML = `<div style="padding:4px 0;">${aiHtml}${rows}</div>`;

  // Footer — show buttons based on current status
  const footer = document.getElementById('modalFooter');
  if (c.status === 'settled') {
    footer.innerHTML = `
      <div style="width:100%;text-align:center;font-size:0.82rem;color:#0a3622;font-weight:600;padding:6px 0;">
        <i class="bi bi-check-circle-fill me-1" style="color:#00c853;"></i>This contract has been settled.
      </div>`;
  } else if (c.status === 'disputed') {
    footer.innerHTML = `
      <div style="width:100%;text-align:center;font-size:0.82rem;color:#842029;font-weight:600;padding:6px 0;">
        <i class="bi bi-exclamation-circle-fill me-1" style="color:#E90101;"></i>This contract has been disputed.
      </div>`;
  } else {
    footer.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;width:100%;">
        <button onclick="openConfirm()" style="padding:12px;border:none;border-radius:12px;background:linear-gradient(135deg,#00c853,#009624);color:#fff;font-family:Poppins,sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
          <i class="bi bi-check2-circle"></i> Settled
        </button>
        <button onclick="openDispute()" style="padding:12px;border:none;border-radius:12px;background:linear-gradient(135deg,#E90101,#b30000);color:#fff;font-family:Poppins,sans-serif;font-size:0.82rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
          <i class="bi bi-exclamation-triangle"></i> Disputed
        </button>
      </div>`;
  }

  detailModal.show();
}

async function generateAISummary(ref) {
  const btn = document.getElementById('btnAiSummarize');
  const textDiv = document.getElementById('aiSummaryText');
  
  btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Thinking...';
  btn.disabled = true;
  textDiv.innerHTML = '<span style="color:var(--muted);">Analyzing contract details...</span>';
  
  try {
    const fd = new FormData();
    fd.append('reference_number', ref);
    
    const res = await fetch('api/summarize_contract.php', { method: 'POST', body: fd });
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

// ── Settled flow ──────────────────────────────────────────
function openConfirm() {
  detailModal.hide();
  document.getElementById('detailModal').addEventListener('hidden.bs.modal', function handler() {
    confirmModal.show();
    document.getElementById('detailModal').removeEventListener('hidden.bs.modal', handler);
  });
}

function closeConfirm() {
  confirmModal.hide();
  document.getElementById('confirmModal').addEventListener('hidden.bs.modal', function handler() {
    detailModal.show();
    document.getElementById('confirmModal').removeEventListener('hidden.bs.modal', handler);
  });
}

async function confirmSettle() {
  const btn = document.getElementById('confirmSettleBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="width:14px;height:14px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:6px;"></span>Saving…';

  try {
    const fd = new FormData();
    fd.append('reference_number', activeContractRef);
    fd.append('status', 'settled');

    const res  = await fetch('api/update_contract_status.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      const idx = allContracts.findIndex(c => c.reference_number === activeContractRef);
      if (idx !== -1) allContracts[idx].status = 'settled';
      renderContracts();
      confirmModal.hide();
      showToast('Contract marked as settled!', 'success');
    } else {
      throw new Error(data.message || 'Unknown error');
    }

  } catch {
    confirmModal.hide();
    showToast('Failed to update status. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Yes, Settle';
  }
}

// ── Disputed flow ─────────────────────────────────────────
function openDispute() {
  detailModal.hide();
  document.getElementById('detailModal').addEventListener('hidden.bs.modal', function handler() {
    disputeModal.show();
    document.getElementById('detailModal').removeEventListener('hidden.bs.modal', handler);
  });
}

function closeDispute() {
  disputeModal.hide();
  document.getElementById('disputeModal').addEventListener('hidden.bs.modal', function handler() {
    detailModal.show();
    document.getElementById('disputeModal').removeEventListener('hidden.bs.modal', handler);
  });
}

async function confirmDispute() {
  const btn = document.getElementById('confirmDisputeBtn');
  btn.disabled = true;
  btn.innerHTML = '<span style="width:14px;height:14px;border:2px solid rgba(255,255,255,0.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;display:inline-block;vertical-align:middle;margin-right:6px;"></span>Saving…';

  try {
    const fd = new FormData();
    fd.append('reference_number', activeContractRef);
    fd.append('status', 'disputed');

    const res  = await fetch('api/update_contract_status.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      const idx = allContracts.findIndex(c => c.reference_number === activeContractRef);
      if (idx !== -1) allContracts[idx].status = 'disputed';
      renderContracts();
      disputeModal.hide();
      showToast('Contract marked as disputed.', 'error');
    } else {
      throw new Error(data.message || 'Unknown error');
    }

  } catch {
    disputeModal.hide();
    showToast('Failed to update status. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Yes, Dispute';
  }
}

// ── Toast helper ──────────────────────────────────────────
function showToast(message, type = 'success') {
  const bg   = type === 'success' ? 'linear-gradient(135deg,#00c853,#009624)' : 'linear-gradient(135deg,#E90101,#b30000)';
  const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
  const toast = document.createElement('div');
  toast.style.cssText = `position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:${bg};color:#fff;padding:12px 20px;border-radius:14px;font-family:Poppins,sans-serif;font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:8px;z-index:2000;box-shadow:0 4px 20px rgba(0,0,0,0.2);animation:stepIn .3s ease;`;
  toast.innerHTML = `<i class="bi ${icon}"></i> ${message}`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

loadContracts();
</script>

</body>
</html>