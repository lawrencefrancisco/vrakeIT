<?php
// Load .env variables into $_ENV (simple key=value parser)
$_envFile = dirname(__DIR__) . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_envLine) {
        $_envLine = trim($_envLine);
        if ($_envLine === '' || str_starts_with($_envLine, '#')) continue;
        if (str_contains($_envLine, '=')) {
            [$_envKey, $_envVal] = explode('=', $_envLine, 2);
            $_envKey = trim($_envKey);
            $_envVal = trim(trim($_envVal), "\"'"); // Strip surrounding quotes
            if (!isset($_ENV[$_envKey])) {
                $_ENV[$_envKey] = $_envVal;
                putenv("{$_envKey}={$_envVal}");
            }
        }
    }
    unset($_envFile, $_envLine, $_envKey, $_envVal);
}
// ==========================================
// VrakeIT - Application Configuration
// ==========================================

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'vrakeit');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- App Settings ---
define('APP_NAME', 'VrakeIT');

// Dynamic BASE_URL: works on localhost AND behind ngrok / any reverse proxy.
// Reads X-Forwarded-Proto set by ngrok to pick up https:// automatically.
if (!defined('BASE_URL')) {
    $__proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
        ? $_SERVER['HTTP_X_FORWARDED_PROTO']
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $__host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Detect the sub-path by finding where 'vrakeIT' sits in SCRIPT_FILENAME
    $__root  = dirname(__DIR__);  // absolute path to project root
    $__docroot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $__subpath = str_replace(['\\', $__docroot], ['/', ''], $__root);
    define('BASE_URL', $__proto . '://' . $__host . $__subpath);
    unset($__proto, $__host, $__root, $__docroot, $__subpath);
}

define('UPLOAD_DIR', dirname(__DIR__) . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// --- Private (non-web-accessible) ID uploads ---
define('ID_UPLOAD_DIR', dirname(__DIR__) . '/private/id_uploads/');

// --- OCR / Identity Verification ---
define('OCR_PROVIDER', 'gemini');               // Swap here to change provider
define('OCR_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('OCR_MAX_ATTEMPTS', 3);                  // Max OCR retries per user
define('OCR_NAME_MATCH_THRESHOLD', 0.72);       // Fuzzy name match minimum

// --- OTP Settings ---
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_LENGTH', 6);

// --- philSMS API ---
define('PHILSMS_TOKEN', '2780|rU9yZFm5NVaDk2lCQU0EkrxxNRcCwJUgHcMGkFuZ44ff09d3');
define('PHILSMS_URL', 'https://dashboard.philsms.com/api/v3/sms/send');
define('PHILSMS_SENDER_ID', 'PhilSMS');

// --- Email (Gmail SMTP) ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'vrakeit@gmail.com');
define('SMTP_PASS', 'tyfu izuw ixpo azrt');
define('SMTP_FROM_NAME', 'VrakeIT System');

// --- Good Citizen Points ---
define('GOOD_CITIZEN_POINTS', 50);

// --- Allowed upload types ---
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/quicktime', 'video/x-msvideo']);
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB
