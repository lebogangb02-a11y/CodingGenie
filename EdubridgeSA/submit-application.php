<?php
require_once 'session_config.php';
require_once 'config.php';

if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

$studentEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
$submitMessage = '';
$submitError = '';

// Generate CSRF token if needed
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Resolve latest application for this user
$application = null;
if ($studentEmail) {
    try {
        $stmt = $pdo->prepare('SELECT id, status, reference_number, updated_at FROM applications WHERE email = ? OR email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1');
        $stmt->execute([$studentEmail, $studentEmail]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('submit-application: failed to resolve application: ' . $e->getMessage());
        $submitError = 'Database error occurred.';
    }
}

if (!$application) {
    $submitError = 'No application found. Please start an application first.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Server-side CSRF enforcement (best-effort)
    if (function_exists('require_csrf')) { require_csrf(); }

    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $csrfToken) {
        $submitError = 'Invalid request token.';
    } else if ($application) {
        try {
            // Set to Submitted (without docs); document upload handler will upgrade later when complete
            $update = $pdo->prepare('UPDATE applications SET status = ? WHERE id = ?');
            $newStatus = 'Submitted (without docs)';
            $update->execute([$newStatus, (int)$application['id']]);

            // Update session-based tracker fields for dashboard/API
            $_SESSION['application_status'] = 'in_review';
            $_SESSION['current_application_step'] = max(4, (int)($_SESSION['current_application_step'] ?? 1));
            $_SESSION['application_progress'] = max(70, (int)($_SESSION['application_progress'] ?? 0));
            $_SESSION['application_last_updated'] = time();

            // CREATE ADMIN NOTIFICATION FOR NEW APPLICATION SUBMISSION
            if (function_exists('createApplicationNotification')) {
                // Fetch complete application data for notification
                $appStmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
                $appStmt->execute([(int)$application['id']]);
                $appData = $appStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($appData) {
                    createApplicationNotification($pdo, (int)$application['id'], [
                        'first_name' => $appData['full_name'] ?? $appData['first_name'] ?? '',
                        'last_name' => $appData['surname'] ?? $appData['last_name'] ?? '',
                        'program_choice_1' => $appData['program_choice_1'] ?? $appData['course_first_choice'] ?? 'Not specified'
                    ]);
                }
            }

            $submitMessage = 'Application submitted successfully.';
        } catch (PDOException $e) {
            error_log('submit-application: update failed: ' . $e->getMessage());
            $submitError = 'Could not submit application.';
        }
    }
}

$uploadUrl = '';
if ($application) {
    if (!empty($application['reference_number'])) {
        $uploadUrl = 'document_upload.php?ref=' . urlencode($application['reference_number']);
    } else if (isset($application['id'])) {
        // Fallback to legacy URL if reference is missing
        $uploadUrl = 'upload-documents.php?app_id=' . (int)$application['id'] . '&token=' . md5(((int)$application['id']) . AUTH_SALT);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Submit Application - EduBridgeSA</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .container { max-width: 720px; margin: 30px auto; padding: 20px; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; border-radius: 6px; padding: 10px 16px; text-decoration: none; }
        .btn-primary { background: #1a5fb4; color: #fff; }
        .btn-success { background: #2e7d32; color: #fff; }
        .btn-secondary { background: #374151; color: #fff; }
        .msg { margin-top: 12px; padding: 10px; border-radius: 6px; }
        .msg.error { background: #fee2e2; color: #b91c1c; }
        .msg.success { background: #ecfdf5; color: #065f46; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Submit Application</h1>
            <p>Finalize your application and move it into review. You can upload documents after submission.</p>

            <?php if ($submitError): ?>
                <div class="msg error"><?php echo htmlspecialchars($submitError); ?></div>
            <?php elseif ($submitMessage): ?>
                <div class="msg success"><?php echo htmlspecialchars($submitMessage); ?></div>
            <?php endif; ?>

            <?php if ($application): ?>
                <form method="post" style="margin-top: 10px;">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo htmlspecialchars($csrfToken); ?>" />
                    <button class="btn btn-primary" type="submit" name="submit_application">Submit Application</button>
                </form>

                <?php if ($uploadUrl): ?>
                    <p style="margin-top: 16px;">Next: upload your required documents.</p>
                    <a class="btn btn-success" href="<?php echo $uploadUrl; ?>">Go to Upload Documents</a>
                <?php endif; ?>
            <?php else: ?>
                <a class="btn btn-secondary" href="student-apply.php">Start Application</a>
            <?php endif; ?>

            <p style="margin-top: 16px;"><a href="application-access.php">Back to Dashboard</a></p>
        </div>
    </div>
</body>
</html>