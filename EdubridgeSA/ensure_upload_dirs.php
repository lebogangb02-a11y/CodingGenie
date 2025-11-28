<?php
// Utility script to ensure required upload directories exist and are writable
$base = __DIR__;
$dirs = [
    'uploads',
    'uploads/applications',
    'uploads/applications/academic_results',
    'uploads/applications/id_documents',
    'uploads/applications/parent_guardian_id',
    'uploads/applications/proof_of_residence',
    'uploads/parent_guardian_id',
    'uploads/profile_pictures',
    'uploads/proof_of_residence',
];

header('Content-Type: text/plain');

foreach ($dirs as $rel) {
    $path = $base . DIRECTORY_SEPARATOR . $rel;
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true)) {
            echo "Failed to create: {$rel}\n";
            continue;
        }
        echo "Created: {$rel}\n";
    } else {
        echo "Exists: {$rel}\n";
    }

    // Try to write a .keep file to verify write perms
    $testFile = $path . DIRECTORY_SEPARATOR . '.perm_check';
    $ok = @file_put_contents($testFile, 'ok');
    if ($ok === false) {
        echo "Not writable: {$rel}\n";
    } else {
        echo "Writable: {$rel}\n";
        @unlink($testFile);
    }
}

// Ensure a restrictive .htaccess in uploads if not present
$uploadsHtaccess = $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($uploadsHtaccess)) {
    $rules = "Options -Indexes\n<FilesMatch \\\"\\\\.(php|phar|phtml|php3|php4|php5|php7|php8)\\\\$\\\">\n  Deny from all\n</FilesMatch>\n";
    if (@file_put_contents($uploadsHtaccess, $rules) !== false) {
        echo "Created uploads/.htaccess\n";
    } else {
        echo "Failed to create uploads/.htaccess\n";
    }
} else {
    echo "uploads/.htaccess present\n";
}