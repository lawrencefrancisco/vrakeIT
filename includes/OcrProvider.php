<?php
// ======================================================================
// VrakeIT – OcrProvider.php
// Modular OCR/document-verification class.
// ENGINE: Tesseract (local, no internet, no API key required)
// ======================================================================

require_once __DIR__ . '/TesseractProvider.php';

class OcrProvider
{
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
    // Runs Tesseract and returns structured OCR data array.
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
            'ocr_engine' => 'tesseract',
        ];

        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            return array_merge($blank, ['error' => 'Image file not found or unreadable.']);
        }

        $tesseractBin = TesseractProvider::findBinary();
        if (!$tesseractBin) {
            return array_merge($blank, [
                'error' => 'Tesseract is not installed or not found. Please install Tesseract OCR.',
            ]);
        }

        $result = TesseractProvider::extractFromImage($imagePath);
        $result['ocr_engine'] = 'tesseract';

        if (!$result['success']) {
            return array_merge($blank, [
                'error'      => $result['error'] ?? 'Tesseract OCR failed.',
                'raw_text'   => $result['raw_text'] ?? '',
                'ocr_engine' => 'tesseract',
            ]);
        }

        return $result;
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
     * Uses similar_text() for fuzzy matching.
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
