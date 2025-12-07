<?php

/**
 * Document Upload Page
 * Allows students to upload documents using their reference number
<?php
/**
 * Cleaned Document Upload Page
 * - Validates CSRF
 * - Validates MIME types using finfo
 * - Prevents path traversal and uses randomized filenames
 * - Inserts document records using prepared statements
 */

require_once 'config_application.php';

// Map upload fields to configured directories
function getUploadDirFor($field)
{
    switch ($field) {
        case 'certified_id':
            return defined('UPLOAD_ID_DIR') ? UPLOAD_ID_DIR : (defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'id_documents' . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'id_documents' . DIRECTORY_SEPARATOR);
        case 'proof_of_residence':
            return defined('UPLOAD_RESIDENCE_DIR') ? UPLOAD_RESIDENCE_DIR : (defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'proof_of_residence' . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'proof_of_residence' . DIRECTORY_SEPARATOR);
        case 'parent_guardian_id':
            return defined('UPLOAD_PARENT_ID_DIR') ? UPLOAD_PARENT_ID_DIR : (defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'parent_guardian_id' . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'parent_guardian_id' . DIRECTORY_SEPARATOR);
        case 'academic_results':
            return defined('UPLOAD_ACADEMIC_DIR') ? UPLOAD_ACADEMIC_DIR : (defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'academic_results' . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR . 'academic_results' . DIRECTORY_SEPARATOR);
        default:
            return defined('UPLOAD_BASE_DIR') ? UPLOAD_BASE_DIR : (defined('UPLOAD_DIR') ? rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR : __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'applications' . DIRECTORY_SEPARATOR);
    }
}

$message = '';
$error = '';
$application = null;
$reference_number = $_GET['ref'] ?? $_POST['reference_number'] ?? '';

