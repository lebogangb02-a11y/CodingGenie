<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// Simple IP helper
$client_ip = SecurityUtils::getClientIP();

// Read inputs (GET or POST)
$email = trim($_REQUEST['email'] ?? '');
$email = $email ? strtolower($email) : '';
$raw_login_method = $_REQUEST['login_method'] ?? '';
$reference_number = trim($_REQUEST['reference_number'] ?? '');
$password = $_REQUEST['password'] ?? '';

// Infer method similar to student-login.php
if ($raw_login_method === 'reference' || $raw_login_method === 'password') {
    $login_method = $raw_login_method;
} elseif (!empty($reference_number) && empty($password)) {
    $login_method = 'reference';
} elseif (!empty($password) && empty($reference_number)) {
    $login_method = 'password';
} else {
    $login_method = 'reference';
}

// Prepare outputs
$steps = [];
$likely_blocker = null;
$pdo_ok = false;

// 0) DB connectivity
try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $pdo_ok = true;
    $steps[] = [
        'title' => 'Database Connection',
        'status' => 'ok',
        'detail' => 'Connected via PDO driver: ' . htmlspecialchars($driver),
    ];
} catch (Exception $e) {
    $steps[] = [
        'title' => 'Database Connection',
        'status' => 'fail',
        'detail' => 'PDO not available: ' . htmlspecialchars($e->getMessage()),
    ];
    $likely_blocker = 'Database connectivity issue';
}

// 1) Rate limit check
try {
    if ($pdo_ok) {
        SecurityUtils::checkRateLimit($pdo, $client_ip, 'login_attempt', 5, 900);
        $steps[] = [
            'title' => 'Rate Limiting',
            'status' => 'ok',
            'detail' => 'Not rate-limited for IP ' . htmlspecialchars($client_ip),
        ];
    }
} catch (Exception $e) {
    $steps[] = [
        'title' => 'Rate Limiting',
        'status' => 'fail',
        'detail' => 'Blocked by rate limit: ' . htmlspecialchars($e->getMessage()),
    ];
    $likely_blocker = $likely_blocker ?? 'Too many attempts (rate limited)';
}

// 2) Email validation
try {
    if ($email === '1' || $email === '0' || is_numeric($email)) {
        $email = '';
    }
    $validated_email = $email ? SecurityUtils::validateEmail($email) : '';
    if ($validated_email) {
        $steps[] = [
            'title' => 'Email Validation',
            'status' => 'ok',
            'detail' => 'Email is valid: ' . htmlspecialchars($validated_email),
        ];
        $email = $validated_email;
    } else {
        $steps[] = [
            'title' => 'Email Validation',
            'status' => 'fail',
            'detail' => 'Invalid or empty email',
        ];
        $likely_blocker = $likely_blocker ?? 'Invalid email address';
    }
} catch (Exception $e) {
    $steps[] = [
        'title' => 'Email Validation',
        'status' => 'fail',
        'detail' => 'Validation error: ' . htmlspecialchars($e->getMessage()),
    ];
    $likely_blocker = $likely_blocker ?? 'Email validation error';
}

// 3) Branch: Reference vs Password
$steps[] = [
    'title' => 'Method Selection',
    'status' => 'info',
    'detail' => 'Inferred method: ' . htmlspecialchars($login_method),
];

