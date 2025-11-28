<?php
/**
 * Full System Health Check
 * One-page diagnostics to validate environment, config, DB, sessions, security, filesystem, and app flow.
 *
 * Usage: place in project root and open in browser.
 * Security: remove this file from production after use.
 */

// Try to use the same session hardening as the app
@require_once __DIR__ . '/session_config.php';
@require_once __DIR__ . '/config.php';

$results = [];

function section($title) {
    echo "<h2>$title</h2>";
}

function ok($msg) { echo '<div class="ok">✅ ' . htmlspecialchars($msg) . '</div>'; }
function warn($msg) { echo '<div class="warn">⚠️ ' . htmlspecialchars($msg) . '</div>'; }
function err($msg) { echo '<div class="err">❌ ' . htmlspecialchars($msg) . '</div>'; }

function tailFile($path, $lines = 40) {
    if (!is_file($path)) return '';
    $arr = @file($path, FILE_IGNORE_NEW_LINES);
    if (!$arr) return '';
    return implode("\n", array_slice($arr, -$lines));
}

// 1) Environment checks
ob_start();
section('Environment');
$phpv = PHP_VERSION;
ok("PHP version: $phpv");
$required_exts = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl'];
foreach ($required_exts as $ext) {
    if (extension_loaded($ext)) ok("Extension loaded: $ext"); else err("Missing extension: $ext");
}
$display_errors = ini_get('display_errors');
$memory_limit = ini_get('memory_limit');
ok('display_errors=' . ($display_errors ? 'On' : 'Off') . ', memory_limit=' . $memory_limit);
$results['environment'] = ob_get_clean();

// 2) Config checks
ob_start();
section('Config');
$cfg_ok = true;
$consts = ['DB_HOST','DB_NAME','DB_USER','DB_PASS'];
foreach ($consts as $c) {
    if (defined($c)) ok("Defined: $c"); else { $cfg_ok=false; err("Missing constant: $c"); }
}
if ($cfg_ok) ok('Database config constants present');
$results['config'] = ob_get_clean();

// 3) Database connectivity and schema
ob_start();
section('Database');
$pdo = null;
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    ok('Connected to database');
    $pdo->query('SELECT 1');
    ok('SELECT 1 succeeded');
} catch (Throwable $e) {
    err('DB connection failed: ' . $e->getMessage());
}

if ($pdo) {
    $expectedTables = [
        'users' => ['id','email','password_hash','status','email_verified'],
        'applications' => ['id','email_address','reference_number','status'],
        'parent_guardian_details' => ['application_id'],
        'documents' => ['application_id'],
        'application_steps' => ['application_id','step_name','step_order'],
        'application_status_history' => ['application_id','previous_status','new_status'],
    ];
    foreach ($expectedTables as $table => $reqCols) {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            $cols = array_map(fn($r) => $r['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($cols) {
                ok("Table exists: $table");
                $missing = array_diff($reqCols, $cols);
                if ($missing) warn("Missing columns in $table: " . implode(', ', $missing));
                else ok("Required columns present in $table");
            }
        } catch (Throwable $e) {
            warn("Table check failed for $table: " . $e->getMessage());
        }
    }
}
$results['database'] = ob_get_clean();

// 4) Session and Auth
ob_start();
section('Session & Auth');
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}
if (function_exists('isLoggedIn')) {
    $status = isLoggedIn() ? 'Yes' : 'No';
    ok('isLoggedIn(): ' . $status);
} else {
    warn('isLoggedIn() not found (session_config.php)');
}
$keys = ['student_logged_in','student_id','student_email','student_name','reference_number','application_status','user_id','user_type'];
foreach ($keys as $k) {
    if (isset($_SESSION[$k])) ok("Session key set: $k = " . htmlspecialchars((string)$_SESSION[$k]));
    else warn("Session key missing: $k");
}
$results['session'] = ob_get_clean();

// 5) Security: CSRF
ob_start();
section('Security: CSRF');
$csrf_ok = false;
try {
    @require_once __DIR__ . '/security-utils.php';
    if (class_exists('SecurityUtils') && method_exists('SecurityUtils','generateCSRFToken') && method_exists('SecurityUtils','validateCSRFToken')) {
        $token = SecurityUtils::generateCSRFToken();
        if ($token && SecurityUtils::validateCSRFToken($token)) {
            ok('CSRF token generate/validate OK');
            $csrf_ok = true;
        } else {
            err('CSRF validation failed');
        }
    } else {
        warn('SecurityUtils::generateCSRFToken/validateCSRFToken not available');
    }
} catch (Throwable $e) {
    err('CSRF check error: ' . $e->getMessage());
}
$results['csrf'] = ob_get_clean();

// 6) Filesystem permissions & .htaccess
ob_start();
section('Filesystem');
$dirs = [
    'uploads',
    'uploads/applications',
    'uploads/applications/academic_results',
    'uploads/applications/id_documents',
    'uploads/applications/parent_guardian_id',
    'uploads/applications/proof_of_residence',
    'uploads/id_documents',
    'uploads/matric_certificates',
    'uploads/parent_guardian_id',
    'uploads/profile_pictures',
    'uploads/proof_of_residence'
];
foreach ($dirs as $d) {
    if (is_dir($d)) {
        ok("Dir exists: $d");
        if (is_writable($d)) ok("Writable: $d"); else warn("Not writable: $d");
    } else {
        warn("Missing dir: $d");
    }
}
if (is_file('uploads/.htaccess')) ok('uploads/.htaccess present'); else warn('uploads/.htaccess missing');
$results['filesystem'] = ob_get_clean();

