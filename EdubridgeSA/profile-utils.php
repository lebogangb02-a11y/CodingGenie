<?php
require_once __DIR__ . '/config.php';

function getProfilePicture($user_data) {
    // Prefer DB path if valid
    $path = trim($user_data['profile_picture'] ?? '');
    if ($path) {
        $abs = __DIR__ . '/' . ltrim($path, '/');
        if (file_exists($abs)) {
            return $path;
        }
    }

    // Fallback: derive path from session student_id
    $student_id = $_SESSION['student_id'] ?? null;
    if ($student_id) {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id);
        $derived = 'uploads/profile_pictures/' . $safeId . '.jpg';
        if (file_exists(__DIR__ . '/' . $derived)) {
            return $derived;
        }
    }

    return 'images/default-avatar.png';
}

function handleProfilePictureUpload($file, $student_id) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error.'];
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Image too large. Max 5MB.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        return ['success' => false, 'error' => 'Unsupported image type. Use JPG/PNG/WEBP.'];
    }

    $info = getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['success' => false, 'error' => 'Invalid image file.'];
    }
    [$width, $height] = $info;
    if ($width < 150 || $height < 150) {
        return ['success' => false, 'error' => 'Image too small. Min 150x150.'];
    }

    // Create image resource
    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png': $src = imagecreatefrompng($file['tmp_name']); break;
        case 'image/webp': $src = imagecreatefromwebp($file['tmp_name']); break;
        default: return ['success' => false, 'error' => 'Unsupported image type.'];
    }

    if (!$src) {
        return ['success' => false, 'error' => 'Failed to process image.'];
    }

    // Square center-crop
    $size = min($width, $height);
    $x = (int)(($width - $size) / 2);
    $y = (int)(($height - $size) / 2);
    $crop = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $size, 'height' => $size]);
    if ($crop === false) { $crop = $src; }

    // Resize to 400x400
    $destSize = 400;
    $dest = imagecreatetruecolor($destSize, $destSize);
    imagecopyresampled($dest, $crop, 0, 0, 0, 0, $destSize, $destSize, imagesx($crop), imagesy($crop));

    // Ensure destination directory
    $dir = __DIR__ . '/uploads/profile_pictures';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $filename = $dir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id) . '.jpg';
    $webPath = 'uploads/profile_pictures/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id) . '.jpg';

    // Save as JPEG
    if (!imagejpeg($dest, $filename, 85)) {
        return ['success' => false, 'error' => 'Failed to save image.'];
    }
    @chmod($filename, 0644);

    imagedestroy($src);
    if ($crop && $crop !== $src) imagedestroy($crop);
    imagedestroy($dest);

    // Update DB profile_picture path
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $stmt = $pdo->prepare('UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE student_id = ?');
        $stmt->execute([$webPath, $student_id]);
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'DB update failed.'];
    }

    return ['success' => true, 'path' => $webPath];
}

?>