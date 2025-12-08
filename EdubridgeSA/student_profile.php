<?php
// student_profile.php - Updated with proper form actions
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Use centralized configuration (load DB credentials from environment in config.php)
require_once __DIR__ . '/config.php';
if (empty($pdo) || !($pdo instanceof PDO)) {
    die('Database connection unavailable.');
}

// Simple Student Controller
class SimpleStudentController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function get($id)
    {
        try {
            $sql = "SELECT * FROM applications WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Get student error: " . $e->getMessage());
            return null;
        }
    }

    public function update($id, $data)
    {
        try {
            $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'id_number', 'school', 'grade', 'province', 'status', 'admin_notes'];
            $updates = [];
            $params = [];

            foreach ($data as $key => $value) {
                if (in_array($key, $allowedFields)) {
                    $updates[] = "$key = ?";
                    $params[] = $value;
                }
            }

            if (empty($updates)) return false;

            $params[] = $id;
            $sql = "UPDATE applications SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Update student error: " . $e->getMessage());
            return false;
        }
    }

    public function logActivity($username, $action, $details = '')
    {
        try {
            // Create activity logs table if not exists
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_username VARCHAR(100) NOT NULL,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $sql = "INSERT INTO admin_activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username, $action, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
}

// Initialize controller
$controller = new SimpleStudentController($pdo);

// Get student ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: manage_students.php');
    exit;
}

// Get student data
$student = $controller->get($id);
if (!$student) {
    header('Location: manage_students.php');
    exit;
}

$edit = isset($_GET['edit']);
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforce server-side CSRF (use require_csrf() when available)
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    $action = $_POST['action'] ?? '';

    // CSRF validation
    $csrfOk = true;
    if (!isset($_POST[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], (string)($_POST[CSRF_TOKEN_NAME] ?? ''))) {
        $message = 'Security token mismatch. Action aborted.';
        $csrfOk = false;
    }

    if ($csrfOk) {
        if (in_array($action, ['approve', 'reject', 'pending'], true)) {
            // Update status
            $newStatus = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending');
            $controller->update($id, ['status' => $newStatus]);
            $student = $controller->get($id); // Refresh data

            // Log activity
            $controller->logActivity(
                $_SESSION['admin_username'] ?? 'Admin',
                'Status update',
                "Changed status to {$newStatus} for student ID: {$id}"
            );

            $message = "Application marked as " . ucfirst($newStatus);
        }
    } elseif ($action === 'update_profile') {
        // Update profile
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'id_number' => trim($_POST['id_number'] ?? ''),
            'school' => trim($_POST['school'] ?? ''),
            'grade' => trim($_POST['grade'] ?? ''),
            'province' => trim($_POST['province'] ?? ''),
            'status' => trim($_POST['status'] ?? $student['status'] ?? 'pending')
        ];

        if ($controller->update($id, $data)) {
            $student = $controller->get($id); // Refresh data
            $controller->logActivity(
                $_SESSION['admin_username'] ?? 'Admin',
                'Profile updated',
                "Updated profile for student ID: {$id}"
            );
            $message = 'Profile updated successfully';
        } else {
            $message = 'Error updating profile';
        }
    } elseif ($action === 'request_docs') {
        // Simple email notification
        $controller->logActivity(
            $_SESSION['admin_username'] ?? 'Admin',
            'Document request',
            "Requested missing documents for student ID: {$id}"
        );
        $message = 'Document request logged (email functionality can be added later)';
    } elseif ($action === 'save_notes') {
        // Save admin notes
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        if ($controller->update($id, ['admin_notes' => $adminNotes])) {
            $student = $controller->get($id); // Refresh data
            $controller->logActivity(
                $_SESSION['admin_username'] ?? 'Admin',
                'Notes updated',
                "Updated admin notes for student ID: {$id}"
            );
            $message = 'Admin notes saved successfully';
        } else {
            $message = 'Error saving notes';
        }
    }
}

