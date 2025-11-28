<?php
require_once __DIR__ . '/admin_helpers.php';

$admin = require_admin_session();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

$errors = [];
if (!$current || !$new || !$confirm) {
    $errors[] = 'All password fields are required.';
}
if ($new !== $confirm) {
    $errors[] = 'New password and confirmation do not match.';
}

// Strength
$errors = array_merge($errors, validate_password_strength($new));

// Verify current
if (!password_verify($current, $admin['password_hash'])) {
    $errors[] = 'Current password is incorrect.';
}

// Prevent reuse of last 3
if (is_password_reused((int)$admin['id'], $new, 3)) {
    $errors[] = 'You cannot reuse any of your last 3 passwords.';
}

if ($errors) {
    $_SESSION['flash_error'] = implode('\n', $errors);
    header('Location: /admin_profile.php');
    exit;
}

// Update password and store old in history
global $pdo;
$pdo->beginTransaction();
try {
    store_password_history((int)$admin['id'], $admin['password_hash']);

    $newHash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE admins SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$newHash, (int)$admin['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = 'Failed to change password. Please try again.';
    header('Location: /admin_profile.php');
    exit;
}

log_admin_action((int)$admin['id'], 'Change Password');

$_SESSION['flash_success'] = 'Password updated successfully.';
header('Location: /admin_profile.php');
exit;
?>