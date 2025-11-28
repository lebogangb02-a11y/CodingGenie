<?php
echo "<h2>Database Structure Verification</h2>";

// First, let's check if we can establish a database connection manually
echo "<h3>Database Connection Test:</h3>";

// Database Configuration (from config.php)
$db_host = 'localhost';
$db_user = 'u839420047_Edubridge';
$db_pass = 'BAs1m@n3';
$db_name = 'u839420047_applications';

try {
    $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    echo "<p>✅ Database connection successful!</p>";
} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database credentials and server status.</p>";
    exit;
}

try {
    // Check applications table structure
    echo "<h3>Applications Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE applications");
    $applicationColumns = [];
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $applicationColumns[] = $row['Field'];
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Check application_subjects table structure
    echo "<h3>Application Subjects Table Structure:</h3>";
    $stmt = $pdo->query("DESCRIBE application_subjects");
    $subjectColumns = [];
    echo "<table border='1'><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subjectColumns[] = $row['Field'];
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
    }
    echo "</table>";
    
    // Check columns used in process_application.php
    echo "<h3>Columns Used in process_application.php:</h3>";
    $usedColumns = [
        'first_name', 'last_name', 'id_number',
        'email_address', 'phone',
        'program_choice_1', 'institution_choice_1', 'program_specialization_1',
        'program_choice_2', 'institution_choice_2', 'program_specialization_2',
        'program_choice_3', 'institution_choice_3', 'program_specialization_3',
        'aps',
        'status'
    ];
    
    echo "<h4>Applications Table Column Check:</h4>";
    echo "<ul>";
    foreach ($usedColumns as $column) {
        $exists = in_array($column, $applicationColumns);
        $status = $exists ? "✅ EXISTS" : "❌ MISSING";
        echo "<li><strong>$column</strong>: $status</li>";
    }
    echo "</ul>";
    
    // Check subject table columns
    $subjectUsedColumns = ['application_id', 'subject_name', 'final_mark', 'level'];
    echo "<h4>Application Subjects Table Column Check:</h4>";
    echo "<ul>";
    foreach ($subjectUsedColumns as $column) {
        $exists = in_array($column, $subjectColumns);
        $status = $exists ? "✅ EXISTS" : "❌ MISSING";
        echo "<li><strong>$column</strong>: $status</li>";
    }
    echo "</ul>";
    
    // Test a simple query to see if there are any JOIN issues
    echo "<h3>Testing Simple Queries:</h3>";
    
    // Test applications table query
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM applications");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Applications table query successful. Count: {$result['count']}</p>";
    } catch (Exception $e) {
        echo "<p>❌ Applications table query failed: " . $e->getMessage() . "</p>";
    }
    
    // Test application_subjects table query
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM application_subjects");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Application subjects table query successful. Count: {$result['count']}</p>";
    } catch (Exception $e) {
        echo "<p>❌ Application subjects table query failed: " . $e->getMessage() . "</p>";
    }
    
    // Test JOIN query (this might be where the error is coming from)
    try {
        $stmt = $pdo->query("SELECT a.id AS application_id, COUNT(s.id) as subject_count 
                            FROM applications a 
                            LEFT JOIN application_subjects s ON a.id = s.application_id 
                            GROUP BY a.id 
                            LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ JOIN query successful</p>";
    } catch (Exception $e) {
        echo "<p>❌ JOIN query failed: " . $e->getMessage() . "</p>";
        echo "<p>This might be the source of the 'Unknown column a.id' error</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>