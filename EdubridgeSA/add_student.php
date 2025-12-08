<?php
// add_student.php - Enhanced Add Student Manually
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Use existing config
require_once __DIR__ . '/config.php';

// Verify database connection
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed");
}

// Include StudentController for additional functionality
require_once __DIR__ . '/controllers/StudentController.php';
$controller = new StudentController($pdo);

$message = '';
$message_type = 'info'; // info, success, danger
$form_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'id_number' => '',
    'school' => '',
    'grade' => '',
    'province' => '',
    'program_choice_1' => '',
    'program_choice_2' => '',
    'program_choice_3' => '',
    'address' => '',
    'city' => '',
    'postal_code' => '',
    'gender' => '',
    'date_of_birth' => '',
    'emergency_contact' => '',
    'emergency_phone' => '',
    'notes' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side CSRF enforcement (best-effort, no-op if helper absent)
    if (function_exists('require_csrf')) { require_csrf(); }

    // Collect and sanitize form data
    $form_data = [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'id_number' => trim($_POST['id_number'] ?? ''),
        'school' => trim($_POST['school'] ?? ''),
        'grade' => trim($_POST['grade'] ?? ''),
        'province' => trim($_POST['province'] ?? ''),
        'program_choice_1' => trim($_POST['program_choice_1'] ?? ''),
        'program_choice_2' => trim($_POST['program_choice_2'] ?? ''),
        'program_choice_3' => trim($_POST['program_choice_3'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'gender' => trim($_POST['gender'] ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'emergency_contact' => trim($_POST['emergency_contact'] ?? ''),
        'emergency_phone' => trim($_POST['emergency_phone'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'status' => $_POST['status'] ?? 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Enhanced validation
    $errors = [];

    // Required fields
    if (empty($form_data['first_name'])) $errors[] = "First name is required";
    if (empty($form_data['last_name'])) $errors[] = "Last name is required";
    if (empty($form_data['email'])) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // ID number validation (South African ID)
    if (!empty($form_data['id_number']) && !preg_match('/^[0-9]{13}$/', $form_data['id_number'])) {
        $errors[] = "ID number must be 13 digits";
    }

    // Phone validation
    if (!empty($form_data['phone']) && !preg_match('/^[0-9+\-\s()]{10,}$/', $form_data['phone'])) {
        $errors[] = "Invalid phone number format";
    }

    // Check for duplicate email
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM applications WHERE email = ? OR email_address = ?");
            $stmt->execute([$form_data['email'], $form_data['email']]);
            if ($stmt->fetch()) {
                $errors[] = "A student with this email already exists";
            }
        } catch (Exception $e) {
            // Continue if check fails
        }
    }

    if (empty($errors)) {
        try {
            // Build dynamic INSERT query based on available columns
            $columns = [];
            $placeholders = [];
            $values = [];

            // Check which columns exist in the table
            $table_columns = $pdo->query("SHOW COLUMNS FROM applications")->fetchAll(PDO::FETCH_COLUMN);

            $field_mapping = [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
                'phone' => 'phone',
                'id_number' => 'id_number',
                'school' => 'school',
                'grade' => 'grade',
                'province' => 'province',
                'program_choice_1' => 'program_choice_1',
                'program_choice_2' => 'program_choice_2',
                'program_choice_3' => 'program_choice_3',
                'address' => 'address',
                'city' => 'city',
                'postal_code' => 'postal_code',
                'gender' => 'gender',
                'date_of_birth' => 'date_of_birth',
                'emergency_contact' => 'emergency_contact_name',
                'emergency_phone' => 'emergency_contact_phone',
                'notes' => 'admin_notes',
                'status' => 'status',
                'created_at' => 'created_at'
            ];

            foreach ($field_mapping as $form_field => $db_column) {
                if (in_array($db_column, $table_columns) && isset($form_data[$form_field])) {
                    $columns[] = $db_column;
                    $placeholders[] = '?';
                    $values[] = $form_data[$form_field];
                }
            }

            // Add email_address if email column doesn't exist but email_address does
            if (!in_array('email', $table_columns) && in_array('email_address', $table_columns) && !empty($form_data['email'])) {
                $columns[] = 'email_address';
                $placeholders[] = '?';
                $values[] = $form_data['email'];
            }

            if (!empty($columns)) {
                $sql = "INSERT INTO applications (" . implode(', ', $columns) . ") 
                        VALUES (" . implode(', ', $placeholders) . ")";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                
                $studentId = $pdo->lastInsertId();
                
                // Generate reference number if applicable
                if (in_array('reference_number', $table_columns)) {
                    $reference = 'EB' . str_pad($studentId, 6, '0', STR_PAD_LEFT);
                    $pdo->prepare("UPDATE applications SET reference_number = ? WHERE id = ?")->execute([$reference, $studentId]);
                }

                $message = "Student added successfully! Student ID: " . $studentId;
                if (isset($reference)) {
                    $message .= " | Reference: " . $reference;
                }
                $message_type = 'success';
                
                // Clear form on success
                if ($message_type === 'success') {
                    $form_data = array_fill_keys(array_keys($form_data), '');
                }
                
            } else {
                throw new Exception("No valid columns found for insertion");
            }
            
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $message_type = 'danger';
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'danger';
    }
}

// Get available programs for dropdown
$programs = [];
try {
    $programs = $pdo->query("SELECT DISTINCT program_name FROM programs WHERE program_name IS NOT NULL ORDER BY program_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Use default programs if table doesn't exist
    $programs = [
        'Computer Science',
        'Business Administration', 
        'Engineering',
        'Health Sciences',
        'Education',
        'Arts and Design',
        'Tourism and Hospitality'
    ];
}

$provinces = ['Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape', 'Free State', 'North West', 'Mpumalanga', 'Northern Cape', 'Limpopo'];
$grades = ['8', '9', '10', '11', '12'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-shadow { 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0,0,0,0.125);
        }
        .navbar { background-color: #2c3e50; }
        .form-section {
            border-left: 4px solid #0d6efd;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .form-section h6 {
            color: #0d6efd;
            margin-bottom: 1rem;
        }
        .required::after {
            content: " *";
            color: #dc3545;
        }
        .quick-fill {
            font-size: 0.875rem;
        }
        .tab-pane {
            padding: 1.5rem 0;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
    <div class="container-fluid">
        <span class="navbar-brand">
            <i class="bi bi-person-plus me-2"></i>Add New Student
        </span>
        <div>
            <a href="manage_students.php" class="btn btn-outline-light btn-sm me-2">
                <i class="bi bi-arrow-left me-1"></i>Back to Students
            </a>
            <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <?= $message_type === 'success' ? '<i class="bi bi-check-circle me-2"></i>' : '<i class="bi bi-exclamation-triangle me-2"></i>' ?>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card card-shadow">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-plus me-2"></i>Add Student Manually
                    </h5>
                    <p class="text-muted mb-0">Complete student information form</p>
                </div>
                <div class="card-body">
                    <form method="post" action="add_student.php" id="studentForm">
                        <!-- Navigation Tabs -->
                        <ul class="nav nav-tabs mb-4" id="formTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                                    <i class="bi bi-person me-1"></i>Personal
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="education-tab" data-bs-toggle="tab" data-bs-target="#education" type="button" role="tab">
                                    <i class="bi bi-book me-1"></i>Education
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button" role="tab">
                                    <i class="bi bi-briefcase me-1"></i>Programs
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="emergency-tab" data-bs-toggle="tab" data-bs-target="#emergency" type="button" role="tab">
                                    <i class="bi bi-telephone me-1"></i>Emergency
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="formTabsContent">
                            <!-- Personal Information Tab -->
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label required">First Name</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?= htmlspecialchars($form_data['first_name']) ?>" required
                                               placeholder="Enter first name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Last Name</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?= htmlspecialchars($form_data['last_name']) ?>" required
                                               placeholder="Enter last name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required">Email Address</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($form_data['email']) ?>" required
                                               placeholder="student@example.com">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?= htmlspecialchars($form_data['phone']) ?>"
                                               placeholder="+27 12 345 6789">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Number</label>
                                        <input type="text" class="form-control" name="id_number" 
                                               value="<?= htmlspecialchars($form_data['id_number']) ?>"
                                               placeholder="13-digit ID number" maxlength="13"
                                               pattern="[0-9]{13}" title="13-digit ID number">
                                        <div class="form-text">Format: 1234567890123</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Gender</label>
                                        <select class="form-select" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?= $form_data['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                                            <option value="Female" <?= $form_data['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                                            <option value="Other" <?= $form_data['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" name="date_of_birth" 
                                               value="<?= htmlspecialchars($form_data['date_of_birth']) ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Initial Status</label>
                                        <select class="form-select" name="status">
                                            <option value="pending" <?= $form_data['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="approved" <?= $form_data['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                            <option value="rejected" <?= $form_data['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <textarea class="form-control" name="address" rows="2" 
                                                  placeholder="Full physical address"><?= htmlspecialchars($form_data['address']) ?></textarea>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" 
                                               value="<?= htmlspecialchars($form_data['city']) ?>"
                                               placeholder="City">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code" 
                                               value="<?= htmlspecialchars($form_data['postal_code']) ?>"
                                               placeholder="Postal code">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Province</label>
                                        <select class="form-select" name="province">
                                            <option value="">Select Province</option>
                                            <?php foreach ($provinces as $province): ?>
                                                <option value="<?= $province ?>" <?= $form_data['province'] == $province ? 'selected' : '' ?>>
                                                    <?= $province ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Education Information Tab -->
                            <div class="tab-pane fade" id="education" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">School Name</label>
                                        <input type="text" class="form-control" name="school" 
                                               value="<?= htmlspecialchars($form_data['school']) ?>"
                                               placeholder="Current or previous school">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Grade</label>
                                        <select class="form-select" name="grade">
                                            <option value="">Select Grade</option>
                                            <?php foreach ($grades as $grade): ?>
                                                <option value="<?= $grade ?>" <?= $form_data['grade'] == $grade ? 'selected' : '' ?>>
                                                    Grade <?= $grade ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Admin Notes</label>
                                        <textarea class="form-control" name="notes" rows="4" 
                                                  placeholder="Any additional notes or comments"><?= htmlspecialchars($form_data['notes']) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Program Choices Tab -->
                            <div class="tab-pane fade" id="programs" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">First Program Choice</label>
                                        <select class="form-select" name="program_choice_1">
                                            <option value="">Select Program</option>
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?= htmlspecialchars($program) ?>" <?= $form_data['program_choice_1'] == $program ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($program) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Second Program Choice</label>
                                        <select class="form-select" name="program_choice_2">
                                            <option value="">Select Program</option>
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?= htmlspecialchars($program) ?>" <?= $form_data['program_choice_2'] == $program ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($program) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Third Program Choice</label>
                                        <select class="form-select" name="program_choice_3">
                                            <option value="">Select Program</option>
                                            <?php foreach ($programs as $program): ?>
                                                <option value="<?= htmlspecialchars($program) ?>" <?= $form_data['program_choice_3'] == $program ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($program) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Emergency Contact Tab -->
                            <div class="tab-pane fade" id="emergency" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Name</label>
                                        <input type="text" class="form-control" name="emergency_contact" 
                                               value="<?= htmlspecialchars($form_data['emergency_contact']) ?>"
                                               placeholder="Full name of emergency contact">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Emergency Contact Phone</label>
                                        <input type="tel" class="form-control" name="emergency_phone" 
                                               value="<?= htmlspecialchars($form_data['emergency_phone']) ?>"
                                               placeholder="Emergency contact phone number">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-plus-circle me-1"></i>Add Student
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset Form
                            </button>
                            <a href="manage_students.php" class="btn btn-outline-danger">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            
                            <div class="form-check form-check-inline ms-3">
                                <input class="form-check-input" type="checkbox" id="addAnother" name="add_another">
                                <label class="form-check-label" for="addAnother">
                                    Add another student after this
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Help & Tips -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card card-shadow">
                        <div class="card-body">
                            <h6><i class="bi
                                                        <h6><i class="bi bi-lightbulb me-2"></i>Quick Tips</h6>
                            <ul class="small text-muted">
                                <li>Required fields are marked with <span class="text-danger">*</span></li>
                                <li>Use the tabbed interface to organize information logically</li>
                                <li>Check for duplicate emails before submitting</li>
                                <li>Set initial status to "Pending" for review or "Approved" for immediate acceptance</li>
                                <li>Use the "Add another" checkbox for batch entries</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card card-shadow">
                        <div class="card-body">
                            <h6><i class="bi bi-clock me-2"></i>Recent Additions</h6>
                            <?php
                            try {
                                $recent = $pdo->query("SELECT first_name, last_name, created_at FROM applications ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
                                if ($recent) {
                                    foreach ($recent as $student) {
                                        echo '<div class="small text-muted mb-1">';
                                        echo '<i class="bi bi-person me-1"></i>';
                                        echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                                        echo ' <span class="badge bg-light text-dark">' . date('M j', strtotime($student['created_at'])) . '</span>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo '<div class="small text-muted">No students added yet</div>';
                                }
                            } catch (Exception $e) {
                                echo '<div class="small text-muted">Unable to load recent additions</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('studentForm');
    const emailInput = document.querySelector('input[name="email"]');
    const addAnotherCheckbox = document.getElementById('addAnother');
    
    // Real-time email validation
    emailInput.addEventListener('blur', function() {
        const email = this.value.trim();
        if (email && !isValidEmail(email)) {
            showFieldError(this, 'Please enter a valid email address');
        } else {
            clearFieldError(this);
        }
    });

    // ID number validation
    const idInput = document.querySelector('input[name="id_number"]');
    idInput.addEventListener('input', function() {
        const value = this.value.replace(/\D/g, '');
        this.value = value;
        
        if (value.length === 13) {
            clearFieldError(this);
        } else if (value.length > 0 && value.length !== 13) {
            showFieldError(this, 'ID number must be exactly 13 digits');
        } else {
            clearFieldError(this);
        }
    });

    // Phone number formatting
    const phoneInput = document.querySelector('input[name="phone"]');
    phoneInput.addEventListener('input', function() {
        this.value = formatPhoneNumber(this.value);
    });

    // Form submission handling
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required');
                isValid = false;
            } else {
                clearFieldError(field);
            }
        });

        // Validate email format
        if (emailInput.value.trim() && !isValidEmail(emailInput.value)) {
            showFieldError(emailInput, 'Please enter a valid email address');
            isValid = false;
        }

        // Validate ID number if provided
        if (idInput.value && idInput.value.length !== 13) {
            showFieldError(idInput, 'ID number must be exactly 13 digits');
            isValid = false;
        }

        if (!isValid) {
            e.preventDefault();
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
            
            // Show alert
            showAlert('Please correct the errors in the form before submitting.', 'danger');
        } else {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Adding Student...';
            submitBtn.disabled = true;
            
            // Re-enable after 5 seconds (safety net)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        }
    });

    // Tab navigation with form validation
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const currentTab = document.querySelector('.tab-pane.active');
            const requiredFields = currentTab.querySelectorAll('[required]');
            let currentTabValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    currentTabValid = false;
                    showFieldError(field, 'This field is required');
                }
            });
            
            if (!currentTabValid) {
                e.preventDefault();
                showAlert('Please complete all required fields in the current tab before proceeding.', 'warning');
            }
        });
    });

    // Quick fill for testing (remove in production)
    const quickFillBtn = document.createElement('button');
    quickFillBtn.type = 'button';
    quickFillBtn.className = 'btn btn-sm btn-outline-info quick-fill';
    quickFillBtn.innerHTML = '<i class="bi bi-magic me-1"></i>Fill Test Data';
    quickFillBtn.style.position = 'fixed';
    quickFillBtn.style.bottom = '20px';
    quickFillBtn.style.right = '20px';
    quickFillBtn.style.zIndex = '1000';
    
    quickFillBtn.addEventListener('click', function() {
        if (confirm('Fill form with test data? This is for testing only.')) {
            fillTestData();
        }
    });
    
    document.body.appendChild(quickFillBtn);

    // Helper functions
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function formatPhoneNumber(phone) {
        // Remove all non-digit characters
        let cleaned = phone.replace(/\D/g, '');
        
        // South African phone number formatting
        if (cleaned.length <= 2) {
            return cleaned;
        } else if (cleaned.length <= 5) {
            return cleaned.replace(/(\d{2})(\d{0,3})/, '$1 $2');
        } else if (cleaned.length <= 8) {
            return cleaned.replace(/(\d{2})(\d{3})(\d{0,3})/, '$1 $2 $3');
        } else {
            return cleaned.replace(/(\d{2})(\d{3})(\d{3})(\d{0,4})/, '$1 $2 $3 $4');
        }
    }

    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        let feedback = field.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            field.parentNode.appendChild(feedback);
        }
        feedback.textContent = message;
    }

    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        const feedback = field.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    }

    function showAlert(message, type) {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert-dismissible');
        existingAlerts.forEach(alert => alert.remove());
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="bi bi-${type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const container = document.querySelector('.container-fluid');
        container.insertBefore(alert, container.firstChild);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    function fillTestData() {
        const testData = {
            'first_name': 'John',
            'last_name': 'Doe',
            'email': 'john.doe@example.com',
            'phone': '0821234567',
            'id_number': '1234567890123',
            'school': 'Springfield High School',
            'grade': '12',
            'province': 'Gauteng',
            'program_choice_1': 'Computer Science',
            'program_choice_2': 'Business Administration',
            'address': '123 Main Street, Springfield',
            'city': 'Johannesburg',
            'postal_code': '2000',
            'gender': 'Male',
            'date_of_birth': '2000-01-15',
            'emergency_contact': 'Jane Doe',
            'emergency_phone': '0839876543',
            'notes': 'Test student entry'
        };

        Object.keys(testData).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = testData[key];
                // Trigger change event for validation
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });

        showAlert('Test data filled. Remember to remove this in production!', 'info');
    }

    // Auto-advance tabs on Enter key (except in textareas)
    form.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && !e.target.type.includes('select')) {
            e.preventDefault();
            
            const currentTab = document.querySelector('.tab-pane.active');
            const currentTabId = currentTab.id;
            const tabButtons = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]'));
            const currentIndex = tabButtons.findIndex(btn => btn.getAttribute('data-bs-target') === `#${currentTabId}`);
            
            if (currentIndex < tabButtons.length - 1) {
                const nextTab = tabButtons[currentIndex + 1];
                const tabInstance = new bootstrap.Tab(nextTab);
                tabInstance.show();
            }
        }
    });

    // Show character count for textareas
    const textareas = form.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        const counter = document.createElement('div');
        counter.className = 'form-text text-end small';
        counter.textContent = `0/${textarea.maxLength || '∞'} characters`;
        
        textarea.parentNode.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = this.maxLength || '∞';
            counter.textContent = `${length}/${maxLength} characters`;
            
            if (this.maxLength && length > this.maxLength * 0.8) {
                counter.classList.add('text-warning');
            } else {
                counter.classList.remove('text-warning');
            }
        });
    });
});
</script>
</body>
</html>