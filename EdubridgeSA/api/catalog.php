<?php
declare(strict_types=1);
header('Content-Type: application/json');
// Allow catalog API to function without a database when unavailable
define('CATALOG_DB_OPTIONAL', true);
require_once __DIR__ . '/../config.php';

function csv_path(string $name): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . $name;
}

function detect_delimiter(string $line): string {
    $counts = ["\t" => substr_count($line, "\t"), "," => substr_count($line, ","), ";" => substr_count($line, ";")];
    arsort($counts);
    $top = key($counts);
    // Default to comma if nothing detected
    return $counts[$top] > 0 ? $top : ",";
}

function read_csv(string $file): array {
    $items = [];
    if (!is_file($file)) return $items;
    if (($h = fopen($file, 'r')) === false) return $items;

    $line1 = fgets($h);
    if ($line1 === false) { fclose($h); return $items; }
    $d1 = detect_delimiter($line1);
    $headers1 = array_map(fn($h) => trim((string)$h), str_getcsv($line1, $d1));

    // Peek the next line to improve delimiter detection for inconsistent files
    $line2 = fgets($h);
    $use_line2_as_header = false;
    $active_delim = $d1;
    if ($line2 !== false) {
        $d2 = detect_delimiter($line2);
        $cols_d1 = str_getcsv($line2, $d1);
        $cols_d2 = str_getcsv($line2, $d2);
        // Heuristic: if second line splits into many more columns with its own delimiter,
        // and looks like a header, prefer it as the header and switch delimiter.
        if (count($cols_d2) > max(count($cols_d1), count($headers1))) {
            $use_line2_as_header = true;
            $active_delim = $d2;
            $headers = array_map(fn($h) => trim((string)$h), $cols_d2);
        } else {
            $headers = $headers1;
        }
    } else {
        $headers = $headers1;
    }

    // Read remaining lines (include line2 as data if it wasn't used as header)
    if (!$use_line2_as_header && $line2 !== false) {
        $rows_to_process = [$line2];
    } else {
        $rows_to_process = [];
    }

    while (($line = fgets($h)) !== false) {
        $rows_to_process[] = $line;
    }
    fclose($h);

    foreach ($rows_to_process as $line) {
        $row = str_getcsv($line, $active_delim);
        // Skip empty rows
        if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; }
        // Align row length to headers length
        if (count($row) !== count($headers)) {
            $row = array_slice(array_pad($row, count($headers), ''), 0, count($headers));
        }
        $combined = @array_combine($headers, $row);
        if ($combined === false || !is_array($combined)) { continue; }
        foreach ($combined as $k => $v) { $combined[$k] = is_string($v) ? trim($v) : $v; }
        $items[] = $combined;
    }

    return $items;
}

function mapProvinceCode(string $code): string {
    // Normalise a variety of province codes and names into canonical names
    $upper = strtoupper(trim($code));
    // Handle common synonyms and codes used across datasets (e.g., NATEMIS uses GT for Gauteng)
    $mapping = [
        // Western Cape
        'WC' => 'Western Cape', 'WESTERN CAPE' => 'Western Cape',
        // Eastern Cape
        'EC' => 'Eastern Cape', 'EASTERN CAPE' => 'Eastern Cape',
        // Free State
        'FS' => 'Free State', 'FREE STATE' => 'Free State',
        // Gauteng (accept GP, GT, GAU and name variants)
        'GP' => 'Gauteng', 'GT' => 'Gauteng', 'GAU' => 'Gauteng', 'GAUTENG' => 'Gauteng', 'GAUTENG PROVINCE' => 'Gauteng',
        // KwaZulu-Natal
        'KZN' => 'KwaZulu-Natal', 'KWAZULU-NATAL' => 'KwaZulu-Natal', 'KWAZULU NATAL' => 'KwaZulu-Natal',
        // Limpopo
        'LP' => 'Limpopo', 'LIMPOPO' => 'Limpopo',
        // Mpumalanga
        'MP' => 'Mpumalanga', 'MPUMALANGA' => 'Mpumalanga',
        // Northern Cape
        'NC' => 'Northern Cape', 'NORTHERN CAPE' => 'Northern Cape',
        // North West
        'NW' => 'North West', 'NORTH WEST' => 'North West'
    ];
    return $mapping[$upper] ?? trim($code);
}

