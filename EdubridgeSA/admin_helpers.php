<?php
// Admin helper functions for CSRF, validation, activity logging, and utilities
// Ready for Hostinger (no Composer). Uses PDO via config.php

require_once __DIR__ . '/config.php';

// Ensure session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(400);
            exit('Invalid CSRF token');
        }
    }
}

function require_admin_session(array $roles = ['super', 'admin', 'staff']): array
{
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_username'])) {
        header('Location: /login.php');
        exit;
    }
    $admin = get_admin_by_id((int)$_SESSION['admin_id']);
    if (!$admin) {
        header('Location: /login.php');
        exit;
    }
    if (!in_array($admin['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $admin;
}

function sanitize_string(string $v): string
{
    return trim(filter_var($v, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
}

function sanitize_email(string $v): string
{
    return trim(filter_var($v, FILTER_SANITIZE_EMAIL));
}

function validate_password_strength(string $pwd): array
{
    $errors = [];
    if (strlen($pwd) < 10) $errors[] = 'Password must be at least 10 characters.';
    if (!preg_match('/[A-Z]/', $pwd)) $errors[] = 'Include at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $pwd)) $errors[] = 'Include at least one lowercase letter.';
    if (!preg_match('/\d/', $pwd)) $errors[] = 'Include at least one number.';
    return $errors;
}

function get_admin_by_id(int $id): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, created_at FROM admins WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function get_admin_by_username(string $username): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, role, created_at FROM admins WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function log_admin_action(int $admin_id, string $action, ?string $details = null): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $admin_id,
        $action,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}

function is_password_reused(int $admin_id, string $new_password, int $check_last = 3): bool
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT old_password_hash FROM password_history WHERE admin_id = ? ORDER BY created_at DESC LIMIT ?');
    $stmt->execute([$admin_id, $check_last]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        if (password_verify($new_password, $row['old_password_hash'])) {
            return true;
        }
    }
    return false;
}

function store_password_history(int $admin_id, string $old_hash): void
{
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO password_history (admin_id, old_password_hash) VALUES (?, ?)');
    $stmt->execute([$admin_id, $old_hash]);
}

function update_admin_profile(array $fields, int $admin_id): void
{
    global $pdo;
    $allowed = ['name', 'username', 'email', 'phone', 'language', 'dark_mode', 'email_notifications', 'login_alerts', 'two_factor_enabled'];
    $set = [];
    $vals = [];
    foreach ($fields as $k => $v) {
        if (in_array($k, $allowed, true)) {
            $set[] = "$k = ?";
            $vals[] = $v;
        }
    }
    if (!$set) return;
    $vals[] = $admin_id;
    $sql = 'UPDATE admins SET ' . implode(', ', $set) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($vals);
}

function ensure_upload_dirs(): void
{
    $dir = __DIR__ . '/uploads/admins';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

function resize_and_save_image(string $srcPath, string $mime, string $destPath, int $maxW = 512, int $maxH = 512, int $quality = 85): bool
{
    // Using GD
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $img = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $img = imagecreatefrompng($srcPath);
            break;
        default:
            return false;
    }
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min($maxW / $w, $maxH / $h, 1.0);
    $nw = (int)floor($w * $scale);
    $nh = (int)floor($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);

    // Preserve transparency for PNG
    if ($mime === 'image/png') {
        imagealphablending($out, false);
        imagesavealpha($out, true);
    }

    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = false;
    if ($mime === 'image/png') {
        // Convert to PNG with compression level 6
        $ok = imagepng($out, $destPath, 6);
    } else {
        $ok = imagejpeg($out, $destPath, $quality);
    }
    imagedestroy($img);
    imagedestroy($out);
    return $ok;
}
