<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

startSecureSession();

// If not logged in, save where to go after login, then redirect
if (empty($_SESSION['user_id'])) {
    $returnTo = 'contract_confirm.php?token=' . urlencode($_GET['token'] ?? '');
    $_SESSION['login_redirect'] = $returnTo;
    header('Location: ' . BASE_URL . '/index.php?invite=1');
    exit;
}

// Session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    $returnTo = 'contract_confirm.php?token=' . urlencode($_GET['token'] ?? '');
    session_unset();
    session_destroy();
    startSecureSession();
    $_SESSION['login_redirect'] = $returnTo;
    header('Location: ' . BASE_URL . '/index.php?timeout=1&invite=1');
    exit;
}
$_SESSION['last_activity'] = time();

$user  = getLoggedInUser();
$db    = getDB();
$token = trim($_GET['token'] ?? '');


// Validate token
$contract = null;
$error    = null;

if (!$token) {
    $error = 'Missing invite token.';
} else {
    $stmt = $db->prepare("
        SELECT c.*, u.first_name AS p1_first, u.last_name AS p1_last
        FROM contracts c
        LEFT JOIN users u ON c.party1_user_id = u.id
        WHERE c.invite_token = ?
    ");
    $stmt->execute([$token]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        $error = 'This invite link is invalid or has already been used.';
    } elseif ($contract['status'] !== 'waiting') {
        $error = 'This contract has already been ' . $contract['status'] . '.';
    } elseif (strtotime($contract['invite_expires_at']) < time()) {
        $error = 'This invite link has expired. Please ask Party 1 to send a new invite.';
    } elseif ((int)$contract['party2_user_id'] !== (int)$user['id']) {
        $error = 'This invite was not sent to your account. Make sure you are logged in with the correct email.';
    }
}

$p1Name     = $contract ? trim(($contract['p1_first'] ?? '') . ' ' . ($contract['p1_last'] ?? '')) : '';
$refNum     = $contract['reference_number'] ?? '';
$faultMap   = ['party1' => 'Party 1', 'party2' => 'Party 2', 'shared' => 'Shared Fault', 'undetermined' => 'Not Determined'];
$fault      = $faultMap[$contract['fault'] ?? ''] ?? '—';
$resMap     = [
    'pay_repair'    => 'Pay Repair Costs',
    'shoulder_shop' => 'Shoulder Repairs at a Shop',
    'cash'          => 'Cash Payment',
    'split'         => 'Split Costs',
    'insurance'     => 'Go Through Insurance',
    'no_comp'       => 'No Compensation',
];
$resolution = $resMap[$contract['resolution_type'] ?? ''] ?? ($contract['resolution_type'] ?? '—');
$amount     = $contract && $contract['amount'] ? '₱' . number_format((float)$contract['amount'], 2) : 'Not specified';
$deadline   = $contract && $contract['payment_deadline'] ? date('F d, Y', strtotime($contract['payment_deadline'])) : 'Not specified';
$expiresIn  = $contract ? ceil((strtotime($contract['invite_expires_at']) - time()) / 86400) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VrakeIT — Contract Confirmation</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    :root { --primary:#007ED2; --danger:#E90101; --success:#00c853; }
    body { font-family:'Poppins',sans-serif; background:#f0f4f8; min-height:100vh; padding-bottom:80px; }
    .page-header { background:linear-gradient(135deg,#E90101,#007ED2); color:#fff; padding:1.5rem 1.25rem 2.5rem; position:relative; }
    .page-header h1 { font-size:1.3rem; font-weight:800; margin:0 0 2px; }
    .page-header p  { font-size:0.8rem; opacity:0.85; margin:0; }
    .ref-chip { display:inline-block; background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.4); padding:4px 14px; border-radius:20px; font-size:0.75rem; font-weight:700; margin-top:8px; }
    .content-card { background:#fff; border-radius:1.25rem; margin:0 12px; margin-top:-1.25rem; padding:1.25rem; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
    .section-label { font-size:0.7rem; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.6rem; }
    .info-row { display:flex; align-items:flex-start; gap:0.5rem; margin-bottom:0.5rem; }
    .info-label { font-size:0.75rem; font-weight:700; color:#6b7280; min-width:110px; flex-shrink:0; }
    .info-value { font-size:0.8rem; color:#1f2937; font-weight:500; }
    .party-box { background:#f8fafc; border:1.5px solid #e5e7eb; border-radius:1rem; padding:1rem; margin-bottom:0.75rem; }
    .party-label { font-size:0.7rem; font-weight:800; color:var(--primary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.6rem; }
    .terms-box { background:#f9fafb; border-left:3px solid var(--primary); padding:0.85rem 1rem; border-radius:0.5rem; font-size:0.8rem; line-height:1.6; color:#374151; white-space:pre-wrap; margin-top:0.5rem; }
    .divider { border:none; border-top:1px solid #f3f4f6; margin:1.25rem 0; }
    .chip { display:inline-block; padding:4px 12px; border-radius:20px; font-size:0.72rem; font-weight:700; background:#e0f2fe; color:#0369a1; }
    .consent-check { display:flex; align-items:flex-start; gap:0.75rem; margin-bottom:0.85rem; }
    .consent-check input[type=checkbox] { width:20px; height:20px; flex-shrink:0; margin-top:1px; accent-color:var(--primary); cursor:pointer; }
    .consent-check label { font-size:0.8rem; color:#374151; line-height:1.5; cursor:pointer; }
    .btn-agree { width:100%; padding:1rem; background:linear-gradient(135deg,#00c853,#009624); color:#fff; border:none; border-radius:0.9rem; font-family:'Poppins',sans-serif; font-size:1rem; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:0.5rem; transition:opacity 0.2s; }
    .btn-agree:disabled { opacity:0.5; cursor:not-allowed; }
    .btn-decline { width:100%; padding:0.75rem; background:#fff; color:#6b7280; border:1.5px solid #e5e7eb; border-radius:0.9rem; font-family:'Poppins',sans-serif; font-size:0.85rem; font-weight:600; cursor:pointer; margin-top:0.75rem; }
    .expires-banner { background:#fef9c3; border:1px solid #fde68a; border-radius:0.75rem; padding:0.75rem 1rem; font-size:0.78rem; color:#92400e; display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; }
    .error-card { background:#fef2f2; border:1.5px solid #fecaca; border-radius:1.25rem; padding:2rem; text-align:center; margin:1rem; }
    .success-card { background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:1.25rem; padding:2.5rem 1.5rem; text-align:center; margin:1rem; }
    .section-block { margin-bottom:1.25rem; }
  </style>
</head>
<body>

<header class="app-header">
  <a href="landing.php" class="back-btn" style="font-size:22px;"><i class="bi bi-arrow-left"></i></a>
  <span class="header-logo">Contract Confirmation</span>
  <span style="width:38px;"></span>
</header>

<?php if ($error): ?>
  <div class="page-header">
    <h1><i class="bi bi-exclamation-circle-fill"></i> Invalid Invite</h1>
    <p>This link cannot be used.</p>
  </div>
  <div class="error-card">
    <i class="bi bi-link-45deg" style="font-size:3rem;color:#fca5a5;display:block;margin-bottom:1rem;"></i>
    <h5 style="font-weight:800;color:#991b1b;margin-bottom:0.5rem;">Link Not Valid</h5>
    <p style="font-size:0.85rem;color:#6b7280;margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></p>
    <a href="contract.php" class="btn-primary" style="display:inline-block;text-decoration:none;padding:10px 24px;">View My Contracts</a>
  </div>

<?php elseif (isset($_GET['done'])): ?>
  <?php $doneAction = $_GET['done']; ?>
  <div class="page-header">
    <h1><?= $doneAction === 'agreed' ? '✅ Contract Confirmed!' : '↩ Contract Declined' ?></h1>
    <p><?= $doneAction === 'agreed' ? 'Your digital consent has been recorded.' : 'Party 1 has been notified.' ?></p>
    <div class="ref-chip"><?= htmlspecialchars($refNum) ?></div>
  </div>
  <div class="success-card">
    <div style="font-size:3.5rem;margin-bottom:0.75rem;"><?= $doneAction === 'agreed' ? '🤝' : '↩' ?></div>
    <h5 style="font-weight:800;color:<?= $doneAction === 'agreed' ? '#065f46' : '#1f2937' ?>;margin-bottom:0.5rem;">
      <?= $doneAction === 'agreed' ? 'You\'ve agreed to the contract!' : 'You declined the contract.' ?>
    </h5>
    <p style="font-size:0.82rem;color:#6b7280;margin-bottom:1.5rem;">
      <?= $doneAction === 'agreed'
        ? 'Both parties have now signed. You can download the PDF from your contracts page.'
        : 'Party 1 can revise the contract and send a new invite.' ?>
    </p>
    <a href="contract.php" style="display:inline-block;text-decoration:none;padding:12px 28px;background:linear-gradient(135deg,#007ED2,#0056a3);color:#fff;border-radius:12px;font-weight:700;font-size:0.9rem;">
      View My Contracts
    </a>
  </div>

<?php else: ?>
  <div class="page-header">
    <h1>Settlement Contract</h1>
    <p>Invited by <?= htmlspecialchars($p1Name) ?></p>
    <div class="ref-chip"><?= htmlspecialchars($refNum) ?></div>
  </div>

  <div style="padding:0 0 1rem;">
    <div class="content-card">

      <!-- Expiry warning -->
      <div class="expires-banner">
        <i class="bi bi-clock" style="flex-shrink:0;"></i>
        This invite expires in <strong>&nbsp;<?= $expiresIn ?> day<?= $expiresIn !== 1 ? 's' : '' ?></strong>.
      </div>

      <!-- What Happened -->
      <div class="section-block">
        <div class="section-label"><i class="bi bi-file-earmark-text"></i> What Happened</div>
        <div class="info-row">
          <span class="info-label">Incident Type</span>
          <span class="info-value"><span class="chip"><?= htmlspecialchars(ucfirst(str_replace('-', ' ', $contract['incident_type'] ?? '—'))) ?></span></span>
        </div>
        <div class="info-row">
          <span class="info-label">Fault</span>
          <span class="info-value"><?= htmlspecialchars($fault) ?></span>
        </div>
        <?php if ($contract['description']): ?>
        <div class="terms-box"><?= nl2br(htmlspecialchars($contract['description'])) ?></div>
        <?php endif; ?>
        <?php if ($contract['versions_agree'] == 0 && $contract['version_p1']): ?>
        <div style="margin-top:0.75rem;">
          <div style="font-size:0.72rem;font-weight:700;color:#6b7280;margin-bottom:4px;">Party 1's Version</div>
          <div class="terms-box"><?= nl2br(htmlspecialchars($contract['version_p1'])) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <hr class="divider">

      <!-- Parties -->
      <div class="section-block">
        <div class="section-label"><i class="bi bi-people-fill"></i> Parties & Vehicles</div>
        <div class="party-box">
          <div class="party-label">Party 1 (Initiator)</div>
          <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($contract['party1_name'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?= htmlspecialchars($contract['party1_contact'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Vehicle</span><span class="info-value"><?= htmlspecialchars(($contract['party1_vehicle_type'] ?? '') . ($contract['party1_plate'] ? ' · ' . $contract['party1_plate'] : '')) ?></span></div>
          <div class="info-row"><span class="info-label">Insurance</span><span class="info-value"><?= htmlspecialchars($contract['party1_insurance'] ?? '—') ?></span></div>
        </div>
        <div class="party-box">
          <div class="party-label" style="color:#059669;">Party 2 (You)</div>
          <div class="info-row"><span class="info-label">Name</span><span class="info-value"><?= htmlspecialchars($contract['party2_name'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Contact</span><span class="info-value"><?= htmlspecialchars($contract['party2_contact'] ?? '—') ?></span></div>
          <div class="info-row"><span class="info-label">Vehicle</span><span class="info-value"><?= htmlspecialchars(($contract['party2_vehicle_type'] ?? '') . ($contract['party2_plate'] ? ' · ' . $contract['party2_plate'] : '')) ?></span></div>
          <div class="info-row"><span class="info-label">Insurance</span><span class="info-value"><?= htmlspecialchars($contract['party2_insurance'] ?? '—') ?></span></div>
        </div>
      </div>

      <hr class="divider">

      <!-- Damage -->
      <div class="section-block">
        <div class="section-label"><i class="bi bi-car-front"></i> Damage</div>
        <div class="info-row"><span class="info-label">Party 1 Damage</span><span class="info-value"><?= htmlspecialchars($contract['damage_desc_p1'] ?? '—') ?></span></div>
        <?php if ($contract['damage_cost_p1']): ?>
        <div class="info-row"><span class="info-label">Est. Cost (P1)</span><span class="info-value">₱<?= number_format((float)$contract['damage_cost_p1'], 2) ?></span></div>
        <?php endif; ?>
        <div class="info-row"><span class="info-label">Party 2 Damage</span><span class="info-value"><?= htmlspecialchars($contract['damage_desc_p2'] ?? '—') ?></span></div>
        <?php if ($contract['damage_cost_p2']): ?>
        <div class="info-row"><span class="info-label">Est. Cost (P2)</span><span class="info-value">₱<?= number_format((float)$contract['damage_cost_p2'], 2) ?></span></div>
        <?php endif; ?>
      </div>

      <hr class="divider">

      <!-- Agreement -->
      <div class="section-block">
        <div class="section-label"><i class="bi bi-handshake-fill"></i> The Agreement</div>
        <div class="info-row"><span class="info-label">Resolution</span><span class="info-value"><span class="chip"><?= htmlspecialchars($resolution) ?></span></span></div>
        <div class="info-row"><span class="info-label">Who Pays</span><span class="info-value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $contract['who_pays'] ?? '—'))) ?></span></div>
        <div class="info-row"><span class="info-label">Amount</span><span class="info-value" style="font-weight:700;color:#059669;"><?= $amount ?></span></div>
        <div class="info-row"><span class="info-label">Payment Method</span><span class="info-value"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$contract['payment_method'] ?? '—'))) ?></span></div>
        <div class="info-row"><span class="info-label">Schedule</span><span class="info-value"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$contract['payment_schedule'] ?? '—'))) ?></span></div>
        <div class="info-row"><span class="info-label">Deadline</span><span class="info-value"><?= $deadline ?></span></div>
        <?php if ($contract['terms']): ?>
        <div class="terms-box"><?= nl2br(htmlspecialchars($contract['terms'])) ?></div>
        <?php endif; ?>
        <?php if ($contract['escalation_clause']): ?>
        <div style="margin-top:0.75rem;">
          <div style="font-size:0.72rem;font-weight:700;color:#dc2626;margin-bottom:4px;"><i class="bi bi-exclamation-triangle"></i> Escalation Clause</div>
          <div class="terms-box" style="border-left-color:#dc2626;"><?= nl2br(htmlspecialchars($contract['escalation_clause'])) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <hr class="divider">

      <!-- Consent and action -->
      <div class="section-block">
        <div class="section-label"><i class="bi bi-shield-check-fill"></i> Your Consent</div>
        <p style="font-size:0.78rem;color:#6b7280;margin-bottom:1rem;line-height:1.6;">
          By ticking the boxes below and clicking "I Agree", your confirmation is legally equivalent to your signature and consent to this settlement.
        </p>

        <div class="consent-check">
          <input type="checkbox" id="c1" onchange="checkConsents()">
          <label for="c1">I am agreeing to this contract <strong>voluntarily</strong> and without pressure from any party.</label>
        </div>
        <div class="consent-check">
          <input type="checkbox" id="c2" onchange="checkConsents()">
          <label for="c2">I understand that <strong>hidden damage or injuries discovered later</strong> may not be covered by this settlement.</label>
        </div>
        <div class="consent-check">
          <input type="checkbox" id="c3" onchange="checkConsents()">
          <label for="c3">I have <strong>read and understood</strong> all terms and conditions stated in this contract above.</label>
        </div>

        <button id="btnAgree" class="btn-agree" onclick="submitConsent('agree')" disabled>
          <i class="bi bi-check-circle-fill"></i> I Agree — Sign This Contract
        </button>
        <button class="btn-decline" onclick="submitConsent('decline')">
          <i class="bi bi-x-circle"></i> Decline Contract
        </button>
      </div>

    </div>
  </div>
<?php endif; ?>

<!-- Confirm / Decline modals -->
<div id="loadingOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:9999;display:none;align-items:center;justify-content:center;">
  <div style="background:#fff;padding:2rem;border-radius:1rem;text-align:center;min-width:200px;">
    <div style="width:40px;height:40px;border:3px solid #e0e0e0;border-top-color:#007ED2;border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 12px;"></div>
    <div style="font-weight:600;color:#374151;" id="loadingText">Processing...</div>
  </div>
</div>

<style>@keyframes spin{to{transform:rotate(360deg)}}</style>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function checkConsents() {
  const all = document.getElementById('c1')?.checked && document.getElementById('c2')?.checked && document.getElementById('c3')?.checked;
  const btn = document.getElementById('btnAgree');
  if (btn) btn.disabled = !all;
}

async function submitConsent(action) {
  if (action === 'decline') {
    if (!confirm('Are you sure you want to decline this contract? Party 1 will be notified.')) return;
  }

  const overlay = document.getElementById('loadingOverlay');
  document.getElementById('loadingText').textContent = action === 'agree' ? 'Recording your consent...' : 'Declining...';
  overlay.style.display = 'flex';

  const fd = new FormData();
  fd.append('token', '<?= htmlspecialchars($token) ?>');
  fd.append('action', action);

  try {
    const res  = await fetch('api/confirm_contract.php', { method: 'POST', body: fd });
    const data = await res.json();
    overlay.style.display = 'none';

    if (data.success) {
      window.location.href = 'contract_confirm.php?token=<?= urlencode($token) ?>&done=' + (action === 'agree' ? 'agreed' : 'declined');
    } else {
      alert('Error: ' + data.message);
    }
  } catch (e) {
    overlay.style.display = 'none';
    alert('Network error. Please try again.');
  }
}
</script>
</body>
</html>
