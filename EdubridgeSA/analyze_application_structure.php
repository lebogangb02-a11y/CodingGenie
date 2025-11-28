<?php
/**
 * Application Structure Analyzer - Customized for Your Database Schema
 */

// Database configuration
require_once 'config.php';

// Start session for security
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple security check
$allowed = isset($_SESSION['student_logged_in']) || isset($_SESSION['admin_logged_in']) || $_SERVER['REMOTE_ADDR'] === '127.0.0.1';
if (!$allowed) {
    die('<h2>Access Denied</h2><p>Please <a href="student-login.php">login as student</a> or <a href="admin/admin_login.php">login as admin</a> first.</p>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Structure Analyzer - EduBridge SA</title>
    <style>
        :root {
            --primary: #1e3a8a;
            --secondary: #059669;
            --danger: #dc2626;
            --warning: #d97706;
            --info: #2563eb;
            --light: #f8fafc;
            --dark: #1e293b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; color: #333; line-height: 1.6; padding: 20px; }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary), #3730a3);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .content { padding: 2rem; }
        
        .section {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        tr:hover { background: #f8fafc; }
        
        .field-type { 
            background: #e0f2fe; 
            color: #0369a1; 
            padding: 0.2rem 0.4rem; 
            border-radius: 4px; 
            font-size: 0.75rem; 
            font-family: monospace; 
        }
        
        .required { background: #fecaca; color: #dc2626; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75rem; }
        .optional { background: #dcfce7; color: #16a34a; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.75rem; }
        
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 1.5rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            margin: 1rem 0;
            line-height: 1.4;
        }
        
        .form-preview {
            background: white;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151; }
        .form-input, .form-select, .form-textarea { 
            width: 100%; 
            padding: 0.75rem; 
            border: 1px solid #d1d5db; 
            border-radius: 6px; 
            font-size: 1rem; 
        }
        
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 1rem;
        }
        
        .success { background: #dcfce7; color: #16a34a; }
        .warning { background: #fef3c7; color: #d97706; }
        .error { background: #fecaca; color: #dc2626; }
        
        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 600;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Application Structure Analyzer</h1>
            <p>Customized for Your EduBridge SA Database Schema</p>
        </div>
        
        <div class="content">
            <?php
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo '<div class="status-badge success">✅ Database connection successful</div>';
                
                // Analyze your specific database structure
                analyzeYourDatabase($pdo);
                
                // Generate form structure based on your schema
                generateFormStructure($pdo);
                
                // Show implementation code
                showImplementationCode();
                
            } catch (PDOException $e) {
                echo '<div class="status-badge error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        
        function copyCode(elementId) {
            const code = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('Code copied to clipboard!');
            });
        }
    </script>
</body>
</html>

<?php
function analyzeYourDatabase($pdo) {
    echo '<div class="section">';
    echo '<h2>🗃️ Your Database Schema Analysis</h2>';
    
    // Analyze applications table structure
    echo '<h3>📋 Applications Table Structure</h3>';
    
    try {
        $stmt = $pdo->prepare("DESCRIBE applications");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($columns) {
            echo '<div class="table-container">';
            echo '<table>';
            echo '<tr><th>Field</th><th>Type</th><th>Null</th><th>Form Field Type</th><th>Required</th><th>Notes</th></tr>';
            
            foreach ($columns as $column) {
                $field = $column['Field'];
                $type = $column['Type'];
                $isNull = $column['Null'] === 'YES';
                $required = $isNull ? '<span class="optional">Optional</span>' : '<span class="required">Required</span>';
                
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($field) . '</strong></td>';
                echo '<td><span class="field-type">' . htmlspecialchars($type) . '</span></td>';
                echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                echo '<td>' . getFormFieldType($field, $type) . '</td>';
                echo '<td>' . $required . '</td>';
                echo '<td>' . getFieldNotes($field) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
    } catch (PDOException $e) {
        echo '<div class="status-badge warning">Could not analyze applications table</div>';
    }
    
    echo '</div>';
}

function getFormFieldType($field, $type) {
    $mapping = [
        // Personal Information
        'first_name' => 'text', 'last_name' => 'text', 'full_name' => 'text', 'surname' => 'text',
        'id_number' => 'text', 'email_address' => 'email', 'cellphone_number' => 'tel', 'phone' => 'tel',
        'date_of_birth' => 'date', 'gender' => 'select', 'title' => 'select',
        
        // Address Information
        'physical_address' => 'textarea', 'postal_address' => 'textarea', 'address' => 'textarea',
        'city' => 'text', 'province' => 'select', 'postal_code' => 'text', 'country' => 'select',
        
        // Academic Information
        'high_school_name' => 'text', 'matric_year' => 'number', 'aps' => 'number',
        'maths_level' => 'select', 'english_level' => 'select', 'exam_number' => 'text',
        
        // University Choices
        'institution_choice_1' => 'select', 'institution_choice_2' => 'select', 'institution_choice_3' => 'select',
        'program_choice_1' => 'text', 'program_choice_2' => 'text', 'program_choice_3' => 'text',
        'program_specialization_1' => 'text', 'program_specialization_2' => 'text', 'program_specialization_3' => 'text',
        
        // Documents
        'matric_certificate' => 'file', 'id_document' => 'file', 'proof_of_residence' => 'file',
        
        // Boolean fields
        'terms_conditions' => 'checkbox', 'privacy_policy' => 'checkbox', 'has_disability' => 'radio',
        
        // Text areas
        'motivation' => 'textarea', 'disability_details' => 'textarea', 'additional_qualifications' => 'textarea'
    ];
    
    return $mapping[$field] ?? 'text';
}

function getFieldNotes($field) {
    $notes = [
        'first_name' => 'Use with last_name OR full_name alone',
        'last_name' => 'Use with first_name OR full_name alone', 
        'full_name' => 'Alternative to first_name + last_name',
        'surname' => 'Primary surname field',
        'institution_choice_1' => 'Should use universities table for dropdown',
        'program_choice_1' => 'Free text course input',
        'application_status' => 'Main status field - use this',
        'status' => 'Legacy status field'
    ];
    
    return $notes[$field] ?? '';
}

function generateFormStructure($pdo) {
    echo '<div class="section">';
    echo '<h2>🎯 Recommended Form Structure</h2>';
    
    echo '<div class="tabs">
        <div class="tab active" onclick="showTab(\'personal-tab\')">👤 Personal</div>
        <div class="tab" onclick="showTab(\'academic-tab\')">🎓 Academic</div>
        <div class="tab" onclick="showTab(\'choices-tab\')">🏫 University</div>
        <div class="tab" onclick="showTab(\'documents-tab\')">📄 Documents</div>
    </div>';
    
    // Personal Information Tab
    echo '<div id="personal-tab" class="tab-content active">';
    echo '<div class="form-preview">';
    echo '<h4>Personal Information Section</h4>';
    echo '<div class="grid-3">';
    echo '  <div class="form-group"><label class="form-label">Title</label><select class="form-select"><option>Mr</option><option>Ms</option><option>Mrs</option><option>Dr</option></select></div>';
    echo '  <div class="form-group"><label class="form-label">First Name *</label><input type="text" class="form-input" required></div>';
    echo '  <div class="form-group"><label class="form-label">Last Name *</label><input type="text" class="form-input" required></div>';
    echo '</div>';
    echo '<div class="grid-3">';
    echo '  <div class="form-group"><label class="form-label">ID Number *</label><input type="text" class="form-input" required></div>';
    echo '  <div class="form-group"><label class="form-label">Date of Birth *</label><input type="date" class="form-input" required></div>';
    echo '  <div class="form-group"><label class="form-label">Gender *</label><select class="form-select" required><option>Male</option><option>Female</option></select></div>';
    echo '</div>';
    echo '<div class="grid-2">';
    echo '  <div class="form-group"><label class="form-label">Email *</label><input type="email" class="form-input" required></div>';
    echo '  <div class="form-group"><label class="form-label">Cellphone *</label><input type="tel" class="form-input" required></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // Academic Information Tab
    echo '<div id="academic-tab" class="tab-content">';
    echo '<div class="form-preview">';
    echo '<h4>Academic Information</h4>';
    echo '<div class="grid-2">';
    echo '  <div class="form-group"><label class="form-label">High School Name</label><input type="text" class="form-input"></div>';
    echo '  <div class="form-group"><label class="form-label">Matric Year</label><input type="number" class="form-input" min="1950" max="2025"></div>';
    echo '</div>';
    echo '<div class="grid-3">';
    echo '  <div class="form-group"><label class="form-label">APS Score</label><input type="number" class="form-input" min="0" max="42"></div>';
    echo '  <div class="form-group"><label class="form-label">Maths Level</label><select class="form-select"><option>Mathematics</option><option>Mathematical Literacy</option></select></div>';
    echo '  <div class="form-group"><label class="form-label">English Level</label><select class="form-select"><option>Home Language</option><option>First Additional</option></select></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    
    // University Choices Tab
    echo '<div id="choices-tab" class="tab-content">';
    echo '<div class="form-preview">';
    echo '<h4>University Choices (3 Choices)</h4>';
    for ($i = 1; $i <= 3; $i++) {
        echo '<div style="border: 1px solid #e2e8f0; padding: 1rem; margin-bottom: 1rem; border-radius: 8px;">';
        echo '<h5>Choice ' . $i . '</h5>';
        echo '<div class="grid-2">';
        echo '  <div class="form-group"><label class="form-label">Institution</label><select class="form-select"><option>Select University</option><option>University of Cape Town</option><option>University of Pretoria</option></select></div>';
        echo '  <div class="form-group"><label class="form-label">Program Choice</label><input type="text" class="form-input" placeholder="e.g., Bachelor of Commerce"></div>';
        echo '</div>';
        echo '<div class="form-group"><label class="form-label">Specialization</label><input type="text" class="form-input" placeholder="e.g., Accounting"></div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
    
    // Documents Tab
    echo '<div id="documents-tab" class="tab-content">';
    echo '<div class="form-preview">';
    echo '<h4>Required Documents</h4>';
    echo '<div class="form-group"><label class="form-label">ID Document</label><input type="file" class="form-input"></div>';
    echo '<div class="form-group"><label class="form-label">Matric Certificate</label><input type="file" class="form-input"></div>';
    echo '<div class="form-group"><label class="form-label">Proof of Residence</label><input type="file" class="form-input"></div>';
    echo '<div class="form-group"><label class="form-label">Academic Transcript</label><input type="file" class="form-input"></div>';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
}

function showImplementationCode() {
    echo '<div class="section">';
    echo '<h2>💻 Implementation Code</h2>';
    
    echo '<h3>Database Query for Form Data</h3>';
    echo '<div class="code-block" id="query-code">';
    echo htmlspecialchars('<?php
// Get application data with proper field mapping
$stmt = $pdo->prepare("
    SELECT 
        -- Personal Information
        first_name, last_name, full_name, surname,
        id_number, email_address, cellphone_number, date_of_birth,
        gender, title, home_language, nationality,
        
        -- Contact Information  
        physical_address, postal_code, city, province, country,
        
        -- Academic Information
        high_school_name, matric_year, aps, maths_level, english_level,
        exam_number, highest_grade,
        
        -- University Choices
        institution_choice_1, program_choice_1, program_specialization_1,
        institution_choice_2, program_choice_2, program_specialization_2, 
        institution_choice_3, program_choice_3, program_specialization_3,
        
        -- Documents
        id_copy, matric_certificate, proof_of_address, academic_results,
        
        -- System
        application_status, student_id, reference_number
        
    FROM applications 
    WHERE student_id = ? OR id = ?
    LIMIT 1
");
$stmt->execute([$student_id, $application_id]);
$application = $stmt->fetch(PDO::FETCH_ASSOC);
?>');
    echo '</div>';
    echo '<button onclick="copyCode(\'query-code\')" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">📋 Copy Query</button>';
    
    echo '<h3>Form Field Structure</h3>';
    echo '<div class="code-block" id="form-code">';
    echo htmlspecialchars('<!-- Personal Information -->
<div class="form-section">
    <h3>Personal Information</h3>
    <div class="form-grid-3">
        <div class="form-group">
            <label for="first_name" class="required">First Name</label>
            <input type="text" id="first_name" name="first_name" required 
                   value="<?= htmlspecialchars($application[\'first_name\'] ?? \'\') ?>">
        </div>
        <div class="form-group">
            <label for="last_name" class="required">Last Name</label>
            <input type="text" id="last_name" name="last_name" required
                   value="<?= htmlspecialchars($application[\'last_name\'] ?? \'\') ?>">
        </div>
        <div class="form-group">
            <label for="id_number" class="required">ID Number</label>
            <input type="text" id="id_number" name="id_number" required
                   value="<?= htmlspecialchars($application[\'id_number\'] ?? \'\') ?>">
        </div>
    </div>
</div>

<!-- University Choices -->
<div class="form-section">
    <h3>University Choices</h3>
    <?php for ($i = 1; $i <= 3; $i++): ?>
    <div class="choice-group">
        <h4>Choice <?= $i ?></h4>
        <div class="form-grid-2">
            <div class="form-group">
                <label>Institution</label>
                <select name="institution_choice_<?= $i ?>">
                    <option value="">Select University</option>
                    <?php foreach ($universities as $uni): ?>
                    <option value="<?= $uni[\'name\'] ?>" 
                        <?= ($application[\'institution_choice_\'.$i] ?? \'\') === $uni[\'name\'] ? \'selected\' : \'\' ?>>
                        <?= htmlspecialchars($uni[\'name\']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Program</label>
                <input type="text" name="program_choice_<?= $i ?>" 
                       value="<?= htmlspecialchars($application[\'program_choice_\'.$i] ?? \'\') ?>">
            </div>
        </div>
    </div>
    <?php endfor; ?>
</div>');
    echo '</div>';
    echo '<button onclick="copyCode(\'form-code\')" style="background: var(--primary); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer;">📋 Copy Form Code</button>';
    
    echo '</div>';
}
?>