if ($pdo_ok && $email) {
    if ($login_method === 'reference') {
        // Reference sanitization
        try {
            $san_ref = SecurityUtils::sanitizeInput(['ref' => $reference_number])['ref'];
            // Normalize common formatting issues: remove spaces/dashes/underscores and force uppercase
            $san_ref = $san_ref ? strtoupper(preg_replace('/[\s\-_]+/', '', $san_ref)) : '';
            $steps[] = [
                'title' => 'Reference Sanitization',
                'status' => $san_ref ? 'ok' : 'fail',
                'detail' => $san_ref ? ('Sanitized reference: ' . htmlspecialchars($san_ref)) : 'Empty reference number',
            ];
            if (!$san_ref) {
                $likely_blocker = $likely_blocker ?? 'Missing application reference number';
            }

            // Exact match query
            $stmt = $pdo->prepare('SELECT id, reference_number, status, full_name FROM applications WHERE LOWER(email_address) = LOWER(?) AND reference_number = ?');
            $stmt->execute([$email, $san_ref]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($app) {
                $steps[] = [
                    'title' => 'Exact Match (applications)',
                    'status' => 'ok',
                    'detail' => 'Found application ID ' . htmlspecialchars($app['id']) . ' (status: ' . htmlspecialchars($app['status'] ?? 'N/A') . ')',
                ];
            } else {
                $steps[] = [
                    'title' => 'Exact Match (applications)',
                    'status' => 'fail',
                    'detail' => 'No application matches email + reference',
                ];
                // Guidance queries
                $stmtE = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE LOWER(email_address) = LOWER(?)');
                $stmtE->execute([$email]);
                $countEmail = (int)$stmtE->fetchColumn();

                $stmtR = $pdo->prepare('SELECT email_address, reference_number FROM applications WHERE reference_number = ? ORDER BY id DESC LIMIT 3');
                $stmtR->execute([$san_ref]);
                $rowsRef = $stmtR->fetchAll(PDO::FETCH_ASSOC);

                $detail = 'Applications for email: ' . $countEmail;
                if ($rowsRef) {
                    $items = [];
                    foreach ($rowsRef as $r) { $items[] = $r['email_address'] . ' / ' . $r['reference_number']; }
                    $detail .= '; Reference belongs to: ' . htmlspecialchars(implode(', ', $items));
                } else {
                    $detail .= '; Reference not found in DB';
                }
                $steps[] = [
                    'title' => 'Guidance',
                    'status' => 'info',
                    'detail' => $detail,
                ];
                $likely_blocker = $likely_blocker ?? 'Invalid email + reference combination';
            }
        } catch (Exception $e) {
            $steps[] = [
                'title' => 'Reference Lookup',
                'status' => 'fail',
                'detail' => 'Error looking up reference: ' . htmlspecialchars($e->getMessage()),
            ];
            $likely_blocker = $likely_blocker ?? 'Reference lookup error';
        }
    } else {
        // Password branch
        try {
            $stmt = $pdo->prepare('SELECT id, student_id, email, password_hash, status, email_verified FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $steps[] = [
                    'title' => 'User Lookup (users)',
                    'status' => 'ok',
                    'detail' => 'Found users.id ' . htmlspecialchars($user['id']) . ' (status: ' . htmlspecialchars($user['status']) . ', verified: ' . htmlspecialchars((string)$user['email_verified']) . ')',
                ];

                // Status checks
                if ($user['status'] !== 'active') {
                    $steps[] = [
                        'title' => 'Account Status',
                        'status' => 'fail',
                        'detail' => 'Account is not active',
                    ];
                    $likely_blocker = $likely_blocker ?? 'Account inactive';
                }
                if ((int)$user['email_verified'] !== 1) {
                    $steps[] = [
                        'title' => 'Email Verification',
                        'status' => 'fail',
                        'detail' => 'Email not verified',
                    ];
                    $likely_blocker = $likely_blocker ?? 'Email not verified';
                }

                // Password check
                if (!empty($password)) {
                    $ok = password_verify($password, $user['password_hash'] ?? '');
                    $steps[] = [
                        'title' => 'Password Verify',
                        'status' => $ok ? 'ok' : 'fail',
                        'detail' => $ok ? 'Password matches hash' : 'Password does not match',
                    ];
                    if (!$ok) { $likely_blocker = $likely_blocker ?? 'Invalid password'; }
                } else {
                    $steps[] = [
                        'title' => 'Password Provided',
                        'status' => 'fail',
                        'detail' => 'No password provided',
                    ];
                    $likely_blocker = $likely_blocker ?? 'Missing password';
                }

                // Application association
                $stmtA = $pdo->prepare('SELECT id, reference_number, status FROM applications WHERE LOWER(email_address) = LOWER(?) ORDER BY id DESC LIMIT 3');
                $stmtA->execute([$email]);
                $apps = $stmtA->fetchAll(PDO::FETCH_ASSOC);
                if ($apps) {
                    $items = [];
                    foreach ($apps as $a) { $items[] = '#' . $a['id'] . ' (' . ($a['reference_number'] ?? 'N/A') . ', ' . ($a['status'] ?? 'N/A') . ')'; }
                    $steps[] = [
                        'title' => 'Application Link',
                        'status' => 'ok',
                        'detail' => 'Applications linked to email: ' . htmlspecialchars(implode(', ', $items)),
                    ];
                } else {
                    $steps[] = [
                        'title' => 'Application Link',
                        'status' => 'info',
                        'detail' => 'No application record linked to this email',
                    ];
                }
            } else {
                $steps[] = [
                    'title' => 'User Lookup (users)',
                    'status' => 'fail',
                    'detail' => 'No user account for this email',
                ];
                // Provide hint if reference exists
                $stmtRef = $pdo->prepare('SELECT reference_number FROM applications WHERE LOWER(email_address) = LOWER(?) ORDER BY id DESC LIMIT 1');
                $stmtRef->execute([$email]);
                $rowRef = $stmtRef->fetch(PDO::FETCH_ASSOC);
                if ($rowRef && !empty($rowRef['reference_number'])) {
                    $steps[] = [
                        'title' => 'Hint',
                        'status' => 'info',
                        'detail' => 'Try Application Reference login using ref ' . htmlspecialchars($rowRef['reference_number']),
                    ];
                }
                $likely_blocker = $likely_blocker ?? 'No user account found';
            }
        } catch (Exception $e) {
            $steps[] = [
                'title' => 'User Lookup/Error',
                'status' => 'fail',
                'detail' => 'Error in user lookup: ' . htmlspecialchars($e->getMessage()),
            ];
            $likely_blocker = $likely_blocker ?? 'User lookup error';
        }
    }
}

