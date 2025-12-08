<?php

/**
 * Application Edit System - COMPLETE WORKING VERSION
 * EduBridge SA - Allow students to edit their application details
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration files (ensure session is configured before config)
require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// Check if user is logged in (after session initialization)
if (!isLoggedIn()) {
    die('<h2>Access Denied</h2><p>Please <a href="student-login.php">login</a> first.</p>');
}

// Get student data
$student_id = $_SESSION['student_id'];
$application_status = $_SESSION['application_status'] ?? 'draft';
$error_message = '';
$success_message = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current application data
    $application = null;

    // Try by student_id
    if (!empty($student_id)) {
        $stmt = $pdo->prepare("
            SELECT a.*
            FROM applications a
            WHERE a.student_id = ?
            ORDER BY a.updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $application_id = $application['id'] ?? null;

    // Normalize effective status
    $effective_status = $application['application_status'] ?? $application['status'] ?? ($_SESSION['application_status'] ?? 'draft');
    $_SESSION['application_status'] = $effective_status;
    $application_status = $effective_status;

    if (!$application) {
        // Auto-create a minimal draft application
        $reference_number = 'EBS-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $studentName = $_SESSION['student_name'] ?? '';
        $nameParts = preg_split('/\s+/', trim($studentName));
        $first = $nameParts[0] ?? 'Student';
        $last = $_SESSION['student_surname'] ?? ($nameParts[1] ?? 'User');
        $email = $_SESSION['student_email'] ?? 'pending@example.local';
        $defaultGender = 'Male';
        $defaultId = 'TEMP-' . substr(md5(uniqid('', true)), 0, 10);
        $defaultPhone = '0000000000';
        $defaultDob = '2000-01-01';
        $defaultPostal = '0000';
        $defaultCountry = 'South Africa';

        $insert = $pdo->prepare("
            INSERT INTO applications (
                reference_number, gender, full_name, surname, id_number, cellphone_number,
                date_of_birth, email_address, physical_address, postal_code, country_of_residence,
                application_status, status, step_completed, created_at, updated_at, student_id
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, 'draft', 'Pending', 0, NOW(), NOW(), ?
            )
        ");
        $insert->execute([
            $reference_number,
            $defaultGender,
            $first,
            $last,
            $defaultId,
            $defaultPhone,
            $defaultDob,
            $email,
            $defaultPostal,
            $defaultCountry,
            $student_id
        ]);

        $application_id = (int)$pdo->lastInsertId();
        $_SESSION['reference_number'] = $reference_number;
        $_SESSION['application_status'] = 'draft';

        // Re-fetch the new application row
        $stmt = $pdo->prepare("SELECT a.* FROM applications a WHERE a.id = ? LIMIT 1");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get all universities for dropdown (limit to reasonable number)
    $stmt = $pdo->query("SELECT id, name, country FROM universities ORDER BY name LIMIT 500");
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = 'Database error occurred. Please try again.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforce server-side CSRF (best-effort)
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    try {
        // CSRF protection (avoid TypeError on null)
        $csrfToken = isset($_POST[CSRF_TOKEN_NAME]) ? (string)$_POST[CSRF_TOKEN_NAME] : '';
        if ($csrfToken === '' || !SecurityUtils::validateCSRFToken($csrfToken)) {
            throw new Exception('Security token mismatch. Please try again.');
        }

        $pdo->beginTransaction();

        // Validate user access
        if (empty($_SESSION['student_id'])) {
            throw new Exception('Invalid user session. Please log in again.');
        }

        // Check if editing is allowed
        if (!SecurityUtils::isActionAllowed('edit', $application_status)) {
            throw new Exception('Editing is not allowed for your current application status.');
        }

        // Check rate limiting
        SecurityUtils::checkRateLimit($pdo, $_SESSION['student_id'], 'application_edit', 5, 3600);

        // Sanitize and validate input
        $input_data = SecurityUtils::sanitizeInput($_POST);

        // Personal Information
        $full_name = trim($input_data['full_name'] ?? '');
        $surname = trim($input_data['surname'] ?? '');
        $email_address = trim($input_data['email_address'] ?? '');
        $cellphone_number = trim($input_data['cellphone_number'] ?? '');
        $date_of_birth = $input_data['date_of_birth'] ?? '';
        $id_number = trim($input_data['id_number'] ?? '');
        $gender = $input_data['gender'] ?? '';
        $home_language = trim($input_data['home_language'] ?? '');
        $nationality = trim($input_data['nationality'] ?? '');
        $title = $input_data['title'] ?? '';

        // Address Information
        $physical_address = trim($input_data['physical_address'] ?? '');
        $city = trim($input_data['city'] ?? '');
        $postal_code = trim($input_data['postal_code'] ?? '');
        $province = $input_data['province'] ?? '';
        $country_of_residence = trim($input_data['country_of_residence'] ?? '');

        // Academic Information
        $high_school_name = trim($input_data['high_school_name'] ?? '');
        $matric_year = trim($input_data['matric_year'] ?? '');
        $aps = trim($input_data['aps'] ?? '');
        $maths_level = $input_data['maths_level'] ?? '';
        $english_level = $input_data['english_level'] ?? '';
        $exam_number = trim($input_data['exam_number'] ?? '');

        // University Choices
        $institution_choice_1 = trim($input_data['institution_choice_1'] ?? '');
        $program_choice_1 = trim($input_data['program_choice_1'] ?? '');
        $program_specialization_1 = trim($input_data['program_specialization_1'] ?? '');
        $program_other_comment_1 = trim($input_data['program_other_comment_1'] ?? '');

        $institution_choice_2 = trim($input_data['institution_choice_2'] ?? '');
        $program_choice_2 = trim($input_data['program_choice_2'] ?? '');
        $program_specialization_2 = trim($input_data['program_specialization_2'] ?? '');
        $program_other_comment_2 = trim($input_data['program_other_comment_2'] ?? '');

        $institution_choice_3 = trim($input_data['institution_choice_3'] ?? '');
        $program_choice_3 = trim($input_data['program_choice_3'] ?? '');
        $program_specialization_3 = trim($input_data['program_specialization_3'] ?? '');
        $program_other_comment_3 = trim($input_data['program_other_comment_3'] ?? '');

        // Enhanced validation
        if (empty($full_name) || empty($surname) || empty($email_address) || empty($cellphone_number)) {
            throw new Exception('Please fill in all required fields.');
        }

        // Validate email
        $validated_email = SecurityUtils::validateEmail($email_address);
        if (!$validated_email) {
            throw new Exception('Please enter a valid email address.');
        }
        $email_address = $validated_email;

        // Validate phone number
        $validated_phone = SecurityUtils::validatePhone($cellphone_number);
        if (!$validated_phone) {
            throw new Exception('Please enter a valid cellphone number.');
        }
        $cellphone_number = $validated_phone;

        // Validate ID number if provided
        if (!empty($id_number) && !SecurityUtils::validateSAIdNumber($id_number)) {
            throw new Exception('Please enter a valid South African ID number.');
        }

        // Validate date of birth
        if (empty($date_of_birth)) {
            throw new Exception('Date of birth is required.');
        }

        // Update applications table
        $stmt = $pdo->prepare("
            UPDATE applications SET 
                full_name = ?, surname = ?, email_address = ?, cellphone_number = ?, 
                date_of_birth = ?, id_number = ?, gender = ?, home_language = ?, 
                nationality = ?, title = ?,
                physical_address = ?, city = ?, postal_code = ?, province = ?, country_of_residence = ?,
                high_school_name = ?, matric_year = ?, aps = ?, maths_level = ?, english_level = ?, exam_number = ?,
                institution_choice_1 = ?, program_choice_1 = ?, program_specialization_1 = ?, program_other_comment_1 = ?,
                institution_choice_2 = ?, program_choice_2 = ?, program_specialization_2 = ?, program_other_comment_2 = ?,
                institution_choice_3 = ?, program_choice_3 = ?, program_specialization_3 = ?, program_other_comment_3 = ?,
                updated_at = NOW(), application_status = ?
            WHERE id = ? AND student_id = ?
        ");

        $result = $stmt->execute([
            $full_name,
            $surname,
            $email_address,
            $cellphone_number,
            $date_of_birth,
            $id_number,
            $gender,
            $home_language,
            $nationality,
            $title,
            $physical_address,
            $city,
            $postal_code,
            $province,
            $country_of_residence,
            $high_school_name,
            $matric_year,
            $aps,
            $maths_level,
            $english_level,
            $exam_number,
            $institution_choice_1,
            $program_choice_1,
            $program_specialization_1,
            $program_other_comment_1,
            $institution_choice_2,
            $program_choice_2,
            $program_specialization_2,
            $program_other_comment_2,
            $institution_choice_3,
            $program_choice_3,
            $program_specialization_3,
            $program_other_comment_3,
            'submitted',
            $application_id,
            $student_id
        ]);

        if (!$result) {
            throw new Exception('Failed to update application in database.');
        }

        // Add to status history
        $stmt = $pdo->prepare("
            INSERT INTO application_status_history (application_id, previous_status, new_status, notes, created_at)
            VALUES (?, ?, ?, 'Application details updated', NOW())
        ");
        $stmt->execute([$application_id, $application_status, 'submitted']);

        $pdo->commit();

        // Update session status
        $_SESSION['application_status'] = 'submitted';

        // Log successful edit
        SecurityUtils::logSecurityEvent(
            $pdo,
            'application_edit',
            'Application successfully updated',
            $_SESSION['student_id'],
            SecurityUtils::getClientIP()
        );

        $success_message = 'Application updated successfully!';
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log failed edit attempt
        if (isset($pdo)) {
            SecurityUtils::logSecurityEvent(
                $pdo,
                'application_edit_failed',
                'Application edit failed: ' . $e->getMessage(),
                $_SESSION['student_id'] ?? null,
                SecurityUtils::getClientIP()
            );
        }

        $error_message = 'Unexpected error: ' . $e->getMessage();
        error_log("Application update error [fatal]: " . $error_message);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application - EduBridge SA</title>
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

        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .edit-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .edit-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .edit-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .edit-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .form-label.required::after {
            content: ' *';
            color: var(--red-500);
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .choice-group {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: var(--gray-50);
        }

        .choice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .choice-number {
            font-weight: 600;
            color: var(--royal-blue);
        }

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
            font-family: inherit;
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--royal-blue);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--royal-blue-light);
            transform: translateY(-1px);
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
            padding-top: 2rem;
            border-top: 1px solid var(--gray-200);
        }

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

        .success-message {
            background: #dcfce7;
            color: #16a34a;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-notice {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .nav-container {
                padding: 1rem;
            }

            .edit-card {
                padding: 1.5rem;
            }

            .form-grid-2,
            .form-grid-3 {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
                width: 100%;
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
        <div class="edit-card">
            <div class="edit-header">
                <h1 class="edit-title">Edit Application</h1>
                <p class="edit-subtitle">Update your application details</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (in_array($application_status, ['draft', 'documents_pending', 'submitted', 'in_progress', 'under_review'])): ?>
                <div class="status-notice">
                    <i class="fas fa-info-circle"></i>
                    You can edit your application while it's in "<?php echo ucfirst($application_status); ?>" status.
                </div>
            <?php endif; ?>

            <?php
            // Ensure CSRF token exists before rendering form
            if (defined('CSRF_TOKEN_NAME') && (!isset($_SESSION[CSRF_TOKEN_NAME]) || empty($_SESSION[CSRF_TOKEN_NAME]))) {
                $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
            }
            ?>
            <form action="student-apply.php" method="POST" id="editForm">
                <?php if (defined('CSRF_TOKEN_NAME') && isset($_SESSION[CSRF_TOKEN_NAME])): ?>
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                <?php endif; ?>

                <!-- Personal Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h2>
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <select id="title" name="title" class="form-select">
                                <option value="">Select Title</option>
                                <option value="Mr" <?php echo ($application['title'] ?? '') === 'Mr' ? 'selected' : ''; ?>>Mr</option>
                                <option value="Ms" <?php echo ($application['title'] ?? '') === 'Ms' ? 'selected' : ''; ?>>Ms</option>
                                <option value="Mrs" <?php echo ($application['title'] ?? '') === 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                                <option value="Dr" <?php echo ($application['title'] ?? '') === 'Dr' ? 'selected' : ''; ?>>Dr</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="full_name" class="form-label required">Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-input"
                                value="<?php echo htmlspecialchars($application['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="surname" class="form-label required">Surname</label>
                            <input type="text" id="surname" name="surname" class="form-input"
                                value="<?php echo htmlspecialchars($application['surname'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="id_number" class="form-label required">ID Number</label>
                            <input type="text" id="id_number" name="id_number" class="form-input"
                                value="<?php echo htmlspecialchars($application['id_number'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label required">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input"
                                value="<?php echo htmlspecialchars($application['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender" class="form-label required">Gender</label>
                            <select id="gender" name="gender" class="form-select" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($application['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($application['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="email_address" class="form-label required">Email Address</label>
                            <input type="email" id="email_address" name="email_address" class="form-input"
                                value="<?php echo htmlspecialchars($application['email_address'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="cellphone_number" class="form-label required">Cellphone Number</label>
                            <input type="tel" id="cellphone_number" name="cellphone_number" class="form-input"
                                value="<?php echo htmlspecialchars($application['cellphone_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="home_language" class="form-label">Home Language</label>
                            <input type="text" id="home_language" name="home_language" class="form-input"
                                value="<?php echo htmlspecialchars($application['home_language'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nationality" class="form-label">Nationality</label>
                            <input type="text" id="nationality" name="nationality" class="form-input"
                                value="<?php echo htmlspecialchars($application['nationality'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="country_of_residence" class="form-label required">Country of Residence</label>
                            <input type="text" id="country_of_residence" name="country_of_residence" class="form-input"
                                value="<?php echo htmlspecialchars($application['country_of_residence'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-home"></i> Address Information
                    </h2>
                    <div class="form-group full-width">
                        <label for="physical_address" class="form-label">Physical Address</label>
                        <textarea id="physical_address" name="physical_address" class="form-textarea"><?php echo htmlspecialchars($application['physical_address'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="city" class="form-label">City</label>
                            <input type="text" id="city" name="city" class="form-input"
                                value="<?php echo htmlspecialchars($application['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="province" class="form-label">Province</label>
                            <select id="province" name="province" class="form-select">
                                <option value="">Select Province</option>
                                <option value="Eastern Cape" <?php echo ($application['province'] ?? '') === 'Eastern Cape' ? 'selected' : ''; ?>>Eastern Cape</option>
                                <option value="Free State" <?php echo ($application['province'] ?? '') === 'Free State' ? 'selected' : ''; ?>>Free State</option>
                                <option value="Gauteng" <?php echo ($application['province'] ?? '') === 'Gauteng' ? 'selected' : ''; ?>>Gauteng</option>
                                <option value="KwaZulu-Natal" <?php echo ($application['province'] ?? '') === 'KwaZulu-Natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
                                <option value="Limpopo" <?php echo ($application['province'] ?? '') === 'Limpopo' ? 'selected' : ''; ?>>Limpopo</option>
                                <option value="Mpumalanga" <?php echo ($application['province'] ?? '') === 'Mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
                                <option value="Northern Cape" <?php echo ($application['province'] ?? '') === 'Northern Cape' ? 'selected' : ''; ?>>Northern Cape</option>
                                <option value="North West" <?php echo ($application['province'] ?? '') === 'North West' ? 'selected' : ''; ?>>North West</option>
                                <option value="Western Cape" <?php echo ($application['province'] ?? '') === 'Western Cape' ? 'selected' : ''; ?>>Western Cape</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="postal_code" class="form-label required">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input"
                                value="<?php echo htmlspecialchars($application['postal_code'] ?? ''); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap"></i> Academic Information
                    </h2>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="high_school_name" class="form-label">High School Name</label>
                            <input type="text" id="high_school_name" name="high_school_name" class="form-input"
                                value="<?php echo htmlspecialchars($application['high_school_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="matric_year" class="form-label">Matric Year</label>
                            <input type="number" id="matric_year" name="matric_year" class="form-input"
                                value="<?php echo htmlspecialchars($application['matric_year'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-grid-3">
                        <div class="form-group">
                            <label for="aps" class="form-label">APS Score</label>
                            <input type="number" id="aps" name="aps" class="form-input"
                                value="<?php echo htmlspecialchars($application['aps'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="maths_level" class="form-label">Maths Level</label>
                            <select id="maths_level" name="maths_level" class="form-select">
                                <option value="">Select Level</option>
                                <option value="Mathematics" <?php echo ($application['maths_level'] ?? '') === 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Mathematical Literacy" <?php echo ($application['maths_level'] ?? '') === 'Mathematical Literacy' ? 'selected' : ''; ?>>Mathematical Literacy</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="english_level" class="form-label">English Level</label>
                            <select id="english_level" name="english_level" class="form-select">
                                <option value="">Select Level</option>
                                <option value="Home Language" <?php echo ($application['english_level'] ?? '') === 'Home Language' ? 'selected' : ''; ?>>Home Language</option>
                                <option value="First Additional Language" <?php echo ($application['english_level'] ?? '') === 'First Additional Language' ? 'selected' : ''; ?>>First Additional Language</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="exam_number" class="form-label">Exam Number</label>
                        <input type="text" id="exam_number" name="exam_number" class="form-input"
                            value="<?php echo htmlspecialchars($application['exam_number'] ?? ''); ?>">
                    </div>
                </div>

                <!-- University Choices -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-university"></i> University Choices
                    </h2>

                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="choice-group">
                            <div class="choice-header">
                                <span class="choice-number">Choice <?php echo $i; ?></span>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Institution</label>
                                    <select name="institution_choice_<?php echo $i; ?>" class="form-select">
                                        <option value="">Select University</option>
                                        <?php foreach ($universities as $university): ?>
                                            <option value="<?php echo htmlspecialchars($university['name']); ?>"
                                                <?php echo ($application['institution_choice_' . $i] ?? '') === $university['name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($university['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Program Choice</label>
                                    <input type="text" name="program_choice_<?php echo $i; ?>" class="form-input"
                                        value="<?php echo htmlspecialchars($application['program_choice_' . $i] ?? ''); ?>"
                                        placeholder="e.g., Bachelor of Commerce">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Specialization</label>
                                <input type="text" name="program_specialization_<?php echo $i; ?>" class="form-input"
                                    value="<?php echo htmlspecialchars($application['program_specialization_' . $i] ?? ''); ?>"
                                    placeholder="e.g., Accounting">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Additional Comments</label>
                                <textarea name="program_other_comment_<?php echo $i; ?>" class="form-textarea"
                                    placeholder="Any additional information about this choice"><?php echo htmlspecialchars($application['program_other_comment_' . $i] ?? ''); ?></textarea>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="form-actions">
                    <a href="student-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editForm');

            if (form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('input[required], select[required]');
                    let isValid = true;

                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.style.borderColor = 'var(--red-500)';
                            isValid = false;
                        } else {
                            field.style.borderColor = '';
                        }
                    });

                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields (marked with *).');
                    }
                });

                // Real-time validation
                form.querySelectorAll('input[required], select[required]').forEach(input => {
                    input.addEventListener('blur', function() {
                        if (!this.value.trim()) {
                            this.style.borderColor = 'var(--red-500)';
                        } else {
                            this.style.borderColor = '';
                        }
                    });

                    input.addEventListener('input', function() {
                        if (this.value.trim()) {
                            this.style.borderColor = '';
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>