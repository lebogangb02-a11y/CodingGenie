<?php
$src = __DIR__ . '/../schools.csv';
$tmp = $src . '.tmp';

function detectDelimiter($line) {
    $tabs = substr_count($line, "\t");
    $commas = substr_count($line, ",");
    return $tabs > $commas ? "\t" : ",";
}

function findIndex(array $headers, array $candidates) {
    $map = [];
    foreach ($headers as $i => $h) {
        $key = strtolower(trim($h));
        $map[$key] = $i;
    }
    foreach ($candidates as $cand) {
        $cand = strtolower($cand);
        foreach ($map as $key => $i) {
            if ($key === $cand) return $i;
        }
    }
    // substring matching fallback
    foreach ($map as $key => $i) {
        foreach ($candidates as $cand) {
            $cand = strtolower($cand);
            if (strpos($key, $cand) !== false) return $i;
        }
    }
    return -1;
}

function normalizeProvince($prov) {
    $prov = trim($prov);
    $codes = [
        'EC' => 'Eastern Cape',
        'FS' => 'Free State',
        'GP' => 'Gauteng',
        'KZN' => 'KwaZulu-Natal',
        'LP' => 'Limpopo',
        'MP' => 'Mpumalanga',
        'NC' => 'Northern Cape',
        'NW' => 'North West',
        'WC' => 'Western Cape',
    ];
    $upper = strtoupper($prov);
    if (isset($codes[$upper])) return $codes[$upper];
    // unify spacing and casing
    $prov = preg_replace('/\s+/', ' ', $prov);
    // Title case but keep hyphens
    $parts = preg_split('/\s+/', strtolower($prov));
    $parts = array_map(function($p){
        return ucfirst($p);
    }, $parts);
    $tc = implode(' ', $parts);
    // special cases
    $tc = str_replace('Kwa Zulu Natal', 'KwaZulu-Natal', $tc);
    return $tc;
}

$in = fopen($src, 'r');
if (!$in) {
    fwrite(STDERR, "Cannot open schools.csv\n");
    exit(1);
}

$out = fopen($tmp, 'w');
if (!$out) {
    fwrite(STDERR, "Cannot open temp file for writing\n");
    exit(1);
}

// Write normalized header
fwrite($out, "province,name\n");

$line1 = fgets($in);
if ($line1 === false) {
    fwrite(STDERR, "Empty file\n");
    exit(1);
}
// If the first line is already our header, skip it and read the next line as header of raw dataset
if (stripos($line1, 'province') !== false && stripos($line1, 'name') !== false && strpos($line1, ',') !== false) {
    $rawHeaderLine = fgets($in);
} else {
    $rawHeaderLine = $line1;
}
if ($rawHeaderLine === false) {
    fwrite(STDERR, "No data after header\n");
    exit(1);
}
$delimiter = detectDelimiter($rawHeaderLine);
$headers = str_getcsv($rawHeaderLine, $delimiter);

// Find indices
$provIdx = findIndex($headers, ['province']);
$nameIdx = findIndex($headers, ['ecd_name','school name','school','name']);

if ($provIdx < 0 || $nameIdx < 0) {
    fwrite(STDERR, "Could not find province or name columns in source headers\n");
    fwrite(STDERR, "Headers detected: " . implode('|', $headers) . "\n");
    exit(1);
}

$seen = [];
$countIn = 0; $countOut = 0;
while (($line = fgets($in)) !== false) {
    $row = str_getcsv($line, $delimiter);
    if (count($row) <= max($provIdx, $nameIdx)) continue;
    $prov = normalizeProvince($row[$provIdx]);
    $name = trim($row[$nameIdx]);
    if ($name === '' || $prov === '') continue;
    $key = strtolower($prov . '|' . $name);
    if (isset($seen[$key])) { $countIn++; continue; }
    $seen[$key] = true;
    fputcsv($out, [$prov, $name]);
    $countIn++; $countOut++;
}

fclose($in);
fclose($out);

// Replace original file
if (!rename($tmp, $src)) {
    fwrite(STDERR, "Failed to replace original schools.csv\n");
    exit(1);
}

fwrite(STDOUT, "Normalized schools.csv. Rows in: $countIn, rows out: $countOut\n");