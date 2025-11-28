<?php
/**
 * EduBridgeSA Application System Reset Script
 * 
 * This script safely resets the application system by:
 * 1. Clearing all application records from database
 * 2. Removing all uploaded files
 * 3. Preserving table structures and system integrity
 * 
 * IMPORTANT: This action is IRREVERSIBLE. Make sure to backup your data first!
 */

require_once 'config.php';
require_once 'session_config.php';

// Security check - only allow execution by authorized users
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    die("❌ Access denied. Only administrators can reset the system.");
}

// Configuration
$BACKUP_RECOMMENDED = true;
$CONFIRM_RESET = false; // Set to true after reading instructions

// Set execution time limit for large operations
set_time_limit(300); // 5 minutes

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔄 System Reset - EduBridgeSA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .danger { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .btn { padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 10px 5px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { opacity: 0.9; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #007cba; background: #f8f9fa; }
        .log { background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto; }
        h1 { color: #333; text-align: center; }
        h2 { color: #007cba; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .checkbox-container { margin: 20px 0; }
        .checkbox-container input[type="checkbox"] { margin-right: 10px; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Application System Reset</h1>
        
        <div class="danger">
            <h3>⚠️ CRITICAL WARNING</h3>
            <p><strong>This action will permanently delete:</strong></p>
            <ul>
                <li>All student application records</li>
                <li>All uploaded documents (ID copies, academic results, etc.)</li>
                <li>All application status history</li>
                <li>All document verification records</li>
            </ul>
            <p><strong>This action is IRREVERSIBLE!</strong></p>
        </div>

        <?php if (!isset($_POST['action'])): ?>
        
        <h2>📋 Pre-Reset Checklist</h2>
        
        <div class="step">
            <h3>Step 1: Database Backup (REQUIRED)</h3>
            <p>Before proceeding, you MUST create a backup of your database:</p>
            <div class="info">
                <strong>MySQL Backup Command:</strong><br>
                <code>mysqldump -u [username] -p [database_name] > backup_$(date +%Y%m%d_%H%M%S).sql</code>
            </div>
        </div>

        <div class="step">
            <h3>Step 2: File System Backup (RECOMMENDED)</h3>
            <p>Consider backing up the uploads directory:</p>
            <div class="info">
                <strong>Backup Command:</strong><br>
                <code>cp -r uploads/ uploads_backup_$(date +%Y%m%d_%H%M%S)/</code>
            </div>
        </div>

        <div class="step">
            <h3>Step 3: System Status Check</h3>
            <div id="systemCheck">
                <button type="button" class="btn btn-secondary" onclick="checkSystemStatus()">🔍 Check System Status</button>
                <div id="statusResult"></div>
            </div>
        </div>

        <form method="POST" action="reset_application_system.php" id="resetForm">
            <h2>🔐 Confirmation Required</h2>
            
            <div class="checkbox-container">
                <label>
                    <input type="checkbox" id="backupConfirm" required>
                    I have created a complete database backup
                </label>
            </div>
            
            <div class="checkbox-container">
                <label>
                    <input type="checkbox" id="understandConfirm" required>
                    I understand this action is irreversible
                </label>
            </div>
            
            <div class="checkbox-container">
                <label>
                    <input type="checkbox" id="authorizeConfirm" required>
                    I authorize the complete reset of the application system
                </label>
            </div>

            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="confirm_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn btn-danger" id="resetButton" disabled>
                    🔄 RESET APPLICATION SYSTEM
                </button>
            </div>
        </form>

        <?php else: ?>
        
        <h2>🔄 System Reset in Progress...</h2>
        <div class="log" id="resetLog">
<?php
        // Perform the reset
        $log = [];
        $errors = [];
        $success = true;

        try {
            $log[] = "[" . date('Y-m-d H:i:s') . "] Starting system reset...";
            
            // Connect to database
            $pdo = new PDO($dsn, $username, $password, $options);
            $log[] = "[" . date('Y-m-d H:i:s') . "] Database connection established";

            // 1. Get statistics before reset
            $stats = [];
            $tables = ['applications', 'application_documents', 'application_status_history'];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                    $count = $stmt->fetchColumn();
                    $stats[$table] = $count;
                    $log[] = "[" . date('Y-m-d H:i:s') . "] Found $count records in $table";
                } catch (PDOException $e) {
                    $log[] = "[" . date('Y-m-d H:i:s') . "] Warning: Could not count records in $table (table may not exist)";
                }
            }

            // 2. Clear database tables (in correct order to respect foreign keys)
            $log[] = "[" . date('Y-m-d H:i:s') . "] Starting database cleanup...";
            
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $tablesToClear = [
                'application_status_history',
                'application_documents', 
                'applications'
            ];
            
            foreach ($tablesToClear as $table) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM $table");
                    $result = $stmt->execute();
                    if ($result) {
                        $log[] = "[" . date('Y-m-d H:i:s') . "] ✅ Cleared table: $table";
                    } else {
                        $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ Warning: Could not clear table: $table";
                    }
                } catch (PDOException $e) {
                    $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ Warning: Table $table may not exist or could not be cleared";
                }
            }
            
            // Reset auto-increment counters
            foreach ($tablesToClear as $table) {
                try {
                    $pdo->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
                    $log[] = "[" . date('Y-m-d H:i:s') . "] Reset auto-increment for $table";
                } catch (PDOException $e) {
                    $log[] = "[" . date('Y-m-d H:i:s') . "] Warning: Could not reset auto-increment for $table";
                }
            }
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $log[] = "[" . date('Y-m-d H:i:s') . "] Database cleanup completed";

            // 3. Clear uploaded files
            $log[] = "[" . date('Y-m-d H:i:s') . "] Starting file cleanup...";
            
            $uploadDirs = [
                'uploads/',
                'uploads/id_documents/',
                'uploads/matric_certificates/',
                'uploads/additional_documents/'
            ];
            
            $totalFilesDeleted = 0;
            
            foreach ($uploadDirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '*');
                    $fileCount = 0;
                    
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            if (unlink($file)) {
                                $fileCount++;
                                $totalFilesDeleted++;
                            } else {
                                $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ Could not delete file: $file";
                            }
                        }
                    }
                    
                    $log[] = "[" . date('Y-m-d H:i:s') . "] ✅ Deleted $fileCount files from $dir";
                } else {
                    $log[] = "[" . date('Y-m-d H:i:s') . "] ⚠️ Directory not found: $dir";
                }
            }
            
            $log[] = "[" . date('Y-m-d H:i:s') . "] File cleanup completed. Total files deleted: $totalFilesDeleted";

            // 4. Clear session data related to applications
            $log[] = "[" . date('Y-m-d H:i:s') . "] Clearing application-related session data...";
            
            $sessionKeysToRemove = [
                'application_id',
                'application_step',
                'application_data',
                'upload_progress'
            ];
            
            foreach ($sessionKeysToRemove as $key) {
                if (isset($_SESSION[$key])) {
                    unset($_SESSION[$key]);
                    $log[] = "[" . date('Y-m-d H:i:s') . "] Cleared session key: $key";
                }
            }

            // 5. Generate summary
            $log[] = "[" . date('Y-m-d H:i:s') . "] ==========================================";
            $log[] = "[" . date('Y-m-d H:i:s') . "] RESET SUMMARY:";
            foreach ($stats as $table => $count) {
                $log[] = "[" . date('Y-m-d H:i:s') . "] - Cleared $count records from $table";
            }
            $log[] = "[" . date('Y-m-d H:i:s') . "] - Deleted $totalFilesDeleted uploaded files";
            $log[] = "[" . date('Y-m-d H:i:s') . "] - Cleared application session data";
            $log[] = "[" . date('Y-m-d H:i:s') . "] ==========================================";
            $log[] = "[" . date('Y-m-d H:i:s') . "] ✅ SYSTEM RESET COMPLETED SUCCESSFULLY!";
            
        } catch (Exception $e) {
            $success = false;
            $errors[] = $e->getMessage();
            $log[] = "[" . date('Y-m-d H:i:s') . "] ❌ ERROR: " . $e->getMessage();
        }

        // Output the log
        foreach ($log as $entry) {
            echo htmlspecialchars($entry) . "\n";
        }
