<?php
// manage_admins.php - Universal Admin Management

// UNIVERSAL AUTHENTICATION - WORKS WITH BOTH LOGIN SYSTEMS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Universal authentication check - works with both systems
$is_logged_in = false;
$username = '';

// Check all possible login session variables from both systems
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $is_logged_in = true;
    $username = $_SESSION['admin_username'] ?? 'admin';
} elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    $is_logged_in = true;
    $username = $_SESSION['admin_username'] ?? 'admin';
}

// Redirect if not logged in
if (!$is_logged_in) {
    header('Location: admin_login_debug.php');
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

// Role check (support both 'super' and 'super_admin' labels)
$currentRole = $_SESSION['admin_role'] ?? 'staff';
$isSuperAdmin = in_array($currentRole, ['super', 'super_admin'], true);

// Create admin_users table if not exists (only if DB available)
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'moderator', 'staff') DEFAULT 'staff',
            full_name VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        // Insert default admin if no admins exist
        $checkAdmins = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
        if ($checkAdmins == 0) {
            $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES 
                ('admin', 'admin@edubridgesa.co.za', '$defaultPassword', 'System Administrator', 'super_admin')");
        }
    } catch (Exception $e) {
        // Table might already exist
    }
}

$message = '';
$msgType = 'info';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enforce server-side CSRF when available
    if (function_exists('require_csrf')) {
        require_csrf();
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add_admin') {
        if (!$isSuperAdmin) {
            $message = 'Only super admins can add administrators.';
            $msgType = 'warning';
        } else {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $full_name = trim($_POST['full_name'] ?? '');
            $role = $_POST['role'] ?? 'staff';

            if ($username && $email && $password && $full_name) {
                try {
                    // Ensure role is valid
                    $validRoles = ['super_admin', 'moderator', 'staff'];
                    if (!in_array($role, $validRoles, true)) {
                        $role = 'staff';
                    }

                    // Uniqueness check
                    $check = $pdo->prepare('SELECT id FROM admin_users WHERE username = ? OR email = ? LIMIT 1');
                    $check->execute([$username, $email]);
                    if ($check->fetch()) {
                        $message = 'Username or Email already exists.';
                        $msgType = 'warning';
                    } else {
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = 'INSERT INTO admin_users (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)';
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$username, $email, $password_hash, $full_name, $role]);
                        $message = "Admin user '$username' added successfully";
                        $msgType = 'success';
                    }
                } catch (Throwable $e) {
                    $message = 'Error adding admin: ' . htmlspecialchars($e->getMessage());
                    $msgType = 'danger';
                }
            } else {
                $message = 'Please fill all required fields';
                $msgType = 'warning';
            }
        }
    }
}

// Get all admins
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->query('SELECT id, username, email, role, created_at FROM admin_users ORDER BY created_at DESC LIMIT 500');
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $admins = [];
        $message = 'Database connection not available.';
        $msgType = 'danger';
    }
} catch (Throwable $e) {
    $admins = [];
    $message = 'Error loading admins: ' . htmlspecialchars($e->getMessage());
    $msgType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card-shadow {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 10px;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .role-badge.super_admin {
            background-color: #dc3545;
        }

        .role-badge.moderator {
            background-color: #fd7e14;
        }

        .role-badge.staff {
            background-color: #6c757d;
        }

        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            min-height: 100vh;
        }

        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" style="width: 250px; position: fixed; left: 0; top: 0; height: 100vh;">
        <div class="p-3 text-center border-bottom border-secondary">
            <h5 class="text-white mb-0">EduBridgeSA</h5>
            <small class="text-white-50">Admin Panel</small>
        </div>

        <nav class="nav flex-column p-3">
            <a href="admin_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2 me-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_students.php" class="nav-link">
                <i class="bi bi-people me-3"></i>
                <span>Manage Students</span>
            </a>
            <a href="manage_admins.php" class="nav-link active">
                <i class="bi bi-shield-lock me-3"></i>
                <span>Manage Admins</span>
            </a>
            <a href="system_status.php" class="nav-link">
                <i class="bi bi-graph-up me-3"></i>
                <span>System Status</span>
            </a>
            <div class="mt-auto p-3">
                <a href="admin_login_debug.php?action=logout" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right me-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div style="margin-left: 250px;">
        <!-- Top Navigation -->
        <nav class="navbar navbar-dark mb-4">
            <div class="container-fluid">
                <span class="navbar-brand">
                    <i class="bi bi-shield-lock me-2"></i>Manage Administrators
                </span>
                <div class="text-white">
                    <i class="bi bi-person-circle me-2"></i>
                    <?php echo htmlspecialchars($username); ?>
                    <span class="badge bg-light text-dark ms-2"><?php echo htmlspecialchars($currentRole); ?></span>
                </div>
            </div>
        </nav>

        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-<?= htmlspecialchars($msgType) ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Add Admin Form -->
                <div class="col-md-4">
                    <div class="card card-shadow">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-plus me-2"></i>Add New Admin
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!$isSuperAdmin): ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    Only Super Administrators can add new admin users.
                                </div>
                            <?php endif; ?>

                            <form method="post" action="manage_admins.php">
                                <input type="hidden" name="action" value="add_admin">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required
                                        <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" required
                                        <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" required
                                        <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required
                                        <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                                        <option value="staff">Staff</option>
                                        <option value="moderator">Moderator</option>
                                        <?php if ($isSuperAdmin): ?>
                                            <option value="super_admin">Super Admin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" <?= $isSuperAdmin ? '' : 'disabled' ?>>
                                    <i class="bi bi-plus-circle me-1"></i>Add Admin
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Admin List -->
                <div class="col-md-8">
                    <div class="card card-shadow">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people me-2"></i>Administrator List
                                <span class="badge bg-primary ms-2"><?php echo count($admins); ?> users</span>
                            </h5>
                            <div class="text-muted small">
                                Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($admins)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-people display-4"></i>
                                    <p class="mt-2">No administrators found</p>
                                    <p class="small">Default admin account will be created automatically</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Username</th>
                                                <th>Full Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Last Login</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($admins as $admin): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($admin['username']) ?></strong>
                                                        <?php if ($admin['username'] === $username): ?>
                                                            <span class="badge bg-primary">You</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($admin['email']) ?></td>
                                                    <td>
                                                        <span class="badge role-badge <?= $admin['role'] ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $admin['is_active'] ? 'success' : 'danger' ?>">
                                                            <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never' ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?= date('M j, Y', strtotime($admin['created_at'])) ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6><i class="bi bi-info-circle me-2"></i>Information</h6>
                                    <p class="mb-1 small">
                                        <strong>Current User:</strong> <?php echo htmlspecialchars($username); ?>
                                        (<?php echo htmlspecialchars($currentRole); ?>)
                                    </p>
                                    <p class="mb-0 small text-muted">
                                        Only Super Administrators can add new admin users. Edit and delete functionality coming soon.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>