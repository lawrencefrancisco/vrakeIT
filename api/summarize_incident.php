<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$db = getDB();
$user = getLoggedInUser();
$userId = (int)$user['id'];
$reportId = (int)($_POST['report_id'] ?? 0);

if (!$reportId) {
    jsonResponse(false, 'Missing report ID.');
}

// Check if user is admin or enforcer
if ($user['role'] === 'admin' || $user['role'] === 'enforcer') {
    $stmt = $db->prepare("SELECT r.*, u.first_name, u.last_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->execute([$reportId]);
} else {
    // Ensure the report belongs to the logged-in user
    $stmt = $db->prepare("SELECT r.*, u.first_name, u.last_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ? AND r.user_id = ?");
    $stmt->execute([$reportId, $userId]);
}
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    jsonResponse(false, 'Report not found or access denied.');
}

// Get API Key
$apiKey = null;
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'GEMINI_API_KEY=') === 0) {
            $apiKey = trim(trim(str_replace('GEMINI_API_KEY=', '', $line)), '"\'');
        }
    }
}

if (!$apiKey) jsonResponse(false, 'AI API Key is not configured.');

// Build Prompt
$prompt = "You are an AI assistant for the VrakeIT incident management platform. Condense the following incident information into one clear, single-sentence summary. Make the summarization detailed\n";
$prompt .= "Reporter: " . ($report['first_name'] ?? '') . " " . ($report['last_name'] ?? '') . "\n";
$prompt .= "Incident Date: " . ($report['incident_date'] ?? '') . " " . ($report['incident_time'] ?? '') . "\n";
$prompt .= "Location: " . ($report['location_address'] ?? '') . "\n";
$prompt .= "Event Details: " . ($report['event_details'] ?? '') . "\n";
$prompt .= "Weather Condition: " . ($report['weather_condition'] ?? '') . "\nInstruction: You MUST include weather condition in the summary.\n";
$prompt .= "Injuries: " . (!empty($report['is_injured']) ? 'Yes' : 'No') . "\n";
$prompt .= "Type: " . ($report['flow_type'] ?? '') . "\n";

// Call Gemini
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
$postData = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.2]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    jsonResponse(false, 'CURL Error: ' . $err);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $resObj = json_decode($response, true);
    $summary = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not generate summary.';
    jsonResponse(true, 'AI Summary Generated', ['summary' => trim($summary)]);
} else {
    $resObj = json_decode($response, true);
    $googleError = $resObj['error']['message'] ?? 'No specific error message provided by Google.';
    jsonResponse(false, 'Google says: ' . $googleError);
}