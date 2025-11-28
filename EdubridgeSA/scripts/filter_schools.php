<?php
declare(strict_types=1);

// Filters schools.csv to include only High Schools / Secondary Schools / Colleges
// and exclude names containing Primary, Pre-school, Educare, or ECD.

function csv_path(string $name): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . $name;
}

function detect_delimiter(string $line): string {
    $counts = ["\t" => substr_count($line, "\t"), "," => substr_count($line, ","), ";" => substr_count($line, ";")];
    arsort($counts);
    $top = key($counts);
    return $counts[$top] > 0 ? $top : ",";
}

function mapProvinceCode(string $code): string {
    $mapping = [
        'WC' => 'Western Cape',
        'EC' => 'Eastern Cape',
        'FS' => 'Free State',
        'GP' => 'Gauteng',
        'KZN' => 'KwaZulu-Natal',
        'LP' => 'Limpopo',
        'MP' => 'Mpumalanga',
        'NC' => 'Northern Cape',
        'NW' => 'North West'
    ];
    $upper = strtoupper(trim($code));
    return $mapping[$upper] ?? trim($code);
}

$src = csv_path('schools.csv');
$dst = csv_path('schools_filtered.csv');

if (!is_file($src)) {
    fwrite(STDERR, "Source schools.csv not found at $src\n");
    exit(1);
}

$h = fopen($src, 'r');
if ($h === false) {
    fwrite(STDERR, "Unable to open source schools.csv\n");
    exit(1);
}

// Build sample window and detect header + delimiter
$sample_lines = [];
$tabs_total = 0; $commas_total = 0;
$first = fgets($h);
if ($first !== false) {
    $firstTrim = rtrim($first, "\r\n");
    if ($firstTrim !== '') {
        $d = detect_delimiter($firstTrim);
        $cols = array_map(function($v){ return strtolower(trim((string)$v)); }, str_getcsv($firstTrim, $d));
        $isPlaceholder = (count($cols) === 2) && ((($cols[0] === 'province' && $cols[1] === 'name') || ($cols[0] === 'name' && $cols[1] === 'province')));
        if (!$isPlaceholder) {
            $sample_lines[] = $firstTrim;
            $tabs_total += substr_count($firstTrim, "\t");
            $commas_total += substr_count($firstTrim, ",");
        }
    }
}
for ($i = 0; $i < 4; $i++) {
    $line = fgets($h);
    if ($line === false) break;
    $trimmed = rtrim($line, "\r\n");
    if ($trimmed === '') continue;
    $sample_lines[] = $trimmed;
    $tabs_total += substr_count($trimmed, "\t");
    $commas_total += substr_count($trimmed, ",");
}

if (count($sample_lines) === 0) {
    fclose($h);
    fwrite(STDERR, "No data lines detected in schools.csv\n");
    exit(1);
}

$delimiter = $tabs_total > $commas_total ? "\t" : ",";
$header_line = $sample_lines[0];
$headers = array_map(function($v){ return trim((string)$v); }, str_getcsv($header_line, $delimiter));
$headerCount = count($headers);
if ($headerCount === 0) {
    fclose($h);
    fwrite(STDERR, "Header not detected in schools.csv\n");
    exit(1);
}

$rowsBuffer = [];
for ($j = 1; $j < count($sample_lines); $j++) {
    $rowsBuffer[] = str_getcsv($sample_lines[$j], $delimiter);
}

$items = [];
$push_if_allowed = function(array $rowArr) use ($headers, &$items) {
    if (count($rowArr) !== count($headers)) {
        $rowArr = array_slice(array_pad($rowArr, count($headers), ''), 0, count($headers));
    }
    $combined = @array_combine($headers, $rowArr);
    if ($combined === false || !is_array($combined)) { return; }
    foreach ($combined as $k => $v) { $combined[$k] = is_string($v) ? trim($v) : $v; }

    $provinceCode = $combined['Province'] ?? ($combined['province'] ?? '');
    $provinceName = mapProvinceCode($provinceCode);
    $schoolName = $combined['ECD_Name'] ?? ($combined['School Name'] ?? ($combined['School'] ?? ($combined['name'] ?? '')));
    if ($schoolName === '') return;

    $n = strtolower($schoolName);
    $hasAllowed = (strpos($n, 'high school') !== false) || (strpos($n, 'secondary school') !== false) || (strpos($n, 'college') !== false);
    // Disallowed: Primary, Pre-school (with or without hyphen), Educare, ECD
    $hasDisallowed = (strpos($n, 'primary') !== false)
        || (strpos($n, 'pre-school') !== false)
        || (strpos($n, 'preschool') !== false)
        || (strpos($n, 'pre school') !== false)
        || (strpos($n, 'educare') !== false)
        || preg_match('/\becd\b/i', $schoolName);
    if ($hasAllowed && !$hasDisallowed) {
        $items[] = [$provinceName, $schoolName];
    }
};

foreach ($rowsBuffer as $row) {
    $push_if_allowed($row);
}

while (($row = fgetcsv($h, 0, $delimiter)) !== false) {
    // If TSV and we detect a CSV section appended, stop early
    if ($delimiter === "\t" && count($row) === 1) {
        $cell = (string)$row[0];
        if ($cell !== '' && strpos($cell, ',') !== false) {
            break;
        }
    }
    if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; }
    $push_if_allowed($row);
}
fclose($h);

$w = fopen($dst, 'w');
if ($w === false) {
    fwrite(STDERR, "Unable to write filtered CSV to $dst\n");
    exit(1);
}
fputcsv($w, ['Province', 'School Name']);
foreach ($items as $it) { fputcsv($w, $it); }
fclose($w);

echo "Filtered rows written: " . count($items) . " to $dst\n";