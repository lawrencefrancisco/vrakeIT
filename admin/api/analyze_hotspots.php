<?php
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/admin_auth.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

startSecureSession();
requireAdminLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$db = getDB();
$stmt = $db->query("SELECT id, flow_type, weather_condition, road_condition, is_injured, location_lat, location_lng, location_address, incident_date, incident_time FROM reports WHERE location_lat IS NOT NULL AND location_lng IS NOT NULL");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reports)) {
    jsonResponse(false, 'No valid reports with locations found.');
}

function haversineGreatCircleDistance($latFrom, $lonFrom, $latTo, $lonTo, $earthRadius = 6371000) {
    $latFrom = deg2rad($latFrom);
    $lonFrom = deg2rad($lonFrom);
    $latTo = deg2rad($latTo);
    $lonTo = deg2rad($lonTo);
  
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
  
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
      cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

$clusters = [];
$visited = [];

foreach ($reports as $i => $r1) {
    if (isset($visited[$r1['id']])) continue;
    
    $cluster = [$r1];
    $visited[$r1['id']] = true;
    
    foreach ($reports as $j => $r2) {
        if ($i === $j || isset($visited[$r2['id']])) continue;
        
        $dist = haversineGreatCircleDistance(
            (float)$r1['location_lat'], (float)$r1['location_lng'],
            (float)$r2['location_lat'], (float)$r2['location_lng']
        );
        
        // 150 meters radius
        if ($dist <= 150) {
            $cluster[] = $r2;
            $visited[$r2['id']] = true;
        }
    }
    
    if (count($cluster) > 1) { // Only keep clusters of 2 or more
        $clusters[] = $cluster;
    }
}

usort($clusters, function($a, $b) {
    return count($b) <=> count($a);
});

// Take top 3 hotspots
$topHotspots = array_slice($clusters, 0, 3);

if (empty($topHotspots)) {
    jsonResponse(true, 'No significant hotspots detected currently.', ['hotspots' => []]);
}

$prompt = "You are an expert urban planner and traffic safety engineer for the government. Based on the following accident hotspots (clusters of incidents within 150m of each other) in Valenzuela City, analyze the patterns and suggest specific, concrete infrastructure improvements for each hotspot (e.g., 'Install LED streetlights', 'Add convex blindspot mirrors', 'Implement speed bumps', etc.). Keep it concise but highly professional.\n\n";

$hotspotData = [];

foreach ($topHotspots as $index => $cluster) {
    $count = count($cluster);
    $centerLat = 0; $centerLng = 0;
    $details = [];
    $mainAddress = $cluster[0]['location_address'];
    
    foreach ($cluster as $c) {
        $centerLat += (float)$c['location_lat'];
        $centerLng += (float)$c['location_lng'];
        $details[] = "- " . str_replace('_', ' ', $c['flow_type']) . " | Weather: " . ($c['weather_condition'] ?: 'Unknown') . " | Road: " . ($c['road_condition'] ?: 'Unknown') . " | Injured: " . ($c['is_injured'] ? 'Yes' : 'No');
    }
    $centerLat /= $count;
    $centerLng /= $count;
    
    $hotspotData[] = [
        'lat' => $centerLat,
        'lng' => $centerLng,
        'count' => $count,
        'address' => $mainAddress
    ];
    
    $prompt .= "Hotspot #" . ($index + 1) . ": Near " . $mainAddress . " ($count accidents)\n";
    $prompt .= implode("\n", $details) . "\n\n";
}

$prompt .= "Provide your analysis and suggestions directly in this exact HTML structure for each hotspot, do not use markdown code blocks like ```html. Output raw HTML only:\n";
$prompt .= "<div class='hotspot-suggestion' style='margin-bottom:20px; padding:15px; border-radius:12px; background:rgba(0,126,210,0.1); border:1px solid rgba(0,126,210,0.2);'><h4 style='font-size:16px; font-weight:700; color:#007ED2; margin-top:0;'>Hotspot #[N]: [Brief Location Name]</h4><p style='font-size:13px; color:#444; margin-bottom:8px;'><strong>Analysis:</strong> [Your short analysis]</p><p style='font-size:13px; color:#444; margin-bottom:0;'><strong>Infrastructure Suggestions:</strong><ul style='margin-top:4px; padding-left:20px; margin-bottom:0;'><li>[Suggestion 1]</li><li>[Suggestion 2]</li></ul></p></div>";

$apiKey = null;
$envPath = dirname(__DIR__, 2) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'GEMINI_API_KEY=') === 0) {
            $apiKey = trim(trim(str_replace('GEMINI_API_KEY=', '', $line)), '"\'');
        }
    }
}

if (!$apiKey) jsonResponse(false, 'AI API Key is not configured.');

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;
$postData = json_encode([
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.3]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);

if ($response === false) {
    jsonResponse(false, 'CURL Error: ' . curl_error($ch));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $resObj = json_decode($response, true);
    $analysis = $resObj['candidates'][0]['content']['parts'][0]['text'] ?? 'Could not generate analysis.';
    
    // Strip markdown formatting if AI still outputs it
    $analysis = preg_replace('/```html\s*/', '', $analysis);
    $analysis = preg_replace('/```\s*/', '', $analysis);
    
    jsonResponse(true, 'Analysis complete', [
        'hotspots' => $hotspotData,
        'ai_html' => trim($analysis)
    ]);
} else {
    $resObj = json_decode($response, true);
    $googleError = $resObj['error']['message'] ?? 'No specific error message provided by Google.';
    jsonResponse(false, 'Google API Error: ' . $googleError);
}
