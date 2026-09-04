<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSecureSession();

// Allow both users and admins to access this endpoint
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.');
}

$db = getDB();
$ref = $_POST['reference_number'] ?? '';

if (!$ref) {
    jsonResponse(false, 'Missing reference number.');
}

$stmt = $db->prepare("SELECT * FROM contracts WHERE reference_number = ?");
$stmt->execute([$ref]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    jsonResponse(false, 'Contract not found.');
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
$prompt = "You are an AI assistant for the VrakeIT incident management platform. Condense the following settlement contract into one clear, single-sentence summary.\n";
$prompt .= "Reference: " . ($contract['reference_number'] ?? '') . "\n";
$prompt .= "Status: " . ($contract['status'] ?? '') . "\n";
$prompt .= "Party 1: " . ($contract['party1_name'] ?? '') . "\n";
$prompt .= "Party 2: " . ($contract['party2_name'] ?? '') . "\n";
$prompt .= "Agreed Amount: PHP " . ($contract['amount'] ?? '0') . "\n";
$prompt .= "Incident Description: " . ($contract['description'] ?? '') . "\n";
$prompt .= "Agreed Terms: " . ($contract['terms'] ?? '') . "\n";

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