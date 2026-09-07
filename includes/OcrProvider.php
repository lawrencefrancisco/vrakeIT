<?php
// ======================================================================
// VrakeIT – OcrProvider.php  (Gemini Vision Edition)
// Uses Google Gemini 2.5 Flash vision API to extract identity fields
// from Philippine government-issued ID photos.
// Dramatically more accurate than local Tesseract for real-world IDs.
// ======================================================================

class OcrProvider
{
    // ── Fuzzy match threshold (0.0–1.0)
    private const NAME_MATCH_THRESHOLD = 0.70;

    // ── Gemini API endpoint
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: extractFromImage
    // Sends the image to Gemini Vision and returns structured OCR data.
    // ─────────────────────────────────────────────────────────────────
    public static function extractFromImage(string $imagePath): array
    {
        $blank = [
            'success'    => false,
            'name'       => '',
            'dob'        => '',
            'doc_number' => '',
            'expiry'     => '',
            'doc_type'   => '',
            'is_gov_id'  => false,
            'readable'   => false,
            'confidence' => 0.0,
            'raw_text'   => '',
            'error'      => '',
            'ocr_engine' => 'gemini_vision',
        ];

        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            return array_merge($blank, ['error' => 'Image file not found or unreadable.']);
        }

        $apiKey = self::loadApiKey();
        if (!$apiKey) {
            return array_merge($blank, ['error' => 'Gemini API key not configured. Please add GEMINI_API_KEY to your .env file.']);
        }

        // ── Encode the image as base64
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType  = self::getMimeType($imagePath);

