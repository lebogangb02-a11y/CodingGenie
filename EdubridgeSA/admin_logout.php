<?php
session_start();
require_once __DIR__ . '/admin_helpers.php';

// Log the action if an admin session exists
$adminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : 0;
if ($adminId > 0) {
    log_admin_action($adminId, 'Logout');
}

// Clear all session data and cookie
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect to admin login
header('Location: /admin_login.php');
exit;