<?php

/**
 * Document Upload Page
 * EduBridge SA - University Application System
 * 
 * Allows applicants to upload required documents after application submission
 */

session_start();
require_once 'config.php';

// Resolve application link from session if query missing
if (!isset($_GET['app_id']) || !isset($_GET['token'])) {
    $sessionEmail = $_SESSION['email'] ?? $_SESSION['student_email'] ?? null;
    if ($sessionEmail) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM applications WHERE email = ? OR email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
            $stmt->execute([$sessionEmail, $sessionEmail]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['id'])) {
                $resolvedId = (int)$row['id'];
                $resolvedToken = md5($resolvedId . AUTH_SALT);
                header('Location: upload-documents.php?app_id=' . $resolvedId . '&token=' . $resolvedToken);
                exit;
            }
        } catch (PDOException $e) {
            error_log('upload-documents session fallback error: ' . $e->getMessage());
        }
    }
    $_SESSION['form_message'] = 'Invalid access. Please submit your application first.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

$applicationId = (int)$_GET['app_id'];
$token = $_GET['token'];

// Verify token
$expectedToken = md5($applicationId . AUTH_SALT);
if ($token !== $expectedToken) {
    $_SESSION['form_message'] = 'Invalid access token.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

// Get application details
try {
    $sql = "SELECT id, reference_number, email_address, application_status, created_at FROM applications WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['form_message'] = 'Application not found.';
        $_SESSION['form_message_type'] = 'error';
        header('Location: apply_improved.php');
        exit;
    }

    // Get uploaded documents
    $docSql = "SELECT id, application_id, doc_type, file_name, file_path, file_size, uploaded_at FROM documents WHERE application_id = ? ORDER BY uploaded_at DESC LIMIT 200";
    $docStmt = $pdo->prepare($docSql);
    $docStmt->execute([$applicationId]);
    $uploadedDocs = $docStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in upload_documents.php: " . $e->getMessage());
    $_SESSION['form_message'] = 'Database error occurred.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: apply_improved.php');
    exit;
}

// Required document types
$requiredDocs = [
    'id_document' => 'South African ID Document',
    'matric_certificate' => 'Matric Certificate / Senior Certificate',
    'academic_transcript' => 'Academic Transcript (if applicable)'
    // Proof of Residence is optional and excluded from required docs
];

// Check which documents are already uploaded
$uploadedDocTypes = [];
foreach ($uploadedDocs as $doc) {
    $uploadedDocTypes[] = $doc['doc_type'];
}

// Generate application reference
$applicationRef = 'EBS-' . str_pad($applicationId, 6, '0', STR_PAD_LEFT);

