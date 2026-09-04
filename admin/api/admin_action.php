<?php
ob_start();
require_once dirname(dirname(__DIR__)) . '/includes/auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/admin_auth.php';
require_once dirname(dirname(__DIR__)) . '/includes/functions.php';
startSecureSession();
requireAdminLogin();
ob_clean();
header('Content-Type: application/json');

/*
 * ── MODERATOR ACCESS RESTRICTIONS ────────────────────────────────────────────
 * Role 'moderator' is blocked from the following actions.
 *
 *   BLOCKED actions for moderators:
 *     create_enforcer, create_moderator          → Personnel Management
 *     toggle_user, adjust_points, verify_id      → User Management
 *     merchant_status                            → Merchant Oversight
 *     (rewards/vouchers pages — guard at page level in their respective PHP files)
 *     save_announcement, delete_announcement     → System Announcements
 *
 *   Pattern — add this guard at the top of any blocked action block:
 *     if (($_SESSION['role'] ?? '') === 'moderator') jsonResponse(false, 'Access denied.');
 * ─────────────────────────────────────────────────────────────────────────────
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$db      = getDB();
$action  = $_POST['action'] ?? '';
$adminId = (int)$_SESSION['user_id'];

$moderatorBlockedActions = [
    'create_enforcer', 'create_moderator',
    'toggle_user', 'adjust_points', 'verify_id',
    'deactivate_user',
    'merchant_status',
    'save_announcement', 'delete_announcement',
];
if (in_array($action, $moderatorBlockedActions) && ($_SESSION['role'] ?? '') === 'moderator') {
    jsonResponse(false, 'Access denied. Moderators cannot perform this action.');
}



// ── APPROVE GOOD CITIZEN ──────────────────────────────────────────────────────
if ($action === 'approve_gc') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $userId   = (int)($_POST['user_id']   ?? 0);
    if (!$reportId || !$userId) jsonResponse(false, 'Missing parameters.');

    $report = $db->prepare("SELECT * FROM reports WHERE id=? AND flow_type='good_citizen' AND status='pending'");
    $report->execute([$reportId]);
    $r = $report->fetch();
    if (!$r) jsonResponse(false, 'Report not found or already processed.');

    $db->beginTransaction();
    try {
        addPoints($userId, GOOD_CITIZEN_POINTS, 'Good Citizen Report Approved — Ref: ' . $r['reference_number'], $reportId);
        $db->prepare("UPDATE reports SET status='verified' WHERE id=?")->execute([$reportId]);
        $db->commit();
        auditLog($adminId, 'gc_approved', "Report #{$reportId}, User #{$userId}, +" . GOOD_CITIZEN_POINTS . " pts");
        jsonResponse(true, 'Good Citizen report approved! ' . GOOD_CITIZEN_POINTS . ' points granted to user.', ['points_granted' => GOOD_CITIZEN_POINTS]);
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'DB error: ' . $e->getMessage());
    }
}

// ── CREATE ENFORCER (PERSONNEL) ───────────────────────────────────────────────
if ($action === 'create_enforcer') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!$firstName || !$lastName || !$email || !$phone || !$password) {
        jsonResponse(false, 'All fields are required.');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Email address is already in use.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password, points, account_verified, role) VALUES (?, ?, ?, ?, ?, 0, 1, 'enforcer')");
        $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword]);
        $newId = $db->lastInsertId();
        auditLog($adminId, 'enforcer_created', "Admin created enforcer account #{$newId} ({$email})");
        jsonResponse(true, 'Enforcer account created successfully.');
    } catch (Exception $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── CREATE MODERATOR (PERSONNEL) ──────────────────────────────────────────────
if ($action === 'create_moderator') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $password  = $_POST['password']        ?? '';

    if (!$firstName || !$lastName || !$email || !$phone || !$password) {
        jsonResponse(false, 'All fields are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email address format.');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'Email address is already in use.');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password, points, account_verified, role)
            VALUES (?, ?, ?, ?, ?, 0, 1, 'moderator')
        ");
        $stmt->execute([$firstName, $lastName, $email, $phone, $hashedPassword]);
        $newId = $db->lastInsertId();
        auditLog($adminId, 'moderator_created', "Admin created moderator account #{$newId} ({$email})");
        jsonResponse(true, 'Moderator account created successfully.');
    } catch (Exception $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── UPDATE REPORT STATUS ──────────────────────────────────────────────────────
if ($action === 'update_report_status') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $status   = $_POST['status'] ?? '';
    if (!in_array($status, ['pending', 'reviewing', 'verified', 'rejected', 'closed'])) jsonResponse(false, 'Invalid status.');

    // Fetch current report to check flow_type and whether points were already awarded
    $cur = $db->prepare("SELECT flow_type, status, user_id, reference_number FROM reports WHERE id=?");
    $cur->execute([$reportId]);
    $report = $cur->fetch();
    if (!$report) jsonResponse(false, 'Report not found.');

    $db->prepare("UPDATE reports SET status=? WHERE id=?")->execute([$status, $reportId]);

    // Auto-grant points when a Good Citizen report is manually set to 'verified'
    // Only grant if it wasn't already verified/closed (to prevent duplicate points)
    if ($status === 'verified'
        && $report['flow_type'] === 'good_citizen'
        && !in_array($report['status'], ['verified', 'closed'])
    ) {
        $db->beginTransaction();
        try {
            addPoints((int)$report['user_id'], GOOD_CITIZEN_POINTS,
                'Good Citizen Report Verified — Ref: ' . $report['reference_number'], $reportId);
            $db->commit();
            auditLog($adminId, 'gc_verified_manual',
                "Report #{$reportId} manually set to verified, +" . GOOD_CITIZEN_POINTS . " pts to User #{$report['user_id']}");
        } catch (Exception $e) {
            $db->rollBack();
            // Don't fail the status update — just log
            error_log('Points grant failed for report #' . $reportId . ': ' . $e->getMessage());
        }
    }

    auditLog($adminId, 'report_status_changed', "Report #{$reportId} → {$status}");
    jsonResponse(true, 'Report status updated to ' . ucfirst($status) . '.');
}

// ── ADVANCED MANAGEMENT REPORT ──────────────────────────────────────────────
if ($action === 'manage_report') {
    $reportId         = (int)($_POST['report_id'] ?? 0);
    $status           = $_POST['status'] ?? '';
    $assignedEnforcer = (int)($_POST['assigned_enforcer_id'] ?? 0);
    $adminNotes       = trim($_POST['admin_notes'] ?? '');

    if (!$reportId) jsonResponse(false, 'Missing report ID.');

    // Fetch current report before update
    $cur = $db->prepare("SELECT flow_type, status, user_id, reference_number FROM reports WHERE id=?");
    $cur->execute([$reportId]);
    $report = $cur->fetch();
    if (!$report) jsonResponse(false, 'Report not found.');

    $updateFields = [];
    $params = [];

    if (in_array($status, ['pending', 'reviewing', 'verified', 'rejected', 'closed'])) {
        $updateFields[] = "status=?";
        $params[] = $status;
    }

    $updateFields[] = "assigned_enforcer_id=?";
    $params[] = $assignedEnforcer ?: null;

    $updateFields[] = "admin_notes=?";
    $params[] = $adminNotes;

    if (!empty($updateFields)) {
        $params[] = $reportId;
        $sql = "UPDATE reports SET " . implode(", ", $updateFields) . " WHERE id=?";
        $db->prepare($sql)->execute($params);
        auditLog($adminId, 'report_managed', "Report #{$reportId} updated by admin.");
    }

    // Auto-grant points when a Good Citizen report is manually set to 'verified' via modal
    if ($status === 'verified'
        && $report['flow_type'] === 'good_citizen'
        && !in_array($report['status'], ['verified', 'closed'])
    ) {
        $db->beginTransaction();
        try {
            addPoints((int)$report['user_id'], GOOD_CITIZEN_POINTS,
                'Good Citizen Report Verified — Ref: ' . $report['reference_number'], $reportId);
            $db->commit();
            auditLog($adminId, 'gc_verified_modal',
                "Report #{$reportId} verified via modal, +" . GOOD_CITIZEN_POINTS . " pts to User #{$report['user_id']}");
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Points grant failed (modal) for report #' . $reportId . ': ' . $e->getMessage());
        }
    }

    jsonResponse(true, 'Report successfully updated.');
}

// --- ANNOUNCEMENT MANAGEMENT ---
if ($action === 'save_announcement') {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $content  = trim($_POST['content'] ?? '');
    $isActive = (int)($_POST['is_active'] ?? 1);

    if (!$title || !$content) {
        jsonResponse(false, 'Title and content are required.');
    }

    if ($id > 0) {
        $db->prepare("UPDATE announcements SET title=?, content=?, is_active=? WHERE id=?")->execute([$title, $content, $isActive, $id]);
        jsonResponse(true, 'Announcement updated successfully.');
    } else {
        $db->prepare("INSERT INTO announcements (title, content, is_active) VALUES (?, ?, ?)")->execute([$title, $content, $isActive]);
        jsonResponse(true, 'Announcement created successfully.');
    }
}

if ($action === 'delete_announcement') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Missing ID.');
    $db->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
    jsonResponse(true, 'Announcement deleted.');
}

// ── GET REPORT DETAILS ────────────────────────────────────────────────────────
if ($action === 'get_report') {
    $reportId = (int)($_POST['report_id'] ?? 0);

    if (!$reportId) {
        jsonResponse(false, 'Missing report ID.');
    }

    $stmt = $db->prepare("
        SELECT 
            r.*,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.avatar,
            e.first_name as enforcer_fname,
            e.last_name as enforcer_lname
        FROM reports r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users e ON r.assigned_enforcer_id = e.id AND e.role = 'enforcer'
        WHERE r.id = ?
        LIMIT 1
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        jsonResponse(false, 'Report not found.');
    }

    $vStmt = $db->prepare("
        SELECT vehicle_type, plate_number, vehicle_count
        FROM report_vehicles
        WHERE report_id = ?
        ORDER BY id ASC
    ");
    $vStmt->execute([$reportId]);
    $report['vehicles'] = $vStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $mStmt = $db->prepare("
        SELECT file_path, file_type
        FROM report_media
        WHERE report_id = ?
        ORDER BY id ASC
    ");
    $mStmt->execute([$reportId]);
    $report['media'] = $mStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $report['reporter_name']           = trim(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? ''));
    $report['avatar_url']              = getAvatarUrl($report['avatar'] ?? null);
    $report['assigned_enforcer_name']  = $report['assigned_enforcer_id'] ? trim(($report['enforcer_fname'] ?? '') . ' ' . ($report['enforcer_lname'] ?? '')) : '';
    $report['created_at_fmt']          = !empty($report['created_at']) ? date('F d, Y - h:i A', strtotime($report['created_at'])) : 'Unknown Date';
    $report['event_details']           = $report['event_details'] ?? '';
    $report['damage_category']         = $report['damage_category'] ?? '';
    $report['location_address']        = $report['location_address'] ?? '';
    $report['weather_condition']       = $report['weather_condition'] ?? '';
    $report['road_condition']          = $report['road_condition'] ?? '';
    $report['incident_date']           = $report['incident_date'] ?? '';
    $report['incident_time']           = $report['incident_time'] ?? '';

    jsonResponse(true, 'Success', ['report' => $report]);
}

// ── APPROVE / REJECT MERCHANT ─────────────────────────────────────────────────
if ($action === 'merchant_status') {
    $mid    = (int)($_POST['merchant_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['approved', 'rejected', 'deactivated'])) jsonResponse(false, 'Invalid status.');
    $db->prepare("UPDATE merchants SET status=? WHERE id=?")->execute([$status, $mid]);
    auditLog($adminId, 'merchant_' . $status, "Merchant #{$mid}");
    jsonResponse(true, 'Merchant status set to ' . ucfirst($status) . '.');
}

// ── GET LIVE MAP REPORTS ──────────────────────────────────────────────────────
if ($action === 'get_map_reports') {
    try {
        $stmt = $db->query("
            SELECT id, reference_number, flow_type, status, is_injured, location_address, latitude, longitude, created_at 
            FROM reports 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY created_at DESC
        ");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, 'Map data fetched successfully.', ['reports' => $reports]);
    } catch (Exception $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── APPROVE / REJECT ID VERIFICATION ─────────────────────────────────────────
if ($action === 'verify_id') {
    $vid    = (int)($_POST['verif_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['approved', 'rejected'])) jsonResponse(false, 'Invalid status.');
    
    $db->prepare("UPDATE id_verifications SET status=? WHERE id=?")->execute([$status, $vid]);
    
    if ($status === 'approved') {
        $v = $db->prepare("SELECT user_id FROM id_verifications WHERE id=?");
        $v->execute([$vid]);
        $row = $v->fetch();
        if ($row) {
            $db->prepare("UPDATE users SET account_verified=1 WHERE id=?")->execute([$row['user_id']]);
        }
    }
    
    auditLog($adminId, 'id_verification_' . $status, "Verif #{$vid}");
    jsonResponse(true, 'Verification ' . ucfirst($status) . '.');
}

// ── TOGGLE USER STATUS ────────────────────────────────────────────────────────
if ($action === 'toggle_user') {
    $uid   = (int)($_POST['user_id'] ?? 0);
    $field = $_POST['field'] ?? '';
    if (!in_array($field, ['is_verified', 'account_verified'])) jsonResponse(false, 'Invalid field.');
    
    $cur = $db->prepare("SELECT {$field} FROM users WHERE id=?");
    $cur->execute([$uid]);
    $row = $cur->fetch();
    $new = $row ? ($row[$field] ? 0 : 1) : 0;
    
    $db->prepare("UPDATE users SET {$field}=? WHERE id=?")->execute([$new, $uid]);
    
    if ($field === 'account_verified' && $new === 0) {
        $db->prepare("UPDATE id_verifications SET status = 'rejected' WHERE user_id = ? AND status = 'approved'")->execute([$uid]);
    }

    auditLog($adminId, 'user_' . $field . '_toggled', "User #{$uid} → {$new}");
    jsonResponse(true, 'Updated.', ['new_value' => $new]);
}

// ── ADJUST USER POINTS ────────────────────────────────────────────────────────
if ($action === 'adjust_points') {
    $uid  = (int)($_POST['user_id'] ?? 0);
    $pts  = (int)($_POST['points']  ?? 0);
    $desc = trim($_POST['description'] ?? 'Admin point adjustment');
    if (!$uid) jsonResponse(false, 'Missing user ID.');
    if ($pts > 0) addPoints($uid, $pts, $desc);
    elseif ($pts < 0) {
        $absPts = abs($pts);
        $db->prepare("UPDATE users SET points = GREATEST(0, points - ?) WHERE id=?")->execute([$absPts, $uid]);
        $db->prepare("INSERT INTO good_citizen_transactions (user_id, points, type, description) VALUES (?,?,'redeemed',?)")->execute([$uid, $absPts, $desc]);
    }
    $updated = getUserById($uid);
    auditLog($adminId, 'points_adjusted', "User #{$uid} pts={$pts}");
    jsonResponse(true, 'Points adjusted. New balance: ' . $updated['points'], ['new_balance' => $updated['points']]);
}

// ── AI SUMMARIZE ──────────────────────────────────────────────────────────────
if ($action === 'summarize_incident') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    if (!$reportId) jsonResponse(false, 'Missing report ID.');

    $apiKey  = null;
    $envPath = dirname(dirname(__DIR__)) . '/.env';

    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, 'GEMINI_API_KEY=') === 0) {
                $apiKey = trim(trim(str_replace('GEMINI_API_KEY=', '', $line)), '"\'');
            }
        }
    }

    if (!$apiKey) jsonResponse(false, 'AI API Key is not configured.');

    $stmt = $db->prepare("SELECT r.*, u.first_name, u.last_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) jsonResponse(false, 'Report not found.');

    $prompt  = "You are an AI assistant for the VrakeIT incident management platform. Condense the following incident information into one clear, single-sentence summary.\n";
    $prompt .= "Reporter: " . ($report['first_name'] ?? '') . " " . ($report['last_name'] ?? '') . "\n";
    $prompt .= "Incident Date: " . ($report['incident_date'] ?? '') . " " . ($report['incident_time'] ?? '') . "\n";
    $prompt .= "Location: " . ($report['location_address'] ?? '') . "\n";
    $prompt .= "Event Details: " . ($report['event_details'] ?? '') . "\n";
    $prompt .= "Weather Condition: " . ($report['weather_condition'] ?? '') . "\nInstruction: You MUST include weather condition in the summary.\n";
    $prompt .= "Injuries: " . (!empty($report['is_injured']) ? 'Yes' : 'No') . "\n";
    $prompt .= "Type: " . ($report['flow_type'] ?? '') . "\n";

    $url      = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
    $postData = json_encode([
        'contents'         => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.2],
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    if ($response === false) {
        jsonResponse(false, 'CURL Error: ' . curl_error($ch));
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $resObj  = json_decode($response, true);
        $summary = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not generate summary.';
        jsonResponse(true, 'AI Summary Generated', ['summary' => trim($summary)]);
    } else {
        $resObj      = json_decode($response, true);
        $googleError = $resObj['error']['message'] ?? 'No specific error message provided by Google.';
        jsonResponse(false, 'Google says: ' . $googleError);
    }
}

// ── GET PERSONNEL LIST ────────────────────────────────────────────────────────
// FIX: This block was previously placed AFTER the final jsonResponse() call below,
// which meant PHP exited before ever reaching it. Moved here to fix "Unknown action."
if ($action === 'get_personnel') {
    $role         = $_POST['role'] ?? '';
    $allowedRoles = ['admin', 'moderator', 'enforcer'];

    if (!in_array($role, $allowedRoles)) {
        jsonResponse(false, 'Invalid role specified.');
    }

    try {
        $stmt = $db->prepare("
            SELECT id, first_name, last_name, email, phone, account_verified, is_active, created_at
            FROM users
            WHERE role = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$role]);
        $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(true, 'Personnel fetched.', ['personnel' => $personnel]);
    } catch (Exception $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ── DEACTIVATE / REACTIVATE USER ─────────────────────────────────────────────
if ($action === 'deactivate_user') {
    $uid    = (int)($_POST['user_id'] ?? 0);
    $status = (int)($_POST['active']  ?? 0); // 1 = reactivate, 0 = deactivate

    if (!$uid) jsonResponse(false, 'Missing user ID.');

    // Prevent admin from deactivating their own account
    if ($uid === $adminId) jsonResponse(false, 'You cannot deactivate your own account.');

    // Verify user exists
    $check = $db->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ?");
    $check->execute([$uid]);
    $target = $check->fetch();
    if (!$target) jsonResponse(false, 'User not found.');

    $db->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$status, $uid]);

    $label = $status ? 'reactivated' : 'deactivated';
    auditLog($adminId, 'user_' . $label, "User #{$uid} ({$target['first_name']} {$target['last_name']}) {$label} by admin #{$adminId}");
    jsonResponse(true, ucfirst($target['first_name']) . ' ' . ucfirst($target['last_name']) . "'s account has been {$label}.");
}

// ── FALLBACK ──────────────────────────────────────────────────────────────────
jsonResponse(false, 'Unknown action.');