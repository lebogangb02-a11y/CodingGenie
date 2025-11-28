<?php
require_once __DIR__ . '/admin_helpers.php';

$admin = require_admin_session();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (empty($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['flash_error'] = 'Please select an image to upload.';
    header('Location: /admin_profile.php');
    exit;
}

$file = $_FILES['profile_image'];
if ($file['size'] > 2 * 1024 * 1024) { // 2MB
    $_SESSION['flash_error'] = 'Image exceeds 2MB limit.';
    header('Location: /admin_profile.php');
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg','image/jpg','image/png'];
if (!in_array($mime, $allowed, true)) {
    $_SESSION['flash_error'] = 'Only JPG and PNG images are allowed.';
    header('Location: /admin_profile.php');
    exit;
}

ensure_upload_dirs();

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$basename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $admin['username']) . '_' . date('YmdHis');
$destRel = '/uploads/admins/' . $basename . '.' . $ext;
$destAbs = __DIR__ . $destRel;

if (!resize_and_save_image($file['tmp_name'], $mime, $destAbs, 512, 512, 85)) {
    $_SESSION['flash_error'] = 'Failed to process image.';
    header('Location: /admin_profile.php');
    exit;
}

// Delete previous image if exists and is within uploads/admins
global $pdo;
try {
    $pdo->beginTransaction();
    $prev = $admin['profile_image'] ?? null;
    if ($prev && str_starts_with($prev, '/uploads/admins/')) {
        $prevAbs = __DIR__ . $prev;
        if (is_file($prevAbs)) @unlink($prevAbs);
    }

    $stmt = $pdo->prepare('UPDATE admins SET profile_image = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$destRel, (int)$admin['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($destAbs);
    $_SESSION['flash_error'] = 'Failed to save image. Please try again.';
    header('Location: /admin_profile.php');
    exit;
}

log_admin_action((int)$admin['id'], 'Upload Profile Image');

$_SESSION['flash_success'] = 'Profile image updated.';
header('Location: /admin_profile.php');
exit;
?>