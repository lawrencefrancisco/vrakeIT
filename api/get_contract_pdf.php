<?php
/**
 * api/get_contract_pdf.php
 * Generates and streams a server-side PDF of a signed settlement contract using DOMPDF.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

startSecureSession();
requireLogin();

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$ref    = trim($_GET['ref'] ?? '');

if (!$ref) {
    http_response_code(400);
    exit('Missing reference number.');
}

// Fetch the contract — user must be party1 or party2
$stmt = $db->prepare("SELECT * FROM contracts WHERE reference_number = ? AND (party1_user_id = ? OR party2_user_id = ?)");
$stmt->execute([$ref, $userId, $userId]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    http_response_code(403);
    exit('Contract not found or access denied.');
}

if (!in_array($c['status'], ['signed', 'fulfilled', 'settled'])) {
    http_response_code(403);
    exit('PDF is only available for signed contracts.');
}

// ── Computed display values ───────────────────────────────────────
$p1Signed   = $c['p1_signed_at'] ? date('F d, Y h:i A', strtotime($c['p1_signed_at'])) : '—';
$p2Signed   = $c['p2_signed_at'] ? date('F d, Y h:i A', strtotime($c['p2_signed_at'])) : '—';
$created    = date('F d, Y', strtotime($c['created_at']));
$deadline   = $c['payment_deadline'] ? date('F d, Y', strtotime($c['payment_deadline'])) : 'Not specified';
$amount     = $c['amount'] ? '&#8369;' . number_format((float)$c['amount'], 2) : 'Not specified';

$faultMap   = ['party1' => 'Party 1', 'party2' => 'Party 2', 'shared' => 'Shared Fault', 'undetermined' => 'Not Determined'];
$fault      = $faultMap[$c['fault'] ?? ''] ?? ($c['fault'] ?: '&mdash;');

$resMap     = [
    'pay_repair'    => 'Pay Repair Costs',
    'shoulder_shop' => 'Shoulder Repairs at a Shop',
    'cash'          => 'Cash Payment',
    'split'         => 'Split Costs',
    'insurance'     => 'Go Through Insurance',
    'no_comp'       => 'No Compensation',
];
$resolution  = $resMap[$c['resolution_type'] ?? ''] ?? ($c['resolution_type'] ?: '—');
$payMethod   = ucfirst(str_replace('_', ' ', $c['payment_method']   ?? '—'));
$paySchedule = ucfirst(str_replace('_', ' ', $c['payment_schedule'] ?? '—'));
$incTypeLabel= ucfirst(str_replace('-', ' ', $c['incident_type']    ?? '—'));

// ── Generate HTML ─────────────────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body   { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; margin: 0; padding: 0; }
  .page  { padding: 36px 40px; }
  .hdr   { background-color: #E90101; color: #fff; padding: 28px 36px; margin: -36px -40px 24px; }
  .hdr h1{ margin: 0 0 4px; font-size: 20px; font-weight: 800; }
  .hdr p { margin: 0; font-size: 10px; opacity: 0.85; }
  .ref   { display: inline-block; border: 1px solid rgba(255,255,255,0.5); padding: 3px 12px; border-radius: 20px; font-size: 10px; font-weight: 700; margin-top: 6px; }
  .sec   { margin-bottom: 18px; }
  .sec-title { font-size: 9px; font-weight: 800; color: #007ED2; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px; padding-bottom: 3px; border-bottom: 2px solid #e5e7eb; }
  table.info td { padding: 4px 0; font-size: 10.5px; vertical-align: top; }
  table.info td:first-child { font-weight: 700; color: #555; width: 36%; padding-right: 8px; }
  .pbox  { background: #f8fafc; border: 1px solid #e5e7eb; padding: 8px 12px; margin-bottom: 8px; }
  .plabel{ font-size: 9px; font-weight: 800; color: #007ED2; text-transform: uppercase; margin-bottom: 5px; }
  .tbox  { background: #f9fafb; border-left: 3px solid #007ED2; padding: 10px 12px; font-size: 10.5px; line-height: 1.6; }
  .sblock{ border: 1px solid #e5e7eb; padding: 10px 12px; }
  .sname { font-weight: 700; font-size: 11px; margin-bottom: 3px; }
  .sdate { font-size: 9.5px; color: #6b7280; }
  .sconsent { font-size: 9.5px; color: #059669; margin-top: 3px; }
  .foot  { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px; text-align: center; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>
<div class="page">

<div class="hdr">
  <h1>VrakeIT Settlement Contract</h1>
  <p>Road Incident Settlement Agreement</p>
  <div class="ref"><?= htmlspecialchars($ref) ?></div>
</div>

<!-- Contract Info -->
<div class="sec">
  <div class="sec-title">Contract Information</div>
  <table class="info" width="100%">
    <tr><td>Date Created</td><td><?= $created ?></td></tr>
    <tr><td>Incident Type</td><td><?= htmlspecialchars($incTypeLabel) ?></td></tr>
    <tr><td>Fault</td><td><?= htmlspecialchars($fault) ?></td></tr>
    <tr><td>Status</td><td><?= ucfirst(htmlspecialchars($c['status'])) ?></td></tr>
  </table>
</div>

<?php if ($c['description']): ?>
<div class="sec">
  <div class="sec-title">Incident Description</div>
  <div class="tbox"><?= nl2br(htmlspecialchars($c['description'])) ?></div>
</div>
<?php endif; ?>

<!-- Parties -->
<div class="sec">
  <div class="sec-title">Parties Involved</div>
  <div class="pbox">
    <div class="plabel">Party 1 (Initiator)</div>
    <table class="info" width="100%">
      <tr><td>Name</td><td><?= htmlspecialchars($c['party1_name'] ?? '—') ?></td></tr>
      <tr><td>Contact</td><td><?= htmlspecialchars($c['party1_contact'] ?? '—') ?></td></tr>
      <tr><td>Address</td><td><?= htmlspecialchars($c['party1_address'] ?? '—') ?></td></tr>
      <tr><td>Vehicle</td><td><?= htmlspecialchars(trim(($c['party1_vehicle_type'] ?? '') . ' ' . ($c['party1_plate'] ?? ''))) ?></td></tr>
      <tr><td>License</td><td><?= htmlspecialchars($c['party1_license'] ?? '—') ?></td></tr>
      <tr><td>Insurance</td><td><?= htmlspecialchars($c['party1_insurance'] ?? '—') ?></td></tr>
    </table>
  </div>
  <div class="pbox">
    <div class="plabel">Party 2</div>
    <table class="info" width="100%">
      <tr><td>Name</td><td><?= htmlspecialchars($c['party2_name'] ?? '—') ?></td></tr>
      <tr><td>Contact</td><td><?= htmlspecialchars($c['party2_contact'] ?? '—') ?></td></tr>
      <tr><td>Address</td><td><?= htmlspecialchars($c['party2_address'] ?? '—') ?></td></tr>
      <tr><td>Vehicle</td><td><?= htmlspecialchars(trim(($c['party2_vehicle_type'] ?? '') . ' ' . ($c['party2_plate'] ?? ''))) ?></td></tr>
      <tr><td>License</td><td><?= htmlspecialchars($c['party2_license'] ?? '—') ?></td></tr>
      <tr><td>Insurance</td><td><?= htmlspecialchars($c['party2_insurance'] ?? '—') ?></td></tr>
    </table>
  </div>
  <?php if ($c['witness_name']): ?>
  <p style="font-size:10.5px;margin:6px 0 0;">
    <strong>Witness:</strong> <?= htmlspecialchars($c['witness_name']) ?>
    <?= $c['witness_contact'] ? ' &mdash; ' . htmlspecialchars($c['witness_contact']) : '' ?>
  </p>
  <?php endif; ?>
</div>

<!-- Damage -->
<div class="sec">
  <div class="sec-title">Damage Summary</div>
  <table class="info" width="100%">
    <tr><td>Party 1 Damage</td><td><?= htmlspecialchars($c['damage_desc_p1'] ?? '—') ?></td></tr>
    <tr><td>Est. Cost (P1)</td><td><?= $c['damage_cost_p1'] ? '&#8369;' . number_format((float)$c['damage_cost_p1'], 2) : '—' ?></td></tr>
    <tr><td>Party 2 Damage</td><td><?= htmlspecialchars($c['damage_desc_p2'] ?? '—') ?></td></tr>
    <tr><td>Est. Cost (P2)</td><td><?= $c['damage_cost_p2'] ? '&#8369;' . number_format((float)$c['damage_cost_p2'], 2) : '—' ?></td></tr>
  </table>
</div>

<!-- Agreement -->
<div class="sec">
  <div class="sec-title">Agreement Terms</div>
  <table class="info" width="100%">
    <tr><td>Resolution</td><td><?= htmlspecialchars($resolution) ?></td></tr>
    <tr><td>Who Pays</td><td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $c['who_pays'] ?? '—'))) ?></td></tr>
    <tr><td>Amount</td><td><?= $amount ?></td></tr>
    <tr><td>Payment Method</td><td><?= htmlspecialchars($payMethod) ?></td></tr>
    <tr><td>Schedule</td><td><?= htmlspecialchars($paySchedule) ?></td></tr>
    <tr><td>Deadline</td><td><?= $deadline ?></td></tr>
  </table>
  <?php if ($c['terms']): ?>
  <div style="margin-top:8px;">
    <div style="font-size:9px;font-weight:700;color:#555;margin-bottom:4px;">Written Terms</div>
    <div class="tbox"><?= nl2br(htmlspecialchars($c['terms'])) ?></div>
  </div>
  <?php endif; ?>
  <?php if ($c['escalation_clause']): ?>
  <div style="margin-top:8px;">
    <div style="font-size:9px;font-weight:700;color:#dc2626;margin-bottom:4px;">Escalation Clause</div>
    <div class="tbox" style="border-left-color:#dc2626;"><?= nl2br(htmlspecialchars($c['escalation_clause'])) ?></div>
  </div>
  <?php endif; ?>
</div>

<!-- Signatures -->
<div class="sec">
  <div class="sec-title">Signatures &amp; Digital Consent</div>
  <table width="100%">
    <tr>
      <td width="49%" style="vertical-align:top; padding-right:6px;">
        <div class="sblock">
          <div class="sname">Party 1: <?= htmlspecialchars($c['party1_name'] ?? '') ?></div>
          <div class="sdate">Signed: <?= $p1Signed ?></div>
          <div class="sconsent">&#10003; Agreed voluntarily &nbsp; &#10003; Understands hidden damage clause</div>
        </div>
      </td>
      <td width="49%" style="vertical-align:top; padding-left:6px;">
        <div class="sblock">
          <div class="sname">Party 2: <?= htmlspecialchars($c['party2_name'] ?? '') ?></div>
          <div class="sdate">Confirmed: <?= $p2Signed ?></div>
          <div class="sconsent">&#10003; Digitally consented via VrakeIT account</div>
        </div>
      </td>
    </tr>
  </table>
</div>

<div class="foot">
  Generated by VrakeIT &middot; Ref: <?= htmlspecialchars($ref) ?><br>
  Digital consent was recorded with timestamps and IP address. This document serves as a binding settlement record.
</div>

</div>
</body>
</html>
<?php
$html = ob_get_clean();

// ── Render PDF with DOMPDF ────────────────────────────────────────
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'VrakeIT-Contract-' . $ref . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$dompdf->stream($filename, ['Attachment' => true]);
