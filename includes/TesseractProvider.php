<?php
// ======================================================================
// VrakeIT – TesseractProvider.php
// Local OCR engine using Tesseract. No API key, no internet, no limits.
// Called by OcrProvider as primary extraction engine.
// ======================================================================

class TesseractProvider
{
    // Known Tesseract install paths on Windows
    private const WIN_PATHS = [
        'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
        'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
        'C:\\xampp\\tesseract\\tesseract.exe',
        'C:\\xampp\\tesseract.exe',
        'C:\\tesseract\\tesseract.exe',
    ];

    // Philippine gov ID keyword patterns (regex)
    private const DOC_KEYWORDS = [
        'driver' => "driver'?s licen[cs]e|lto|land transportation",
        'national' => 'philsys|national id|republic of the philippines.*id|psa',
        'passport' => 'passport|republic of the philippines.*passport|dfa',
        'sss' => '\bsss\b|social security',
        'gsis' => '\bgsis\b|government service insurance',
        'philhealth' => 'philhealth|national health insurance',
        'voter' => "voter'?s.*id|comelec|commission on elections",
        'prc' => '\bprc\b|professional regulation',
        'umid' => '\bumid\b|unified multi.*purpose',
        'postal' => '\bpostal\b|philippine postal',
        'senior' => 'senior citizen|osca',
        'pwd' => '\bpwd\b|persons? with disabilit',
    ];

