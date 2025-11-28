<?php
/**
 * File-Only System Reset
 * Cleans up uploaded files when database access is not available
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🗂️ File-Only System Reset</h1>";
echo "<style>
body{font-family:Arial;max-width:800px;margin:20px auto;padding:20px;background:#f5f5f5;}
.section{background:white;padding:20px;margin:15px 0;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}
.success{background:#d4edda;border-left:4px solid #28a745;color:#155724;}
.warning{background:#fff3cd;border-left:4px solid #ffc107;color:#856404;}
.danger{background:#f8d7da;border-left:4px solid #dc3545;color:#721c24;}
.info{background:#d1ecf1;border-left:4px solid #17a2b8;color:#0c5460;}
button{background:#dc3545;color:white;padding:12px 24px;border:none;border-radius:5px;cursor:pointer;font-size:16px;margin:10px 5px;}
button:hover{background:#c82333;}
.safe-btn{background:#28a745;} .safe-btn:hover{background:#218838;}
.info-btn{background:#17a2b8;} .info-btn:hover{background:#138496;}
</style>";

// Define upload directories to check
$upload_dirs = [
    'uploads',
    'uploads/applications',
    'uploads/documents', 
    'uploads/proof_of_residence',
    'uploads/academic_records',
    'uploads/identity_documents',
    'uploads/temp',
    'uploads/profile_pictures'
];

// Function to scan directory and get file info
function scanDirectory($dir) {
    $files = [];
    $totalSize = 0;
    
    if (!is_dir($dir)) {
        return ['files' => [], 'count' => 0, 'size' => 0, 'exists' => false];
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = [
                'path' => $file->getPathname(),
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified' => $file->getMTime()
            ];
            $totalSize += $file->getSize();
        }
    }
    
    return [
        'files' => $files,
        'count' => count($files),
        'size' => $totalSize,
        'exists' => true
    ];
}

// Function to format file size
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    return round($size, $precision) . ' ' . $units[$i];
}

// Handle reset action
if (isset($_POST['action'])) {
    echo "<div class='section'>";
    echo "<h2>🔄 Reset Operation Results</h2>";
    
    $action = $_POST['action'];
    $deletedFiles = 0;
    $deletedSize = 0;
    $errors = [];
    
    if ($action === 'reset_all' || $action === 'reset_specific') {
        $dirsToReset = ($action === 'reset_all') ? $upload_dirs : [$_POST['specific_dir']];
        
        foreach ($dirsToReset as $dir) {
            if (!is_dir($dir)) continue;
            
            $dirInfo = scanDirectory($dir);
            
            foreach ($dirInfo['files'] as $file) {
                if (unlink($file['path'])) {
                    $deletedFiles++;
                    $deletedSize += $file['size'];
                } else {
                    $errors[] = "Failed to delete: " . $file['path'];
                }
            }
        }
        
        if ($deletedFiles > 0) {
            echo "<div class='success'>";
            echo "<h3>✅ Reset Successful!</h3>";
            echo "<strong>Files deleted:</strong> $deletedFiles files<br>";
            echo "<strong>Space freed:</strong> " . formatBytes($deletedSize) . "<br>";
            echo "</div>";
        } else {
            echo "<div class='info'>";
            echo "<h3>ℹ️ No Files to Delete</h3>";
            echo "All specified directories were already clean.<br>";
            echo "</div>";
        }
        
        if (!empty($errors)) {
            echo "<div class='warning'>";
            echo "<h3>⚠️ Some Issues Occurred</h3>";
            foreach ($errors as $error) {
                echo "• $error<br>";
            }
            echo "</div>";
        }
    }
    echo "</div>";
}

// Display current file status
echo "<div class='section'>";
echo "<h2>📊 Current File System Status</h2>";

$totalFiles = 0;
$totalSize = 0;
$directoriesWithFiles = [];

foreach ($upload_dirs as $dir) {
    $dirInfo = scanDirectory($dir);
    
    if ($dirInfo['exists']) {
        echo "<div style='margin:10px 0;padding:10px;background:#f8f9fa;border-radius:5px;'>";
        echo "<strong>📁 $dir/</strong><br>";
        
        if ($dirInfo['count'] > 0) {
            echo "<span style='color:#dc3545;'>📄 {$dirInfo['count']} files (" . formatBytes($dirInfo['size']) . ")</span><br>";
            $totalFiles += $dirInfo['count'];
            $totalSize += $dirInfo['size'];
            $directoriesWithFiles[] = $dir;
            
            // Show first few files as examples
            $exampleFiles = array_slice($dirInfo['files'], 0, 3);
            echo "<small>Examples: ";
            foreach ($exampleFiles as $file) {
                echo basename($file['name']) . " (" . formatBytes($file['size']) . "), ";
            }
            if ($dirInfo['count'] > 3) {
                echo "... and " . ($dirInfo['count'] - 3) . " more";
            }
            echo "</small>";
        } else {
            echo "<span style='color:#28a745;'>✅ Empty</span>";
        }
        echo "</div>";
    } else {
        echo "<div style='margin:10px 0;padding:10px;background:#fff3cd;border-radius:5px;'>";
        echo "<strong>📁 $dir/</strong> <span style='color:#856404;'>⚠️ Directory does not exist</span>";
        echo "</div>";
    }
}

echo "<div class='info'>";
echo "<h3>📈 Summary</h3>";
echo "<strong>Total files:</strong> $totalFiles files<br>";
echo "<strong>Total size:</strong> " . formatBytes($totalSize) . "<br>";
echo "<strong>Directories with files:</strong> " . count($directoriesWithFiles) . "<br>";
echo "</div>";

echo "</div>";

// Reset options
if ($totalFiles > 0) {
    echo "<div class='section'>";
    echo "<h2>🗑️ Reset Options</h2>";
    
    echo "<div class='danger'>";
    echo "<h3>⚠️ Warning</h3>";
    echo "These operations will <strong>permanently delete</strong> uploaded files. This action cannot be undone!<br>";
    echo "Make sure you have backups if needed.<br>";
    echo "</div>";
    
    echo "<form method='post' action='file_only_reset.php' onsubmit='return confirm(\"Are you sure you want to delete all files? This cannot be undone!\");'>";
    echo "<input type='hidden' name='action' value='reset_all'>";
    echo "<button type='submit'>🗑️ Delete All Files ($totalFiles files)</button>";
    echo "</form>";
    
    if (count($directoriesWithFiles) > 1) {
        echo "<h4>Or delete specific directories:</h4>";
        foreach ($directoriesWithFiles as $dir) {
            $dirInfo = scanDirectory($dir);
            echo "<form method='post' action='file_only_reset.php' style='display:inline;' onsubmit='return confirm(\"Delete all files in $dir? This cannot be undone!\");'>";
            echo "<input type='hidden' name='action' value='reset_specific'>";
            echo "<input type='hidden' name='specific_dir' value='$dir'>";
            echo "<button type='submit' style='background:#ffc107;color:#212529;'>🗑️ Delete $dir ({$dirInfo['count']} files)</button>";
            echo "</form> ";
        }
    }
    
    echo "</div>";
} else {
    echo "<div class='section success'>";
    echo "<h2>✅ System is Clean</h2>";
    echo "No uploaded files found. The file system is already clean!<br>";
    echo "</div>";
}

// Database status note
echo "<div class='section info'>";
echo "<h2>💡 About Database Reset</h2>";
echo "<strong>Database Status:</strong> No working connection available<br>";
echo "<strong>Impact:</strong> Database records will remain unchanged<br>";
echo "<strong>Note:</strong> This file-only reset cleans uploaded files but cannot reset database records<br>";
echo "<strong>For full reset:</strong> Database access would be needed to clear application records<br>";
echo "</div>";

// Navigation
echo "<div style='text-align:center;margin:20px 0;'>";
echo "<a href='verify_system_status_test.php' class='info-btn' style='text-decoration:none;padding:12px 24px;border-radius:5px;color:white;margin:5px;display:inline-block;'>🔄 Check System Status</a> ";
echo "<a href='admin_login.php' class='safe-btn' style='text-decoration:none;padding:12px 24px;border-radius:5px;color:white;margin:5px;display:inline-block;'>🔐 Admin Login</a> ";
echo "<a href='quick_db_test.php' class='info-btn' style='text-decoration:none;padding:12px 24px;border-radius:5px;color:white;margin:5px;display:inline-block;'>🔍 Database Test</a>";
echo "</div>";

?>