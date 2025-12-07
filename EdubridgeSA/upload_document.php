<?php
/**
 * Document Upload System
 * EduBridge SA - Secure document upload for student applications
 */

require_once 'session_config.php';
require_once 'config.php';
require_once __DIR__ . '/includes/security_helpers.php';
require_once __DIR__ . '/includes/upload_helper.php';

// Check if user is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Get student data
$student_id = $_SESSION['student_id'];
$application_status = $_SESSION['application_status'];

// Check if uploads are allowed for current status
if (!in_array($application_status, ['draft', 'submitted', 'documents_pending'])) {
    $_SESSION['form_message'] = 'Document uploads are not allowed for your current application status.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-dashboard.php');
    exit();
}

// Get document type from URL
$document_type = $_GET['type'] ?? '';
$allowed_types = ['certified_id', 'proof_of_residence', 'parent_guardian_id', 'academic_results'];

if (!in_array($document_type, $allowed_types)) {
    $_SESSION['form_message'] = 'Invalid document type specified.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-dashboard.php');
    exit();
}

// Document type labels
function getDocumentLabel($type) {
    switch ($type) {
        case 'certified_id': return 'Certified ID Copy';
        case 'proof_of_residence': return 'Proof of Residence';
        case 'parent_guardian_id': return 'Parent/Guardian ID';
        case 'academic_results': return 'Academic Results';
        default: return ucfirst(str_replace('_', ' ', $type));
    }
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $file = $_FILES['document'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }
        
        // Check file type
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.');
        }
        
        // Validate file content (basic MIME type check)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/jpg', 
            'image/png'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            throw new Exception('Invalid file content. File appears to be corrupted or not a valid document/image.');
        }
        
        // Use centralized upload helper to store file
        $res = store_uploaded_file($file, 'documents/' . $student_id, ['application/pdf','image/jpeg','image/png'], MAX_FILE_SIZE);
        if (!$res['success']) {
            throw new Exception('Failed to save uploaded file: ' . $res['error']);
        }
        $file_path = $res['path'];
        
        // Check if document already exists
        $stmt = $pdo->prepare("SELECT id FROM application_documents WHERE application_id = ? AND document_type = ?");
        $stmt->execute([$student_id, $document_type]);
        $existing_doc = $stmt->fetch();
        
        if ($existing_doc) {
            // Update existing document
            $stmt = $pdo->prepare("
                UPDATE application_documents 
                SET file_path = ?, original_filename = ?, upload_status = 'uploaded', upload_date = NOW(), updated_at = NOW()
                WHERE application_id = ? AND document_type = ?
            ");
            $stmt->execute([$file_path, $file['name'], $student_id, $document_type]);
        } else {
            // Insert new document
            $stmt = $pdo->prepare("
                INSERT INTO application_documents (application_id, document_type, file_path, original_filename, upload_status, upload_date, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'uploaded', NOW(), NOW(), NOW())
            ");
            $stmt->execute([$student_id, $document_type, $file_path, $file['name']]);
        }
        
        // Update application status if needed
        if ($application_status === 'draft') {
            $stmt = $pdo->prepare("UPDATE applications SET status = 'documents_pending', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$student_id]);
            $_SESSION['application_status'] = 'documents_pending';
            
            // Add to status history
            $stmt = $pdo->prepare("
                INSERT INTO application_status_history (application_id, previous_status, new_status, notes, created_at)
                VALUES (?, 'draft', 'documents_pending', 'Document uploaded: " . getDocumentLabel($document_type) . "', NOW())
            ");
            $stmt->execute([$student_id]);
        }
        
        $_SESSION['form_message'] = getDocumentLabel($document_type) . ' uploaded successfully!';
        $_SESSION['form_message_type'] = 'success';
        header('Location: student-dashboard.php');
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Document upload error: " . $error_message);
    }
}

// Get existing document info
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ? AND document_type = ?");
    $stmt->execute([$student_id, $document_type]);
    $existing_document = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --royal-blue: #1e3a8a;
            --royal-blue-light: #3b82f6;
            --emerald-green: #059669;
            --emerald-green-light: #10b981;
            --gold: #f59e0b;
            --gold-light: #fbbf24;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --red-500: #ef4444;
            --green-500: #22c55e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            color: var(--royal-blue);
            font-weight: 700;
            font-size: 1.5rem;
            text-decoration: none;
        }
        .logo img {
            height: 32px;
            width: auto;
            margin-right: 0.5rem;
        }

        .back-btn {
            background: var(--gray-500);
            color: var(--white);
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }

        /* Main Container */
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .upload-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .upload-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .upload-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .upload-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Current Document Info */
        .current-document {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--royal-blue);
        }

        .current-document h3 {
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .document-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--gray-700);
        }

        .info-value {
            color: var(--gray-600);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge.uploaded {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.verified {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Upload Form */
        .upload-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-input-display {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            border: 2px dashed var(--gray-300);
            border-radius: 10px;
            background: var(--gray-50);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-input-display:hover {
            border-color: var(--royal-blue);
            background: var(--white);
        }

        .file-input-display.has-file {
            border-color: var(--emerald-green);
            background: #f0fdf4;
        }

        .file-input-content {
            text-align: center;
        }

        .file-input-icon {
            font-size: 3rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .file-input-display:hover .file-input-icon {
            color: var(--royal-blue);
        }

        .file-input-display.has-file .file-input-icon {
            color: var(--emerald-green);
        }

        .file-input-text {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .file-input-subtext {
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .file-name {
            font-weight: 500;
            color: var(--emerald-green);
            margin-top: 0.5rem;
        }

        /* Requirements */
        .requirements {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .requirements h4 {
            color: #92400e;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirements ul {
            list-style: none;
            padding-left: 0;
        }

        .requirements li {
            color: #92400e;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirements li::before {
            content: '•';
            color: #f59e0b;
            font-weight: bold;
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--royal-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--royal-blue-light);
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            background: var(--gray-400);
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: var(--gray-500);
            color: var(--white);
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        /* Error Message */
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .nav-container {
                padding: 1rem;
            }

            .upload-card {
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
            <a href="student-dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="upload-card">
            <div class="upload-header">
                <h1 class="upload-title">Upload Document</h1>
                <p class="upload-subtitle"><?php echo getDocumentLabel($document_type); ?></p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($existing_document): ?>
            <div class="current-document">
                <h3><i class="fas fa-file-alt"></i> Current Document</h3>
                <div class="document-info">
                    <div class="info-item">
                        <span class="info-label">File Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($existing_document['original_filename']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Upload Date:</span>
                        <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($existing_document['upload_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge <?php echo $existing_document['upload_status']; ?>">
                                <?php echo ucfirst($existing_document['upload_status']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="requirements">
                <h4><i class="fas fa-info-circle"></i> Document Requirements</h4>
                <ul>
                    <li>File must be in PDF, JPG, JPEG, or PNG format</li>
                    <li>Maximum file size: 5MB</li>
                    <li>Document must be clear and readable</li>
                    <li>For ID documents: ensure all details are visible</li>
                    <li>For academic results: official transcripts preferred</li>
                </ul>
            </div>

            <form method="POST" action="upload_document.php" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                <div class="form-group">
                    <label for="document" class="form-label">
                        <?php echo $existing_document ? 'Replace Document' : 'Select Document'; ?>
                    </label>
                    <div class="file-input-wrapper">
                        <input type="file" id="document" name="document" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                        <div class="file-input-display" id="fileDisplay">
                            <div class="file-input-content">
                                <div class="file-input-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-input-text">Click to select a file or drag and drop</div>
                                <div class="file-input-subtext">PDF, JPG, JPEG, PNG (Max 5MB)</div>
                                <div class="file-name" id="fileName" style="display: none;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="student-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary" id="uploadBtn" disabled>
                        <i class="fas fa-upload"></i> 
                        <?php echo $existing_document ? 'Replace Document' : 'Upload Document'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const fileInput = document.getElementById('document');
        const fileDisplay = document.getElementById('fileDisplay');
        const fileName = document.getElementById('fileName');
        const uploadBtn = document.getElementById('uploadBtn');

        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum size is 5MB.');
                    fileInput.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Invalid file type. Only PDF, JPG, JPEG, and PNG files are allowed.');
                    fileInput.value = '';
                    return;
                }
                
                // Update display
                fileDisplay.classList.add('has-file');
                fileName.textContent = file.name;
                fileName.style.display = 'block';
                uploadBtn.disabled = false;
            } else {
                fileDisplay.classList.remove('has-file');
                fileName.style.display = 'none';
                uploadBtn.disabled = true;
            }
        });

        // Drag and drop functionality
        fileDisplay.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--royal-blue)';
        });

        fileDisplay.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--gray-300)';
        });

        fileDisplay.addEventListener('drop', function(e) {
            e.preventDefault();
            fileDisplay.style.borderColor = 'var(--gray-300)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });

        // Form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        });
    </script>
</body>
</html>