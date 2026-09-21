<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
header('Content-Type: application/json');
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$userId = (int)$_SESSION['user_id'];
$db     = getDB();
$data   = $_POST;

$flowType = 'standard'; // Enforcer reports are always standard
$refNum = generateReferenceNumber();

$stmt = $db->prepare("
    INSERT INTO reports (
        user_id, reference_number, flow_type, reporter_role,
        is_injured, injured_count, injury_severity, has_deceased,
        enforcer_type, enforcer_documented, emergency_services,
        is_safe, incident_date, incident_time,
        location_lat, location_lng, location_address,
        has_other_parties, other_parties_present,
        weather_condition, road_condition, insurance_type,
        event_details, damage_category, parties,
        assigned_enforcer_id, status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')
");

$emergencyServices = !empty($data['emergency_services']) ? json_encode((array)$data['emergency_services']) : null;

try {
    $stmt->execute([
        $userId,
        $refNum,
        $flowType,
        'enforcer', // Enforcer-submitted reports are role='enforcer'
        (int)($data['is_injured'] ?? 0),
        in_array($data['injured_count'] ?? '', ['none','one','multiple']) ? $data['injured_count'] : null,
        !empty($data['injury_severity']) ? $data['injury_severity'] : null,
        isset($data['has_deceased']) && $data['has_deceased'] !== '' ? (int)$data['has_deceased'] : null,
        sanitize($data['enforcer_type'] ?? 'TMO'),
        $data['enforcer_documented'] ?? null,
        $emergencyServices,
        isset($data['is_safe']) ? (int)$data['is_safe'] : null,
        $data['incident_date'] ?? null,
        $data['incident_time'] ?? null,
        $data['location_lat'] ?? null,
        $data['location_lng'] ?? null,
        sanitize($data['location_address'] ?? null),
        (int)($data['has_other_parties'] ?? 0),
        isset($data['other_parties_present']) ? (int)$data['other_parties_present'] : null,
        sanitize($data['weather_condition'] ?? null),
        sanitize($data['road_condition'] ?? null),
        $data['insurance_type'] ?? null,
        sanitize($data['event_details'] ?? null),
        sanitize($data['damage_category'] ?? null),
        in_array($data['parties'] ?? '', ['self','two','multiple']) ? $data['parties'] : null,
        $userId, // assigned_enforcer_id = the enforcer who filed it
    ]);
} catch (Exception $e) {
    jsonResponse(false, 'Failed to save report: ' . $e->getMessage());
}

$reportId = (int)$db->lastInsertId();

// Insert vehicles
if (!empty($data['vehicle_types']) && is_array($data['vehicle_types'])) {
    $vStmt = $db->prepare("INSERT INTO report_vehicles (report_id, vehicle_type, plate_number, vehicle_count) VALUES (?,?,?,?)");
    foreach ($data['vehicle_types'] as $i => $vType) {
        $plate = sanitize($data['plate_numbers'][$i] ?? '');
        $count = (int)($data['vehicle_counts'][$i] ?? 1);
        $vStmt->execute([$reportId, sanitize($vType), $plate, $count]);
    }
}

// Handle media uploads
if (!empty($_FILES['media']['name'][0])) {
    $mediaDir = UPLOAD_DIR . 'reports/' . $reportId . '/';
    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

    $mStmt   = $db->prepare("INSERT INTO report_media (report_id, file_name, file_path, file_type) VALUES (?,?,?,?)");
    $allowed = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES);
    $files   = $_FILES['media'];
    $cnt     = count($files['name']);

    for ($i = 0; $i < $cnt; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($files['size'][$i] > MAX_UPLOAD_SIZE) continue;
        if (!in_array($files['type'][$i], $allowed)) continue;

        $ext      = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $newName  = 'media_' . $i . '_' . time() . '.' . $ext;
        $fullPath = $mediaDir . $newName;

        if (move_uploaded_file($files['tmp_name'][$i], $fullPath)) {
            $relPath  = 'reports/' . $reportId . '/' . $newName;
            $fileType = str_starts_with($files['type'][$i], 'video/') ? 'video' : 'image';
            $mStmt->execute([$reportId, $files['name'][$i], $relPath, $fileType]);
        }
    }
}

auditLog($userId, 'enforcer_report_submitted', "Ref: {$refNum}, Enforcer ID: {$userId}");
jsonResponse(true, 'Report submitted successfully!', ['reference_number' => $refNum]);