function get_universities(?PDO $pdo): array {
    $items = [];
    // Prefer DB if table exists and connection is available
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->query('SELECT name, code FROM universities ORDER BY name');
            $db_items = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $db_items[] = [
                    'name' => $row['name'],
                    'code' => $row['code'] ?? null,
                    'type' => 'public' // default type if unknown
                ];
            }
            $items = $db_items;
        } catch (Throwable $e) {
            // Table not available, fallback to CSV
            $items = [];
        }
    }
    // Merge with CSV list to include private institutions and ensure completeness
    $csv_items = read_csv(csv_path('universities.csv'));
    $byName = [];
    foreach ($items as $u) { $byName[strtolower($u['name'])] = $u; }
    foreach ($csv_items as $u) {
        $key = strtolower($u['name']);
        $byName[$key] = [
            'name' => $u['name'],
            'code' => $u['code'] ?? null,
            'type' => $u['type'] ?? 'public'
        ];
    }
    $items = array_values($byName);
    return $items;
}

function get_courses(): array {
    $items = read_csv(csv_path('courses.csv'));
    return array_map(function($c){
        $name = $c['course'] ?? ($c['name'] ?? '');
        return [
            'category' => $c['category'] ?? 'General',
            'course' => $name,
            'name' => $name
        ];
    }, $items);
}

