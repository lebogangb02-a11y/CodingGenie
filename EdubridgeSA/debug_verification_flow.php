<?php
// Debug helper for email verification flow
// Usage:
//  - Browser: /debug_verification_flow.php
//  - Target user: /debug_verification_flow.php?email=user@example.com
//  - JSON: /debug_verification_flow.php?format=json

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
if (file_exists(__DIR__ . '/config_application.php')) {
    require_once __DIR__ . '/config_application.php';
}

// Only allow debug execution when DEBUG_MODE is enabled
if (!defined('DEBUG_MODE') || DEBUG_MODE !== true) {
    http_response_code(404);
    exit;
}

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$report = [
    'environment' => [
        'php_version' => PHP_VERSION,
        'db_host' => defined('DB_HOST') ? DB_HOST : '(undefined)',
        'db_name' => defined('DB_NAME') ? DB_NAME : '(undefined)',
        'base_url' => defined('BASE_URL') ? BASE_URL : ($GLOBALS['BASE_URL'] ?? ''),
        'use_minimal_verification_template' => defined('USE_MINIMAL_VERIFICATION_TEMPLATE') ? (bool)USE_MINIMAL_VERIFICATION_TEMPLATE : null,
    ],
    'files' => [
        'verify_email' => file_exists(__DIR__ . '/verify-email.php'),
        'verify_redirect' => file_exists(__DIR__ . '/verify-redirect.php'),
        'email_templates' => file_exists(__DIR__ . '/email-templates.php'),
        'email_functions' => file_exists(__DIR__ . '/email_functions.php'),
    ],
    'schema' => [
        'users_columns' => [],
        'has_verified_at' => null,
        'has_email_verified' => null,
        'has_verification_token' => null,
        'has_verification_sent_at' => null,
        'has_status' => null,
    ],
    'users_summary' => [
        'pending_with_token' => 0,
        'verified_active' => 0,
    ],
    'target_user' => null,
    'suggested_links' => [
        'verification_link' => null,
        'resend_link' => null,
    ],
    'notes' => [
        'verify_email_requires_pending' => "verify-email.php updates user when status='pending'",
        'token_validity_hours' => 24,
    ],
];

// DB connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // Columns present in users table
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'");
    $stmt->execute([DB_NAME]);
    $cols = array_map(fn($r) => $r['COLUMN_NAME'], $stmt->fetchAll());
    $report['schema']['users_columns'] = $cols;
    $report['schema']['has_verified_at'] = in_array('verified_at', $cols, true);
    $report['schema']['has_email_verified'] = in_array('email_verified', $cols, true);
    $report['schema']['has_verification_token'] = in_array('verification_token', $cols, true);
    $report['schema']['has_verification_sent_at'] = in_array('verification_sent_at', $cols, true);
    $report['schema']['has_status'] = in_array('status', $cols, true);

    // Counts
    $report['users_summary']['pending_with_token'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='pending' AND verification_token IS NOT NULL")->fetchColumn();
    $report['users_summary']['verified_active'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND email_verified=1")->fetchColumn();

    // Target user
    $targetEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
    if ($targetEmail !== '') {
        $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, status, email_verified, verification_token, created_at, verification_sent_at FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$targetEmail]);
        $u = $stmt->fetch();
        if ($u) {
            $report['target_user'] = $u;
            $token = $u['verification_token'] ?? '';
            if (!empty($token)) {
                $base = $report['environment']['base_url'] ?: '';
                $report['suggested_links']['verification_link'] = ($base ? rtrim($base, '/') : '') . '/verify-redirect.php?token=' . $token;
                $report['suggested_links']['resend_link'] = 'resend-verification.php?email=' . urlencode($u['email']);
            }
        }
    } else {
        // Provide a sample pending user if any
        $stmt = $pdo->query("SELECT email, verification_token FROM users WHERE status='pending' AND verification_token IS NOT NULL ORDER BY id DESC LIMIT 1");
        $u = $stmt->fetch();
        if ($u) {
            $report['target_user'] = $u;
            $token = $u['verification_token'] ?? '';
            if (!empty($token)) {
                $base = $report['environment']['base_url'] ?: '';
                $report['suggested_links']['verification_link'] = ($base ? rtrim($base, '/') : '') . '/verify-redirect.php?token=' . $token;
                $report['suggested_links']['resend_link'] = 'resend-verification.php?email=' . urlencode($u['email']);
            }
        }
    }
} catch (PDOException $e) {
    $report['db_error'] = $e->getMessage();
}

