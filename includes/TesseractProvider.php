<?php
// ======================================================================
// VrakeIT – TesseractProvider.php  (Professional Edition)
// Local OCR engine using Tesseract 5 LSTM. No API key, no internet.
// Called by OcrProvider as primary extraction engine.
//
// Improvements over v1:
//   • LSTM-best trained data (eng+fil) — highest accuracy
//   • ImageMagick pre-processing: deskew, denoise, adaptive threshold
//   • Dual PSM pass (PSM 6 + PSM 3) — picks the best result
//   • Real HOCR confidence parsing (Tesseract's own word-level score)
//   • Force --oem 1 (LSTM-only, no legacy engine)
//   • Filipino label recognition (-l eng+fil)
//   • Smarter Philippine ID parsing (label-first, LTO, PhilSys, Passport)
// ======================================================================

class TesseractProvider
{
    // ── Known Tesseract install paths on Windows
    private const WIN_PATHS = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        'C:\\xampp\\tesseract\\tesseract.exe',
        'C:\\tesseract\\tesseract.exe',
    ];

    // ── Philippine gov ID keyword patterns (regex, case-insensitive)
    private const DOC_KEYWORDS = [
        'drivers_license' => "driver'?s\\s*licen[cs]e|land transportation|\\blto\\b",
        'national_id'     => 'philsys|national\\s+id|psa|philippine\\s+identification',
        'passport'        => '\\bpassport\\b|department of foreign|\\bdfa\\b',
        'sss'             => '\\bsss\\b|social security system',
        'gsis'            => '\\bgsis\\b|government service insurance',
        'philhealth'      => 'philhealth|national health insurance',
        'voters_id'       => "voter'?s\\s*(id|card)|\\bcomelec\\b|commission on elections",
        'prc'             => '\\bprc\\b|professional regulation commission',
        'umid'            => '\\bumid\\b|unified multi.?purpose',
        'postal_id'       => '\\bpostal\\b.*id|philippine postal',
        'senior_id'       => 'senior\\s*citizen|\\bosca\\b',
        'pwd_id'          => '\\bpwd\\b|persons?\\s+with\\s+disabilit',
        'tin_id'          => '\\btin\\b|taxpayer.*identification',
    ];

    // ── Doc type display labels
    private const DOC_LABELS = [
        'drivers_license' => "Driver's License",
        'national_id'     => 'Philippine National ID',
        'passport'        => 'Passport',
        'sss'             => 'SSS ID',
        'gsis'            => 'GSIS ID',
        'philhealth'      => 'PhilHealth ID',
        'voters_id'       => "Voter's ID",
        'prc'             => 'PRC ID',
        'umid'            => 'UMID',
        'postal_id'       => 'Postal ID',
        'senior_id'       => 'Senior Citizen ID',
        'pwd_id'          => 'PWD ID',
        'tin_id'          => 'TIN ID',
    ];

    // ── Words that indicate an agency/institution line, NOT a person's name
    private const NAME_REJECTS = [
        'republic', 'philippines', 'pilipinas', 'department', 'bureau',
        'license', 'licencia', 'national', 'official', 'office', 'authority',
        'agency', 'commission', 'council', 'center', 'centre', 'administration',
        'transportation', 'government', 'ministry', 'service', 'system',
        'corporation', 'organization', 'association', 'insurance', 'security',
        'registration', 'regulation', 'professional', 'postal', 'electoral',
        'philippine', 'citizen', 'identity', 'identification',
        'land', 'civil', 'local', 'provincial', 'regional', 'municipal',
        'barangay', 'city', 'province', 'district', 'lto', 'comelec',
        'philsys', 'umid', 'gsis', 'sss', 'prc', 'pwd', 'osca', 'dfa',
        'immigration', 'foreign', 'affairs', 'interior', 'local government',
        'health', 'education', 'finance', 'justice', 'labor',
        'certified', 'certify', 'bearer', 'holder', 'valid',
    ];

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: find the tesseract binary on this system
    // ─────────────────────────────────────────────────────────────────
    public static function findBinary(): ?string
    {
        foreach (self::WIN_PATHS as $path) {
            if (@is_executable($path)) return $path;
        }
        $output = [];
        exec('where tesseract 2>NUL', $output);
        if (!empty($output[0]) && @is_executable(trim($output[0]))) {
            return trim($output[0]);
        }
        exec('which tesseract 2>/dev/null', $output);
        if (!empty($output[0]) && @is_executable(trim($output[0]))) {
            return trim($output[0]);
        }
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: extractFromImage
    // Preprocesses the image, runs Tesseract with dual PSM pass,
    // parses structured fields and real HOCR confidence.
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
        ];

        $tesseract = self::findBinary();
        if (!$tesseract) {
            return array_merge($blank, ['error' => 'Tesseract not found. Please install it.']);
        }

        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            return array_merge($blank, ['error' => 'Image file not found or unreadable.']);
        }

        // ── 1. Pre-process the image for best OCR accuracy
        $processedPath = self::preprocessImage($imagePath);
        $workPath = $processedPath ?? $imagePath;

        // ── 2. Check which languages are available
        $langStr = self::buildLangString($tesseract);

        // ── 3. Run dual PSM pass — PSM 6 (uniform block) is best for IDs,
        //       PSM 3 (auto) catches awkward layouts. We pick the better result.
        $resultPsm6 = self::runTesseract($tesseract, $workPath, 6, $langStr);
        $resultPsm3 = self::runTesseract($tesseract, $workPath, 3, $langStr);

        // Clean up preprocessed temp file
        if ($processedPath && $processedPath !== $imagePath) {
            @unlink($processedPath);
        }

        // Pick the PSM result with the higher confidence / more fields
        $bestResult = self::pickBestResult($resultPsm6, $resultPsm3);

        if (!$bestResult['success']) {
            return array_merge($blank, ['error' => $bestResult['error'] ?? 'Tesseract could not read the image.']);
        }

        $rawText = $bestResult['text'];

        // ── 4. Parse structured identity fields from raw text
        $parsed = self::parseIdFields($rawText);

        // ── 5. Get real confidence (from HOCR if available, else estimated)
        $confidence = $bestResult['hocr_confidence'] ?? self::estimateConfidence($rawText, $parsed);

        return [
            'success'    => true,
            'name'       => $parsed['name'],
            'dob'        => $parsed['dob'],
            'doc_number' => $parsed['doc_number'],
            'expiry'     => $parsed['expiry'],
            'doc_type'   => $parsed['doc_type'],
            'is_gov_id'  => $parsed['is_gov_id'],
            'readable'   => $confidence >= 0.30,
            'confidence' => $confidence,
            'raw_text'   => $rawText,
            'error'      => '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: buildLangString
    // Use eng+fil if Filipino data is available, else just eng
    // ─────────────────────────────────────────────────────────────────
    private static function buildLangString(string $tesseractBin): string
    {
        $output = [];
        exec(escapeshellarg($tesseractBin) . ' --list-langs 2>&1', $output);
        $langs = implode(' ', $output);
        return str_contains($langs, 'fil') ? 'eng+fil' : 'eng';
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: runTesseract
    // Runs Tesseract with a given PSM mode. Returns both plain text
    // AND parses HOCR for real word-level confidence.
    // ─────────────────────────────────────────────────────────────────
    private static function runTesseract(
        string $tesseract,
        string $imagePath,
        int    $psm,
        string $langStr
    ): array {
        $blank = ['success' => false, 'text' => '', 'hocr_confidence' => null, 'error' => ''];

        // ── Run plain text pass
        $cmd = escapeshellarg($tesseract)
             . ' ' . escapeshellarg($imagePath)
             . ' stdout'
             . ' -l ' . escapeshellarg($langStr)
             . ' --psm ' . $psm
             . ' --oem 1'       // LSTM only (most accurate)
             . ' 2>NUL';

        $lines = [];
        $returnCode = -1;
        exec($cmd, $lines, $returnCode);
        $rawText = implode("\n", $lines);

        if ($returnCode !== 0 || trim($rawText) === '') {
            return array_merge($blank, ['error' => "Tesseract PSM{$psm} failed (code {$returnCode})."]);
        }

        // ── Run HOCR pass to get real word confidence scores
        $tmpHocr = tempnam(sys_get_temp_dir(), 'hocr_');
        $hocrBase = $tmpHocr; // tesseract appends .hocr automatically
        $hocrCmd = escapeshellarg($tesseract)
                 . ' ' . escapeshellarg($imagePath)
                 . ' ' . escapeshellarg($hocrBase)
                 . ' -l ' . escapeshellarg($langStr)
                 . ' --psm ' . $psm
                 . ' --oem 1'
                 . ' hocr'
                 . ' 2>NUL';

        exec($hocrCmd, $dummy, $hocrRc);

        $hocrConfidence = null;
        $hocrFile = $hocrBase . '.hocr';
        if ($hocrRc === 0 && file_exists($hocrFile)) {
            $hocrConfidence = self::parseHocrConfidence($hocrFile);
            @unlink($hocrFile);
        }
        @unlink($tmpHocr);

        return [
            'success'          => true,
            'text'             => $rawText,
            'hocr_confidence'  => $hocrConfidence,
            'error'            => '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: parseHocrConfidence
    // Parses Tesseract HOCR XML to get mean word-level confidence.
    // Tesseract encodes: <span class='ocrx_word' title='... x_wconf 87 ...'>
    // Returns float 0.0–1.0
    // ─────────────────────────────────────────────────────────────────
    private static function parseHocrConfidence(string $hocrPath): float
    {
        $content = @file_get_contents($hocrPath);
        if (!$content) return 0.0;

        // Extract all x_wconf values (word confidence, 0-100)
        preg_match_all('/x_wconf\s+(\d+)/', $content, $matches);
        if (empty($matches[1])) return 0.0;

        $scores = array_map('intval', $matches[1]);
        // Filter out very low-confidence words (junk/artifacts below 20)
        $scores = array_filter($scores, fn($s) => $s >= 20);

        if (empty($scores)) return 0.0;

        $mean = array_sum($scores) / count($scores);
        return round($mean / 100.0, 4); // Normalize to 0.0–1.0
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: pickBestResult
    // Compares two PSM results and picks the more useful one.
    // ─────────────────────────────────────────────────────────────────
    private static function pickBestResult(array $psm6, array $psm3): array
    {
        if (!$psm6['success'] && !$psm3['success']) {
            return $psm6; // Both failed, return first
        }
        if (!$psm6['success']) return $psm3;
        if (!$psm3['success']) return $psm6;

        // Prefer the result with higher HOCR confidence
        $conf6 = $psm6['hocr_confidence'] ?? 0.0;
        $conf3 = $psm3['hocr_confidence'] ?? 0.0;

        // Also consider which result has more text (more extraction surface)
        $words6 = str_word_count($psm6['text']);
        $words3 = str_word_count($psm3['text']);

        // Weight: 70% confidence + 30% word count richness
        $score6 = ($conf6 * 0.70) + (min($words6, 100) / 100.0 * 0.30);
        $score3 = ($conf3 * 0.70) + (min($words3, 100) / 100.0 * 0.30);

        return $score6 >= $score3 ? $psm6 : $psm3;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: preprocessImage
    // Uses ImageMagick to prepare the image for optimal OCR accuracy:
    //   1. Upscale to 300 DPI equivalent
    //   2. Convert to greyscale
    //   3. Deskew (straighten tilted IDs)
    //   4. Adaptive threshold (normalize lighting)
    //   5. Unsharp mask (crisp text edges)
    //   6. Denoise
    // Returns the path to the processed temp image, or null if IM unavailable.
    // ─────────────────────────────────────────────────────────────────
    private static function preprocessImage(string $imagePath): ?string
    {
        $magick = self::findImageMagick();
        if (!$magick) return null;

        $tmpOut = tempnam(sys_get_temp_dir(), 'tess_') . '.png';

        // Professional pre-processing pipeline:
        // -density 300           → treat as 300 DPI (higher resolution hint)
        // -resize 200%           → upscale for sub-pixel text
        // -colorspace Gray       → greyscale (OCR only needs luminance)
        // -deskew 40%            → auto-straighten tilted IDs (up to 40° skew)
        // -auto-level            → normalize exposure/brightness
        // -sharpen 0x1.5         → crisp text edges
        // -adaptive-sharpen 0x1  → adaptive unsharp mask
        // -despeckle             → remove noise/speckles
        // -threshold 50%         → binarize (black/white) — helps Tesseract LSTM
        $cmd = escapeshellarg($magick)
             . ' ' . escapeshellarg($imagePath)
             . ' -density 300'
             . ' -resize 200%'
             . ' -colorspace Gray'
             . ' -deskew 40%'
             . ' -auto-level'
             . ' -sharpen 0x1.5'
             . ' -adaptive-sharpen 0x1'
             . ' -despeckle'
             . ' ' . escapeshellarg($tmpOut)
             . ' 2>NUL';

        exec($cmd, $dummy, $rc);

        if ($rc === 0 && file_exists($tmpOut) && filesize($tmpOut) > 0) {
            return $tmpOut;
        }

        // Fallback: lighter pipeline without -deskew (some IM builds skip it)
        $cmd2 = escapeshellarg($magick)
              . ' ' . escapeshellarg($imagePath)
              . ' -resize 200%'
              . ' -colorspace Gray'
              . ' -auto-level'
              . ' -sharpen 0x1.5'
              . ' -despeckle'
              . ' ' . escapeshellarg($tmpOut)
              . ' 2>NUL';

        exec($cmd2, $dummy2, $rc2);
        @unlink($tmpOut . '.png'); // clean up any extra file IM might create

        return ($rc2 === 0 && file_exists($tmpOut) && filesize($tmpOut) > 0) ? $tmpOut : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: findImageMagick
    // Finds the ImageMagick 'magick' binary on this system.
    // ─────────────────────────────────────────────────────────────────
    private static function findImageMagick(): ?string
    {
        // ImageMagick 7+ uses 'magick' command
        $knownPaths = [
            'C:\\Program Files\\ImageMagick-7.1.2-Q16-HDRI\\magick.exe',
            'C:\\Program Files\\ImageMagick-7.1.1-Q16-HDRI\\magick.exe',
            'C:\\Program Files\\ImageMagick-7.1.0-Q16-HDRI\\magick.exe',
            'C:\\Program Files\\ImageMagick\\magick.exe',
        ];
        foreach ($knownPaths as $p) {
            if (@is_executable($p)) return $p;
        }

        $out = [];
        exec('where magick 2>NUL', $out);
        if (!empty($out[0]) && @is_executable(trim($out[0]))) {
            return trim($out[0]);
        }

        // ImageMagick 6 uses 'convert' — but Windows has a built-in convert.exe
        // that is NOT ImageMagick. Only use it if it's in an IM path.
        $out2 = [];
        exec('where convert 2>NUL', $out2);
        foreach ($out2 as $candidate) {
            $c = trim($candidate);
            if (stripos($c, 'ImageMagick') !== false || stripos($c, 'magick') !== false) {
                return $c;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: parseIdFields
    // Extracts structured identity fields from raw Tesseract text.
    // Priority order: labelled fields → doc-specific layout → all-caps
    // ─────────────────────────────────────────────────────────────────
    private static function parseIdFields(string $text): array
    {
        $fields = [
            'name'       => '',
            'dob'        => '',
            'doc_number' => '',
            'expiry'     => '',
            'doc_type'   => '',
            'is_gov_id'  => false,
        ];

        // ── Detect document type ──
        foreach (self::DOC_KEYWORDS as $key => $pattern) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $fields['doc_type'] = self::DOC_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
                $fields['is_gov_id'] = true;
                break;
            }
        }

        // Generic Philippine gov ID detection (fallback)
        if (!$fields['is_gov_id']) {
            if (
                stripos($text, 'republic of the philippines') !== false ||
                stripos($text, 'pilipinas') !== false ||
                preg_match('/\b(id no|card no|document no|license no)\b/i', $text)
            ) {
                $fields['is_gov_id'] = true;
                $fields['doc_type'] = 'Government ID';
            }
        }

        // ── Extract Name (multiple strategies, priority order) ──

        // Strategy 1: Labelled fields (LAST NAME / SURNAME / APELLIDO + FIRST NAME / PANGALAN)
        $name = self::extractNameLabelled($text);

        // Strategy 2: Inline "Name:" label
        if (!$name) {
            $name = self::extractNameInlineLabel($text);
        }

        // Strategy 3: LTO Driver's License-specific layout
        if (!$name && $fields['doc_type'] === "Driver's License") {
            $name = self::extractNameLTO($text);
        }

        // Strategy 4: Passport layout (SURNAME / GIVEN NAMES separate lines)
        if (!$name && $fields['doc_type'] === 'Passport') {
            $name = self::extractNamePassport($text);
        }

        // Strategy 5: PhilSys National ID layout
        if (!$name && $fields['doc_type'] === 'Philippine National ID') {
            $name = self::extractNamePhilSys($text);
        }

        // Strategy 6: All-caps line fallback (last resort, heavily filtered)
        if (!$name) {
            $name = self::extractNameAllCaps($text);
        }

        $fields['name'] = $name ? self::cleanName($name) : '';

        // ── Extract Date of Birth ──
        $fields['dob'] = self::extractDate($text, [
            'birth', 'bday', 'birthday', 'born', 'dob', 'date of birth',
            'petsa ng kapanganakan', 'kapanganakan', 'birthdate',
        ]);

        // ── Extract Expiry ──
        $fields['expiry'] = self::extractDate($text, [
            'expir', 'valid until', 'valid thru', 'expiration',
            'validity', 'valid to', 'expiry',
        ]);

        // ── Extract Document Number ──
        $fields['doc_number'] = self::extractDocNumber($text, $fields['doc_type']);

        return $fields;
    }

    // ─────────────────────────────────────────────────────────────────
    // Name extraction strategies
    // ─────────────────────────────────────────────────────────────────

    // Strategy 1: Labelled field pairs (LAST NAME / FIRST NAME / MIDDLE NAME)
    private static function extractNameLabelled(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $last = $first = $middle = '';

        for ($i = 0; $i < count($lines); $i++) {
            $line  = $lines[$i];
            $lline = strtolower(trim($line));

            // ── Last name
            if (preg_match('/\b(last\s*name|surname|apellido|family\s*name)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['last name', 'surname', 'apellido', 'family name']);
                if (!$val) $val = self::nextNonEmptyLine($lines, $i, ['first', 'given', 'middle', 'pangalan', 'gitnang']);
                if ($val && self::looksLikeName($val)) {
                    $last = ucwords(strtolower(trim($val)));
                }
            }

            // ── First / Given name
            if (preg_match('/\b(first\s*name|given\s*name|pangalan|christian\s*name)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['first name', 'given name', 'pangalan', 'christian name']);
                if (!$val) $val = self::nextNonEmptyLine($lines, $i, ['last', 'surname', 'middle', 'gitnang', 'apellido']);
                if ($val && self::looksLikeName($val)) {
                    $first = ucwords(strtolower(trim($val)));
                }
            }

            // ── Middle name
            if (preg_match('/\b(middle\s*name|gitnang\s*pangalan|middle\s*initial)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['middle name', 'gitnang pangalan', 'middle initial']);
                if (!$val) $val = self::nextNonEmptyLine($lines, $i, ['last', 'first', 'given', 'surname', 'pangalan', 'apellido']);
                if ($val && self::looksLikeName($val)) {
                    $middle = ucwords(strtolower(trim($val)));
                }
            }
        }

        if ($last && $first) {
            return trim($first . ($middle ? ' ' . $middle : '') . ' ' . $last);
        }
        return '';
    }

    // Strategy 2: Inline "Name:" or "Full Name:" label on same line
    private static function extractNameInlineLabel(string $text): string
    {
        if (preg_match('/(?:full\s*)?name\s*[:\-–]\s*([A-ZÑa-zñ][A-Za-zÑñÁÉÍÓÚáéíóú\s\.\,\-]+)/i', $text, $m)) {
            $candidate = trim($m[1]);
            if (strlen($candidate) >= 5 && str_word_count($candidate) >= 2 && !self::isInstitutionLine($candidate)) {
                return $candidate;
            }
        }
        return '';
    }

    // Strategy 3: LTO Driver's License — "DELA CRUZ, JUAN P" format
    private static function extractNameLTO(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $pastHeader = false;

        foreach ($lines as $line) {
            $l  = trim($line);
            $lw = strtolower($l);

            if (!$pastHeader && (
                str_contains($lw, 'land transportation') ||
                str_contains($lw, 'driver') ||
                str_contains($lw, 'license') ||
                str_contains($lw, 'lto')
            )) {
                $pastHeader = true;
                continue;
            }

            if (!$pastHeader || $l === '') continue;
            if (self::isInstitutionLine($l)) continue;

            // Skip date/address/sex/stats lines
            if (preg_match('/\d{4}|\b(male|female|single|married|m\/f|address|nationality|height|weight|eyes|blood|sex|civil)\b/i', $l)) continue;

            // LTO: ALL-CAPS, 2–5 words, optional comma separator
            if (preg_match('/^[A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-,\.ÑÁÉÍÓÚñáéíóú]{4,50}$/', $l)) {
                $words = str_word_count(preg_replace('/[^A-Za-zÑñÁÉÍÓÚáéíóú\s]/', ' ', $l));
                if ($words >= 2 && $words <= 5) {
                    // "DELA CRUZ, JUAN P" → "Juan P Dela Cruz"
                    if (preg_match('/^([A-ZÑÁÉÍÓÚ\s\-]+),\s*([A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-\.]+)$/', $l, $m)) {
                        return ucwords(strtolower(trim($m[2]))) . ' ' . ucwords(strtolower(trim($m[1])));
                    }
                    return ucwords(strtolower($l));
                }
            }
        }
        return '';
    }

    // Strategy 4: Passport — separate SURNAME / GIVEN NAMES sections
    private static function extractNamePassport(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $surname = $given = '';

        for ($i = 0; $i < count($lines); $i++) {
            $lw = strtolower(trim($lines[$i]));
            if (str_contains($lw, 'surname') || str_contains($lw, 'last name')) {
                $val = self::nextNonEmptyLine($lines, $i, ['given', 'first', 'nationality', 'sex', 'birth']);
                if ($val && self::looksLikeName($val)) $surname = ucwords(strtolower($val));
            }
            if (str_contains($lw, 'given name') || str_contains($lw, 'first name')) {
                $val = self::nextNonEmptyLine($lines, $i, ['surname', 'last', 'nationality', 'sex', 'birth']);
                if ($val && self::looksLikeName($val)) $given = ucwords(strtolower($val));
            }
        }

        if ($given && $surname) return trim($given . ' ' . $surname);
        if ($surname) return $surname;
        return '';
    }

    // Strategy 5: PhilSys National ID — "JUAN DELA CRUZ" large text block
    private static function extractNamePhilSys(string $text): string
    {
        // PhilSys often has name in a prominent all-caps line after PSN number block
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $l = trim($line);
            if (self::isInstitutionLine($l)) continue;
            if (preg_match('/\d/', $l)) continue; // skip number-containing lines

            // All-caps 2–4 word name line
            if (preg_match('/^[A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-\.]{5,45}$/', $l)) {
                $words = str_word_count(preg_replace('/[^A-Za-z\s]/', '', $l));
                if ($words >= 2 && $words <= 4 && !self::isInstitutionLine($l)) {
                    return ucwords(strtolower($l));
                }
            }
        }
        return '';
    }

    // Strategy 6: All-caps name line (last resort, heavily filtered)
    private static function extractNameAllCaps(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^[A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-,\.]{7,50}$/', $line)) {
                $words = str_word_count($line, 0, 'ÑÁÉÍÓÚñáéíóú,-.');
                if ($words >= 2 && $words <= 5) {
                    if (self::isInstitutionLine($line)) continue;
                    if (preg_match('/\d/', $line)) continue;
                    return ucwords(strtolower($line));
                }
            }
        }
        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: extractDate
    // Finds a date near given keyword context lines.
    // Supports many date formats used on Philippine IDs.
    // ─────────────────────────────────────────────────────────────────
    private static function extractDate(string $text, array $keywords): string
    {
        $datePatterns = [
            // MM/DD/YYYY or DD/MM/YYYY or DD-MM-YYYY
            '/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})\b/',
            // YYYY-MM-DD (ISO)
            '/\b(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\b/',
            // DD Mon YYYY (e.g. 15 JAN 1990)
            '/\b(\d{1,2})\s+(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{4})\b/i',
            // Mon DD, YYYY (e.g. January 15, 1990)
            '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{1,2})[,\s]+(\d{4})\b/i',
            // Mon-DD-YYYY (e.g. JAN-15-1990)
            '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)[\/\-\.](\d{1,2})[\/\-\.](\d{4})\b/i',
        ];

        $lines    = preg_split('/\r?\n/', $text);
        $kwStr    = implode('|', array_map('preg_quote', $keywords));
        $allDates = [];

        // First pass: dates near keyword context
        for ($i = 0; $i < count($lines); $i++) {
            $lline = strtolower($lines[$i]);
            if (preg_match('/(' . $kwStr . ')/i', $lline)) {
                $searchRange = implode(' ', array_slice($lines, $i, 3));
                foreach ($datePatterns as $dp) {
                    if (preg_match($dp, $searchRange, $m)) {
                        $normalized = self::normalizeDate($m[0]);
                        if ($normalized) return $normalized;
                    }
                }
            }
        }

        // Second pass: for DOB, try to find a plausible birth date anywhere
        if (in_array('birth', $keywords)) {
            foreach ($datePatterns as $dp) {
                if (preg_match_all($dp, $text, $allMatches)) {
                    foreach ($allMatches[0] as $match) {
                        $ts = strtotime($match);
                        // Plausible DOB: between 120 years ago and 16 years ago
                        if ($ts && $ts < strtotime('-16 years') && $ts > strtotime('-120 years')) {
                            return date('Y-m-d', $ts);
                        }
                    }
                }
            }
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: extractDocNumber
    // Extracts the document/ID number, aware of common Philippine ID formats.
    // ─────────────────────────────────────────────────────────────────
    private static function extractDocNumber(string $text, string $docType = ''): string
    {
        // Labelled ID number (highest priority)
        if (preg_match('/(?:id\s*no|card\s*no|license\s*no|document\s*no|no\.|number|doc\s*no)\s*[:\-–\.]\s*([A-Z0-9\-\/]{5,20})/i', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        // LTO Driver's License: X##-##-######
        if (preg_match('/\b[A-Z]\d{2}-\d{2}-\d{6}\b/', $text, $m)) return $m[0];

        // SSS: ##-#######-#
        if (preg_match('/\b\d{2}-\d{7}-\d\b/', $text, $m)) return $m[0];

        // PhilSys PCN: ####-####-####
        if (preg_match('/\b\d{4}[\s\-]\d{4}[\s\-]\d{4}\b/', $text, $m)) {
            return preg_replace('/\s/', '-', trim($m[0]));
        }

        // PRC license number: #######
        if (str_contains(strtolower($docType), 'prc') && preg_match('/\b\d{7}\b/', $text, $m)) {
            return $m[0];
        }

        // UMID: ####-#######-#
        if (preg_match('/\b\d{4}-\d{7}-\d\b/', $text, $m)) return $m[0];

        // Passport: 2 letters + 7 digits (Philippine passport format)
        if (preg_match('/\b[A-Z]{2}\d{7}\b/', $text, $m)) return $m[0];

        // Generic alphanumeric ID (8–16 chars)
        if (preg_match('/\b([A-Z]{1,3}[\-]?\d{6,14})\b/', $text, $m)) return $m[1];

        return '';
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: Helper utilities
    // ─────────────────────────────────────────────────────────────────

    // Get the next non-empty line that doesn't match any exclude keywords
    private static function nextNonEmptyLine(array $lines, int $fromIndex, array $excludeKeywords): string
    {
        for ($j = $fromIndex + 1; $j <= $fromIndex + 3 && isset($lines[$j]); $j++) {
            $candidate = trim($lines[$j]);
            if ($candidate === '') continue;
            $lc = strtolower($candidate);
            $excluded = false;
            foreach ($excludeKeywords as $kw) {
                if (str_contains($lc, $kw)) { $excluded = true; break; }
            }
            if (!$excluded) return $candidate;
        }
        return '';
    }

    // Extract value after a label on the same line: "LAST NAME: DELA CRUZ"
    private static function extractValueAfterLabel(string $line, array $labels): string
    {
        foreach ($labels as $label) {
            $pattern = '/' . preg_quote($label, '/') . '\s*[:\-–]?\s*(.+)/i';
            if (preg_match($pattern, $line, $m)) {
                $val = trim($m[1]);
                if (!preg_match('/\b(last|first|given|middle|surname|pangalan|apellido|gitnang)\b/i', $val)) {
                    return $val;
                }
            }
        }
        return '';
    }

    // Does a string look like a person's name (not institution/number)?
    private static function looksLikeName(string $s): bool
    {
        $s = trim($s);
        if (strlen($s) < 3 || strlen($s) > 60) return false;
        if (preg_match('/\d/', $s)) return false;               // no digits
        if (self::isInstitutionLine($s)) return false;
        if (!preg_match('/^[A-Za-zÑÁÉÍÓÚñáéíóú]/', $s)) return false;
        return true;
    }

    // Is a text line an institution/agency header rather than a person's name?
    private static function isInstitutionLine(string $line): bool
    {
        $lower = strtolower($line);
        foreach (self::NAME_REJECTS as $reject) {
            if (str_contains($lower, $reject)) return true;
        }
        return false;
    }

    // Normalize a date string to YYYY-MM-DD
    private static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if (!$raw) return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    // Clean and proper-case a name string
    private static function cleanName(string $name): string
    {
        $name = preg_replace('/[^A-Za-zÑñÁÉÍÓÚáéíóú\s\-\.]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: estimateConfidence
    // Fallback confidence estimation when HOCR is unavailable.
    // Based on text richness and number of fields extracted.
    // ─────────────────────────────────────────────────────────────────
    private static function estimateConfidence(string $text, array $parsed): float
    {
        $score    = 0.0;
        $words    = str_word_count($text);
        $chars    = strlen(preg_replace('/\s+/', '', $text));

        if ($words < 5)   return 0.05;
        if ($words < 10)  $score += 0.10;
        elseif ($words < 20) $score += 0.20;
        else              $score += 0.30;

        // Character density (ID should have dense text)
        if ($chars > 100) $score += 0.05;

        if ($parsed['is_gov_id'])  $score += 0.25;
        if ($parsed['name'])       $score += 0.20;
        if ($parsed['dob'])        $score += 0.15;
        if ($parsed['doc_number']) $score += 0.05;

        return min(1.0, round($score, 4));
    }
}
