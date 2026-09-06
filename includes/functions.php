<?php
require_once __DIR__ . '/db.php';

function sanitize(?string $value): ?string {
    $value = trim($value ?? '');

    if ($value === '') {
        return null;
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function generateOTP(): string {
    return str_pad((string)random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
}

function generateReferenceNumber(): string {
    return 'VR' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

function auditLog(?int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently fail audit logs so they don't break user flow
    }
}

function getUserById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function addPoints(int $userId, int $points, string $description, ?int $reportId = null): void {
    $db = getDB();
    $db->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points, $userId]);
    $db->prepare("INSERT INTO good_citizen_transactions (user_id, points, type, description) VALUES (?, ?, 'earned', ?)")
       ->execute([$userId, $points, $description]);
}

function formatDate(string $date): string {
    return date('F d, Y', strtotime($date));
}

function formatDateTime(string $datetime): string {
    return date('F d, Y h:i A', strtotime($datetime));
}

function getStatusBadge(string $status): string {
    return match($status) {
        'pending'   => '<span class="badge-status pending">Pending</span>',
        'reviewing' => '<span class="badge-status reviewing">Reviewing</span>',
        'closed'    => '<span class="badge-status closed">Closed</span>',
        default     => '<span class="badge-status pending">Pending</span>',
    };
}

function getAvatarUrl(?string $avatar): string {
    if ($avatar && file_exists(UPLOAD_DIR . 'avatars/' . $avatar)) {
        return UPLOAD_URL . 'avatars/' . $avatar;
    }
    return BASE_URL . '/assets/img/default-avatar.png';
}

function isValidPhone(string $phone): bool {
    return preg_match('/^(09|\+639)\d{9}$/', $phone) === 1;
}

function formatPhone(string $phone): string {
    // Convert 09XXXXXXXXX to +639XXXXXXXXX for philSMS
    if (str_starts_with($phone, '09')) {
        return '+63' . substr($phone, 1);
    }
    return $phone;
}
