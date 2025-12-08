<?php

/**
 * Application Edit System
 * EduBridge SA - Allow students to edit their application details
 */

require_once 'session_config.php';
require_once 'config.php';
require_once 'security-utils.php';

// Check if user is logged in
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Get student data
$student_id = $_SESSION['student_id'];
$application_status = $_SESSION['application_status'];

// Check if editing is allowed for current status
if (!in_array($application_status, ['draft', 'documents_pending'])) {
    $_SESSION['form_message'] = 'Application editing is not allowed for your current status.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-dashboard.php');
    exit();
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current application data
    $stmt = $pdo->prepare("
        SELECT a.*, pg.* 
        FROM applications a 
        LEFT JOIN parent_guardian_details pg ON a.id = pg.application_id 
        WHERE a.id = ?
    ");
    $stmt->execute([$student_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$application) {
        $_SESSION['form_message'] = 'Application not found.';
        $_SESSION['form_message_type'] = 'error';
        header('Location: student-dashboard.php');
        exit();
    }

    // Get university choices
    $stmt = $pdo->prepare("
        SELECT uc.*, u.name as university_name 
        FROM university_choices uc 
        LEFT JOIN universities u ON uc.university_id = u.id 
        WHERE uc.application_id = ? 
        ORDER BY uc.choice_order
    ");
    $stmt->execute([$student_id]);
    $university_choices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all universities for dropdown
    $stmt = $pdo->query("SELECT id, name, country FROM universities ORDER BY name LIMIT 500");
    $universities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['form_message'] = 'Database error occurred.';
    $_SESSION['form_message_type'] = 'error';
    header('Location: student-dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side CSRF enforcement using helper when available
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    try {
        $pdo->beginTransaction();

        // Validate user access
        SecurityUtils::validateUserAccess($pdo, $student_id, $_SESSION['student_id']);

        // Check if editing is allowed
        if (!SecurityUtils::isActionAllowed('edit', $application_status)) {
            throw new Exception('Editing is not allowed for your current application status.');
        }

        // Check rate limiting
        SecurityUtils::checkRateLimit($pdo, $_SESSION['student_id'], 'application_edit', 5, 3600);

        // Sanitize and validate input
        $input_data = SecurityUtils::sanitizeInput($_POST);

        $full_name = trim($input_data['full_name'] ?? '');
        $surname = trim($input_data['surname'] ?? '');
        $email_address = trim($input_data['email_address'] ?? '');
        $cellphone_number = trim($input_data['cellphone_number'] ?? '');
        $date_of_birth = $input_data['date_of_birth'] ?? '';
        $id_number = trim($input_data['id_number'] ?? '');
        $gender = $input_data['gender'] ?? '';
        $home_language = trim($input_data['home_language'] ?? '');
        $nationality = trim($input_data['nationality'] ?? '');
        $postal_address = trim($input_data['postal_address'] ?? '');
        $physical_address = trim($input_data['physical_address'] ?? '');
        $city = trim($input_data['city'] ?? '');
        $postal_code = trim($input_data['postal_code'] ?? '');
        $province = $input_data['province'] ?? '';

        // Parent/Guardian details
        $parent_full_name = trim($input_data['parent_full_name'] ?? '');
        $parent_surname = trim($input_data['parent_surname'] ?? '');
        $parent_relationship = $input_data['parent_relationship'] ?? '';
        $parent_cellphone = trim($input_data['parent_cellphone'] ?? '');
        $parent_email = trim($input_data['parent_email'] ?? '');
        $parent_occupation = trim($input_data['parent_occupation'] ?? '');
        $parent_employer = trim($input_data['parent_employer'] ?? '');

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

        // Update applications table
        $stmt = $pdo->prepare("
            UPDATE applications SET 
                full_name = ?, surname = ?, email_address = ?, cellphone_number = ?, 
                date_of_birth = ?, id_number = ?, gender = ?, home_language = ?, 
                nationality = ?, postal_address = ?, physical_address = ?, 
                city = ?, postal_code = ?, province = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $full_name,
            $surname,
            $email_address,
            $cellphone_number,
            $date_of_birth,
            $id_number,
            $gender,
            $home_language,
            $nationality,
            $postal_address,
            $physical_address,
            $city,
            $postal_code,
            $province,
            $student_id
        ]);

        // Update parent/guardian details
        $stmt = $pdo->prepare("
            INSERT INTO parent_guardian_details (
                application_id, full_name, surname, relationship, cellphone_number,
                email_address, occupation, employer, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                surname = VALUES(surname),
                relationship = VALUES(relationship),
                cellphone_number = VALUES(cellphone_number),
                email_address = VALUES(email_address),
                occupation = VALUES(occupation),
                employer = VALUES(employer),
                updated_at = NOW()
        ");
        $stmt->execute([
            $student_id,
            $parent_full_name,
            $parent_surname,
            $parent_relationship,
            $parent_cellphone,
            $parent_email,
            $parent_occupation,
            $parent_employer
        ]);

        // Handle university choices
        if (isset($_POST['university_choices']) && is_array($_POST['university_choices'])) {
            // Delete existing choices
            $stmt = $pdo->prepare("DELETE FROM university_choices WHERE application_id = ?");
            $stmt->execute([$student_id]);

            // Insert new choices
            foreach ($_POST['university_choices'] as $index => $choice) {
                if (!empty($choice['university_id']) && !empty($choice['course'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO university_choices (application_id, university_id, course, choice_order, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$student_id, $choice['university_id'], trim($choice['course']), $index + 1]);
                }
            }
        }

        // Add to status history
        $stmt = $pdo->prepare("
            INSERT INTO application_status_history (application_id, previous_status, new_status, notes, created_at)
            VALUES (?, ?, ?, 'Application details updated', NOW())
        ");
        $stmt->execute([$student_id, $application_status, $application_status]);

        $pdo->commit();

        // Log successful edit
        SecurityUtils::logSecurityEvent(
            $pdo,
            'application_edit',
            'Application successfully updated',
            $_SESSION['student_id'],
            SecurityUtils::getClientIP()
        );

        $_SESSION['form_message'] = 'Application updated successfully!';
        $_SESSION['form_message_type'] = 'success';
        header('Location: student-dashboard.php');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();

        // Log failed edit attempt
        SecurityUtils::logSecurityEvent(
            $pdo,
            'application_edit_failed',
            'Application edit failed: ' . $e->getMessage(),
            $_SESSION['student_id'] ?? null,
            SecurityUtils::getClientIP()
        );

        $error_message = $e->getMessage();
        error_log("Application update error: " . $error_message);
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

        /* Form Sections */
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        /* University Choices */
        .university-choice {
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

        .remove-choice {
            background: var(--red-500);
            color: var(--white);
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .add-choice {
            background: var(--emerald-green);
            color: var(--white);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-choice:hover {
            background: var(--emerald-green-light);
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

        /* Status Notice */
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

        /* Responsive */
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

            .form-grid {
                grid-template-columns: 1fr;
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
        <div class="edit-card">
            <div class="edit-header">
                <h1 class="edit-title">Edit Application</h1>
                <p class="edit-subtitle">Update your application details</p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="status-notice">
                <i class="fas fa-info-circle"></i>
                You can edit your application while it's in "<?php echo ucfirst($application_status); ?>" status.
            </div>

            <form method="POST" action="student-application.php" id="editForm">
                <!-- Personal Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name" class="form-label required">First Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-input"
                                value="<?php echo htmlspecialchars($application['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="surname" class="form-label required">Surname</label>
                            <input type="text" id="surname" name="surname" class="form-input"
                                value="<?php echo htmlspecialchars($application['surname'] ?? ''); ?>" required>
                        </div>
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
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-input"
                                value="<?php echo htmlspecialchars($application['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="id_number" class="form-label">ID Number</label>
                            <input type="text" id="id_number" name="id_number" class="form-input"
                                value="<?php echo htmlspecialchars($application['id_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo ($application['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo ($application['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo ($application['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
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
                            <label for="province" class="form-label">Province</label>
                            <select id="province" name="province" class="form-select">
                                <option value="">Select Province</option>
                                <option value="eastern_cape" <?php echo ($application['province'] ?? '') === 'eastern_cape' ? 'selected' : ''; ?>>Eastern Cape</option>
                                <option value="free_state" <?php echo ($application['province'] ?? '') === 'free_state' ? 'selected' : ''; ?>>Free State</option>
                                <option value="gauteng" <?php echo ($application['province'] ?? '') === 'gauteng' ? 'selected' : ''; ?>>Gauteng</option>
                                <option value="kwazulu_natal" <?php echo ($application['province'] ?? '') === 'kwazulu_natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
                                <option value="limpopo" <?php echo ($application['province'] ?? '') === 'limpopo' ? 'selected' : ''; ?>>Limpopo</option>
                                <option value="mpumalanga" <?php echo ($application['province'] ?? '') === 'mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
                                <option value="northern_cape" <?php echo ($application['province'] ?? '') === 'northern_cape' ? 'selected' : ''; ?>>Northern Cape</option>
                                <option value="north_west" <?php echo ($application['province'] ?? '') === 'north_west' ? 'selected' : ''; ?>>North West</option>
                                <option value="western_cape" <?php echo ($application['province'] ?? '') === 'western_cape' ? 'selected' : ''; ?>>Western Cape</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="postal_address" class="form-label">Postal Address</label>
                            <textarea id="postal_address" name="postal_address" class="form-textarea"><?php echo htmlspecialchars($application['postal_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label for="physical_address" class="form-label">Physical Address</label>
                            <textarea id="physical_address" name="physical_address" class="form-textarea"><?php echo htmlspecialchars($application['physical_address'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="city" class="form-label">City</label>
                            <input type="text" id="city" name="city" class="form-input"
                                value="<?php echo htmlspecialchars($application['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="postal_code" class="form-label">Postal Code</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-input"
                                value="<?php echo htmlspecialchars($application['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Parent/Guardian Information -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i> Parent/Guardian Information
                    </h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parent_full_name" class="form-label">First Name</label>
                            <input type="text" id="parent_full_name" name="parent_full_name" class="form-input"
                                value="<?php echo htmlspecialchars($application['full_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="parent_surname" class="form-label">Surname</label>
                            <input type="text" id="parent_surname" name="parent_surname" class="form-input"
                                value="<?php echo htmlspecialchars($application['surname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="parent_relationship" class="form-label">Relationship</label>
                            <select id="parent_relationship" name="parent_relationship" class="form-select">
                                <option value="">Select Relationship</option>
                                <option value="parent" <?php echo ($application['relationship'] ?? '') === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                <option value="guardian" <?php echo ($application['relationship'] ?? '') === 'guardian' ? 'selected' : ''; ?>>Guardian</option>
                                <option value="grandparent" <?php echo ($application['relationship'] ?? '') === 'grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                                <option value="other" <?php echo ($application['relationship'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="parent_cellphone" class="form-label">Cellphone Number</label>
                            <input type="tel" id="parent_cellphone" name="parent_cellphone" class="form-input"
                                value="<?php echo htmlspecialchars($application['cellphone_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="parent_email" class="form-label">Email Address</label>
                            <input type="email" id="parent_email" name="parent_email" class="form-input"
                                value="<?php echo htmlspecialchars($application['email_address'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="parent_occupation" class="form-label">Occupation</label>
                            <input type="text" id="parent_occupation" name="parent_occupation" class="form-input"
                                value="<?php echo htmlspecialchars($application['occupation'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="parent_employer" class="form-label">Employer</label>
                            <input type="text" id="parent_employer" name="parent_employer" class="form-input"
                                value="<?php echo htmlspecialchars($application['employer'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- University Choices -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-university"></i> University Choices
                    </h2>
                    <div id="universityChoices">
                        <?php foreach ($university_choices as $index => $choice): ?>
                            <div class="university-choice">
                                <div class="choice-header">
                                    <span class="choice-number">Choice <?php echo $index + 1; ?></span>
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="remove-choice" onclick="removeChoice(this)">Remove</button>
                                    <?php endif; ?>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">University</label>
                                        <select name="university_choices[<?php echo $index; ?>][university_id]" class="form-select">
                                            <option value="">Select University</option>
                                            <?php foreach ($universities as $university): ?>
                                                <option value="<?php echo $university['id']; ?>"
                                                    <?php echo $choice['university_id'] == $university['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($university['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Course/Program</label>
                                        <input type="text" name="university_choices[<?php echo $index; ?>][course]" class="form-input"
                                            value="<?php echo htmlspecialchars($choice['course'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($university_choices)): ?>
                            <div class="university-choice">
                                <div class="choice-header">
                                    <span class="choice-number">Choice 1</span>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">University</label>
                                        <select name="university_choices[0][university_id]" class="form-select">
                                            <option value="">Select University</option>
                                            <?php foreach ($universities as $university): ?>
                                                <option value="<?php echo $university['id']; ?>">
                                                    <?php echo htmlspecialchars($university['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Course/Program</label>
                                        <input type="text" name="university_choices[0][course]" class="form-input">
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="add-choice" onclick="addChoice()">
                        <i class="fas fa-plus"></i> Add Another Choice
                    </button>
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
        let choiceCount = <?php echo max(1, count($university_choices)); ?>;
        const universities = <?php echo json_encode($universities); ?>;

        function addChoice() {
            if (choiceCount >= 5) {
                alert('Maximum 5 university choices allowed.');
                return;
            }

            const container = document.getElementById('universityChoices');
            const choiceDiv = document.createElement('div');
            choiceDiv.className = 'university-choice';

            choiceDiv.innerHTML = `
                <div class="choice-header">
                    <span class="choice-number">Choice ${choiceCount + 1}</span>
                    <button type="button" class="remove-choice" onclick="removeChoice(this)">Remove</button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">University</label>
                        <select name="university_choices[${choiceCount}][university_id]" class="form-select">
                            <option value="">Select University</option>
                            ${universities.map(uni => `<option value="${uni.id}">${uni.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Course/Program</label>
                        <input type="text" name="university_choices[${choiceCount}][course]" class="form-input">
                    </div>
                </div>
            `;

            container.appendChild(choiceDiv);
            choiceCount++;
            updateChoiceNumbers();
        }

        function removeChoice(button) {
            button.closest('.university-choice').remove();
            choiceCount--;
            updateChoiceNumbers();
        }

        function updateChoiceNumbers() {
            const choices = document.querySelectorAll('.university-choice');
            choices.forEach((choice, index) => {
                choice.querySelector('.choice-number').textContent = `Choice ${index + 1}`;

                // Update input names
                const selects = choice.querySelectorAll('select');
                const inputs = choice.querySelectorAll('input');

                selects.forEach(select => {
                    const name = select.name.replace(/\[\d+\]/, `[${index}]`);
                    select.name = name;
                });

                inputs.forEach(input => {
                    const name = input.name.replace(/\[\d+\]/, `[${index}]`);
                    input.name = name;
                });
            });
        }

        // Form validation
        document.getElementById('editForm').addEventListener('submit', function(e) {
            const requiredFields = ['full_name', 'surname', 'email_address', 'cellphone_number'];
            let isValid = true;

            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--red-500)';
                    isValid = false;
                } else {
                    input.style.borderColor = 'var(--gray-300)';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
</body>

</html>