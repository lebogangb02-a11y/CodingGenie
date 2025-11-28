<?php
session_start();
require_once 'config.php';
require_once 'security-utils.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$user_id = $_SESSION['user_id'];
$upload_dir = 'uploads/profile_pictures/';

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit();
    }
}

// Check if file was uploaded
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'No file uploaded';
    if (isset($_FILES['profile_picture']['error'])) {
        switch ($_FILES['profile_picture']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File size exceeds maximum allowed (2MB)';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File upload was interrupted';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Temporary directory missing';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'File upload stopped by extension';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

$file = $_FILES['profile_picture'];
$original_name = $file['name'];
$tmp_name = $file['tmp_name'];
$file_size = $file['size'];

// Validate file size (2MB max)
$max_size = 2 * 1024 * 1024; // 2MB in bytes
if ($file_size > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 2MB limit']);
    exit();
}

// Validate file type
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $tmp_name);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG and PNG files are allowed']);
    exit();
}

// Validate file extension
$file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png'];
if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
    exit();
}

// Generate unique filename
$new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . $new_filename;

// Additional security: Check if file is actually an image
$image_info = getimagesize($tmp_name);
if ($image_info === false) {
    echo json_encode(['success' => false, 'message' => 'File is not a valid image']);
    exit();
}

// Move uploaded file
if (!move_uploaded_file($tmp_name, $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit();
}

try {
    // Start database transaction
    $pdo->beginTransaction();
    
    // Get current profile picture to delete old one
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_picture = $stmt->fetchColumn();
    
    // Update user's profile picture in database
    $stmt = $pdo->prepare("UPDATE users SET profile_picture = ?, profile_updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_filename, $user_id]);
    
    // Mark previous profile pictures as not current
    $stmt = $pdo->prepare("UPDATE profile_picture_uploads SET is_current = FALSE WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Insert new profile picture record
    $stmt = $pdo->prepare("
        INSERT INTO profile_picture_uploads 
        (user_id, original_filename, stored_filename, file_path, file_size, mime_type, is_current) 
        VALUES (?, ?, ?, ?, ?, ?, TRUE)
    ");
    $stmt->execute([
        $user_id,
        $original_name,
        $new_filename,
        $file_path,
        $file_size,
        $mime_type
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Delete old profile picture file (but keep the first one as backup)
    if ($current_picture && $current_picture !== $new_filename && file_exists($upload_dir . $current_picture)) {
        // Check if this is not the user's first profile picture
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profile_picture_uploads WHERE user_id = ? AND is_current = FALSE");
        $stmt->execute([$user_id]);
        $old_pictures_count = $stmt->fetchColumn();
        
        if ($old_pictures_count > 0) {
            unlink($upload_dir . $current_picture);
        }
    }
    
    // Update session if needed
    $_SESSION['profile_picture'] = $new_filename;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Profile picture updated successfully',
        'filename' => $new_filename,
        'file_path' => $file_path
    ]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Delete uploaded file if database update failed
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    error_log("Profile picture upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>