<?php
// admin_profile.php - Universal Admin Profile

// UNIVERSAL AUTHENTICATION - WORKS WITH BOTH LOGIN SYSTEMS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Universal authentication check - works with both systems
$is_logged_in = false;
$username = '';
$user_role = '';

// Check all possible login session variables from both systems
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $is_logged_in = true;
    $username = $_SESSION['admin_username'] ?? 'admin';
    $user_role = $_SESSION['admin_role'] ?? 'super';
} elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    $is_logged_in = true;
    $username = $_SESSION['admin_username'] ?? 'admin';
    $user_role = $_SESSION['admin_role'] ?? 'super';
}

// Redirect if not logged in
if (!$is_logged_in) {
    header('Location: admin_login_debug.php');
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

// Initialize variables
$message = '';
$msgType = 'info';
$full_name = '';
$email = '';

// Try to get user data from database if available
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        // Create admin_users table if not exists
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
        
        // Try to get current user data
        $stmt = $pdo->prepare('SELECT full_name, email, role FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $full_name = $user_data['full_name'];
            $email = $user_data['email'];
            // Use database role if available, otherwise use session role
            $user_role = $user_data['role'] ?? $user_role;
        } else {
            // Set default values for debug admin
            $full_name = $full_name ?: 'System Administrator';
            $email = $email ?: 'admin@edubridgesa.co.za';
        }
    } catch (Exception $e) {
        // Use session data if database fails
        $full_name = $full_name ?: 'System Administrator';
        $email = $email ?: 'admin@edubridgesa.co.za';
    }
} else {
    // Use session data if no database
    $full_name = $full_name ?: 'System Administrator';
    $email = $email ?: 'admin@edubridgesa.co.za';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $new_full_name = trim($_POST['full_name'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        
        if ($new_full_name && $new_email) {
            try {
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmt = $pdo->prepare('UPDATE admin_users SET full_name = ?, email = ? WHERE username = ?');
                    $stmt->execute([$new_full_name, $new_email, $username]);
                    
                    if ($stmt->rowCount() > 0) {
                        $full_name = $new_full_name;
                        $email = $new_email;
                        $message = 'Profile updated successfully!';
                        $msgType = 'success';
                    } else {
                        $message = 'No changes made or user not found in database.';
                        $msgType = 'info';
                    }
                } else {
                    $message = 'Database not available. Changes not saved.';
                    $msgType = 'warning';
                }
            } catch (Exception $e) {
                $message = 'Error updating profile: ' . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $message = 'Please fill in all fields.';
            $msgType = 'warning';
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($current_password && $new_password && $confirm_password) {
            if ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $msgType = 'warning';
            } elseif (strlen($new_password) < 8) {
                $message = 'Password must be at least 8 characters long.';
                $msgType = 'warning';
            } else {
                try {
                    if (isset($pdo) && $pdo instanceof PDO) {
                        // Get current password hash
                        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE username = ?');
                        $stmt->execute([$username]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user) {
                            // For debug admin, accept any current password
                            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $update_stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
                            $update_stmt->execute([$new_password_hash, $username]);
                            
                            $message = 'Password changed successfully!';
                            $msgType = 'success';
                        } else {
                            $message = 'User not found in database.';
                            $msgType = 'warning';
                        }
                    } else {
                        $message = 'Database not available. Password not changed.';
                        $msgType = 'warning';
                    }
                } catch (Exception $e) {
                    $message = 'Error changing password: ' . $e->getMessage();
                    $msgType = 'danger';
                }
            }
        } else {
            $message = 'Please fill in all password fields.';
            $msgType = 'warning';
        }
    }
}

// Format role for display
$display_role = ucfirst(str_replace('_', ' ', $user_role));
$is_super_admin = in_array($user_role, ['super', 'super_admin'], true);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            z-index: 1000;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .profile-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .role-badge {
            font-size: 0.8em;
        }
        
        .super-admin { background-color: #dc3545; }
        .moderator { background-color: #fd7e14; }
        .staff { background-color: #6c757d; }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
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
            <a href="admin_messages.php" class="nav-link">
                <i class="bi bi-chat-dots me-3"></i>
                <span>Messages & Notifications</span>
            </a>
            <a href="manage_admins.php" class="nav-link">
                <i class="bi bi-shield-lock me-3"></i>
                <span>Manage Admins</span>
            </a>
            <a href="admin_profile.php" class="nav-link active">
                <i class="bi bi-person me-3"></i>
                <span>Profile</span>
            </a>
            <a href="email_logs.php" class="nav-link">
                <i class="bi bi-envelope me-3"></i>
                <span>Email Logs</span>
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
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary me-3" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                
                <!-- Search Bar -->
                <form class="d-flex search-box" action="manage_students.php" method="GET">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search students...">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="d-flex align-items-center ms-auto">
                    <!-- Dark Mode Toggle -->
                    <button class="btn btn-outline-secondary me-2" id="darkModeToggle">
                        <i class="bi bi-moon"></i>
                    </button>
                    
                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            <?php echo htmlspecialchars($username); ?>
                            <span class="badge <?php echo $is_super_admin ? 'super-admin' : 'staff'; ?> role-badge ms-1">
                                <?php echo htmlspecialchars($display_role); ?>
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="admin_profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                            <a class="dropdown-item" href="admin_messages.php">
                                <i class="bi bi-envelope me-2"></i>Messages
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="admin_login_debug.php?action=logout">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content Area -->
        <div class="container-fluid py-4">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Left Column - Profile Forms -->
                <div class="col-lg-8">
                    <!-- Profile Information -->
                    <div class="card profile-card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person me-2"></i>Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" disabled>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Role</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($display_role); ?>" disabled>
                                        <div class="form-text">Your administrative role</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="card profile-card">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-lock me-2"></i>Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" required>
                                    <div class="form-text">Password must contain at least 8 characters with uppercase, lowercase, and numbers</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="bi bi-key me-1"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Account Info -->
                <div class="col-lg-4">
                    <!-- Account Overview -->
                    <div class="card profile-card mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>Account Overview
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary rounded-circle p-3 me-3">
                                    <i class="bi bi-person text-white fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($username); ?></h6>
                                    <span class="badge <?php echo $is_super_admin ? 'super-admin' : 'staff'; ?>">
                                        <?php echo htmlspecialchars($display_role); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="small">
                                <div class="mb-2">
                                    <strong>Full Name:</strong><br>
                                    <?php echo htmlspecialchars($full_name); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Email:</strong><br>
                                    <?php echo htmlspecialchars($email); ?>
                                </div>
                                <div class="mb-2">
                                    <strong>Status:</strong><br>
                                    <span class="badge bg-success">Active</span>
                                </div>
                                <div>
                                    <strong>Last Login:</strong><br>
                                    <?php echo date('M j, Y g:i A'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Tips -->
                    <div class="card profile-card">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-check me-2"></i>Security Tips
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled small">
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Use a strong, unique password
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Enable two-factor authentication if available
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Never share your login credentials
                                </li>
                                <li class="mb-2">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Log out after each session
                                </li>
                                <li>
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    Regularly update your password
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Dark Mode Toggle
        document.getElementById('darkModeToggle').addEventListener('click', function() {
            const html = document.documentElement;
            const theme = html.getAttribute('data-bs-theme');
            const newTheme = theme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', newTheme);
            
            // Update icon
            const icon = this.querySelector('i');
            icon.className = newTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
            
            // Save preference
            localStorage.setItem('theme', newTheme);
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', savedTheme);
        const darkModeIcon = document.querySelector('#darkModeToggle i');
        darkModeIcon.className = savedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon';

        // Sidebar Toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('d-none');
            document.querySelector('.main-content').classList.toggle('ms-0');
        });
    </script>
</body>
</html>