// Handle reference number lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lookup_application'])) {
    $reference_number = sanitizeInput($_POST['reference_number']);

    if (empty($reference_number)) {
        $error = 'Please enter your reference number.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE reference_number = ?");
            $stmt->execute([$reference_number]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                $error = 'Application not found. Please check your reference number.';
            }
        } catch (Exception $e) {
            error_log("Error looking up application: " . $e->getMessage());
            $error = 'Database error. Please try again later.';
        }
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents'])) {
    // CSRF validation
    if (!isset($_POST[CSRF_TOKEN_NAME]) || ($_POST[CSRF_TOKEN_NAME] ?? '') !== ($_SESSION[CSRF_TOKEN_NAME] ?? '')) {
        http_response_code(403);
        $error = 'CSRF token validation failed.';
    } else {
        $reference_number = sanitizeInput($_POST['reference_number']);

        try {
            $pdo = getDBConnection();
            // Fetch full application details
            $stmt = $pdo->prepare("SELECT * FROM applications WHERE reference_number = ?");
            $stmt->execute([$reference_number]);
            $applicationRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$applicationRow) {
                $error = 'Application not found.';
            } else {
                $application_id = $applicationRow['id'];
                $application = $applicationRow;
                $uploaded_files = [];
                $upload_errors = [];

                // Document types mapping
                $document_types = [
                    'certified_id' => 'Certified ID Copy',
                    'proof_of_residence' => 'Proof of Residential Address',
                    'parent_guardian_id' => 'Parent/Guardian Certified ID Copy',
                    'academic_results' => 'Latest Academic Results'
                ];

                // Process each upload field
                foreach ($document_types as $field_name => $document_name) {
                    if (empty($_FILES[$field_name]) || ($_FILES[$field_name]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $file = $_FILES[$field_name];

                    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                        $code = $file['error'];
                        $messages = [
                            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
                            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
                            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                            UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing',
                            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        ];
                        $upload_errors[] = $document_name . ': ' . ($messages[$code] ?? 'Unknown upload error');
                        continue;
                    }

                    // Size check
                    $maxSize = defined('MAX_FILE_SIZE') ? MAX_FILE_SIZE : (5 * 1024 * 1024);
                    if ($file['size'] > $maxSize) {
                        $upload_errors[] = "$document_name: File too large (max " . ($maxSize / 1024 / 1024) . "MB)";
                        continue;
                    }

                    // MIME validation
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    $extension_to_mime = [
                        'pdf' => 'application/pdf',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'png' => 'image/png'
                    ];

                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($file_extension, ALLOWED_FILE_TYPES) || !isset($extension_to_mime[$file_extension])) {
                        $upload_errors[] = "$document_name: Invalid file extension.";
                        continue;
                    }
                    if ($mime !== $extension_to_mime[$file_extension]) {
                        $upload_errors[] = "$document_name: MIME type does not match file extension.";
                        continue;
                    }

                    // Sanitize reference
                    $reference_safe = preg_replace('/[^A-Za-z0-9_-]/', '', $reference_number);
                    if (empty($reference_safe)) {
                        $upload_errors[] = "$document_name: Invalid reference number.";
                        continue;
                    }

                    // Prepare target path
                    $randomName = bin2hex(random_bytes(16)) . '.' . $file_extension;
                    $targetDir = getUploadDirFor($field_name);
                    if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
                        $upload_errors[] = "$document_name: Failed to create upload directory.";
                        continue;
                    }
                    $realTarget = realpath($targetDir);
                    $uploadBase = realpath(UPLOAD_DIR) ?: realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
                    if ($realTarget === false || $uploadBase === false || strpos($realTarget, $uploadBase) !== 0) {
                        $upload_errors[] = "$document_name: Invalid upload directory configuration.";
                        continue;
                    }

                    $upload_path = $realTarget . DIRECTORY_SEPARATOR . $randomName;
                    if (file_exists($upload_path)) {
                        $upload_errors[] = "$document_name: A file with this name already exists.";
                        continue;
                    }

                    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $upload_errors[] = "$document_name: Failed to move uploaded file.";
                        continue;
                    }

                    // Insert record
                    try {
                        $stmt = $pdo->prepare("INSERT INTO documents (application_id, doc_type, file_name, file_path, file_size, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$application_id, $field_name, $file['name'], $upload_path, $file['size']]);
                        $uploaded_files[] = $document_name;
                    } catch (Exception $dbEx) {
                        @unlink($upload_path);
                        $upload_errors[] = "$document_name: Failed to record upload in database.";
                        continue;
                    }
                }

                // Update application status if documents were uploaded
                if (!empty($uploaded_files)) {
                    $stmt = $pdo->prepare("UPDATE applications SET status = 'Submitted (with docs)', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$application_id]);

                    // Optional history logging
                    try {
                        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'application_status_history'");
                        $checkStmt->execute();
                        $historyExists = ($checkStmt->fetchColumn() > 0);
                        if ($historyExists) {
                            $stmt = $pdo->prepare("INSERT INTO application_status_history (application_id, status, notes, created_at) VALUES (?, 'documents_uploaded', ?, NOW())");
                            $stmt->execute([$application_id, 'Documents uploaded: ' . implode(', ', $uploaded_files)]);
                        }
                    } catch (Exception $logEx) {
                        error_log("Optional status history logging failed: " . $logEx->getMessage());
                    }
                }

                if (!empty($uploaded_files) && empty($upload_errors)) {
                    $message = 'Documents uploaded successfully: ' . implode(', ', $uploaded_files);
                } elseif (!empty($uploaded_files) && !empty($upload_errors)) {
                    $message = 'Some documents uploaded successfully: ' . implode(', ', $uploaded_files);
                    $error = 'Upload errors: ' . implode('; ', $upload_errors);
                } elseif (empty($uploaded_files) && !empty($upload_errors)) {
                    $error = 'Upload errors: ' . implode('; ', $upload_errors);
                } else {
                    $error = 'No files were selected for upload.';
                }
            }
        } catch (Exception $e) {
            error_log("Error uploading documents: " . $e->getMessage());
            $error = 'Database error. Please try again later.';
        }
    }
}

// If reference number is provided, look up the application
if (!empty($reference_number) && !$application) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE reference_number = ?");
        $stmt->execute([$reference_number]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error looking up application: " . $e->getMessage());
    }
}

