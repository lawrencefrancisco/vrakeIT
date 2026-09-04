<?php
require_once __DIR__ . '/../config/config.php';

function sendSMS(string $phone, string $message): array {
    $phone = formatPhone($phone);

    $payload = json_encode([
        'recipient' => $phone,
        'sender_id' => PHILSMS_SENDER_ID,
        'type'      => 'plain',
        'message'   => $message,
    ]);

    $ch = curl_init(PHILSMS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PHILSMS_TOKEN,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'SMS cURL error: ' . $curlError];
    }

    $decoded = json_decode($response, true);
    if ($httpCode === 200 && isset($decoded['data'])) {
        return ['success' => true, 'message' => 'SMS sent successfully.'];
    }

    return ['success' => false, 'message' => $decoded['message'] ?? 'SMS sending failed.'];
}

function sendOtpSMS(string $phone, string $otp): array {
    $message = "Your VrakeIT verification code is: {$otp}. Valid for " . OTP_EXPIRY_MINUTES . " minutes. Do not share this code.";
    return sendSMS($phone, $message);
}
