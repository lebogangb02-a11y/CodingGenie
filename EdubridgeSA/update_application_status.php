<?php
/**
 * Database Migration Script - Update Application Status Field
 * This script updates the applications table to support new status values for document uploads
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Application Status Field Migration</h1>\n";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

// Include configuration
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    echo "<p style='color: green;'>✓ Database connection successful</p>\n";
    
    // Check current status field structure
    echo "<h2>1. Current Status Field Structure</h2>\n";
    $result = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo "<p><strong>Current Type:</strong> " . htmlspecialchars($row['Type']) . "</p>\n";
        echo "<p><strong>Current Default:</strong> " . htmlspecialchars($row['Default']) . "</p>\n";
        
        // Check if we need to update the status field
        $currentType = $row['Type'];
        $needsUpdate = !str_contains($currentType, 'Pending')
            || !str_contains($currentType, 'Accepted')
            || !str_contains($currentType, 'Rejected')
            || !str_contains($currentType, 'Submitted (with docs)')
            || !str_contains($currentType, 'Submitted (without docs)');
        
        if ($needsUpdate) {
            echo "<h2>2. Updating Status Field</h2>\n";
            
            // First, let's see what status values currently exist
            $statusResult = $conn->query("SELECT DISTINCT status FROM applications");
            echo "<p><strong>Current status values in database:</strong></p>\n";
            echo "<ul>\n";
            while ($statusRow = $statusResult->fetch_assoc()) {
                echo "<li>" . htmlspecialchars($statusRow['status']) . "</li>\n";
            }
            echo "</ul>\n";
            
            // Update the status field to include new values (Dashboard standard)
            $sql = "ALTER TABLE applications MODIFY COLUMN status ENUM('Pending','Accepted','Rejected','Submitted (with docs)','Submitted (without docs)') DEFAULT 'Pending'";
            
            if ($conn->query($sql)) {
                echo "<p style='color: green;'>✓ Status field updated successfully!</p>\n";
                
                // Verify the update
                $verifyResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'status'");
                if ($verifyResult && $verifyRow = $verifyResult->fetch_assoc()) {
                    echo "<p><strong>New Type:</strong> " . htmlspecialchars($verifyRow['Type']) . "</p>\n";
                }
                
            } else {
                echo "<p style='color: red;'>✗ Failed to update status field: " . $conn->error . "</p>\n";
            }
            
        } else {
            echo "<p style='color: green;'>✓ Status field already supports the required values!</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Could not find status column in applications table</p>\n";
    }
    
    // Check if application_ref column exists
    echo "<h2>3. Checking Application Reference Field</h2>\n";
    $refResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'application_ref'");
    
    if ($refResult && $refResult->num_rows > 0) {
        echo "<p style='color: green;'>✓ application_ref column already exists</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ application_ref column not found. Adding it...</p>\n";
        
        // Add application_ref column
        $sql = "ALTER TABLE applications ADD COLUMN application_ref VARCHAR(50) UNIQUE";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ application_ref column added successfully!</p>\n";
            
            // Generate application references for existing records
            $existingApps = $conn->query("SELECT id FROM applications WHERE application_ref IS NULL OR application_ref = ''");
            
            if ($existingApps && $existingApps->num_rows > 0) {
                echo "<p>Generating application references for existing records...</p>\n";
                
                while ($app = $existingApps->fetch_assoc()) {
                    $appRef = 'APP' . date('Y') . str_pad($app['id'], 6, '0', STR_PAD_LEFT);
                    $updateSql = "UPDATE applications SET application_ref = ? WHERE id = ?";
                    $stmt = $conn->prepare($updateSql);
                    $stmt->bind_param('si', $appRef, $app['id']);
                    
                    if ($stmt->execute()) {
                        echo "<p style='color: green;'>✓ Generated reference {$appRef} for application ID {$app['id']}</p>\n";
                    } else {
                        echo "<p style='color: red;'>✗ Failed to generate reference for application ID {$app['id']}</p>\n";
                    }
                }
            }
            
        } else {
            echo "<p style='color: red;'>✗ Failed to add application_ref column: " . $conn->error . "</p>\n";
        }
    }
    
    // Check for document upload fields
    echo "<h2>4. Checking Document Upload Fields</h2>\n";
    $documentFields = [
        'id_copy' => 'VARCHAR(255)',
        'proof_of_address' => 'VARCHAR(255)', 
        'parent_id_copy' => 'VARCHAR(255)',
        'academic_results' => 'VARCHAR(255)'
    ];
    
    foreach ($documentFields as $field => $type) {
        $fieldResult = $conn->query("SHOW COLUMNS FROM applications LIKE '{$field}'");
        
        if ($fieldResult && $fieldResult->num_rows > 0) {
            echo "<p style='color: green;'>✓ {$field} column already exists</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠ {$field} column not found. Adding it...</p>\n";
            
            $sql = "ALTER TABLE applications ADD COLUMN {$field} {$type}";
            
            if ($conn->query($sql)) {
                echo "<p style='color: green;'>✓ {$field} column added successfully!</p>\n";
            } else {
                echo "<p style='color: red;'>✗ Failed to add {$field} column: " . $conn->error . "</p>\n";
            }
        }
    }
    
    // Add updated_at column if it doesn't exist
    echo "<h2>5. Checking Updated At Field</h2>\n";
    $updatedResult = $conn->query("SHOW COLUMNS FROM applications LIKE 'updated_at'");
    
    if ($updatedResult && $updatedResult->num_rows > 0) {
        echo "<p style='color: green;'>✓ updated_at column already exists</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ updated_at column not found. Adding it...</p>\n";
        
        $sql = "ALTER TABLE applications ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ updated_at column added successfully!</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Failed to add updated_at column: " . $conn->error . "</p>\n";
        }
    }
    
    echo "<h2>6. Final Verification</h2>\n";
    
    // Show final table structure
    $finalResult = $conn->query("DESCRIBE applications");
    if ($finalResult) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";
        
        while ($row = $finalResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
    
    $conn->close();
    
    echo "<p style='color: green; font-weight: bold;'>✓ Database migration completed successfully!</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>\n";
}

echo "<h2>7. Next Steps</h2>\n";
echo "<ul>\n";
echo "<li>Test the document upload functionality</li>\n";
echo "<li>Verify application status updates work correctly</li>\n";
echo "<li>Update admin dashboard to show new statuses</li>\n";
echo "<li>Test the complete application workflow</li>\n";
echo "</ul>\n";
echo "<p><strong>Migration completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>