function get_schools(): array {
    // Prefer api/schools.csv (full EMIS) if present, then filtered, then root fallback
    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'schools.csv',
        csv_path('schools_filtered.csv'),
        csv_path('schools.csv')
    ];
    $file = '';
    foreach ($candidates as $cand) {
        if (is_file($cand) && filesize($cand) > 0) { $file = $cand; break; }
    }
    $items = [];

    // If file is missing, log and return empty
    if (!is_file($file)) {
        error_log('No school records found in schools.csv');
        return $items;
    }

    $h = fopen($file, 'r');
    if ($h === false) {
        error_log('No school records found in schools.csv');
        return $items;
    }
    // Read first line and decide if it is a placeholder or the real header
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
                $sample_lines[] = $firstTrim; // treat as header candidate
                $tabs_total += substr_count($firstTrim, "\t");
                $commas_total += substr_count($firstTrim, ",");
            }
        }
    }

    // Read the next few non-empty lines to detect delimiter and find header
    for ($i = 0; $i < 4; $i++) {
        $line = fgets($h);
        if ($line === false) break;
        $trimmed = rtrim($line, "\r\n");
        if ($trimmed === '') continue; // skip blank lines
        $sample_lines[] = $trimmed;
        $tabs_total += substr_count($trimmed, "\t");
        $commas_total += substr_count($trimmed, ",");
    }

    if (count($sample_lines) === 0) {
        fclose($h);
        error_log('No school records found in schools.csv');
        return $items;
    }

    // Detect delimiter across multiple sample lines (tabs vs commas)
    $delimiter = $tabs_total > $commas_total ? "\t" : ",";

    // First non-empty sampled line becomes the header
    $header_line = $sample_lines[0];
    $headers = array_map(function($h){ return trim((string)$h); }, str_getcsv($header_line, $delimiter));
    $headerCount = count($headers);
    if ($headerCount === 0) {
        fclose($h);
        error_log('No school records found in schools.csv');
        return $items;
    }

    $seen = [];
    // Process any remaining sampled lines (beyond header) as initial data rows
    for ($j = 1; $j < count($sample_lines); $j++) {
        $row = str_getcsv($sample_lines[$j], $delimiter);
        // Skip empty rows
        if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; }
        if ($delimiter === "\t" && count($row) === 1) {
            $cell = (string)$row[0];
            // If a TSV row was parsed as a single cell and contains commas,
            // it likely indicates a malformed line. Skip it but DO NOT stop
            // processing the rest of the file.
            if ($cell !== '' && strpos($cell, ',') !== false) { continue; }
        }
        if (count($row) !== $headerCount) {
            $row = array_slice(array_pad($row, $headerCount, ''), 0, $headerCount);
        }
        $combined = array_combine($headers, $row);
        if ($combined === false || !is_array($combined)) { continue; }
        foreach ($combined as $k => $v) { $combined[$k] = is_string($v) ? trim($v) : $v; }
        $provinceCode = $combined['Province'] ?? ($combined['province'] ?? '');
        $provinceName = mapProvinceCode($provinceCode);
        $schoolName = $combined['Official_Institution_Name']
            ?? ($combined['ECD_Name']
            ?? ($combined['School Name']
            ?? ($combined['School']
            ?? ($combined['Name']
            ?? ($combined['name'] ?? '')))));
        if ($schoolName !== '' && $provinceName !== '') {
            $key = strtolower($provinceName . '|' . $schoolName);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $items[] = [ 'province' => $provinceName, 'name' => $schoolName ];
            }
        }
    }

    // Continue with the rest of file
    while (($row = fgetcsv($h, 0, $delimiter)) !== false) {
        // If we are reading TSV but encounter a CSV section appended, stop early
        if ($delimiter === "\t" && count($row) === 1) {
            $cell = (string)$row[0];
            // Encountered a malformed TSV line that looks like CSV; skip it
            // and continue reading subsequent lines to avoid truncating output.
            if ($cell !== '' && strpos($cell, ',') !== false) {
                continue;
            }
        }
        // Skip empty rows
        if ($row === [null] || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; }
        // Align row length to headers length
        if (count($row) !== $headerCount) {
            $row = array_slice(array_pad($row, $headerCount, ''), 0, $headerCount);
        }
        $combined = array_combine($headers, $row);
        if ($combined === false || !is_array($combined)) { continue; }
        foreach ($combined as $k => $v) { $combined[$k] = is_string($v) ? trim($v) : $v; }

        // Map to expected structure (keep current logic)
        $provinceCode = $combined['Province'] ?? ($combined['province'] ?? '');
        $provinceName = mapProvinceCode($provinceCode);
        $schoolName = $combined['Official_Institution_Name']
            ?? ($combined['ECD_Name']
            ?? ($combined['School Name']
            ?? ($combined['School']
            ?? ($combined['Name']
            ?? ($combined['name'] ?? '')))));

        if ($schoolName !== '' && $provinceName !== '') {
            $key = strtolower($provinceName . '|' . $schoolName);
            if (isset($seen[$key])) continue; // de-duplicate
            $seen[$key] = true;
            $items[] = [
                'province' => $provinceName,
                'name' => $schoolName
            ];
        }
    }
    fclose($h);

    if (count($items) === 0) {
        error_log('No school records found in schools.csv');
    }

    // Sort alphabetically by school name (A–Z). Keep all types.
    usort($items, function($a, $b){
        return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    });

    return $items;
}

$type = strtolower(trim($_GET['type'] ?? ''));
$q = strtolower(trim($_GET['q'] ?? ''));
$province = trim($_GET['province'] ?? '');
// Treat placeholder values as no filter (defensive against frontend variations)
$pl = strtolower($province);
if ($pl === 'all provinces' || $pl === 'all' || $pl === 'any' || $pl === 'all-za') {
    $province = '';
}
$category = trim($_GET['category'] ?? '');
$university = trim($_GET['university'] ?? '');
// Normalize category placeholder to no filter
$cl = strtolower($category);
if ($cl === 'all categories' || $cl === 'all' || $cl === 'any' || $cl === 'all-categories') {
    $category = '';
}