        // ── Build the Gemini Vision prompt
        $prompt = <<<PROMPT
You are an expert document scanner for the Philippine government. Analyze this ID image carefully.

Your task is to extract the following fields EXACTLY as they appear on the ID, and return a valid JSON object. Do NOT include markdown, explanations, or any text outside the JSON.

Required JSON format:
{
  "is_government_id": true or false,
  "doc_type": "The type of ID (e.g., Driver's License, Philippine National ID, Passport, SSS ID, UMID, PhilHealth ID, Voter's ID, PRC ID, GSIS ID, Postal ID, Senior Citizen ID, PWD ID, TIN ID)",
  "full_name": "The complete name of the holder as it appears on the ID (First Middle Last format preferred)",
  "date_of_birth": "The date of birth in YYYY-MM-DD format. If the format on the card is different (e.g., MM/DD/YYYY, DD-MON-YYYY), convert it to YYYY-MM-DD.",
  "id_number": "The ID or license number",
  "expiry_date": "The expiry date in YYYY-MM-DD format, or empty string if not present",
  "confidence": a float from 0.0 to 1.0 representing how clearly you could read the ID (1.0 = perfectly clear, 0.0 = completely unreadable),
  "readable": true if the ID text is clear enough to read reliably, false otherwise,
  "notes": "Any issues with image quality (e.g., blurry, glare, partially obscured)"
}

IMPORTANT RULES:
- If the image is NOT a government ID at all (e.g., it's a selfie, random photo, etc.), set is_government_id to false.
- If a field cannot be found, use an empty string "".
- For full_name: Philippine IDs sometimes show name in "LAST NAME, FIRST NAME MIDDLE NAME" format - please reorder to "First Middle Last".
- Return ONLY the raw JSON object. No markdown, no code blocks.
PROMPT;

        // ── Call Gemini Vision API
        $payload = json_encode([
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data'      => $imageData,
                        ]
                    ]
                ]
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,  // Low temp = more precise, less creative
                'responseMimeType' => 'application/json',
            ]
        ]);

        $ch = curl_init(self::GEMINI_API_URL . '?key=' . $apiKey);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErr) {
            return array_merge($blank, ['error' => 'Network error: ' . $curlErr]);
        }

        if ($httpCode !== 200) {
            $errObj = json_decode($response, true);
            $errMsg = $errObj['error']['message'] ?? "HTTP $httpCode from Gemini API.";
            return array_merge($blank, ['error' => 'Gemini API error: ' . $errMsg]);
        }

        // ── Parse Gemini's response
        $resObj = json_decode($response, true);
        $rawText = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Clean up any markdown code blocks the model might still return
        $rawText = preg_replace('/^```json\s*/i', '', trim($rawText));
        $rawText = preg_replace('/```\s*$/i', '', $rawText);
        $rawText = trim($rawText);

        $extracted = json_decode($rawText, true);

        if (!$extracted || json_last_error() !== JSON_ERROR_NONE) {
            return array_merge($blank, [
                'error'    => 'Could not parse AI response as JSON. Raw: ' . substr($rawText, 0, 200),
                'raw_text' => $rawText,
            ]);
        }

        $confidence = (float)($extracted['confidence'] ?? 0.0);
        $isGovId    = (bool)($extracted['is_government_id'] ?? false);
        $readable   = (bool)($extracted['readable'] ?? false);
        $name       = trim($extracted['full_name'] ?? '');
        $dob        = self::normalizeDate($extracted['date_of_birth'] ?? '');
        $docType    = trim($extracted['doc_type'] ?? '');
        $docNumber  = trim($extracted['id_number'] ?? '');
        $expiry     = self::normalizeDate($extracted['expiry_date'] ?? '');

        return [
            'success'    => true,
            'name'       => $name,
            'dob'        => $dob,
            'doc_number' => $docNumber,
            'expiry'     => $expiry,
            'doc_type'   => $docType,
            'is_gov_id'  => $isGovId,
            'readable'   => $readable,
            'confidence' => $confidence,
            'raw_text'   => $rawText,
            'error'      => '',
            'ocr_engine' => 'gemini_vision',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: validateDocument
    // Checks whether the extracted data represents a valid, usable doc.
    // ─────────────────────────────────────────────────────────────────
    public static function validateDocument(array $extracted): array
    {
        if (!($extracted['success'] ?? false)) {
            return ['valid' => false, 'reason' => $extracted['error'] ?? 'OCR extraction failed.'];
        }

        if (!($extracted['readable'] ?? false)) {
            return ['valid' => false, 'reason' => 'The document image is not clearly readable. Please upload a well-lit, focused photo with no glare.'];
        }

        if (!($extracted['is_gov_id'] ?? false)) {
            return ['valid' => false, 'reason' => 'The uploaded image does not appear to be a government-issued ID.'];
        }

        $confidence = (float)($extracted['confidence'] ?? 0.0);
        if ($confidence < 0.40) {
            return ['valid' => false, 'reason' => 'The document could not be read with sufficient confidence (' . round($confidence * 100) . '%). Please upload a clearer image.'];
        }

        if (empty(trim($extracted['name'] ?? ''))) {
            return ['valid' => false, 'reason' => 'Name could not be extracted from the document. Ensure the full name is clearly visible.'];
        }

        if (empty(trim($extracted['dob'] ?? ''))) {
            return ['valid' => false, 'reason' => 'Date of birth could not be extracted from the document. Ensure your birthdate is clearly visible on the ID.'];
        }

        // Expiry check
        $expiry = trim($extracted['expiry'] ?? '');
        if ($expiry !== '') {
            $expiryTs = strtotime($expiry);
            if ($expiryTs !== false && $expiryTs < time()) {
                return ['valid' => false, 'reason' => 'The provided ID document has expired. Please upload a valid, non-expired document.'];
            }
        }

        return ['valid' => true, 'reason' => ''];
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: matchIdentity
    // Compares OCR data against user-provided registration data.
    // ─────────────────────────────────────────────────────────────────
    public static function matchIdentity(array $extracted, array $registrationData): array
    {
        $ocrName  = self::normalizeName($extracted['name'] ?? '');
        $regFirst = self::normalizeName($registrationData['first_name'] ?? '');
        $regLast  = self::normalizeName($registrationData['last_name'] ?? '');
        $regFull  = self::normalizeName($registrationData['first_name'] . ' ' . $registrationData['last_name']);

        $nameScore = self::nameMatchScore($ocrName, $regFirst, $regLast, $regFull);
        $nameMatch = $nameScore >= self::NAME_MATCH_THRESHOLD;

        $ocrDob   = self::normalizeDate($extracted['dob'] ?? '');
        $regDob   = self::normalizeDate($registrationData['birthdate'] ?? '');
        $dobMatch = ($ocrDob !== '' && $regDob !== '' && $ocrDob === $regDob);

        $overallScore = ($nameScore + ($dobMatch ? 1.0 : 0.0)) / 2.0;
        $overallMatch = $nameMatch && $dobMatch;

        return [
            'name_match'    => $nameMatch,
            'dob_match'     => $dobMatch,
            'overall_match' => $overallMatch,
            'score'         => round($overallScore, 4),
            'name_score'    => round($nameScore, 4),
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ════════════════════════════════════════════════════════════════

    private static function loadApiKey(): ?string
    {
        $envPath = dirname(__DIR__) . '/.env';
        if (!file_exists($envPath)) return null;

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), 'GEMINI_API_KEY=')) {
                $val = substr(trim($line), strlen('GEMINI_API_KEY='));
                return trim($val, '"\'');
            }
        }
        return null;
    }

    private static function getMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };
    }

    private static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z\s]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if (!$date) return '';
        $ts = strtotime($date);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    private static function nameMatchScore(
        string $ocrName,
        string $regFirst,
        string $regLast,
        string $regFull
    ): float {
        if (!$ocrName || !$regFull) return 0.0;

        $candidates = [
            $regFull,
            $regLast . ' ' . $regFirst,
            $regFirst,
            $regLast,
        ];

        $best = 0.0;
        foreach ($candidates as $cand) {
            if (!$cand) continue;

            similar_text($ocrName, $cand, $pct);
            $score = $pct / 100.0;

            $ocrTokens  = explode(' ', $ocrName);
            $firstMatch = in_array($regFirst, $ocrTokens) || str_contains($ocrName, $regFirst);
            $lastMatch  = in_array($regLast, $ocrTokens) || str_contains($ocrName, $regLast);

            if ($firstMatch && $lastMatch) {
                $score = max($score, 0.92);
            } elseif ($firstMatch || $lastMatch) {
                $score = max($score, $score + 0.12);
            }

            $best = max($best, $score);
        }

        return min(1.0, $best);
    }
}
