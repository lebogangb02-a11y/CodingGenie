<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Basic authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Check required files
if (!file_exists(__DIR__ . '/config.php')) {
    die("Configuration file missing");
}

require_once __DIR__ . '/config.php';

// Verify database connection
if (!isset($pdo) || !$pdo instanceof PDO) {
    die("Database connection failed");
}

// Include the StudentController
require_once __DIR__ . '/controllers/StudentController.php';

// Initialize controller
$controller = new StudentController($pdo);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    // Server-side CSRF enforcement (if helpers available)
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    $selected = array_map('intval', $_POST['selected'] ?? []);
    $action = $_POST['bulk_action'];

    if ($selected) {
        switch ($action) {
            case 'approve':
                $controller->updateStatusBulk($selected, 'approved');
                $_SESSION['message'] = "Approved " . count($selected) . " student(s)";
                break;
            case 'reject':
                $controller->updateStatusBulk($selected, 'rejected');
                $_SESSION['message'] = "Rejected " . count($selected) . " student(s)";
                break;
            case 'pending':
                $controller->updateStatusBulk($selected, 'pending');
                $_SESSION['message'] = "Marked " . count($selected) . " student(s) as pending";
                break;
            case 'delete':
                foreach ($selected as $id) {
                    $controller->delete($id);
                }
                $_SESSION['message'] = "Deleted " . count($selected) . " student(s)";
                break;
            case 'export_csv':
                $controller->exportSelectedCSV($selected);
                exit; // Controller handles output and exit
        }
        header('Location: manage_students.php');
        exit;
    }
}

// Handle single actions
if (isset($_GET['delete'])) {
    $controller->delete((int)$_GET['delete']);
    $_SESSION['message'] = "Student deleted successfully";
    header('Location: manage_students.php');
    exit;
}

