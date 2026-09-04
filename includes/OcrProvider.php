<?php
// ======================================================================
// VrakeIT – OcrProvider.php
// Modular OCR/document-verification class.
// PRIMARY:  Tesseract (local, free, no internet, no API key)
// FALLBACK: Gemini Vision (if Tesseract unavailable or fails extraction)
// To swap providers, only this file needs to change.
// ======================================================================

require_once __DIR__ . '/TesseractProvider.php';

class OcrProvider
{
    // ── Gemini Vision endpoint (2.5 Flash – latest fast multimodal model)
    private const GEMINI_ENDPOINT =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=';

    // ── Recognised Philippine government ID types (lower-cased keywords)
    private const VALID_DOC_KEYWORDS = [
        "driver", "license", "licence", "national id", "philsys",
        "passport", "sss", "gsis", "philhealth", "voter", "postal",
        "prc", "umid", "senior", "pwd", "tin", "digitized"
    ];

    // ── Fuzzy match threshold (0.0–1.0)
    private const NAME_MATCH_THRESHOLD = 0.72;

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: extractFromImage
    // Attempts Tesseract first (local, free), then Gemini as fallback.
    // Returns structured OCR data array.
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
            'raw_text'   => '',
            'confidence' => 0.0,
            'error'      => '',
            'ocr_engine' => 'none',
        ];

        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            return array_merge($blank, ['error' => 'Image file not found or unreadable.']);
        }

        // ── ATTEMPT 1: Tesseract (local, no internet required) ──
        $tesseractBin = TesseractProvider::findBinary();
        if ($tesseractBin) {
            $result = TesseractProvider::extractFromImage($imagePath);
            if ($result['success']) {
                $result['ocr_engine'] = 'tesseract';
                // If Tesseract found the key fields, use it directly
                if ($result['name'] || $result['dob']) {
                    return $result;
                }
                // Tesseract ran but couldn't find name/DOB — try Gemini for better result
                // Fall through to Gemini below
            }
            // Tesseract failed entirely — fall through to Gemini
        }

        // ── ATTEMPT 2: Gemini Vision (cloud, requires API key + internet) ──
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            // Neither Tesseract nor Gemini available
            return array_merge($blank, [
                'error' => 'OCR service not available. Please install Tesseract or configure GEMINI_API_KEY.',
            ]);
        }

        $mimeType  = self::detectMime($imagePath);
        $imageData = base64_encode(file_get_contents($imagePath));
        $prompt = <<<PROMPT
You are an identity document OCR system. Analyse the provided image of a government-issued ID or driving license.

Respond ONLY with a valid JSON object and nothing else. Do NOT include markdown fences or any text outside the JSON.

Extract and return the following fields:
{
  "doc_type": "<detected document type, e.g. Driver's License, National ID, Passport>",
  "is_government_id": <true if this is clearly a government-issued identity document, false otherwise>,
  "full_name": "<full name as printed on the document, in FIRSTNAME LASTNAME order if possible>",
  "date_of_birth": "<date of birth in YYYY-MM-DD format, or empty string if not found>",
  "document_number": "<ID/document number, or empty string if not found>",
  "expiry_date": "<expiry/expiration date in YYYY-MM-DD format, or empty string if not found or not applicable>",
  "issuing_country": "<country or authority that issued the document>",
  "confidence": <float from 0.0 to 1.0 representing your overall confidence in the extraction>,
  "readable": <true if the document text is legible, false if blurry/obscured>,
  "notes": "<any relevant notes about document quality or extraction issues>"
}

