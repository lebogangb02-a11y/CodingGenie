<?php

/**
 * Application Status Page
 * Allows students to check their application status using reference number
 */

require_once 'config_application.php';

$error = '';
$application = null;
$parent_guardian = null;
$university_choices = null;
$documents = [];
$status_history = [];
$reference_number = $_GET['ref'] ?? $_POST['reference_number'] ?? '';

// Handle reference number lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_status'])) {
    // Server-side CSRF enforcement (best-effort)
    if (function_exists('require_csrf')) { require_csrf(); }

    $reference_number = sanitizeInput($_POST['reference_number']);

    if (empty($reference_number)) {
        $error = 'Please enter your reference number.';
    }
}

// If reference number is provided, look up the application
if (!empty($reference_number)) {
    try {
        $pdo = getDBConnection();

        // OPTIMIZED: Get application details with all related data in 3 queries instead of 5
        // Query 1: Get application + parent/guardian + university choices with JOINs
        $stmt = $pdo->prepare("
            SELECT 
                a.id, a.reference_number, a.email_address, a.full_name, a.phone_number, 
                a.date_of_birth, a.gender, a.application_status, a.progress_percentage,
                a.created_at, a.updated_at,
                pg.id as pg_id, pg.full_name as pg_full_name, pg.email as pg_email, pg.phone as pg_phone,
                uc.university_1, uc.university_2, uc.university_3, uc.programme_choice_1, uc.programme_choice_2
            FROM applications a
            LEFT JOIN parent_guardian_details pg ON pg.application_id = a.id
            LEFT JOIN university_choices uc ON uc.application_id = a.id
            WHERE a.reference_number = ?
            LIMIT 1
        ");
        $stmt->execute([$reference_number]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Application not found. Please check your reference number.';
        } else {
            // Map joined data back to logical structure
            $application = [
                'id' => $row['id'],
                'reference_number' => $row['reference_number'],
                'email_address' => $row['email_address'],
                'full_name' => $row['full_name'],
                'phone_number' => $row['phone_number'],
                'date_of_birth' => $row['date_of_birth'],
                'gender' => $row['gender'],
                'application_status' => $row['application_status'],
                'progress_percentage' => $row['progress_percentage'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];

            $parent_guardian = !empty($row['pg_id']) ? [
                'id' => $row['pg_id'],
                'full_name' => $row['pg_full_name'],
                'email' => $row['pg_email'],
                'phone' => $row['pg_phone']
            ] : null;

            $university_choices = !empty($row['university_1']) ? [
                'university_1' => $row['university_1'],
                'university_2' => $row['university_2'],
                'university_3' => $row['university_3'],
                'programme_choice_1' => $row['programme_choice_1'],
                'programme_choice_2' => $row['programme_choice_2']
            ] : null;

            $application_id = $application['id'];

            // Query 2: Get documents and status history together (batch fetch)
            $stmt = $pdo->prepare("SELECT * FROM application_documents WHERE application_id = ? ORDER BY id DESC");
            $stmt->execute([$application_id]);
            $documents = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT * FROM application_status_history WHERE application_id = ? ORDER BY created_at DESC");
            $stmt->execute([$application_id]);
            $status_history = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Error looking up application: " . $e->getMessage());
        $error = 'Database error. Please try again later.';
    }
}

// Calculate completion percentage
$completion_percentage = 0;
if ($application) {
    $total_steps = 4;
    $completed_steps = 1; // Application submitted

    if ($parent_guardian && !empty($parent_guardian['full_name'])) {
        $completed_steps++;
    }

    if ($university_choices && !empty($university_choices['university_1'])) {
        $completed_steps++;
    }

    // Build uploaded document type set
    $uploaded_types = [];
    foreach ($documents as $doc) {
        if (!empty($doc['document_type'])) {
            $uploaded_types[] = $doc['document_type'];
        }
    }

    // Required (compulsory) documents for completion
    $required_document_types = ['certified_id', 'academic_results'];
    $required_uploaded = array_intersect($required_document_types, $uploaded_types);
    $has_all_required_docs = count($required_uploaded) === count($required_document_types);

    // Treat documents step as completed only when compulsory docs are uploaded
    if ($has_all_required_docs) {
        $completed_steps++;
    }

    $completion_percentage = ($completed_steps / $total_steps) * 100;

    // Override to 100% if all compulsory docs are uploaded, regardless of other optional steps
    if ($has_all_required_docs) {
        $completion_percentage = 100;
    }
}

// Get application status
function getApplicationStatus($application, $documents)
{
    if (!$application) return 'Not Found';

    if (!empty($documents)) {
        return 'Documents Uploaded';
    } elseif (!empty($application['documents_uploaded'])) {
        return 'Documents Uploaded';
    } else {
        return 'Pending Documents';
    }
}

$current_status = getApplicationStatus($application, $documents);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Status - Student Application Portal</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
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
            border-left: 4px solid #8e44ad;
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
            border-color: #8e44ad;
            box-shadow: 0 0 0 3px rgba(142, 68, 173, 0.1);
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
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .status-overview {
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #27ae60;
        }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .status-submitted {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-documents {
            background: #cce5ff;
            color: #004085;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            height: 100%;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card .detail-item {
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-card .detail-item:last-child {
            border-bottom: none;
        }

        .info-card .detail-item strong {
            color: #2c3e50;
            display: inline-block;
            min-width: 120px;
        }

        .documents-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }

        .documents-section h3 {
            color: #856404;
            margin-bottom: 15px;
        }

        .document-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .document-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ffeaa7;
        }

        .document-item h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1em;
        }

        .document-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .status-uploaded {
            background: #d4edda;
            color: #155724;
        }

        .status-missing {
            background: #f8d7da;
            color: #721c24;
        }

        .document-meta {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-top: 8px;
        }

        .status-history {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #2196f3;
        }

        .status-history h3 {
            color: #1976d2;
            margin-bottom: 15px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #2196f3;
        }

        .timeline-item {
            position: relative;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #bbdefb;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #2196f3;
            border: 3px solid white;
        }

        .timeline-item h4 {
            color: #1976d2;
            margin-bottom: 5px;
            font-size: 1em;
        }

        .timeline-item .timestamp {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 8px;
        }

        .timeline-item p {
            color: #2c3e50;
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
            flex-wrap: wrap;
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

            .info-grid {
                grid-template-columns: 1fr;
            }

            .document-grid {
                grid-template-columns: 1fr;
            }

            .status-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>📊 Application Status</h1>
            <p>Track your university application progress and manage your documents</p>
        </div>

        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$application): ?>
                <div class="lookup-section">
                    <h3>🔍 Check Your Application Status</h3>
                    <p>Enter your reference number to view your application status and progress.</p>

                    <form method="POST" action="application_status.php">
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
                        <button type="submit" name="check_status" class="btn btn-primary">
                            🔍 Check Status
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="status-overview">
                    <div class="status-header">
                        <div>
                            <h2>Application Status: <?php echo htmlspecialchars($application['reference_number']); ?></h2>
                            <p>Submitted on <?php
                                            $submittedAt = $application['submitted_at'] ?? null;
                                            $createdAt = $application['created_at'] ?? null;
                                            $submittedTs = ($submittedAt && strtotime($submittedAt)) ? strtotime($submittedAt) : null;
                                            $createdTs = ($createdAt && strtotime($createdAt)) ? strtotime($createdAt) : null;
                                            $displayTs = $submittedTs ?: $createdTs;
                                            echo $displayTs ? date('d M Y, H:i', $displayTs) : 'Unknown';
                                            ?></p>
                        </div>
                        <div class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $current_status)); ?>">
                            <?php echo htmlspecialchars($current_status); ?>
                        </div>
                    </div>

                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%">
                            <?php echo round($completion_percentage); ?>% Complete
                        </div>
                    </div>

                    <?php
                    // Next Step messaging aligned to compulsory documents
                    $nextStepComplete = isset($has_all_required_docs) && $has_all_required_docs;
                    ?>
                    <p style="color: #2c3e50; margin-top: 10px;">
                        <strong>Next Step:</strong>
                        <?php if (!$nextStepComplete): ?>
                            Upload your required documents (Certified ID and Academic Results) to complete your application.
                        <?php else: ?>
                            Your application is complete. Apply directly to your chosen universities.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="info-grid">
                    <div class="info-card">
                        <h3>👤 Personal Information</h3>
                        <div class="detail-item">
                            <strong>Full Name:</strong> <?php echo htmlspecialchars($application['full_name'] . ' ' . $application['surname']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>ID Number:</strong> <?php echo htmlspecialchars($application['id_number']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($application['email_address']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Phone:</strong> <?php echo htmlspecialchars($application['cellphone_number']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Date of Birth:</strong> <?php echo (!empty($application['date_of_birth']) && strtotime($application['date_of_birth'])) ? date('d M Y', strtotime($application['date_of_birth'])) : 'Not provided'; ?>
                        </div>
                    </div>

                    <?php if ($parent_guardian && !empty($parent_guardian['full_name'])): ?>
                        <div class="info-card">
                            <h3>👨‍👩‍👧‍👦 Parent/Guardian Details</h3>
                            <div class="detail-item">
                                <strong>Name:</strong> <?php echo htmlspecialchars($parent_guardian['full_name'] . ' ' . $parent_guardian['surname']); ?>
                            </div>
                            <div class="detail-item">
                                <strong>ID Number:</strong> <?php echo htmlspecialchars($parent_guardian['id_number'] ?? 'Not provided'); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Contact:</strong> <?php echo htmlspecialchars($parent_guardian['contact_number'] ?? 'Not provided'); ?>
                            </div>
                            <div class="detail-item">
                                <strong>Email:</strong> <?php echo htmlspecialchars($parent_guardian['email_address'] ?? 'Not provided'); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($university_choices): ?>
                        <div class="info-card">
                            <h3>🎓 University Choices</h3>
                            <?php if (!empty($university_choices['university_1'])): ?>
                                <div class="detail-item">
                                    <strong>First Choice:</strong> <?php echo htmlspecialchars($university_choices['university_1']); ?>
                                    <?php if (!empty($university_choices['course_first_choice'])): ?>
                                        <br><em>Course: <?php echo htmlspecialchars($university_choices['course_first_choice']); ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($university_choices['university_2'])): ?>
                                <div class="detail-item">
                                    <strong>Second Choice:</strong> <?php echo htmlspecialchars($university_choices['university_2']); ?>
                                    <?php if (!empty($university_choices['course_second_choice'])): ?>
                                        <br><em>Course: <?php echo htmlspecialchars($university_choices['course_second_choice']); ?></em>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($university_choices['university_3'])): ?>
                                <div class="detail-item">
                                    <strong>Third Choice:</strong> <?php echo htmlspecialchars($university_choices['university_3']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="documents-section">
                    <h3>📄 Document Status</h3>
                    <p>Track which documents you've uploaded. Some documents are optional.</p>

                    <div class="document-grid">
                        <?php
                        $required_documents = [
                            'certified_id' => 'Certified ID Copy',
                            'proof_of_residence' => 'Proof of Residential Address (optional)',
                            'parent_guardian_id' => 'Parent/Guardian Certified ID Copy (optional)',
                            'academic_results' => 'Latest Academic Results'
                        ];
                        $optional_types = ['proof_of_residence', 'parent_guardian_id'];

                        $uploaded_docs = [];
                        foreach ($documents as $doc) {
                            $uploaded_docs[$doc['document_type']] = $doc;
                        }

                        foreach ($required_documents as $type => $name):
                        ?>
                            <div class="document-item">
                                <h4><?php echo htmlspecialchars($name); ?></h4>
                                <?php if (isset($uploaded_docs[$type])): ?>
                                    <div class="document-status status-uploaded">✅ Uploaded</div>
                                    <div class="document-meta">
                                        File: <?php echo htmlspecialchars($uploaded_docs[$type]['original_filename']); ?><br>
                                        Size: <?php echo number_format($uploaded_docs[$type]['file_size'] / 1024, 1); ?> KB<br>
                                        Uploaded: <?php
                                                    $doc = $uploaded_docs[$type];
                                                    $rawTs = $doc['uploaded_at'] ?? ($doc['upload_date'] ?? ($doc['created_at'] ?? null));
                                                    $ts = ($rawTs && strtotime($rawTs)) ? strtotime($rawTs) : null;
                                                    echo $ts ? date('d M Y, H:i', $ts) : 'Unknown';
                                                    ?>
                                    </div>
                                <?php else: ?>
                                    <div class="document-status status-missing">❌ Not Uploaded</div>
                                    <div class="document-meta">
                                        <?php if (in_array($type, $optional_types)): ?>
                                            This document is optional. You may upload it later.
                                        <?php else: ?>
                                            This document is required to finalize your application.
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($status_history)): ?>
                    <div class="status-history">
                        <h3>📋 Application History</h3>
                        <div class="timeline">
                            <?php foreach ($status_history as $history): ?>
                                <div class="timeline-item">
                                    <h4>
                                        <?php
                                        // Prefer new_status, fall back to status or previous_status; provide a safe default
                                        $rawStatus = $history['new_status'] ?? ($history['status'] ?? ($history['previous_status'] ?? null));
                                        $label = $rawStatus ? ucwords(str_replace('_', ' ', $rawStatus)) : 'Status Update';
                                        echo htmlspecialchars($label);
                                        ?>
                                    </h4>
                                    <div class="timestamp">
                                        <?php
                                        $rawTs = $history['created_at'] ?? null;
                                        $ts = ($rawTs && strtotime($rawTs)) ? strtotime($rawTs) : null;
                                        echo $ts ? date('d M Y, H:i', $ts) : 'Unknown';
                                        ?>
                                    </div>
                                    <?php if (!empty($history['notes'])): ?>
                                        <p><?php echo htmlspecialchars($history['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="document_upload.php?ref=<?php echo urlencode($application['reference_number']); ?>" class="btn btn-primary">
                        📄 Upload Documents
                    </a>
                    <a href="application_success.php?ref=<?php echo urlencode($application['reference_number']); ?>" class="btn btn-success">
                        🎉 View Success Page
                    </a>
                    <a href="student-application.php" class="btn btn-info">
                        📝 New Application
                    </a>
                </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ecf0f1;">
                <p style="color: #7f8c8d;">
                    Need help? Contact our support team at <strong><?php echo htmlspecialchars(ADMIN_EMAIL); ?></strong> or call <strong>078 323 6239</strong>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh status every 30 seconds if on status page
        <?php if ($application): ?>
            setInterval(function() {
                // Only refresh if user hasn't interacted recently
                if (document.hidden === false) {
                    const lastActivity = localStorage.getItem('lastActivity');
                    const now = Date.now();
                    if (!lastActivity || (now - parseInt(lastActivity)) > 30000) {
                        location.reload();
                    }
                }
            }, 30000);

            // Track user activity
            document.addEventListener('click', function() {
                localStorage.setItem('lastActivity', Date.now());
            });

            document.addEventListener('scroll', function() {
                localStorage.setItem('lastActivity', Date.now());
            });
        <?php endif; ?>
    </script>
</body>

</html>