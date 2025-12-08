<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_auth.php';
// Security helpers provide CSRF and escaping helpers
require_once __DIR__ . '/includes/security_helpers.php';

admin_require_login();

$admin_id = $_SESSION['admin_id'];
$msg = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    // Enforce CSRF validation
    require_csrf();

    $upload_dir = __DIR__ . '/uploads/admins/';

    // Create uploads directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file = $_FILES['profile_image'];
    $remove_image = isset($_POST['remove_image']);

    try {
        if ($remove_image) {
            // Remove current image
            $stmt = $pdo->prepare('SELECT profile_image FROM admins WHERE id = ?');
            $stmt->execute([$admin_id]);
            $current_image = $stmt->fetchColumn();

            if ($current_image && file_exists($upload_dir . $current_image)) {
                unlink($upload_dir . $current_image);
            }

            $stmt = $pdo->prepare('UPDATE admins SET profile_image = NULL WHERE id = ?');
            $stmt->execute([$admin_id]);

            $msg = 'Profile picture removed successfully.';
            $msgType = 'success';
        } else {
            // Validate file
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $max_size = 2 * 1024 * 1024; // 2MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload error: ' . $file['error']);
            }

            if ($file['size'] > $max_size) {
                throw new Exception('File size must be less than 2MB');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception('Only JPG, JPEG, and PNG files are allowed');
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'admin_' . $admin_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;

            // Resize and compress image
            if (!resizeImage($file['tmp_name'], $filepath, 500, 500)) {
                throw new Exception('Failed to process image');
            }

            // Remove old image
            $stmt = $pdo->prepare('SELECT profile_image FROM admins WHERE id = ?');
            $stmt->execute([$admin_id]);
            $old_image = $stmt->fetchColumn();

            if ($old_image && file_exists($upload_dir . $old_image)) {
                unlink($upload_dir . $old_image);
            }

            // Update database
            $stmt = $pdo->prepare('UPDATE admins SET profile_image = ? WHERE id = ?');
            $stmt->execute([$filename, $admin_id]);

            // Log activity
            logAdminActivity($pdo, $admin_id, 'Update Profile Picture', 'Uploaded new profile image', $_SERVER['REMOTE_ADDR']);

            $msg = 'Profile picture updated successfully.';
            $msgType = 'success';
        }
    } catch (Exception $e) {
        $msg = 'Error: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

header('Location: admin_profile.php?msg=' . urlencode($msg) . '&msgType=' . $msgType);
exit;

function resizeImage($source_path, $dest_path, $max_width, $max_height)
{
    list($src_width, $src_height, $src_type) = getimagesize($source_path);

    switch ($src_type) {
        case IMAGETYPE_JPEG:
            $src_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $src_image = imagecreatefrompng($source_path);
            break;
        default:
            return false;
    }

    // Calculate new dimensions
    $ratio = min($max_width / $src_width, $max_height / $src_height);
    $new_width = round($src_width * $ratio);
    $new_height = round($src_height * $ratio);

    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);

    // Preserve transparency for PNG
    if ($src_type === IMAGETYPE_PNG) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }

    // Resize image
    imagecopyresampled($new_image, $src_image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);

    // Save image with compression
    $quality = $src_type === IMAGETYPE_JPEG ? 85 : 9;

    switch ($src_type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $dest_path, $quality);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $dest_path, $quality);
            break;
    }

    imagedestroy($src_image);
    imagedestroy($new_image);

    return true;
}

function logAdminActivity($pdo, $admin_id, $action, $details, $ip_address)
{
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$admin_id, $action, $details, $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
}