// Helper: attempt to load courses associated with a specific university via DB if available,
// otherwise fall back to generic course list (from CSV).
function get_courses_for_university(?PDO $pdo, string $universityName, ?string $category = null): array {
    // Always merge DB-backed courses (if available) with CSV to ensure completeness.
    // This prevents partial DB datasets (e.g., ~50 rows) from limiting the dropdown when
    // the CSV contains the full catalog (e.g., 114+ courses).
    $coursesDb = [];
    if ($pdo instanceof PDO && $universityName !== '') {
        try {
            $stmt = $pdo->prepare('SELECT course_name, category FROM university_courses WHERE university_name = ?');
            $stmt->execute([$universityName]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $name = trim((string)($r['course_name'] ?? ''));
                if ($name === '') continue;
                $coursesDb[] = [
                    'course' => $name,
                    'name' => $name,
                    'category' => $r['category'] ?? 'General',
                    'university' => $universityName,
                ];
            }
        } catch (Throwable $e) {
            // Table likely missing; ignore and rely on CSV below
        }
    }

    // CSV baseline
    $coursesCsv = get_courses();

    // Optional category filter (applied to both sources)
    if ($category !== null && $category !== '') {
        $coursesDb = array_values(array_filter($coursesDb, function($c) use ($category){
            return strtolower($c['category'] ?? '') === strtolower($category);
        }));
        $coursesCsv = array_values(array_filter($coursesCsv, function($c) use ($category){
            return strtolower($c['category'] ?? '') === strtolower($category);
        }));
    }

    // Merge and de-duplicate by course name (case-insensitive)
    $byName = [];
    foreach (array_merge($coursesCsv, $coursesDb) as $c) {
        $key = strtolower((string)($c['course'] ?? $c['name'] ?? ''));
        if ($key === '') continue;
        // Prefer DB-enriched record when available; otherwise CSV record
        if (!isset($byName[$key])) {
            $byName[$key] = $c;
        }
    }
    $courses = array_values($byName);

    // Sort consistently
    usort($courses, function($a,$b){
        $an = strtolower(($a['course'] ?? $a['name'] ?? ''));
        $bn = strtolower(($b['course'] ?? $b['name'] ?? ''));
        return $an <=> $bn;
    });
    return $courses;
}