// Render simple HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Flow Debug</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { margin-bottom: 10px; }
    form { margin-bottom: 20px; }
    .ok { background: #e7f6e7; border: 1px solid #c2e3c2; padding: 8px; }
    .fail { background: #fde2e2; border: 1px solid #f5bcbc; padding: 8px; }
    .info { background: #eef2ff; border: 1px solid #c7d2fe; padding: 8px; }
    .panel { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 6px; }
    label { display: block; margin: 6px 0 2px; }
    input[type=text], input[type=email], input[type=password] { width: 320px; padding: 6px; }
  </style>
</head>
<body>
  <h1>Login Flow Debug</h1>
  <p>This tool mirrors the student login checks and reports the exact step that prevents login.</p>

  <form method="get" action="login_flow_debug.php" class="panel">
    <label>Email</label>
    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="you@example.com" required>
    <label>Login Method</label>
    <label><input type="radio" name="login_method" value="reference" <?php echo ($login_method==='reference'?'checked':''); ?>> Application Reference</label>
    <label><input type="radio" name="login_method" value="password" <?php echo ($login_method==='password'?'checked':''); ?>> Password</label>
    <label>Reference Number (APP##########)</label>
    <input type="text" name="reference_number" value="<?php echo htmlspecialchars($reference_number); ?>" placeholder="APP2025097459">
    <label>Password</label>
    <input type="password" name="password" value="<?php echo htmlspecialchars($password); ?>" placeholder="••••••••">
    <div style="margin-top:10px;"><button type="submit">Run Debug</button></div>
  </form>

  <?php if (!empty($steps)): ?>
    <div class="panel">
      <h2>Results</h2>
      <?php foreach ($steps as $s): ?>
        <div class="<?php echo htmlspecialchars($s['status']); ?>" style="margin-bottom:6px;">
          <strong><?php echo htmlspecialchars($s['title']); ?>:</strong>
          <div><?php echo htmlspecialchars($s['detail']); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="panel">
    <h2>Summary</h2>
    <div><strong>Selected/Infered Method:</strong> <?php echo htmlspecialchars($login_method); ?></div>
    <div><strong>IP:</strong> <?php echo htmlspecialchars($client_ip); ?></div>
    <div><strong>Likely Blocker:</strong> <?php echo htmlspecialchars($likely_blocker ?? 'None — credentials appear valid.'); ?></div>
    <div style="margin-top:6px;">If credentials appear valid here but login fails, the issue may be session or redirect handling. Try a hard refresh and re-test.</div>
  </div>

  <p><a href="student-login.php">Go to Student Login</a></p>
</body>
</html>