// Get documents (check what actually exists)
function getStudentDocuments($studentId)
{
    $basePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
    $documents = [];

    $docTypes = [
        'id_documents' => 'ID Document',
        'matric_certificates' => 'Matric Certificate',
        'proof_of_residence' => 'Proof of Residence',
        'additional_documents' => 'Additional Documents'
    ];

    foreach ($docTypes as $folder => $type) {
        $potentialPath = $basePath . $folder . '/' . $studentId . '.*';
        $files = glob($potentialPath);
        if (!empty($files)) {
            $documents[] = [
                'type' => $type,
                'path' => '/uploads/' . $folder . '/' . basename($files[0]),
                'exists' => true
            ];
        } else {
            $documents[] = [
                'type' => $type,
                'path' => '#',
                'exists' => false
            ];
        }
    }

    return $documents;
}

$documents = getStudentDocuments($id);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-badge.pending {
            background-color: #ffc107;
            color: #000;
        }

        .status-badge.approved {
            background-color: #198754;
            color: #fff;
        }

        .status-badge.rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .card-shadow {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .navbar {
            background-color: #2c3e50;
        }

        .document-missing {
            color: #6c757d;
            font-style: italic;
        }

        .btn-action {
            min-width: 120px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-person-vcard me-2"></i>Student Profile - EduBridgeSA
            </span>
            <div>
                <a href="manage_students.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Students
                </a>
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                </a>
                <a href="admin_logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Student Info Header -->
        <div class="card card-shadow mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="card-title mb-1">
                            <?= htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?>
                        </h4>
                        <p class="text-muted mb-0">
                            Student ID: <?= $id ?> |
                            Email: <?= htmlspecialchars($student['email'] ?? 'No email') ?> |
                            Applied: <?= htmlspecialchars($student['created_at'] ?? 'Unknown date') ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge status-badge fs-6 <?= htmlspecialchars($student['status'] ?? 'pending') ?>">
                            <?= htmlspecialchars(ucfirst($student['status'] ?? 'Pending')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column - Personal Info & Documents -->
            <div class="col-lg-8">
                <!-- Personal Information Card -->
                <div class="card card-shadow">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person me-2"></i>Personal Information
                            <?php if (!$edit): ?>
                                <a href="student_profile.php?id=<?= $id ?>&edit=1" class="btn btn-sm btn-outline-primary float-end">
                                    <i class="bi bi-pencil me-1"></i>Edit
                                </a>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($edit): ?>
                            <form method="post" action="student_profile.php?id=<?= $id ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name"
                                            value="<?= htmlspecialchars($student['first_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name"
                                            value="<?= htmlspecialchars($student['last_name'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email *</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($student['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone"
                                            value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ID Number</label>
                                        <input type="text" class="form-control" name="id_number"
                                            value="<?= htmlspecialchars($student['id_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">School</label>
                                        <input type="text" class="form-control" name="school"
                                            value="<?= htmlspecialchars($student['school'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Grade</label>
                                        <select class="form-select" name="grade">
                                            <option value="">Select Grade</option>
                                            <?php foreach (['8', '9', '10', '11', '12'] as $grade): ?>
                                                <option value="<?= $grade ?>" <?= ($student['grade'] ?? '') == $grade ? 'selected' : '' ?>>
                                                    Grade <?= $grade ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Province</label>
                                        <select class="form-select" name="province">
                                            <option value="">Select Province</option>
                                            <?php
                                            $provinces = ['Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape', 'Free State', 'North West', 'Mpumalanga', 'Northern Cape', 'Limpopo'];
                                            foreach ($provinces as $province): ?>
                                                <option value="<?= $province ?>" <?= ($student['province'] ?? '') == $province ? 'selected' : '' ?>>
                                                    <?= $province ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <?php foreach (['pending', 'approved', 'rejected'] as $status): ?>
                                                <option value="<?= $status ?>" <?= ($student['status'] ?? '') == $status ? 'selected' : '' ?>>
                                                    <?= ucfirst($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle me-1"></i>Save Changes
                                    </button>
                                    <a href="student_profile.php?id=<?= $id ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-circle me-1"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>First Name:</strong> <?= htmlspecialchars($student['first_name'] ?? 'Not provided') ?></p>
                                    <p><strong>Last Name:</strong> <?= htmlspecialchars($student['last_name'] ?? 'Not provided') ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($student['email'] ?? 'Not provided') ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($student['phone'] ?? 'Not provided') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>ID Number:</strong> <?= htmlspecialchars($student['id_number'] ?? 'Not provided') ?></p>
                                    <p><strong>School:</strong> <?= htmlspecialchars($student['school'] ?? 'Not provided') ?></p>
                                    <p><strong>Grade:</strong> <?= htmlspecialchars($student['grade'] ?? 'Not provided') ?></p>
                                    <p><strong>Province:</strong> <?= htmlspecialchars($student['province'] ?? 'Not provided') ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Documents Card -->
                <div class="card card-shadow mt-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-files me-2"></i>Student Documents
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($documents as $doc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-file-earmark me-2"></i>
                                        <?= htmlspecialchars($doc['type']) ?>
                                    </div>
                                    <div>
                                        <?php if ($doc['exists']): ?>
                                            <a href="<?= htmlspecialchars($doc['path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <a href="<?= htmlspecialchars($doc['path']) ?>" download class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Missing</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Admin Actions & Notes -->
            <div class="col-lg-4">
                <!-- Admin Actions Card -->
                <div class="card card-shadow mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>Admin Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="student_profile.php?id=<?= $id ?>" class="d-grid gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success btn-action">
                                <i class="bi bi-check2-circle me-1"></i>Approve Application
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-action">
                                <i class="bi bi-x-circle me-1"></i>Reject Application
                            </button>
                            <button type="submit" name="action" value="pending" class="btn btn-warning btn-action">
                                <i class="bi bi-hourglass-split me-1"></i>Set as Pending
                            </button>
                            <button type="submit" name="action" value="request_docs" class="btn btn-info btn-action">
                                <i class="bi bi-envelope me-1"></i>Request Documents
                            </button>
                        </form>

                        <!-- Quick Export -->
                        <div class="mt-3 pt-3 border-top">
                            <a href="exports/export_application_pdf.php?student_id=<?= $id ?>" class="btn btn-outline-primary w-100">
                                <i class="bi bi-filetype-pdf me-1"></i>Export to PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Admin Notes Card -->
                <div class="card card-shadow">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-journal-text me-2"></i>Admin Notes
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="student_profile.php?id=<?= $id ?>">
                            <input type="hidden" name="action" value="save_notes">
                            <textarea class="form-control" name="admin_notes" rows="6" placeholder="Add internal notes about this student..."><?= htmlspecialchars($student['admin_notes'] ?? '') ?></textarea>
                            <button type="submit" class="btn btn-primary mt-2 w-100">
                                <i class="bi bi-save me-1"></i>Save Notes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Application Timeline -->
                <div class="card card-shadow mt-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $stmt = $pdo->prepare("SELECT action, details, created_at FROM admin_activity_logs WHERE details LIKE ? ORDER BY created_at DESC LIMIT 5");
                            $stmt->execute(["%student ID: {$id}%"]);
                            $activities = $stmt->fetchAll();
                        } catch (PDOException $e) {
                            $activities = [];
                        }
                        ?>

                        <?php if (empty($activities)): ?>
                            <p class="text-muted text-center">No recent activity</p>
                        <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($activities as $activity): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <strong class="small"><?= htmlspecialchars($activity['action']) ?></strong>
                                            <small class="text-muted"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></small>
                                        </div>
                                        <p class="small text-muted mb-0"><?= htmlspecialchars($activity['details'] ?? '') ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Confirm destructive actions
        document.addEventListener('DOMContentLoaded', function() {
            const rejectBtn = document.querySelector('button[value="reject"]');
            if (rejectBtn) {
                rejectBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to reject this application? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html>