// Helper: provide specialization suggestions by course or category
function get_specializations(?PDO $pdo, string $course = '', string $category = ''): array {
    $course = trim($course);
    $category = trim($category);

    // Try database first if table exists
    $dbItems = [];
    if ($pdo instanceof PDO) {
        try {
            // Prefer exact course match, then optional category match
            $sql = 'SELECT course_name, specialization_name, field, career_paths FROM course_specializations WHERE 1=1';
            $params = [];
            if ($course !== '') { $sql .= ' AND LOWER(course_name) = LOWER(?)'; $params[] = $course; }
            if ($category !== '') { $sql .= ' AND LOWER(category) = LOWER(?)'; $params[] = $category; }
            $sql .= ' ORDER BY specialization_name';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = trim((string)($row['specialization_name'] ?? ''));
                if ($name === '') continue;
                $dbItems[] = ['name' => $name];
            }
        } catch (Throwable $e) {
            // Table may not exist; fall back to CSV below
        }
    }

    // CSV fallback
    $csvItemsRaw = read_csv(csv_path('specializations.csv'));
    $csvItems = [];
    foreach ($csvItemsRaw as $r) {
        $cName = trim((string)($r['course_name'] ?? ''));
        $sName = trim((string)($r['specialization_name'] ?? ''));
        $field = trim((string)($r['field'] ?? ''));
        $paths = trim((string)($r['career_paths'] ?? ''));
        if ($sName === '') continue;
        // Filter by course first; if no course param, try category keyword match
        $include = false;
        if ($course !== '' && strcasecmp($cName, $course) === 0) { $include = true; }
        elseif ($course === '' && $category !== '') {
            // Weak mapping: include rows whose field/category hints match
            $include = (strcasecmp($field, $category) === 0);
        }
        if ($include) { $csvItems[] = ['name' => $sName]; }
    }

    // If nothing found by exact filters, attempt heuristic based on course keywords
    if (empty($dbItems) && empty($csvItems) && $course !== '') {
        $lc = strtolower($course);
        $catGuess = '';
        if (strpos($lc,'bcom') !== false || strpos($lc,'business') !== false || strpos($lc,'account') !== false) $catGuess = 'Finance';
        elseif (strpos($lc,'bed') !== false || strpos($lc,'education') !== false || strpos($lc,'pgce') !== false) $catGuess = 'Education';
        elseif (strpos($lc,'bsc') !== false || strpos($lc,'computer') !== false || strpos($lc,'information') !== false || strpos($lc,'it') !== false) $catGuess = 'IT';
        elseif (strpos($lc,'eng') !== false) $catGuess = 'Engineering';
        elseif (strpos($lc,'nurs') !== false || strpos($lc,'pharm') !== false || strpos($lc,'med') !== false) $catGuess = 'Nursing';
        elseif (strpos($lc,'ba ') !== false || strpos($lc,' law') !== false) $catGuess = 'Humanities';
        elseif (strpos($lc,'design') !== false || strpos($lc,'art') !== false || strpos($lc,'architecture') !== false || strpos($lc,'fashion') !== false) $catGuess = 'Arts';
        if ($catGuess !== '') {
            foreach ($csvItemsRaw as $r) {
                $field = trim((string)($r['field'] ?? ''));
                $sName = trim((string)($r['specialization_name'] ?? ''));
                if ($sName === '') continue;
                if (strcasecmp($field, $catGuess) === 0) { $csvItems[] = ['name' => $sName]; }
            }
        }
    }

    // Merge and de-duplicate
    $byName = [];
    foreach (array_merge($dbItems, $csvItems) as $it) {
        $key = strtolower($it['name']);
        if (!isset($byName[$key])) $byName[$key] = $it;
    }
    $items = array_values($byName);
    usort($items, function($a,$b){ return strcasecmp($a['name'],$b['name']); });
    return $items;
}

try {
    // Validate type early with a clear error
    $valid_types = ['universities','courses','schools','specializations'];
    if ($type === '' || !in_array($type, $valid_types, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type parameter. Valid values: universities, courses, or schools']);
        exit;
    }
    $result = [];
    switch ($type) {
        case 'universities':
            $items = get_universities($pdo);
            if ($q !== '') {
                $items = array_values(array_filter($items, function($u) use ($q){
                    return strpos(strtolower($u['name']), $q) !== false || ($u['code'] && strpos(strtolower($u['code']), $q) !== false);
                }));
            }
            $result = ['items' => $items];
            break;
        case 'courses':
            // Enhanced linking: attempt DB-backed associations per university, fallback to generic
            $items = get_courses_for_university($pdo, $university, $category !== '' ? $category : null);
            if ($q !== '') {
                $items = array_values(array_filter($items, function($c) use ($q){
                    return strpos(strtolower($c['course'] ?? $c['name'] ?? ''), $q) !== false;
                }));
            }
            $result = ['items' => $items];
            break;
        case 'specializations':
            $items = get_specializations($pdo, $_GET['course'] ?? '', $category);
            if ($q !== '') {
                $items = array_values(array_filter($items, function($s) use ($q){
                    return strpos(strtolower($s['name'] ?? ''), strtolower($q)) !== false;
                }));
            }
            $result = ['items' => $items];
            break;
        case 'schools':
            $items = get_schools();
            if ($province !== '') {
                $items = array_values(array_filter($items, function($s) use ($province){
                    return strtolower($s['province']) === strtolower($province);
                }));
            }
            if ($q !== '') {
                $items = array_values(array_filter($items, function($s) use ($q){
                    return strpos(strtolower($s['name']), $q) !== false;
                }));
            }
            $result = ['items' => $items];
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid type parameter. Valid values: universities, courses, or schools']);
            exit;
    }
    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}