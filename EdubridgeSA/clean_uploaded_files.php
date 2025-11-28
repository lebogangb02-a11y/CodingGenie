<?php
/**
 * File Cleanup Script for EduBridgeSA Application System
 * 
 * This script safely removes all uploaded files while preserving directory structure.
 * Can be run independently or as part of the full system reset.
 */

require_once 'config.php';
require_once 'session_config.php';

// Security check
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("❌ Access denied. Only administrators can run file cleanup.");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🗂️ File Cleanup - EduBridgeSA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        .log { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        h1 { color: #333; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗂️ File Cleanup Tool</h1>
        
        <?php if (!isset($_POST['action'])): ?>
        
        <div class="warning">
            <h3>⚠️ Warning</h3>
            <p>This will permanently delete all uploaded files including:</p>
            <ul>
                <li>ID document copies</li>
                <li>Matric certificates</li>
                <li>Additional documents</li>
                <li>Proof of residence files</li>
            </ul>
            <p><strong>This action cannot be undone!</strong></p>
        </div>

        <div class="info">
            <h3>📊 Current File Status</h3>
            <?php
            $uploadDirs = [
                'uploads/' => 'Main uploads directory',
                'uploads/id_documents/' => 'ID Documents',
                'uploads/matric_certificates/' => 'Matric Certificates',
                'uploads/additional_documents/' => 'Additional Documents'
            ];
            
            $totalFiles = 0;
            $totalSize = 0;
            
            foreach ($uploadDirs as $dir => $description) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    $fileCount = 0;
                    $dirSize = 0;
                    
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $fileCount++;
                            $dirSize += filesize($file);
                        }
                    }
                    
                    $totalFiles += $fileCount;
                    $totalSize += $dirSize;
                    
                    echo "<p><strong>$description:</strong> $fileCount files (" . formatBytes($dirSize) . ")</p>";
                } else {
                    echo "<p><strong>$description:</strong> Directory not found</p>";
                }
            }
            
            echo "<hr>";
            echo "<p><strong>Total:</strong> $totalFiles files (" . formatBytes($totalSize) . ")</p>";
            
            function formatBytes($size, $precision = 2) {
                $units = array('B', 'KB', 'MB', 'GB', 'TB');
                for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
                    $size /= 1024;
                }
                return round($size, $precision) . ' ' . $units[$i];
            }
            ?>
        </div>

        <form method="POST" action="clean_uploaded_files.php" style="text-align: center;">
            <input type="hidden" name="action" value="cleanup">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete all uploaded files? This cannot be undone!')">
                🗑️ Delete All Files
            </button>
        </form>

        <?php else: ?>
        
        <h2>🗑️ File Cleanup in Progress...</h2>
        <div class="log">
<?php
        $log = [];
        $totalDeleted = 0;
        $totalSize = 0;
        $errors = [];

        $log[] = "[" . date('Y-m-d H:i:s') . "] Starting file cleanup...";

        $uploadDirs = [
            'uploads/',
            'uploads/id_documents/',
            'uploads/matric_certificates/', 
            'uploads/additional_documents/'
        ];

        foreach ($uploadDirs as $dir) {
            if (is_dir($dir)) {
                $log[] = "[" . date('Y-m-d H:i:s') . "] Processing directory: $dir";
                
                $files = glob($dir . '*');
                $dirDeleted = 0;
                $dirSize = 0;
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $fileSize = filesize($file);
                        
                        if (unlink($file)) {
                            $dirDeleted++;
                            $totalDeleted++;
                            $dirSize += $fileSize;
                            $totalSize += $fileSize;
                            $log[] = "[" . date('Y-m-d H:i:s') . "] ✅ Deleted: " . basename($file);
                        } else {
                            $errors[] = "Could not delete: $file";
                            $log[] = "[" . date('Y-m-d H:i:s') . "] ❌ Failed to delete: " . basename($file);
                        }
                    }
                }
                
                $log[] = "[" . date('Y-m-d H:i:s') . "] Directory summary: $dirDeleted files deleted (" . formatBytes($dirSize) . ")";
            } else {
                $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ Directory not found: $dir";
            }
        }

        $log[] = "[" . date('Y-m-d H:i:s') . "] ==========================================";
        $log[] = "[" . date('Y-m-d H:i:s') . "] CLEANUP SUMMARY:";
        $log[] = "[" . date('Y-m-d H:i:s') . "] Total files deleted: $totalDeleted";
        $log[] = "[" . date('Y-m-d H:i:s') . "] Total space freed: " . formatBytes($totalSize);
        
        if (!empty($errors)) {
            $log[] = "[" . date('Y-m-d H:i:s') . "] Errors encountered: " . count($errors);
            foreach ($errors as $error) {
                $log[] = "[" . date('Y-m-d H:i:s') . "] ERROR: $error";
            }
        }
        
        $log[] = "[" . date('Y-m-d H:i:s') . "] ==========================================";
        
        if (empty($errors)) {
            $log[] = "[" . date('Y-m-d H:i:s') . "] ✅ FILE CLEANUP COMPLETED SUCCESSFULLY!";
        } else {
            $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ File cleanup completed with some errors.";
        }

        foreach ($log as $entry) {
            echo htmlspecialchars($entry) . "\n";
        }
?>
        </div>

        <?php if (empty($errors)): ?>
        <div class="success">
            <h3>✅ Cleanup Completed!</h3>
            <p>Successfully deleted <?php echo $totalDeleted; ?> files and freed <?php echo formatBytes($totalSize); ?> of storage space.</p>
        </div>
        <?php else: ?>
        <div class="warning">
            <h3>⚠️ Cleanup Completed with Warnings</h3>
            <p>Deleted <?php echo $totalDeleted; ?> files but encountered <?php echo count($errors); ?> errors. Some files may still remain.</p>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 20px;">
            <a href="reset_application_system.php" class="btn btn-success">🔄 Full System Reset</a>
            <a href="admin_dashboard.php" class="btn btn-success">📊 Admin Dashboard</a>
        </div>

        <?php endif; ?>
    </div>
</body>
</html>