// Optional JSON output
if (isset($_GET['format']) && strtolower($_GET['format']) === 'json') {
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

// HTML output
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Debug</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            padding: 24px;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
            margin-bottom: 16px;
        }

        h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        code {
            background: #eef;
            padding: 2px 4px;
            border-radius: 4px;
        }

        .ok {
            color: #2e7d32;
            font-weight: 600;
        }

        .warn {
            color: #f57c00;
            font-weight: 600;
        }

        .err {
            color: #c62828;
            font-weight: 600;
        }

        .kv {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 8px;
        }

        .kv div {
            padding: 4px 0;
            border-bottom: 1px dashed #eee;
        }

        a.btn {
            display: inline-block;
            padding: 8px 12px;
            background: #1a5fb4;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 8px;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2>Environment</h2>
        <div class="kv">
            <div>PHP Version</div>
            <div><?php echo h($report['environment']['php_version']); ?></div>
            <div>DB Host</div>
            <div><?php echo h($report['environment']['db_host']); ?></div>
            <div>DB Name</div>
            <div><?php echo h($report['environment']['db_name']); ?></div>
            <div>BASE_URL</div>
            <div><?php echo h($report['environment']['base_url']); ?></div>
            <div>Minimal Template</div>
            <div><?php echo isset($report['environment']['use_minimal_verification_template']) ? ($report['environment']['use_minimal_verification_template'] ? 'true' : 'false') : 'n/a'; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Files</h2>
        <div class="kv">
            <div>verify-email.php</div>
            <div><?php echo $report['files']['verify_email'] ? '<span class="ok">present</span>' : '<span class="err">missing</span>'; ?></div>
            <div>verify-redirect.php</div>
            <div><?php echo $report['files']['verify_redirect'] ? '<span class="ok">present</span>' : '<span class="warn">missing</span>'; ?></div>
            <div>email-templates.php</div>
            <div><?php echo $report['files']['email_templates'] ? '<span class="ok">present</span>' : '<span class="warn">missing</span>'; ?></div>
            <div>email_functions.php</div>
            <div><?php echo $report['files']['email_functions'] ? '<span class="ok">present</span>' : '<span class="warn">missing</span>'; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Users Table Schema</h2>
        <div class="kv">
            <div>Has status</div>
            <div><?php echo $report['schema']['has_status'] ? '<span class="ok">yes</span>' : '<span class="err">no</span>'; ?></div>
            <div>Has email_verified</div>
            <div><?php echo $report['schema']['has_email_verified'] ? '<span class="ok">yes</span>' : '<span class="err">no</span>'; ?></div>
            <div>Has verification_token</div>
            <div><?php echo $report['schema']['has_verification_token'] ? '<span class="ok">yes</span>' : '<span class="err">no</span>'; ?></div>
            <div>Has verification_sent_at</div>
            <div><?php echo $report['schema']['has_verification_sent_at'] ? '<span class="ok">yes</span>' : '<span class="warn">no</span>'; ?></div>
            <div>Has verified_at</div>
            <div><?php echo $report['schema']['has_verified_at'] ? '<span class="ok">yes</span>' : '<span class="warn">no</span>'; ?></div>
        </div>
        <div style="margin-top:8px;">
            <small>Columns found: <?php echo h(implode(', ', $report['schema']['users_columns'])); ?></small>
        </div>
    </div>

    <div class="card">
        <h2>Users Summary</h2>
        <div class="kv">
            <div>Pending w/ token</div>
            <div><?php echo (int)$report['users_summary']['pending_with_token']; ?></div>
            <div>Active & verified</div>
            <div><?php echo (int)$report['users_summary']['verified_active']; ?></div>
        </div>
    </div>

    <div class="card">
        <h2>Target User</h2>
        <?php if ($report['target_user']): ?>
            <div class="kv">
                <?php foreach ($report['target_user'] as $k => $v): ?>
                    <div><?php echo h($k); ?></div>
                    <div><?php echo h($v); ?></div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:12px;">
                <?php if (!empty($report['suggested_links']['verification_link'])): ?>
                    <a class="btn" href="<?php echo h($report['suggested_links']['verification_link']); ?>" target="_blank">Open Verification Link</a>
                <?php endif; ?>
                <?php if (!empty($report['suggested_links']['resend_link'])): ?>
                    <a class="btn" href="<?php echo h($report['suggested_links']['resend_link']); ?>">Resend Verification</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p>No specific target. Append <code>?email=your@email</code> to inspect a user.</p>
        <?php endif; ?>
        <div style="margin-top:8px;">
            <small>Note: <?php echo h($report['notes']['verify_email_requires_pending']); ?>.</small>
        </div>
    </div>

    <?php if (!empty($report['db_error'])): ?>
        <div class="card">
            <h2>Database Error</h2>
            <p class="err"><?php echo h($report['db_error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Quick Tips</h2>
        <ul>
            <li>Verification links expire after <?php echo (int)$report['notes']['token_validity_hours']; ?> hours.</li>
            <li>Ensure user status is <code>pending</code> before clicking the link.</li>
            <li>If <code>verified_at</code> is missing, the flow now falls back gracefully.</li>
            <li>Use JSON: <code>debug_verification_flow.php?format=json</code>.</li>
        </ul>
    </div>
</body>

</html>