// Get existing documents if application is found
$existing_documents = [];
if ($application) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$application['id']]);
        $existing_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching existing documents: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Upload - Student Application Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .lookup-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .application-info {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #27ae60;
        }

        .application-info h3 {
            color: #27ae60;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #d4edda;
        }

        .info-item strong {
            color: #2c3e50;
        }

        .upload-section {
            background: #fff;
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-section:hover {
            border-color: #2980b9;
            background: #f8f9fa;
        }

        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .upload-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .upload-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .upload-item p {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 10px 15px;
            background: #3498db;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            transition: background 0.3s ease;
        }

        .file-input-label:hover {
            background: #2980b9;
        }

        .file-selected {
            margin-top: 10px;
            padding: 8px;
            background: #d4edda;
            border-radius: 5px;
            font-size: 0.9em;
            color: #155724;
        }

        .existing-documents {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .existing-documents h3 {
            color: #856404;
            margin-bottom: 15px;
        }

        .document-list {
            list-style: none;
        }

        .document-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #ffeaa7;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .document-info {
            flex: 1;
        }

        .document-info strong {
            color: #2c3e50;
        }

        .document-meta {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 5px;
        }

        .requirements {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }

        .requirements h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .requirements ul {
            color: #2c3e50;
            line-height: 1.6;
        }

        .requirements li {
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 10px;
            }

            .header {
                padding: 20px;
            }

            .header h1 {
                font-size: 2em;
            }

            .content {
                padding: 20px;
            }

            .upload-grid {
                grid-template-columns: 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📄 Document Upload</h1>
            <p>Upload your supporting documents for your university application</p>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$application): ?>
                <div class="lookup-section">
                    <h3>🔍 Find Your Application</h3>
                    <p>Enter your reference number to upload documents for your application.</p>

                    <form method="POST" action="document_upload.php">
                        <div class="form-group">
                            <label for="reference_number">Reference Number *</label>
                            <input type="text"
                                id="reference_number"
                                name="reference_number"
                                class="form-control"
                                value="<?php echo htmlspecialchars($reference_number); ?>"
                                placeholder="Enter your reference number (e.g., APP-2024-XXXXXX)"
                                required>
                        </div>
                        <button type="submit" name="lookup_application" class="btn btn-primary">
                            🔍 Find Application
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="application-info">
                    <h3>✅ Application Found</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Reference:</strong><br>
                            <?php echo htmlspecialchars($application['reference_number']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Applicant:</strong><br>
                            <?php echo htmlspecialchars($application['full_name'] . ' ' . $application['surname']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong><br>
                            <?php echo htmlspecialchars($application['email_address']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Submitted:</strong><br>
                            <?php
                            $submittedDisplay = (!empty($application['submitted_at']) && $application['submitted_at'] !== '0000-00-00 00:00:00')
                                ? date('d M Y', strtotime($application['submitted_at']))
                                : 'N/A';
                            echo htmlspecialchars($submittedDisplay);
                            ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($existing_documents)): ?>
                    <div class="existing-documents">
                        <h3>📋 Previously Uploaded Documents</h3>
                        <ul class="document-list">
                            <?php foreach ($existing_documents as $doc): ?>
                                <li class="document-item">
                                    <div class="document-info">
                                        <strong><?php echo htmlspecialchars($doc['file_name']); ?></strong>
                                        <div class="document-meta">
                                            Type: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $doc['doc_type']))); ?> |
                                            Size: <?php echo number_format($doc['file_size'] / 1024, 1); ?> KB |
                                            Uploaded: <?php echo date('d M Y, H:i', strtotime($doc['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p><em>You can upload new versions of these documents below. New uploads will replace existing files.</em></p>
                    </div>
                <?php endif; ?>

                <div class="requirements">
                    <h3>📋 Document Requirements</h3>
                    <ul>
                        <li><strong>File Types:</strong> PDF, JPG, PNG, DOC, DOCX</li>
                        <li><strong>Maximum Size:</strong> 5MB per file</li>
                        <li><strong>Quality:</strong> Documents must be clear and readable</li>
                        <li><strong>Certification:</strong> ID copies must be certified by a commissioner of oaths</li>
                        <li><strong>Currency:</strong> Proof of residence must be recent (within 3 months)</li>
                    </ul>
                </div>

                <form method="POST" action="document_upload.php" enctype="multipart/form-data">
                    <input type="hidden" name="reference_number" value="<?php echo htmlspecialchars($application['reference_number']); ?>">

                    <div class="upload-section">
                        <h3>📤 Upload Your Documents</h3>
                        <p>Select the documents you want to upload. All documents are optional - you can upload them anytime.</p>

                        <div class="upload-grid">
                            <div class="upload-item">
                                <h4>🆔 Certified ID Copy</h4>
                                <p>A certified copy of your South African ID document or passport</p>
                                <div class="file-input-wrapper">
                                    <input type="file" id="certified_id" name="certified_id" class="file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <label for="certified_id" class="file-input-label">Choose File</label>
                                </div>
                                <div id="certified_id_selected" class="file-selected" style="display: none;"></div>
                            </div>

                            <div class="upload-item">
                                <h4>🏠 Proof of Residential Address</h4>
                                <p>Recent utility bill, bank statement, or municipal account (within 3 months)</p>
                                <div class="file-input-wrapper">
                                    <input type="file" id="proof_of_residence" name="proof_of_residence" class="file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <label for="proof_of_residence" class="file-input-label">Choose File</label>
                                </div>
                                <div id="proof_of_residence_selected" class="file-selected" style="display: none;"></div>
                            </div>

                            <div class="upload-item">
                                <h4>👨‍👩‍👧‍👦 Parent/Guardian Certified ID Copy</h4>
                                <p>A certified copy of your parent or guardian's ID document</p>
                                <div class="file-input-wrapper">
                                    <input type="file" id="parent_guardian_id" name="parent_guardian_id" class="file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <label for="parent_guardian_id" class="file-input-label">Choose File</label>
                                </div>
                                <div id="parent_guardian_id_selected" class="file-selected" style="display: none;"></div>
                            </div>

                            <div class="upload-item">
                                <h4>📚 Latest Academic Results</h4>
                                <p>Your most recent academic results or Grade 11 results</p>
                                <div class="file-input-wrapper">
                                    <input type="file" id="academic_results" name="academic_results" class="file-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <label for="academic_results" class="file-input-label">Choose File</label>
                                </div>
                                <div id="academic_results_selected" class="file-selected" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin: 30px 0;">
                        <button type="submit" name="upload_documents" class="btn btn-success">
                            📤 Upload Documents
                        </button>
                        <a href="application_status.php?ref=<?php echo urlencode($application['reference_number']); ?>" class="btn btn-primary" style="margin-left: 15px;">
                            📊 Check Status
                        </a>
                    </div>
                </form>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1;">
                <p style="color: #7f8c8d;">
                    <a href="student_application.php" style="color: #3498db; text-decoration: none;">← Back to Application Form</a> |
                    <a href="application_success.php?ref=<?php echo urlencode($reference_number); ?>" style="color: #3498db; text-decoration: none;">View Success Page</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // File input change handlers
        document.querySelectorAll('.file-input').forEach(function(input) {
            input.addEventListener('change', function() {
                const selectedDiv = document.getElementById(this.id + '_selected');
                const label = document.querySelector('label[for="' + this.id + '"]');

                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    selectedDiv.innerHTML = `✅ Selected: ${file.name} (${fileSize} MB)`;
                    selectedDiv.style.display = 'block';
                    label.style.background = '#27ae60';
                    label.textContent = 'File Selected';
                } else {
                    selectedDiv.style.display = 'none';
                    label.style.background = '#3498db';
                    label.textContent = 'Choose File';
                }
            });
        });

        // Form validation
        document.querySelector('form[enctype="multipart/form-data"]')?.addEventListener('submit', function(e) {
            const fileInputs = this.querySelectorAll('.file-input');
            let hasFiles = false;

            fileInputs.forEach(function(input) {
                if (input.files.length > 0) {
                    hasFiles = true;
                }
            });

            if (!hasFiles) {
                e.preventDefault();
                alert('Please select at least one document to upload.');
                return false;
            }

            // Check file sizes
            let oversizedFiles = [];
            fileInputs.forEach(function(input) {
                if (input.files.length > 0) {
                    const file = input.files[0];
                    if (file.size > 5 * 1024 * 1024) { // 5MB
                        oversizedFiles.push(file.name);
                    }
                }
            });

            if (oversizedFiles.length > 0) {
                e.preventDefault();
                alert('The following files are too large (max 5MB):\n' + oversizedFiles.join('\n'));
                return false;
            }
        });
    </script>
</body>

</html>
</script>
</body>

</html>