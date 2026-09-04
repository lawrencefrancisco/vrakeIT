<?php
require_once __DIR__ . '/includes/TesseractProvider.php';

$binary = TesseractProvider::findBinary();
echo "Tesseract binary location: " . ($binary ?? 'NOT FOUND') . "\n";

if ($binary) {
    exec('"' . $binary . '" --version', $output, $code);
    echo "Tesseract Version Output: " . implode("\n", $output) . "\n";
}