// Generate new CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Documents - EduBridge SA</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .upload-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .application-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }

        .document-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 15px;
            background: white;
        }

        .document-uploaded {
            background: #d4edda;
            border-color: #c3e6cb;
        }

        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input {
            padding: 8px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }

        .upload-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .upload-btn:hover {
            background: #218838;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-uploaded {
            background: #28a745;
            color: white;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }

        .complete-section {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-top: 30px;
        }

        .file-requirements {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .document-item {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .upload-form {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="upload-container">
        <!-- Application Info Header -->
        <div class="application-info">
            <h1>📄 Document Upload</h1>
            <h2>Application Reference: <?php echo htmlspecialchars($applicationRef); ?></h2>
            <p><strong>Applicant:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($application['status']); ?></p>
        </div>

        <!-- Display Messages -->
        <?php if (isset($_SESSION['form_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['form_message_type']; ?>">
                <?php
                echo htmlspecialchars($_SESSION['form_message']);
                unset($_SESSION['form_message'], $_SESSION['form_message_type']);
                ?>
            </div>
        <?php endif; ?>

        <!-- File Requirements -->
        <div class="file-requirements">
            <h3>📋 File Requirements</h3>
            <ul>
                <li><strong>File Types:</strong> PDF, JPG, JPEG, PNG only</li>
                <li><strong>File Size:</strong> Maximum 5MB per file</li>
                <li><strong>Quality:</strong> Ensure documents are clear and readable</li>
                <li><strong>Completeness:</strong> All required documents must be uploaded</li>
            </ul>
        </div>

        <!-- Progress Bar -->
        <?php
        $totalRequired = count($requiredDocs);
        $totalUploaded = count(array_intersect($uploadedDocTypes, array_keys($requiredDocs)));
        $progressPercentage = ($totalUploaded / $totalRequired) * 100;
        ?>
        <div class="document-section">
            <h3>Upload Progress</h3>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progressPercentage; ?>%"></div>
            </div>
            <p><?php echo $totalUploaded; ?> of <?php echo $totalRequired; ?> required documents uploaded (<?php echo round($progressPercentage); ?>%)</p>
        </div>

        <!-- Document Upload Section -->
        <div class="document-section">
            <h3>Required Documents</h3>

            <?php foreach ($requiredDocs as $docType => $docName): ?>
                <div class="document-item <?php echo in_array($docType, $uploadedDocTypes) ? 'document-uploaded' : ''; ?>">
                    <div>
                        <strong><?php echo htmlspecialchars($docName); ?></strong>
                        <?php if (in_array($docType, $uploadedDocTypes)): ?>
                            <span class="status-badge status-uploaded">✓ Uploaded</span>
                            <?php
                            // Find the uploaded document details
                            foreach ($uploadedDocs as $doc) {
                                if ($doc['doc_type'] === $docType) {
                                    echo '<br><small>Uploaded: ' . date('M j, Y g:i A', strtotime($doc['uploaded_at'])) . '</small>';
                                    break;
                                }
                            }
                            ?>
                        <?php else: ?>
                            <span class="status-badge status-pending">⏳ Pending</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!in_array($docType, $uploadedDocTypes)): ?>
                        <form class="upload-form" action="process_document_upload.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                            <input type="hidden" name="application_id" value="<?php echo $applicationId; ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars($docType); ?>">

                            <input type="file" name="document" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                            <button type="submit" class="upload-btn">Upload <?php echo htmlspecialchars($docName); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Uploaded Documents List -->
        <?php if (!empty($uploadedDocs)): ?>
            <div class="document-section">
                <h3>Uploaded Documents</h3>
                <?php foreach ($uploadedDocs as $doc): ?>
                    <div class="document-item document-uploaded">
                        <div>
                            <strong><?php echo htmlspecialchars($requiredDocs[$doc['doc_type']] ?? $doc['doc_type']); ?></strong><br>
                            <small>
                                Uploaded: <?php echo date('M j, Y g:i A', strtotime($doc['uploaded_at'])); ?><br>
                                File: <?php echo htmlspecialchars(basename($doc['file_path'])); ?>
                            </small>
                        </div>
                        <span class="status-badge status-uploaded">✓ Uploaded</span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Application Complete Section -->
        <?php if ($totalUploaded >= $totalRequired): ?>
            <div class="complete-section">
                <h2>🎉 Application Complete!</h2>
                <p>Congratulations! You have successfully uploaded all required documents.</p>
                <p>Your application status has been updated to <strong>"Submitted (with docs)"</strong>.</p>
                <p>We will review your application and contact you within 5-10 business days.</p>

                <div style="margin-top: 20px;">
                    <a href="apply_improved.php" class="btn btn-primary">Submit Another Application</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Contact Information -->
        <div class="document-section">
            <h3>Need Help?</h3>
            <p>If you're experiencing issues with document upload or have questions about the application process, please contact us:</p>
            <ul>
                <li><strong>Email:</strong> <?php echo ADMIN_EMAIL; ?></li>
                <li><strong>Phone:</strong> +27 11 123 4567</li>
                <li><strong>Office Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM</li>
            </ul>
        </div>
    </div>

    <script>
        // Auto-refresh page every 30 seconds to check for updates
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Show loading state when uploading
        document.querySelectorAll('.upload-form').forEach(form => {
            form.addEventListener('submit', function() {
                const button = this.querySelector('.upload-btn');
                button.textContent = 'Uploading...';
                button.disabled = true;
            });
        });
    </script>
</body>

</html>