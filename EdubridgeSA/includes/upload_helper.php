<?php

/**
 * Centralized upload helper for EduBridgeSA
 * - validates file errors and size
 * - checks MIME using finfo
 * - enforces allowed MIME types
 * - ensures target directory is inside UPLOAD_DIR using realpath
 * - generates randomized filenames
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

require_once __DIR__ . '/../config.php';

function store_uploaded_file(array $file, string $subdir = '', array $allowed_mimes = null, int $maxSize = 0): array
{
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error code: ' . ($file['error'] ?? 'unknown')];
    }

    $maxSize = $maxSize ?: (defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : (5 * 1024 * 1024));
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File too large'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime === false) {
        return ['success' => false, 'error' => 'Unable to determine file MIME type'];
    }

    $default_allowed = ['application/pdf', 'image/jpeg', 'image/png'];
    $allowed_mimes = $allowed_mimes ?: $default_allowed;
    if (!in_array($mime, $allowed_mimes, true)) {
        return ['success' => false, 'error' => 'Invalid MIME type: ' . $mime];
    }

    // Map mime -> extension
    $map = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];
    $ext = $map[$mime] ?? pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext = strtolower($ext ?: 'bin');

    $baseUpload = rtrim(defined('UPLOAD_DIR') ? UPLOAD_DIR : (__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR), '/\\') . DIRECTORY_SEPARATOR;
    $subdir = trim($subdir, '/\\');
    $targetDir = $baseUpload . ($subdir !== '' ? $subdir . DIRECTORY_SEPARATOR : '');

    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
        return ['success' => false, 'error' => 'Failed to create upload directory'];
    }

    $realTarget = realpath($targetDir);
    $uploadBase = realpath($baseUpload);
    if ($realTarget === false || $uploadBase === false || strpos($realTarget, $uploadBase) !== 0) {
        return ['success' => false, 'error' => 'Invalid upload directory configuration'];
    }

    // Random filename
    try {
        $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    } catch (Exception $e) {
        $randomName = uniqid('', true) . '.' . $ext;
    }

    $destination = $realTarget . DIRECTORY_SEPARATOR . $randomName;
    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    @chmod($destination, 0644);

    return ['success' => true, 'path' => $destination, 'filename' => $randomName, 'size' => $file['size'], 'mime' => $mime];
}