    // Words that indicate a line is an institution/agency header, NOT a person's name
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
        'philsys', 'umid', 'gsis', 'sss', 'prc', 'pwd', 'osca',
    ];

    // ─────────────────────────────────────────────────────────────────
    // PUBLIC: find the tesseract binary on this system
    // ─────────────────────────────────────────────────────────────────
    public static function findBinary(): ?string
    {
        // Check known Windows paths first
        foreach (self::WIN_PATHS as $path) {
            if (@is_executable($path)) return $path;
        }
        // Try PATH via where/which
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
    // Runs Tesseract on the image and returns structured extraction data.
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

        // Pre-process: convert to a clean PNG Tesseract can read
        $processedPath = self::preprocessImage($imagePath);
        $workPath = $processedPath ?? $imagePath;

        // Run Tesseract — output plain text to stdout via pipe
        // Using psm 3 (fully automatic page segmentation) for IDs
        $cmd = escapeshellarg($tesseract)
             . ' ' . escapeshellarg($workPath)
             . ' stdout'
             . ' -l eng'
             . ' --psm 3'
             . ' --oem 3'
             . ' 2>NUL';

        $rawText = '';
        $returnCode = -1;
        exec($cmd, $lines, $returnCode);
        $rawText = implode("\n", $lines);

        // Clean up preprocessed temp file
        if ($processedPath && $processedPath !== $imagePath) {
            @unlink($processedPath);
        }

        if ($returnCode !== 0 || trim($rawText) === '') {
            return array_merge($blank, ['error' => 'Tesseract could not read the image (code ' . $returnCode . ').']);
        }

        // Parse the raw text into structured fields
        $parsed = self::parseIdFields($rawText);

        $confidence = self::estimateConfidence($rawText, $parsed);

        return [
            'success'    => true,
            'name'       => $parsed['name'],
            'dob'        => $parsed['dob'],
            'doc_number' => $parsed['doc_number'],
            'expiry'     => $parsed['expiry'],
            'doc_type'   => $parsed['doc_type'],
            'is_gov_id'  => $parsed['is_gov_id'],
            'readable'   => $confidence >= 0.35,
            'confidence' => $confidence,
            'raw_text'   => $rawText,
            'error'      => '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: preprocessImage
    // Upscale and sharpen the image to improve Tesseract accuracy.
    // Uses ImageMagick (convert) if available, otherwise returns null.
    // ─────────────────────────────────────────────────────────────────
    private static function preprocessImage(string $imagePath): ?string
    {
        // Check if ImageMagick is available
        exec('where magick 2>NUL', $out);
        $magick = !empty($out[0]) ? trim($out[0]) : null;
        if (!$magick) {
            exec('where convert 2>NUL', $out2);
            $magick = !empty($out2[0]) ? trim($out2[0]) : null;
        }

        if (!$magick) return null; // No ImageMagick, skip preprocessing

        $tmpOut = tempnam(sys_get_temp_dir(), 'tess_') . '.png';

        // Upscale 2x, convert to greyscale, sharpen, increase contrast
        $cmd = escapeshellarg($magick)
             . ' ' . escapeshellarg($imagePath)
             . ' -resize 200%'
             . ' -colorspace Gray'
             . ' -sharpen 0x1'
             . ' -contrast-stretch 2%x1%'
             . ' ' . escapeshellarg($tmpOut)
             . ' 2>NUL';
        exec($cmd, $dummy, $rc);

        return ($rc === 0 && file_exists($tmpOut)) ? $tmpOut : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // PRIVATE: parseIdFields
    // Extracts structured identity fields from raw OCR text.
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

        $lower = strtolower($text);

        // ── Detect document type ──
        foreach (self::DOC_KEYWORDS as $type => $pattern) {
            if (preg_match('/' . $pattern . '/i', $text)) {
                $fields['doc_type'] = ucfirst($type) . ' ID';
                $fields['is_gov_id'] = true;
                break;
            }
        }
        // Generic Philippine gov ID detection
        if (!$fields['is_gov_id'] && (
            str_contains($lower, 'republic of the philippines') ||
            str_contains($lower, 'pilipinas') ||
            str_contains($lower, 'government') ||
            preg_match('/\b(id no|card no|document no|license no)\b/i', $text)
        )) {
            $fields['is_gov_id'] = true;
            if (!$fields['doc_type']) $fields['doc_type'] = 'Government ID';
        }

        // ── Extract Name ──
        // Strategy 1: labelled field (LAST NAME + GIVEN NAME, or SURNAME + FIRST NAME)
        $name = self::extractNameLabelled($text);

        // Strategy 2: LTO Driver's License layout — name block below agency header
        if (!$name && $fields['doc_type'] === 'Driver ID') {
            $name = self::extractNameLTO($text);
        }

        // Strategy 3: "Name:" or "Full Name:" label
        if (!$name) {
            if (preg_match('/(?:full\s*)?name\s*[:\-–]\s*([A-ZÑa-zñ][A-Za-zÑñ\s\.\,\-]+)/i', $text, $m)) {
                $candidate = trim($m[1]);
                if (strlen($candidate) >= 5 && str_word_count($candidate) >= 2) {
                    $name = $candidate;
                }
            }
        }

        // Strategy 4: ALL-CAPS name line (filtered — last resort)
        if (!$name) {
            $name = self::extractNameAllCaps($text);
        }

        $fields['name'] = $name ? self::cleanName($name) : '';

        // ── Extract Date of Birth ──
        $fields['dob'] = self::extractDate($text, ['birth', 'bday', 'birthday', 'born', 'dob', 'date of birth', 'petsa ng kapanganakan']);

        // ── Extract Expiry ──
        $fields['expiry'] = self::extractDate($text, ['expir', 'valid until', 'valid thru', 'expiration', 'validity']);

        // ── Extract Document Number ──
        $fields['doc_number'] = self::extractDocNumber($text);

        return $fields;
    }

    // Extract name using label pairs: LAST NAME + GIVEN NAME / SURNAME + FIRST NAME
    private static function extractNameLabelled(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $last   = '';
        $first  = '';
        $middle = '';

        foreach ($lines as $i => $line) {
            $lline = strtolower(trim($line));

            // Last name / Surname
            if (preg_match('/\b(last\s*name|surname|apellido)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['last name', 'surname', 'apellido']);
                if (!$val) {
                    // Try next non-empty line
                    for ($j = $i + 1; $j <= $i + 2 && isset($lines[$j]); $j++) {
                        $candidate = trim($lines[$j]);
                        if ($candidate !== '' && !preg_match('/\b(first|given|middle|pangalan|gitnang)\b/i', $candidate)) {
                            $val = $candidate;
                            break;
                        }
                    }
                }
                if ($val && preg_match('/^[A-ZÑÁÉÍÓÚ\s\-\.]+$/i', strtoupper(trim($val)))) {
                    $last = ucwords(strtolower(trim($val)));
                }
            }

            // First / Given name
            if (preg_match('/\b(first\s*name|given\s*name|pangalan)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['first name', 'given name', 'pangalan']);
                if (!$val) {
                    for ($j = $i + 1; $j <= $i + 2 && isset($lines[$j]); $j++) {
                        $candidate = trim($lines[$j]);
                        if ($candidate !== '' && !preg_match('/\b(last|surname|middle|gitnang|apellido)\b/i', $candidate)) {
                            $val = $candidate;
                            break;
                        }
                    }
                }
                if ($val && preg_match('/^[A-ZÑÁÉÍÓÚ\s\-\.]+$/i', trim($val))) {
                    $first = ucwords(strtolower(trim($val)));
                }
            }

            // Middle name
            if (preg_match('/\b(middle\s*name|gitnang\s*pangalan)\b/i', $lline)) {
                $val = self::extractValueAfterLabel($line, ['middle name', 'gitnang pangalan']);
                if (!$val) {
                    for ($j = $i + 1; $j <= $i + 2 && isset($lines[$j]); $j++) {
                        $candidate = trim($lines[$j]);
                        if ($candidate !== '' && !preg_match('/\b(last|first|given|surname|pangalan|apellido)\b/i', $candidate)) {
                            $val = $candidate;
                            break;
                        }
                    }
                }
                if ($val && preg_match('/^[A-ZÑÁÉÍÓÚ\s\-\.]+$/i', trim($val))) {
                    $middle = ucwords(strtolower(trim($val)));
                }
            }
        }

        if ($last && $first) {
            return $first . ($middle ? ' ' . $middle : '') . ' ' . $last;
        }
        return '';
    }

    // LTO Driver's License: name typically appears as LAST NAME, FIRST NAME MI on a single line
    // below the "LAND TRANSPORTATION OFFICE" / "DRIVER'S LICENSE" header block
    private static function extractNameLTO(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        $pastHeader = false;

        foreach ($lines as $line) {
            $l = trim($line);
            $lw = strtolower($l);

            // Mark when we're past the header block
            if (!$pastHeader && (
                str_contains($lw, 'land transportation') ||
                str_contains($lw, 'driver') ||
                str_contains($lw, 'license') ||
                str_contains($lw, 'lto')
            )) {
                $pastHeader = true;
                continue;
            }

            if (!$pastHeader) continue;
            if ($l === '') continue;

            // Skip if this line looks like an agency/institution
            if (self::isInstitutionLine($l)) continue;

            // Skip if it looks like a date, number, address, or sex/civil-status field
            if (preg_match('/\d{4}|\b(male|female|single|married|m\/f|address|nationality|height|weight|eyes|blood)\b/i', $l)) continue;

            // Looks like a name: all caps, 2-4 words, purely alphabetic (with comma/period/hyphen)
            if (preg_match('/^[A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-,\.]{4,50}$/', $l)) {
                $words = str_word_count(preg_replace('/[^A-Za-z\s]/', ' ', $l));
                if ($words >= 2 && $words <= 5) {
                    // LTO format: "DELA CRUZ, JUAN P" => "Juan P Dela Cruz"
                    if (preg_match('/^([A-ZÑÁÉÍÓÚ\s\-]+),\s*([A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-\.]+)$/', $l, $m)) {
                        $last  = ucwords(strtolower(trim($m[1])));
                        $first = ucwords(strtolower(trim($m[2])));
                        return $first . ' ' . $last;
                    }
                    return ucwords(strtolower($l));
                }
            }
        }
        return '';
    }

    // Check if a text line looks like an institution/agency name rather than a person's name
    private static function isInstitutionLine(string $line): bool
    {
        $lower = strtolower($line);
        foreach (self::NAME_REJECTS as $reject) {
            if (str_contains($lower, $reject)) return true;
        }
        return false;
    }

    // Extract value after a label on the same line: "LAST NAME: DELA CRUZ"
    private static function extractValueAfterLabel(string $line, array $labels): string
    {
        foreach ($labels as $label) {
            $pattern = '/' . preg_quote($label, '/') . '\s*[:\-–]?\s*(.+)/i';
            if (preg_match($pattern, $line, $m)) {
                $val = trim($m[1]);
                // Make sure the value isn't just another label keyword
                if (!preg_match('/\b(last|first|given|middle|surname|pangalan|apellido|gitnang)\b/i', $val)) {
                    return $val;
                }
            }
        }
        return '';
    }

    // Extract an all-caps name line (last resort — heavily filtered)
    private static function extractNameAllCaps(string $text): string
    {
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            // All-caps, 2–5 words, letters and spaces and hyphens only, 8–50 chars
            if (preg_match('/^[A-ZÑÁÉÍÓÚ][A-ZÑÁÉÍÓÚ\s\-,\.]{7,50}$/', $line)) {
                $words = str_word_count($line, 0, 'ÑÁÉÍÓÚñáéíóú,-.');
                if ($words >= 2 && $words <= 5) {
                    // Reject any line containing institution/agency words
                    if (self::isInstitutionLine($line)) continue;
                    // Reject if line has numbers
                    if (preg_match('/\d/', $line)) continue;
                    return ucwords(strtolower($line));
                }
            }
        }
        return '';
    }

    // Extract a date near a given set of keywords
    private static function extractDate(string $text, array $keywords): string
    {
        // Date patterns: MM/DD/YYYY, DD/MM/YYYY, YYYY-MM-DD, DD Mon YYYY, Month DD, YYYY
        $datePatterns = [
            '/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})\b/',       // MM/DD/YYYY or DD/MM/YYYY
            '/\b(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})\b/',       // YYYY-MM-DD
            '/\b(\d{1,2})\s+(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{4})\b/i',
            '/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{1,2})[,\s]+(\d{4})\b/i',
        ];

        $lines = preg_split('/\r?\n/', $text);
        $kwStr = implode('|', array_map('preg_quote', $keywords));

        // First pass: look for dates near keyword lines
        for ($i = 0; $i < count($lines); $i++) {
            $lline = strtolower($lines[$i]);
            if (preg_match('/(' . $kwStr . ')/i', $lline)) {
                // Search this line and next 2 lines
                $searchRange = implode(' ', array_slice($lines, $i, 3));
                foreach ($datePatterns as $dp) {
                    if (preg_match($dp, $searchRange, $m)) {
                        return self::normalizeDate($m[0]);
                    }
                }
            }
        }

        // Second pass: return first date found anywhere in document (for DOB only)
        if (in_array('birth', $keywords)) {
            foreach ($datePatterns as $dp) {
                if (preg_match($dp, $text, $m)) {
                    $ts = strtotime($m[0]);
                    // Must be a plausible birth date (between 1900 and 18 years ago)
                    if ($ts && $ts < strtotime('-16 years') && $ts > strtotime('-120 years')) {
                        return date('Y-m-d', $ts);
                    }
                }
            }
        }

        return '';
    }

    // Normalize a date string to YYYY-MM-DD
    private static function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if (!$raw) return '';
        $ts = strtotime($raw);
        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    // Extract document/ID number
    private static function extractDocNumber(string $text): string
    {
        // Look for labelled ID numbers first
        if (preg_match('/(?:id\s*no|card\s*no|license\s*no|document\s*no|no\.|number)\s*[:\-–\.]\s*([A-Z0-9\-]{5,20})/i', $text, $m)) {
            return strtoupper(trim($m[1]));
        }
        // Philippine LTO license number: X##-##-######
        if (preg_match('/\b[A-Z]\d{2}-\d{2}-\d{6}\b/', $text, $m)) {
            return $m[0];
        }
        // SSS number: ##-#######-#
        if (preg_match('/\b\d{2}-\d{7}-\d\b/', $text, $m)) {
            return $m[0];
        }
        // PhilSys / PCN: ####-####-####
        if (preg_match('/\b\d{4}[\s\-]\d{4}[\s\-]\d{4}\b/', $text, $m)) {
            return preg_replace('/\s/', '-', trim($m[0]));
        }
        // Generic alphanumeric ID (8-16 chars)
        if (preg_match('/\b([A-Z]{1,3}[\-]?\d{6,14})\b/', $text, $m)) {
            return $m[1];
        }
        return '';
    }

    // Clean a name string
    private static function cleanName(string $name): string
    {
        // Remove trailing/leading punctuation, normalize spaces
        $name = preg_replace('/[^A-Za-zÑñÁÉÍÓÚáéíóú\s\-\.]/', '', $name);
        $name = preg_replace('/\s+/', ' ', trim($name));
        // Proper case
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    // Estimate confidence based on text quality and field extraction
    private static function estimateConfidence(string $text, array $parsed): float
    {
        $score = 0.0;
        $wordCount = str_word_count($text);

        // Minimum readable text
        if ($wordCount < 5)   return 0.05;
        if ($wordCount < 15)  $score += 0.10;
        else                  $score += 0.30;

        // Gov ID detected
        if ($parsed['is_gov_id'])  $score += 0.25;

        // Name found
        if ($parsed['name'])       $score += 0.20;

        // DOB found
        if ($parsed['dob'])        $score += 0.15;

        // Doc number found
        if ($parsed['doc_number']) $score += 0.10;

        return min(1.0, $score);
    }
}
