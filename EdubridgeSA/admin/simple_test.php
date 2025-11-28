<?php
/**
 * ENUM Status Test and Fix
 */

require_once '../config.php';

try {
    echo "<h2>ENUM Status Field Test</h2>";
    
    echo "<h3>Problem Identified:</h3>";
    echo "<p>The 'status' field is an ENUM with only these allowed values: 'Pending', 'Submitted', 'In Review', 'Accepted', 'Rejected'</p>";
    echo "<p>When we try to update with values outside this list, MySQL silently fails and returns 0 rows affected.</p>";
    
    echo "<h3>Testing with Valid ENUM Values:</h3>";
    
    // Test with application_id = 1
    $testId = 1;
    
    // Show current status
    $currentStmt = $pdo->prepare("SELECT status FROM applications WHERE id = ?");
    $currentStmt->execute([$testId]);
    $currentStatus = $currentStmt->fetchColumn();
    echo "Current status for ID $testId: '" . htmlspecialchars($currentStatus) . "'<br><br>";
    
    // Test updating to each valid ENUM value
    $validStatuses = ['Pending', 'Submitted', 'In Review', 'Accepted', 'Rejected'];
    
    foreach ($validStatuses as $status) {
        echo "<strong>Testing update to '$status':</strong><br>";
        
        $updateStmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $result = $updateStmt->execute([$status, $testId]);
        $rowsAffected = $updateStmt->rowCount();
        
        echo "- Update result: " . ($result ? 'Success' : 'Failed') . "<br>";
        echo "- Rows affected: $rowsAffected<br>";
        
        // Verify the update
        $currentStmt->execute([$testId]);
        $updatedStatus = $currentStmt->fetchColumn();
        echo "- Status after update: '" . htmlspecialchars($updatedStatus) . "'<br>";
        
        if ($rowsAffected > 0) {
            echo "- <span style='color: green;'>✓ SUCCESS!</span><br><br>";
        } else {
            echo "- <span style='color: red;'>✗ FAILED!</span><br><br>";
        }
    }
    
    echo "<h3>Fix for view_application.php:</h3>";
    echo "<p>The approval process should update status to 'Accepted' instead of 'Approved'.</p>";
    echo "<p>Current code likely uses: UPDATE applications SET status = 'Approved' WHERE application_id = ?</p>";
    echo "<p>Should be changed to: UPDATE applications SET status = 'Accepted' WHERE application_id = ?</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>