if (isset($_GET['update_status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];
    if (in_array($status, ['pending', 'approved', 'rejected'])) {
        $controller->update($id, ['status' => $status]);
        $_SESSION['message'] = "Status updated to " . $status;
    }
    header('Location: manage_students.php?' . http_build_query(array_diff_key($_GET, ['update_status' => '', 'id' => '', 'status' => ''])));
    exit;
}

// Get parameters
$q = trim($_GET['q'] ?? '');
$filters = [
    'grade' => $_GET['grade'] ?? '',
    'status' => $_GET['status'] ?? '',
    'province' => $_GET['province'] ?? '',
    'program' => $_GET['program'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Fetch data using controller
$data = $controller->list($filters, $q, $page, $perPage);
$students = $data['rows'];
$total = $data['total'];
$pages = max(1, ceil($total / $perPage));

// Get statistics for dashboard
$stats = $controller->stats();

// Get filter options (simplified - you can enhance this in your controller)
try {
    $grades = $pdo->query("SELECT DISTINCT grade FROM applications WHERE grade IS NOT NULL AND grade != '' ORDER BY grade")->fetchAll(PDO::FETCH_COLUMN);
    $provinces = $pdo->query("SELECT DISTINCT province FROM applications WHERE province IS NOT NULL AND province != '' ORDER BY province")->fetchAll(PDO::FETCH_COLUMN);
    $programs = $pdo->query("SELECT DISTINCT program_choice_1 FROM applications WHERE program_choice_1 IS NOT NULL AND program_choice_1 != '' ORDER BY program_choice_1")->fetchAll(PDO::FETCH_COLUMN);
    $statuses = $pdo->query("SELECT DISTINCT status FROM applications WHERE status IS NOT NULL AND status != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $grades = $provinces = $programs = $statuses = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stats-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #198754;
            color: #fff;
        }

        .badge-rejected {
            background-color: #dc3545;
            color: #fff;
        }

        .badge-under_review {
            background-color: #0dcaf0;
            color: #000;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.025);
        }

        .bulk-actions {
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .export-dropdown .dropdown-menu {
            min-width: 200px;
        }

        .quick-actions .btn {
            font-size: 0.875rem;
        }

        .search-highlight {
            background-color: #fff3cd;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <span class="navbar-brand">
                <i class="bi bi-people me-2"></i>Manage Students
            </span>
            <div>
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
        <!-- Flash Message -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50">Total Students</h6>
                                <h3 class="mb-0"><?= $total ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-dark-50">Pending</h6>
                                <h3 class="mb-0"><?= $stats['pending'] ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock-history fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50">Approved</h6>
                                <h3 class="mb-0"><?= $stats['approved'] ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-white-50">Rejected</h6>
                                <h3 class="mb-0"><?= $stats['rejected'] ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-x-circle-fill fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Filters -->
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-funnel me-2"></i>Filters & Search</h5>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                    <i class="bi bi-chevron-down"></i> Toggle
                </button>
            </div>
            <div class="card-body collapse show" id="filterCollapse">
                <form method="get" action="manage_students.php">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Grade</label>
                            <select name="grade" class="form-select form-select-sm">
                                <option value="">All Grades</option>
                                <?php foreach ($grades as $grade): ?>
                                    <option value="<?= htmlspecialchars($grade) ?>" <?= $filters['grade'] === $grade ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($grade) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                                        <?= ucfirst(htmlspecialchars($status)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold">Province</label>
                            <select name="province" class="form-select form-select-sm">
                                <option value="">All Provinces</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?= htmlspecialchars($province) ?>" <?= $filters['province'] === $province ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($province) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Program</label>
                            <select name="program" class="form-select form-select-sm">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $program): ?>
                                    <option value="<?= htmlspecialchars($program) ?>" <?= $filters['program'] === $program ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($program) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Date Range</label>
                            <div class="input-group input-group-sm">
                                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" placeholder="From">
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>" placeholder="To">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Search</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by name, email, ID number, school...">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end gap-2">
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="bi bi-funnel me-1"></i> Apply Filters
                            </button>
                            <a href="manage_students.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-clockwise me-1"></i> Reset
                            </a>
                            <div class="export-dropdown ms-auto">
                                <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-download me-1"></i> Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="export_tools.php?export=students_csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Export All to CSV</a></li>
                                    <li><a class="dropdown-item" href="export_tools.php?export=statistics_excel"><i class="bi bi-file-earmark-excel me-2"></i>Statistics Report</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions & Students Table -->
        <form method="post" id="bulkForm">
            <!-- Bulk Actions -->
            <div class="bulk-actions p-3 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-auto">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll" onclick="toggleAll(this, 'selected[]')">
                            <label class="form-check-label small fw-bold" for="selectAll">Select All</label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <select name="bulk_action" class="form-select form-select-sm" required onchange="updateBulkButton(this)">
                            <option value="">Bulk Actions</option>
                            <option value="approve">Approve Selected</option>
                            <option value="reject">Reject Selected</option>
                            <option value="pending">Mark as Pending</option>
                            <option value="export_csv">Export Selected as CSV</option>
                            <option value="delete" class="text-danger">Delete Selected</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary" id="bulkActionBtn">
                            <i class="bi bi-gear me-1"></i> Apply
                        </button>
                    </div>
                    <div class="col-auto ms-auto">
                        <a class="btn btn-success btn-sm" href="add_student.php">
                            <i class="bi bi-person-plus me-1"></i> Add New Student
                        </a>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people me-2"></i>Students (<?= $total ?> found)
                    </h5>
                    <div class="text-muted small">
                        Page <?= $page ?> of <?= $pages ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40"></th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>ID Number</th>
                                    <th>School</th>
                                    <th>Grade</th>
                                    <th>Status</th>
                                    <th>Date Applied</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="bi bi-people display-4 d-block mb-2"></i>
                                            No students found matching your criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student):
                                        $name = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                                        $status = $student['status'] ?? 'pending';
                                        $email = $student['email'] ?? '';
                                        $phone = $student['phone'] ?? '';
                                        $school = $student['school'] ?? '';
                                        $id_number = $student['id_number'] ?? '';
                                        $grade = $student['grade'] ?? '';
                                        $date_applied = $student['date_applied'] ?? '';
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected[]" value="<?= (int)$student['id'] ?>" class="row-checkbox">
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($name ?: 'N/A') ?></div>
                                                <?php if (!empty($student['program_choice_1'])): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($student['program_choice_1']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($email ?: 'N/A') ?></td>
                                            <td><?= htmlspecialchars($phone ?: 'N/A') ?></td>
                                            <td><code><?= htmlspecialchars($id_number ?: 'N/A') ?></code></td>
                                            <td><?= htmlspecialchars($school ?: 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($grade ?: 'N/A') ?></span>
                                            </td>
                                            <td>
                                                <span class="badge status-badge badge-<?= $status ?>">
                                                    <?= ucfirst(htmlspecialchars($status)) ?>
                                                </span>
                                                <div class="btn-group quick-actions ms-1">
                                                    <button type="button" class="btn btn-xs btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"></button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['update_status' => 1, 'id' => $student['id'], 'status' => 'pending'])) ?>">Mark Pending</a></li>
                                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['update_status' => 1, 'id' => $student['id'], 'status' => 'approved'])) ?>">Approve</a></li>
                                                        <li><a class="dropdown-item" href="?<?= http_build_query(array_merge($_GET, ['update_status' => 1, 'id' => $student['id'], 'status' => 'rejected'])) ?>">Reject</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?= htmlspecialchars(date('M j, Y', strtotime($date_applied))) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a class="btn btn-outline-primary" href="student_profile.php?id=<?= (int)$student['id'] ?>" title="View Profile">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a class="btn btn-outline-secondary" href="student_profile.php?id=<?= (int)$student['id'] ?>&edit=1" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if (file_exists('exports/export_application_pdf.php')): ?>
                                                        <a class="btn btn-outline-info" href="exports/export_application_pdf.php?student_id=<?= (int)$student['id'] ?>" title="Export PDF">
                                                            <i class="bi bi-filetype-pdf"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a class="btn btn-outline-danger" href="?delete=<?= (int)$student['id'] ?>" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                    </li>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($pages, $page + 2);

                    for ($i = $startPage; $i <= $endPage; $i++):
                        $active = $i == $page ? 'active' : '';
                    ?>
                        <li class="page-item <?= $active ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(source, name) {
            const checkboxes = document.getElementsByName(name);
            for (let checkbox of checkboxes) {
                checkbox.checked = source.checked;
            }
        }

        function updateBulkButton(select) {
            const btn = document.getElementById('bulkActionBtn');
            const value = select.value;

            if (value === 'delete') {
                btn.className = 'btn btn-sm btn-danger';
                btn.innerHTML = '<i class="bi bi-trash me-1"></i> Delete Selected';
            } else if (value === 'export_csv') {
                btn.className = 'btn btn-sm btn-success';
                btn.innerHTML = '<i class="bi bi-download me-1"></i> Export Selected';
            } else {
                btn.className = 'btn btn-sm btn-primary';
                btn.innerHTML = '<i class="bi bi-gear me-1"></i> Apply';
            }
        }

        // Add confirmation for bulk delete
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
            const action = this.bulk_action.value;
            const selected = Array.from(this.elements['selected[]']).filter(cb => cb.checked);

            if (action === 'delete' && selected.length > 0) {
                if (!confirm(`Are you sure you want to delete ${selected.length} student(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            }

            // Add loading state for exports
            if (action === 'export_csv' && selected.length > 0) {
                const btn = this.querySelector('#bulkActionBtn');
                btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Preparing...';
                btn.disabled = true;
            }
        });
    </script>
</body>

</html>