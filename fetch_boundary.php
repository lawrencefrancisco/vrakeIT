<?php
$url = 'https://overpass-api.de/api/interpreter';
$query = '[out:json];area["name"="Philippines"]->.searchArea;relation["name"="Valenzuela"]["admin_level"="4"](area.searchArea);out geom;';
$data = http_build_query(['data' => $query]);
$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: VrakeIT/1.0\r\n",
        'method'  => 'POST',
        'content' => $data,
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
if ($result === FALSE) {
    die('Error fetching data');
}
$data = json_decode($result, true);
$coords = [];
if (isset($data['elements'])) {
    foreach ($data['elements'] as $element) {
        if ($element['type'] === 'relation' && isset($element['members'])) {
            foreach ($element['members'] as $member) {
                if ($member['type'] === 'way' && isset($member['geometry'])) {
                    foreach ($member['geometry'] as $node) {
                        $coords[] = [$node['lat'], $node['lon']];
                    }
                }
            }
        }
    }
}
if (!empty($coords)) {
    file_put_contents('assets/js/valenzuela_boundary.js', 'const VALENZUELA_POLY = ' . json_encode($coords) . ';');
    echo 'Saved ' . count($coords) . ' points.';
} else {
    // If it fails with admin_level 4, let's fallback to searching near Manila coordinates
    $query2 = '[out:json];relation["name"="Valenzuela"](14.63, 120.9, 14.8, 121.1);out geom;';
    $data2 = http_build_query(['data' => $query2]);
    $options['http']['content'] = $data2;
    $context2  = stream_context_create($options);
    $result2 = file_get_contents($url, false, $context2);
    $data2 = json_decode($result2, true);
    if (isset($data2['elements'])) {
        foreach ($data2['elements'] as $element) {
            if ($element['type'] === 'relation' && isset($element['members'])) {
                foreach ($element['members'] as $member) {
                    if ($member['type'] === 'way' && isset($member['geometry'])) {
                        foreach ($member['geometry'] as $node) {
                            $coords[] = [$node['lat'], $node['lon']];
                        }
                    }
                }
            }
        }
    }
    if (!empty($coords)) {
        file_put_contents('assets/js/valenzuela_boundary.js', 'const VALENZUELA_POLY = ' . json_encode($coords) . ';');
        echo 'Saved ' . count($coords) . ' points using bounding box.';
    } else {
        echo 'No coords found.';
    }
}