?>
        </div>

        <?php if ($success): ?>
        <div class="success">
            <h3>✅ Reset Completed Successfully!</h3>
            <p>The application system has been reset. You can now:</p>
            <ul>
                <li>Accept new student applications</li>
                <li>Test the application workflow</li>
                <li>Verify all systems are working correctly</li>
            </ul>
        </div>
        <?php else: ?>
        <div class="danger">
            <h3>❌ Reset Failed</h3>
            <p>Some errors occurred during the reset process. Please check the log above and contact technical support if needed.</p>
        </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="admin_dashboard.php" class="btn btn-success">📊 Go to Admin Dashboard</a>
            <a href="apply_test.php" class="btn btn-secondary">🧪 Test Application Form</a>
        </div>

        <?php endif; ?>
    </div>

    <script>
        // Enable reset button only when all checkboxes are checked
        const checkboxes = document.querySelectorAll('input[type="checkbox"]');
        const resetButton = document.getElementById('resetButton');
        
        if (checkboxes.length > 0 && resetButton) {
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                    resetButton.disabled = !allChecked;
                });
            });
        }

        // System status check
        function checkSystemStatus() {
            const statusDiv = document.getElementById('statusResult');
            statusDiv.innerHTML = '<p>🔍 Checking system status...</p>';
            
            fetch('system_diagnostics.php')
                .then(response => response.text())
                .then(data => {
                    statusDiv.innerHTML = '<div class="info"><h4>System Status:</h4><pre>' + data + '</pre></div>';
                })
                .catch(error => {
                    statusDiv.innerHTML = '<div class="warning"><p>Could not check system status. You may proceed with caution.</p></div>';
                });
        }

        // Confirmation dialog
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure you want to reset the entire application system? This action cannot be undone!')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>