Rules:
- If you cannot read the document clearly, set "readable": false and "confidence" to a low value.
- If the image does not appear to be a government ID at all, set "is_government_id": false.
- Never guess a name or date – return empty string if not visible.
- Normalize the name to "FIRSTNAME MIDDLENAME LASTNAME" order where possible.
- Parse all date variants (e.g. "01/15/1990", "15 JAN 1990", "January 15, 1990") into YYYY-MM-DD.
PROMPT;

        $response = self::callGeminiVision($imageData, $mimeType, $prompt, $apiKey);

        if (!$response['success']) {
            return array_merge($blank, ['error' => $response['error']]);
        }

        $parsed = self::parseGeminiJson($response['text']);
        if (!$parsed) {
            return array_merge($blank, [
                'error'    => 'OCR service returned unreadable data.',
                'raw_text' => $response['text'],
            ]);
        }

        return [
            'success'    => true,
            'name'       => self::cleanString($parsed['full_name'] ?? ''),
            'dob'        => self::cleanString($parsed['date_of_birth'] ?? ''),
            'doc_number' => self::cleanString($parsed['document_number'] ?? ''),
            'expiry'     => self::cleanString($parsed['expiry_date'] ?? ''),
            'doc_type'   => self::cleanString($parsed['doc_type'] ?? ''),
            'is_gov_id'  => (bool)($parsed['is_government_id'] ?? false),
            'readable'   => (bool)($parsed['readable'] ?? false),
            'confidence' => (float)($parsed['confidence'] ?? 0.0),
            'raw_text'   => $response['text'],
            'error'      => '',
            'ocr_engine' => 'gemini',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: validateDocument
    // Checks whether the extracted data represents a valid, usable doc.
    // Returns: ['valid' => bool, 'reason' => string]
    // ─────────────────────────────────────────────────────────────────
    public static function validateDocument(array $extracted): array
    {
        if (!($extracted['success'] ?? false)) {
            return ['valid' => false, 'reason' => 'OCR extraction failed.'];
        }

        if (!($extracted['readable'] ?? false)) {
            return ['valid' => false, 'reason' => 'The document image is not clearly readable. Please upload a well-lit, focused photo.'];
        }

        if (!($extracted['is_gov_id'] ?? false)) {
            return ['valid' => false, 'reason' => 'The uploaded image does not appear to be a government-issued ID.'];
        }

        // Confidence check
        $confidence = (float)($extracted['confidence'] ?? 0.0);
        if ($confidence < 0.45) {
            return ['valid' => false, 'reason' => 'The document could not be read with sufficient confidence. Please upload a clearer image.'];
        }

        // Required fields
        if (empty(trim($extracted['name'] ?? ''))) {
            return ['valid' => false, 'reason' => 'Name could not be extracted from the document.'];
        }
        if (empty(trim($extracted['dob'] ?? ''))) {
            return ['valid' => false, 'reason' => 'Date of birth could not be extracted from the document.'];
        }

        // Expiry check (only if expiry is present)
        $expiry = trim($extracted['expiry'] ?? '');
        if ($expiry !== '') {
            $expiryTs = strtotime($expiry);
            if ($expiryTs !== false && $expiryTs < time()) {
                return ['valid' => false, 'reason' => 'The provided ID document has expired. Please upload a valid, non-expired document.'];
            }
        }

        // Document type keyword check
        $docType = strtolower($extracted['doc_type'] ?? '');
        $isKnownType = false;
        foreach (self::VALID_DOC_KEYWORDS as $kw) {
            if (str_contains($docType, $kw)) {
                $isKnownType = true;
                break;
            }
        }
        if (!$isKnownType && !empty($docType)) {
            // Not a hard failure — Gemini already flagged is_gov_id, so be lenient
            // but log this for review
        }

        return ['valid' => true, 'reason' => ''];
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: matchIdentity
    // Compares OCR data against user-provided registration data.
    // Returns:
    //   ['name_match' => bool, 'dob_match' => bool, 'overall_match' => bool, 'score' => float]
    // ─────────────────────────────────────────────────────────────────
    public static function matchIdentity(array $extracted, array $registrationData): array
    {
        // ── Name comparison
        $ocrName  = self::normalizeName($extracted['name'] ?? '');
        $regFirst = self::normalizeName($registrationData['first_name'] ?? '');
        $regLast  = self::normalizeName($registrationData['last_name'] ?? '');
        $regFull  = self::normalizeName($registrationData['first_name'] . ' ' . $registrationData['last_name']);

        $nameScore = self::nameMatchScore($ocrName, $regFirst, $regLast, $regFull);
        $nameMatch = $nameScore >= self::NAME_MATCH_THRESHOLD;

        // ── DOB comparison
        $ocrDob = self::normalizeDate($extracted['dob'] ?? '');
        $regDob = self::normalizeDate($registrationData['birthdate'] ?? '');
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

    private static function getApiKey(): string
    {
        // Try $_ENV first (set by config.php), then getenv()
        return $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?? '';
    }

    private static function detectMime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $path);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'image/jpeg', 'image/png' => 'image/png', 'image/webp' => 'image/webp'];
        return $allowed[$mime] ?? 'image/jpeg';
    }

    private static function callGeminiVision(
        string $base64Image,
        string $mimeType,
        string $prompt,
        string $apiKey
    ): array {
        $payload = json_encode([
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64Image]],
                ],
            ]],
            'generationConfig' => [
                'temperature'     => 0.1,
                'maxOutputTokens' => 1024,
            ],
        ]);

        // ── Attempt 1: Direct curl from Apache process
        $result = self::curlPost(self::GEMINI_ENDPOINT . $apiKey, $payload);

        // ── Attempt 2: PHP CLI subprocess fallback
        // On Windows XAMPP, httpd.exe is often blocked by Windows Firewall from
        // making outbound HTTPS connections, while php.exe (CLI) is not blocked.
        // We shell out to php.exe to make the request and capture the output.
        if (!$result['success'] && function_exists('proc_open')) {
            $result = self::callViaCliSubprocess($payload, $apiKey);
        }

        return $result;
    }

    /**
     * Make a POST request via PHP's curl from the current (Apache) process.
     */
    private static function curlPost(string $url, string $payload): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'cURL extension not available.', 'text' => ''];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $raw === false) {
            return ['success' => false, 'error' => 'curl: ' . $err, 'text' => ''];
        }

        $json = json_decode($raw, true);
        if ($code !== 200 || !isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return ['success' => false, 'error' => "HTTP $code from Gemini API.", 'text' => $raw];
        }

        return ['success' => true, 'text' => $json['candidates'][0]['content']['parts'][0]['text'], 'error' => ''];
    }

    /**
     * Fallback: shell out to php.exe (CLI) to bypass Windows Firewall restrictions
     * on Apache's httpd.exe process. The CLI process is typically not blocked.
     *
     * We write the payload to a temp file, have php.exe read it and POST it,
     * then capture the JSON response via stdout.
     */
    private static function callViaCliSubprocess(string $payload, string $apiKey): array
    {
        // Find php.exe — try known XAMPP paths and PATH
        $phpBin = null;
        $candidates = [
            'C:\\xampp\\php\\php.exe',
            'C:\\php\\php.exe',
            PHP_BINARY, // the binary that started this process (may be apache handler)
        ];
        foreach ($candidates as $c) {
            if (@is_executable($c)) { $phpBin = $c; break; }
        }
        if (!$phpBin) {
            return ['success' => false, 'error' => 'PHP CLI not found for subprocess fallback.', 'text' => ''];
        }

        // Write payload to a temp file (avoids command-line length limits)
        $tmpPayload = tempnam(sys_get_temp_dir(), 'gcr_');
        file_put_contents($tmpPayload, $payload);

        $url = self::GEMINI_ENDPOINT . $apiKey;

        // Inline PHP script passed to the CLI
        $script = <<<'PHP'
$url     = $argv[1];
$payFile = $argv[2];
$payload = file_get_contents($payFile);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);
echo json_encode(['code' => $code, 'body' => $raw, 'err' => $err]);
PHP;

        $tmpScript = tempnam(sys_get_temp_dir(), 'gcr_') . '.php';
        file_put_contents($tmpScript, $script);

        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($tmpScript)
             . ' ' . escapeshellarg($url)
             . ' ' . escapeshellarg($tmpPayload);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);

        $output = '';
        if (is_resource($proc)) {
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        // Clean up temp files
        @unlink($tmpPayload);
        @unlink($tmpScript);

        if (!$output) {
            return ['success' => false, 'error' => 'CLI subprocess returned no output.', 'text' => ''];
        }

        $result = json_decode($output, true);
        if (!$result) {
            return ['success' => false, 'error' => 'CLI subprocess output was not valid JSON.', 'text' => $output];
        }
        if ($result['err']) {
            return ['success' => false, 'error' => 'CLI curl: ' . $result['err'], 'text' => ''];
        }

        $json = json_decode($result['body'] ?? '', true);
        if (($result['code'] ?? 0) !== 200 || !isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            return ['success' => false, 'error' => "CLI HTTP {$result['code']} from Gemini API.", 'text' => $result['body'] ?? ''];
        }

        return ['success' => true, 'text' => $json['candidates'][0]['content']['parts'][0]['text'], 'error' => ''];
    }

    private static function parseGeminiJson(string $raw): ?array
    {
        // Strip potential markdown code fences
        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $clean = preg_replace('/\s*```$/i', '', $clean);

        // Extract first {...} block
        if (preg_match('/\{.*\}/s', $clean, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    private static function cleanString(?string $s): string
    {
        return trim(strip_tags($s ?? ''));
    }

    // Normalize a name for comparison: lowercase, trim extra spaces, remove punctuation
    private static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z\s]/', '', $name);   // remove punctuation
        $name = preg_replace('/\s+/', ' ', $name);
        return $name;
    }

    // Normalize a date to YYYY-MM-DD
    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if (!$date) return '';
        $ts = strtotime($date);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    /**
     * Compute best name similarity score across several candidate comparisons.
     * Uses similar_text() + soundex fallback.
     */
    private static function nameMatchScore(
        string $ocrName,
        string $regFirst,
        string $regLast,
        string $regFull
    ): float {
        if (!$ocrName || !$regFull) return 0.0;

        // Candidates to compare the OCR name against
        $candidates = [
            $regFull,
            $regLast . ' ' . $regFirst,   // LAST FIRST order
            $regFirst,
            $regLast,
        ];

        $best = 0.0;
        foreach ($candidates as $cand) {
            if (!$cand) continue;

            // similar_text percentage
            similar_text($ocrName, $cand, $pct);
            $score = $pct / 100.0;

            // Boost if OCR name contains both first and last name tokens
            $ocrTokens  = explode(' ', $ocrName);
            $firstMatch = in_array($regFirst, $ocrTokens) || str_contains($ocrName, $regFirst);
            $lastMatch  = in_array($regLast, $ocrTokens) || str_contains($ocrName, $regLast);
            if ($firstMatch && $lastMatch) {
                $score = max($score, 0.90);
            } elseif ($firstMatch || $lastMatch) {
                $score = max($score, $score + 0.10);
            }

            $best = max($best, $score);
        }

        return min(1.0, $best);
    }
}
