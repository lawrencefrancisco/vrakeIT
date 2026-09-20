<?php
/**
 * api/submit_contract.php
 * Saves (or updates) a settlement contract draft, and on final submit
 * sends an email invite to Party 2.
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$data   = $_POST;

// ── Determine action ──────────────────────────────────────────────
$action = $data['action'] ?? 'submit'; // 'draft' or 'submit'

// ── Check if updating existing draft ─────────────────────────────
$existingRef = sanitize($data['existing_ref'] ?? '');
$existingId  = null;
if ($existingRef) {
    $ex = $db->prepare("SELECT id FROM contracts WHERE reference_number = ? AND party1_user_id = ? AND status = 'draft'");
    $ex->execute([$existingRef, $userId]);
    $row = $ex->fetch();
    if ($row) $existingId = (int)$row['id'];
}

// ── Build field map ───────────────────────────────────────────────
$party1User = getUserById($userId);
$refNum     = $existingRef ?: generateReferenceNumber();

$fields = [
    'reference_number'   => $refNum,
    'user_id'            => $userId,
    'party1_user_id'     => $userId,
    'type'               => 'contract',
    // Step 1 — What Happened
    'report_id'          => !empty($data['report_id'])       ? (int)$data['report_id']       : null,
    'incident_type'      => sanitize($data['incident_type']  ?? null),
    'versions_agree'     => isset($data['versions_agree'])   ? (int)$data['versions_agree']  : null,
    'version_p1'         => sanitize($data['version_p1']     ?? null),
    'version_p2'         => sanitize($data['version_p2']     ?? null),
    'fault'              => sanitize($data['fault']          ?? null),
    'description'        => sanitize($data['description']    ?? null),
    // Step 2 — Parties & Vehicles
    'party1_name'        => sanitize($data['party1_name']    ?? ($party1User['first_name'] . ' ' . $party1User['last_name'])),
    'party1_contact'     => sanitize($data['party1_contact'] ?? $party1User['phone'] ?? null),
    'party1_address'     => sanitize($data['party1_address'] ?? null),
    'party1_license'     => sanitize($data['party1_license'] ?? null),
    'party1_plate'       => sanitize($data['party1_plate']   ?? null),
    'party1_vehicle_type'=> sanitize($data['party1_vehicle_type'] ?? null),
    'party1_insurance'   => sanitize($data['party1_insurance']    ?? null),
    'party2_name'        => sanitize($data['party2_name']    ?? null),
    'party2_email'       => sanitize($data['party2_email']   ?? null),
    'party2_contact'     => sanitize($data['party2_contact'] ?? null),
    'party2_address'     => sanitize($data['party2_address'] ?? null),
    'party2_license'     => sanitize($data['party2_license'] ?? null),
    'party2_plate'       => sanitize($data['party2_plate']   ?? null),
    'party2_vehicle_type'=> sanitize($data['party2_vehicle_type'] ?? null),
    'party2_insurance'   => sanitize($data['party2_insurance']    ?? null),
    'witness_name'       => sanitize($data['witness_name']   ?? null),
    'witness_contact'    => sanitize($data['witness_contact']?? null),
    // Step 3 — Damage
    'damage_desc_p1'     => sanitize($data['damage_desc_p1'] ?? null),
    'damage_cost_p1'     => is_numeric($data['damage_cost_p1'] ?? '') ? (float)$data['damage_cost_p1'] : null,
    'damage_desc_p2'     => sanitize($data['damage_desc_p2'] ?? null),
    'damage_cost_p2'     => is_numeric($data['damage_cost_p2'] ?? '') ? (float)$data['damage_cost_p2'] : null,
    // Step 4 — Agreement
    'resolution_type'    => sanitize($data['resolution_type']    ?? null),
    'who_pays'           => sanitize($data['who_pays']           ?? null),
    'amount'             => is_numeric($data['amount'] ?? '')    ? (float)$data['amount']    : null,
    'payment_method'     => sanitize($data['payment_method']     ?? null),
    'payment_schedule'   => sanitize($data['payment_schedule']   ?? null),
    'payment_deadline'   => !empty($data['payment_deadline'])    ? $data['payment_deadline'] : null,
    'escalation_clause'  => sanitize($data['escalation_clause']  ?? null),
    'terms'              => sanitize($data['terms']              ?? null),
    // Step 5 — Consent
    'consent_voluntary'     => isset($data['consent_voluntary'])     ? 1 : null,
    'consent_hidden_damage' => isset($data['consent_hidden_damage']) ? 1 : null,
    'status'             => $action === 'submit' ? 'waiting' : 'draft',
    'updated_at'         => date('Y-m-d H:i:s'),
];

// ── Handle invite token for final submit ──────────────────────────
$inviteToken = null;
if ($action === 'submit') {
    // Validate Party 2 email
    $p2Email = $fields['party2_email'] ?? '';
    if (!filter_var($p2Email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'A valid Party 2 email is required to send the invite.');
    }

    // Check Party 2 has a VrakeIT account
    $p2Stmt = $db->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? AND role = 'user'");
    $p2Stmt->execute([$p2Email]);
    $p2User = $p2Stmt->fetch();
    if (!$p2User) {
        jsonResponse(false, 'Party 2\'s email is not registered in VrakeIT. They must create an account first.');
    }
    if ((int)$p2User['id'] === $userId) {
        jsonResponse(false, 'You cannot invite yourself as Party 2.');
    }

    $fields['party2_user_id'] = (int)$p2User['id'];
    $inviteToken = bin2hex(random_bytes(32));
    $fields['invite_token']      = $inviteToken;
    $fields['invite_sent_at']    = date('Y-m-d H:i:s');
    $fields['invite_expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
    $fields['p1_signed_at']      = date('Y-m-d H:i:s');
}

// ── Upsert ────────────────────────────────────────────────────────
try {
    if ($existingId) {
        // UPDATE
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
        $stmt = $db->prepare("UPDATE `contracts` SET $sets WHERE id = ?");
        $stmt->execute([...array_values($fields), $existingId]);
    } else {
        // INSERT
        $fields['created_at'] = date('Y-m-d H:i:s');
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $db->prepare("INSERT INTO `contracts` ($cols) VALUES ($phs)");
        $stmt->execute(array_values($fields));
    }
} catch (PDOException $e) {
    jsonResponse(false, 'Database error: ' . $e->getMessage());
}

// ── Send invite email ─────────────────────────────────────────────
if ($action === 'submit' && $inviteToken && isset($p2User)) {
    $p1Name     = trim(($party1User['first_name'] ?? '') . ' ' . ($party1User['last_name'] ?? ''));
    $p2Name     = $p2User['first_name'] . ' ' . $p2User['last_name'];
    $confirmUrl = rtrim(BASE_URL, '/') . '/contract_confirm.php?token=' . $inviteToken;
    $emailBody  = getContractInviteEmailBody($p2Name, $p1Name, $refNum, $confirmUrl);
    sendEmail($p2Email, $p2Name, "VrakeIT: Settlement Contract Invitation — $refNum", $emailBody);
}

auditLog($userId, 'contract_' . $action, "Ref: $refNum");
jsonResponse(true, $action === 'submit' ? 'Contract submitted and invite sent!' : 'Draft saved.', [
    'reference_number' => $refNum,
    'action'           => $action,
]);
