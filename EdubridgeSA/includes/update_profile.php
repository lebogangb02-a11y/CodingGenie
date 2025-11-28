<?php
require_once __DIR__ . '/admin_helpers.php';

$admin = require_admin_session();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$name = sanitize_string($_POST['name'] ?? '');
$username = sanitize_string($_POST['username'] ?? '');
$email = sanitize_email($_POST['email'] ?? '');
$phone = sanitize_string($_POST['phone'] ?? '');
$language = sanitize_string($_POST['language'] ?? 'en');
$dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
$email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
$login_alerts = isset($_POST['login_alerts']) ? 1 : 0;
$two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;

$errors = [];
if (!$name) $errors[] = 'Name is required.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
if ($phone && !preg_match('/^[0-9\-\+\s]{7,20}$/', $phone)) $errors[] = 'Phone number is invalid.';
if ($language && !preg_match('/^[a-z]{2}$/i', $language)) $errors[] = 'Language must be a 2-letter code.';

// Username changes only permitted for super admin
if ($username && $username !== $admin['username'] && $admin['role'] !== 'super') {
    $username = $admin['username'];
}

// Ensure email/username uniqueness when changed
global $pdo;
if ($email !== $admin['email']) {
    $q = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id <> ?');
    $q->execute([$email, (int)$admin['id']]);
    if ($q->fetch()) $errors[] = 'Email is already in use.';
}
if ($username !== $admin['username']) {
    $q = $pdo->prepare('SELECT id FROM admins WHERE username = ? AND id <> ?');
    $q->execute([$username, (int)$admin['id']]);
    if ($q->fetch()) $errors[] = 'Username is already in use.';
}

if ($errors) {
    $_SESSION['flash_error'] = implode('\n', $errors);
    header('Location: /admin_profile.php');
    exit;
}

// Update profile
$fields = [
    'name' => $name,
    'email' => $email,
    'phone' => $phone,
    'language' => strtolower($language),
    'dark_mode' => $dark_mode,
    'email_notifications' => $email_notifications,
    'login_alerts' => $login_alerts,
    'two_factor_enabled' => $two_factor_enabled,
];
if ($username) $fields['username'] = $username; // Only if super admin or unchanged

update_admin_profile($fields, (int)$admin['id']);

// Refresh session values for convenience
$_SESSION['admin_username'] = $username ?: $_SESSION['admin_username'];
$_SESSION['admin_name'] = $name;
$_SESSION['dark_mode'] = $dark_mode;

log_admin_action((int)$admin['id'], 'Edit Profile', json_encode(['changed' => array_keys($fields)]));

$_SESSION['flash_success'] = 'Profile updated successfully.';
header('Location: /admin_profile.php');
exit;
?>