<?php
// Account Debug — inspect user/account state for a given email
// Usage: /account_debug.php?email=someone@example.com
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/config.php';

// Helper: check if a column exists in a table (works across environments)
function columnExists(PDO $pdo, $table, $column) {
  try {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($row['cnt']) ? ((int)$row['cnt'] > 0) : false;
  } catch (Throwable $e) {
    // If information_schema fails (permissions or engine), be conservative
    return false;
  }
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function getParam($key){ return trim($_GET[$key] ?? $_POST[$key] ?? ''); }

$email = getParam('email');
$user = null; $apps = []; $error = null;
if ($email !== '') {
  try {
    // Build SELECT dynamically to avoid errors on environments missing new columns
    $baseCols = [
      'id','email','first_name','last_name','student_id','status','email_verified','verification_token','created_at','verified_at'
    ];
    $optionalCols = [];
    if (columnExists($pdo, 'users', 'verification_sent_at')) { $optionalCols[] = 'verification_sent_at'; }
    if (columnExists($pdo, 'users', 'verification_attempts')) { $optionalCols[] = 'verification_attempts'; }
    $cols = implode(', ', array_merge($baseCols, $optionalCols));
    $stmt = $pdo->prepare("SELECT $cols FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmtA = $pdo->prepare("SELECT id, reference_number, status, created_at FROM applications WHERE email_address = ? ORDER BY created_at DESC LIMIT 10");
    $stmtA->execute([$email]);
    $apps = $stmtA->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $error = $e->getMessage();
  }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account Debug</title>
  <style>
    body { font-family: system-ui, Arial, sans-serif; margin: 24px; }
    input, button { padding: 8px 10px; font-size: 14px; }
    .panel { border: 1px solid #ddd; border-radius: 8px; padding: 12px; margin-top: 12px; }
    .ok { color: #2e7d32; }
    .warn { color: #f57c00; }
    .err { color: #c62828; }
    code { background: #f5f5f5; padding: 2px 4px; border-radius: 3px; }
  </style>
</head>
<body>
  <h1>Account Debug</h1>
  <form method="get" action="account_debug.php">
    <label>Email:&nbsp;<input type="email" name="email" value="<?php echo h($email); ?>" required /></label>
    <button type="submit">Inspect</button>
    <?php if ($email): ?>
      <a href="resend-verification.php?email=<?php echo urlencode($email); ?>">Resend verification</a>
      <a href="student-login.php">Login</a>
      <a href="forgot-password.php">Forgot password</a>
    <?php endif; ?>
  </form>

  <?php if ($error): ?>
    <div class="panel err">DB Error: <?php echo h($error); ?></div>
  <?php endif; ?>

  <?php if ($email && !$user && !$error): ?>
    <div class="panel ok">No existing user for <strong><?php echo h($email); ?></strong>. Profile creation should succeed.</div>
  <?php endif; ?>

  <?php if ($user): ?>
    <div class="panel">
      <h2>User Record</h2>
      <div>Email: <strong><?php echo h($user['email']); ?></strong></div>
      <div>Name: <?php echo h(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></div>
      <div>Student ID: <?php echo h($user['student_id'] ?? ''); ?></div>
      <div>Status: <strong><?php echo h($user['status'] ?? ''); ?></strong></div>
      <div>Verified: <strong><?php echo (int)($user['email_verified'] ?? 0) ? 'yes' : 'no'; ?></strong></div>
      <div>Verification token present: <strong><?php echo !empty($user['verification_token']) ? 'yes' : 'no'; ?></strong></div>
      <?php 
        // Compute token age and resend eligibility using new metadata
        $sentAtStr = $user['verification_sent_at'] ?? null;
        $attempts = (int)($user['verification_attempts'] ?? 0);
        $createdAtStr = $user['created_at'] ?? null;
        $tokenRefTime = !empty($sentAtStr) ? $sentAtStr : $createdAtStr;
        $tokenAgeHours = null;
        $resendEligible = null;
        $waitMinutes = null;
        try {
          if (!empty($tokenRefTime)) {
            $ref = new DateTime($tokenRefTime);
            $now = new DateTime();
            $diffSeconds = max(0, $now->getTimestamp() - $ref->getTimestamp());
            $tokenAgeHours = floor($diffSeconds / 3600);
            // Rate limit: max 5 attempts within 1 hour window
            $withinHour = ($diffSeconds < 3600);
            if ($attempts >= 5 && $withinHour) {
              $resendEligible = false;
              $waitMinutes = ceil((3600 - $diffSeconds) / 60);
            } else {
              $resendEligible = true;
            }
          }
        } catch (Throwable $e) { /* ignore */ }
      ?>
      <div>Verification sent at: <?php echo h($user['verification_sent_at'] ?? '—'); ?></div>
      <div>Verification attempts (last hour limit 5): <?php echo h((string)$attempts); ?></div>
      <div>Token age: <?php echo $tokenAgeHours !== null ? h($tokenAgeHours . 'h') : '—'; ?></div>
      <div>Token expiry (24h): <strong><?php echo ($tokenAgeHours !== null && $tokenAgeHours > 24) ? 'expired' : 'valid'; ?></strong></div>
      <div>Resend eligible: <strong><?php echo ($resendEligible === null) ? 'unknown' : ($resendEligible ? 'yes' : 'no'); ?></strong>
        <?php if ($resendEligible === false && $waitMinutes !== null): ?>
          <span class="warn">(wait ~<?php echo h($waitMinutes); ?> min)</span>
        <?php endif; ?>
      </div>
      <div>Created at: <?php echo h($user['created_at'] ?? ''); ?></div>
      <div>Verified at: <?php echo h($user['verified_at'] ?? ''); ?></div>
      <?php if (!empty($user['verification_token'])): ?>
        <div>
          Verify link: <code><?php echo h((isset($GLOBALS['BASE_URL']) ? $GLOBALS['BASE_URL'] : '') . '/verify-email.php?token=' . $user['verification_token']); ?></code>
        </div>
      <?php endif; ?>
      <hr />
      <?php if ((int)($user['email_verified'] ?? 0) === 0): ?>
        <div class="warn">
          Account exists but email is not verified. Use <a href="resend-verification.php?email=<?php echo urlencode($user['email']); ?>">Resend Verification</a>.
          <?php if ($tokenAgeHours !== null): ?>
            <?php if ($tokenAgeHours > 24): ?>
              Token is older than 24 hours — verification will show as expired.
            <?php else: ?>
              Token is <?php echo h($tokenAgeHours); ?>h old and still valid.
            <?php endif; ?>
          <?php else: ?>
            If token is older than 24 hours, verification will show as expired.
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="ok">Email is verified. Use <a href="student-login.php">Login</a> or <a href="forgot-password.php">Reset Password</a> if needed.</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($email): ?>
    <div class="panel">
      <h2>Related Applications</h2>
      <?php if (empty($apps)): ?>
        <div>No applications linked to this email.</div>
      <?php else: ?>
        <ul>
          <?php foreach ($apps as $a): ?>
            <li>#<?php echo h($a['id']); ?> — Ref: <code><?php echo h($a['reference_number']); ?></code> — Status: <strong><?php echo h($a['status']); ?></strong> — <?php echo h($a['created_at']); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p><a href="create-profile.php">Back to Create Profile</a></p>
</body>
</html>