// 7) Form action scan (empty or missing action)
ob_start();
section('Form Actions');
$scanRoots = [__DIR__];
$skipDirs = ['PHPMailer','uploads','images','css','js'];
$issues = 0;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    $path = $file->getPathname();
    if ($file->isDir()) continue;
    $rel = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $path);
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    if (in_array($parts[0], $skipDirs)) continue;
    if (substr($path, -4) !== '.php') continue;
    $content = @file_get_contents($path);
    if ($content === false) continue;
    // Look for <form ...> and check action attribute
    if (preg_match_all('/<form[^>]*>/i', $content, $forms)) {
        foreach ($forms[0] as $f) {
            if (!preg_match('/action\s*=\s*"[^"]*"|action\s*=\s*\'[^\']*\'/i', $f)) {
                warn("Form without explicit action in: $rel");
                $issues++;
            } else if (preg_match('/action\s*=\s*""|action\s*=\s*\'\'/i', $f)) {
                warn("Form with empty action in: $rel");
                $issues++;
            }
        }
    }
}
if ($issues === 0) ok('No empty/missing form actions detected');
$results['forms'] = ob_get_clean();

// 8) App flow guards (static checks)
ob_start();
section('Application Flow Guards');
function fileHas($file, $needle) {
    $c = @file_get_contents($file);
    return $c !== false && strpos($c, $needle) !== false;
}
if (fileHas(__DIR__ . '/application-access.php', 'student-login.php?redirect=')) ok('application-access: unauthenticated redirect present'); else warn('application-access: missing unauthenticated redirect');
if (fileHas(__DIR__ . '/application-access.php', 'header(\'Location: \' . $continue_url')) ok('application-access: auto-forward to continue_url present'); else warn('application-access: auto-forward to continue_url not detected');
if (fileHas(__DIR__ . '/student-login.php', 'name="redirect"')) ok('student-login: redirect input present'); else warn('student-login: redirect input missing');
if (fileHas(__DIR__ . '/includes/navigation.php', 'student-apply.php')) ok('navbar: Apply link points to student-apply.php'); else warn('navbar: Apply link target not detected');
$results['flow'] = ob_get_clean();

// 9) Logs tail
ob_start();
section('Recent Logs');
$logFiles = ['error.log','error_log.txt','form_debug.log','auth_test_results.log','login_test_results.log'];
foreach ($logFiles as $lf) {
    if (is_file($lf)) {
        echo '<details><summary>📄 ' . htmlspecialchars($lf) . '</summary><pre>' . htmlspecialchars(tailFile($lf, 60)) . '</pre></details>';
    } else {
        warn("Log not found: $lf");
    }
}
$results['logs'] = ob_get_clean();

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Full System Health Check</title>
<style>
 body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 0; padding: 20px; background: #f6f7fb; color: #222; }
 h1 { margin: 0 0 10px; }
 .wrap { max-width: 1100px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
 h2 { margin-top: 28px; margin-bottom: 10px; }
 .ok { color: #065f46; background: #ecfdf5; padding: 8px 10px; border-radius: 8px; margin: 6px 0; border: 1px solid #a7f3d0; }
 .warn { color: #92400e; background: #fffbeb; padding: 8px 10px; border-radius: 8px; margin: 6px 0; border: 1px solid #fde68a; }
 .err { color: #7f1d1d; background: #fef2f2; padding: 8px 10px; border-radius: 8px; margin: 6px 0; border: 1px solid #fecaca; }
 details { margin: 8px 0; }
 pre { white-space: pre-wrap; background: #0b1021; color: #e5e7eb; padding: 10px; border-radius: 8px; overflow: auto; max-height: 300px; }
 .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px; }
 .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Full System Health Check</h1>
    <p>Use this page to quickly assess environment, configuration, database, session, security, filesystem, forms, and logs. Remove this file after use in production.</p>

    <div class="grid">
        <div class="card"><?php echo $results['environment']; ?></div>
        <div class="card"><?php echo $results['config']; ?></div>
        <div class="card" style="grid-column: 1 / -1;"><?php echo $results['database']; ?></div>
        <div class="card"><?php echo $results['session']; ?></div>
        <div class="card"><?php echo $results['csrf']; ?></div>
        <div class="card"><?php echo $results['filesystem']; ?></div>
        <div class="card" style="grid-column: 1 / -1;"><?php echo $results['forms']; ?></div>
        <div class="card"><?php echo $results['flow']; ?></div>
        <div class="card" style="grid-column: 1 / -1;"><?php echo $results['logs']; ?></div>
    </div>

    <p style="margin-top: 18px; color:#6b7280;">Tip: If any check fails, click into related files to adjust. Consider deleting this file before deploying to production.</p>
</div>
</body>
</html>