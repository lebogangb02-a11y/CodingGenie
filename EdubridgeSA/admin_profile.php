<?php
// Enhanced Admin Profile — view basic info and change password
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/admin_layout.php';

admin_require_login();

$msg = '';
$msgType = 'info';
$username = $_SESSION['admin_username'] ?? 'admin';
$role = $_SESSION['admin_role'] ?? 'super';
$adminRow = null;
$activityLogs = [];
$loginHistory = [];

// Ensure admins table exists with enhanced structure
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            name VARCHAR(100) NOT NULL,
            role ENUM('super','admin','staff') NOT NULL DEFAULT 'staff',
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            login_attempts INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_role (role),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create admin activity logs table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_username VARCHAR(100) NOT NULL,
            admin_id INT,
            action VARCHAR(255) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_username (admin_username),
            INDEX idx_created_at (created_at),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Create admin login history table
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_username VARCHAR(100) NOT NULL,
            admin_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            success BOOLEAN DEFAULT TRUE,
            failure_reason VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_username (admin_username),
            INDEX idx_created_at (created_at),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
    } catch (Throwable $e) {
        $msg = 'Database initialization warning: ' . htmlspecialchars($e->getMessage());
        $msgType = 'warning';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password']) && isset($pdo) && $pdo instanceof PDO) {
        $currentPass = trim($_POST['current_password'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $confirmPass = trim($_POST['confirm_password'] ?? '');
        
        // Enhanced validation
        $errors = [];
        
        if (empty($currentPass)) {
            $errors[] = 'Current password is required';
        }
        
        if (empty($newPass)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPass) < 8) {
            $errors[] = 'New password must be at least 8 characters long';
        } elseif (!preg_match('/[A-Z]/', $newPass)) {
            $errors[] = 'New password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $newPass)) {
            $errors[] = 'New password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $newPass)) {
            $errors[] = 'New password must contain at least one number';
        }
        
        if ($newPass !== $confirmPass) {
            $errors[] = 'New passwords do not match';
        }
        
        // Verify current password
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$admin || !password_verify($currentPass, $admin['password_hash'])) {
                    $errors[] = 'Current password is incorrect';
                }
            } catch (Throwable $e) {
                $errors[] = 'Error verifying current password';
            }
        }
        
        if (empty($errors)) {
            try {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE username = ?');
                $stmt->execute([$hash, $username]);
                
                $msg = 'Password updated successfully.';
                $msgType = 'success';
                
                // Log activity
                try {
                    $logStmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_username, admin_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
                    $logStmt->execute([
                        $username,
                        $adminRow['id'] ?? null,
                        'Password Change',
                        'Admin changed their password',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (Throwable $ignore) {}
                
            } catch (Throwable $e) {
                $msg = 'Error updating password: ' . htmlspecialchars($e->getMessage());
                $msgType = 'danger';
            }
        } else {
            $msg = implode('<br>', $errors);
            $msgType = 'danger';
        }
    }
    
    // Handle profile update
    if (isset($_POST['update_profile']) && isset($pdo) && $pdo instanceof PDO) {
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        
        $errors = [];
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare('UPDATE admins SET email = ?, name = ?, updated_at = NOW() WHERE username = ?');
                $stmt->execute([$email, $name, $username]);
                
                $msg = 'Profile updated successfully.';
                $msgType = 'success';
                
                // Log activity
                try {
                    $logStmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_username, admin_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
                    $logStmt->execute([
                        $username,
                        $adminRow['id'] ?? null,
                        'Profile Update',
                        'Admin updated profile information',
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (Throwable $ignore) {}
                
            } catch (Throwable $e) {
                $msg = 'Error updating profile: ' . htmlspecialchars($e->getMessage());
                $msgType = 'danger';
            }
        } else {
            $msg = implode('<br>', $errors);
            $msgType = 'danger';
        }
    }
}

// Load admin record
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, name, role, is_active, last_login, created_at, updated_at FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $adminRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        
        // Load recent activity logs
        $activityStmt = $pdo->prepare('SELECT action, details, ip_address, created_at FROM admin_activity_logs WHERE admin_username = ? ORDER BY created_at DESC LIMIT 10');
        $activityStmt->execute([$username]);
        $activityLogs = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load login history
        $loginStmt = $pdo->prepare('SELECT ip_address, success, failure_reason, created_at FROM admin_login_history WHERE admin_username = ? ORDER BY created_at DESC LIMIT 10');
        $loginStmt->execute([$username]);
        $loginHistory = $loginStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Throwable $e) { 
        // Silently fail - these are non-critical features
    }
}

// Function to format time ago
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff/86400) . ' days ago';
    return date('M j, Y', $time);
}

admin_header('Admin Profile');
?>
<style>
.profile-card {
    border: none;
    border-radius: 15px;
    transition: transform 0.2s;
}
.profile-card:hover {
    transform: translateY(-2px);
}
.stats-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 15px;
}
.activity-item {
    border-left: 3px solid #007bff;
    padding-left: 15px;
    margin-bottom: 1rem;
}
.login-success {
    border-left-color: #28a745;
}
.login-failure {
    border-left-color: #dc3545;
}
.password-strength {
    height: 5px;
    border-radius: 2px;
    margin-top: 5px;
}
.strength-weak { background-color: #dc3545; width: 25%; }
.strength-fair { background-color: #ffc107; width: 50%; }
.strength-good { background-color: #28a745; width: 75%; }
.strength-strong { background-color: #20c997; width: 100%; }
</style>

<div class="container-fluid">
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo htmlspecialchars($msgType); ?> alert-dismissible fade show">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column - Profile Information -->
        <div class="col-lg-8">
            <div class="row">
                <!-- Profile Card -->
                <div class="col-md-6 mb-4">
                    <div class="card profile-card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-circle me-2"></i>Profile Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="admin_profile.php">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($username); ?>" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?php echo htmlspecialchars($adminRow['name'] ?? ''); ?>" 
                                           placeholder="Enter your full name">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($adminRow['email'] ?? ''); ?>" 
                                           placeholder="your.email@example.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars(ucfirst($role)); ?>" readonly>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="col-md-6 mb-4">
                    <div class="card profile-card shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-lock me-2"></i>Change Password
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="admin_profile.php" id="passwordForm">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" id="newPassword" required 
                                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                           title="Must contain at least 8 characters, one uppercase, one lowercase, and one number">
                                    <div class="password-strength" id="passwordStrength"></div>
                                    <div class="form-text">
                                        <small>Password must contain at least 8 characters with uppercase, lowercase, and numbers</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword" required>
                                    <div class="form-text" id="passwordMatch"></div>
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="bi bi-key me-1"></i>Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($activityLogs)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($activityLogs as $log): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                            <?php if (!empty($log['details'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($log['details']); ?></div>
                                            <?php endif; ?>
                                            <div class="text-muted smaller">IP: <?php echo htmlspecialchars($log['ip_address']); ?></div>
                                        </div>
                                        <small class="text-muted"><?php echo time_ago($log['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column - Stats & Login History -->
        <div class="col-lg-4">
            <!-- Account Stats -->
            <div class="card stats-card text-white mb-4">
                <div class="card-body">
                    <h6 class="card-title">Account Overview</h6>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="h4 mb-0"><?php echo count($activityLogs); ?></div>
                            <small>Recent Activities</small>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h4 mb-0"><?php echo count($loginHistory); ?></div>
                            <small>Login Sessions</small>
                        </div>
                    </div>
                    <?php if ($adminRow): ?>
                        <div class="small">
                            <div><i class="bi bi-calendar me-2"></i>Created: <?php echo date('M j, Y', strtotime($adminRow['created_at'])); ?></div>
                            <?php if ($adminRow['last_login']): ?>
                                <div><i class="bi bi-clock me-2"></i>Last Login: <?php echo time_ago($adminRow['last_login']); ?></div>
                            <?php endif; ?>
                            <div><i class="bi bi-pencil me-2"></i>Updated: <?php echo time_ago($adminRow['updated_at']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Login History -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lock me-2"></i>Login History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($loginHistory)): ?>
                        <div class="text-center text-muted py-3">
                            <i class="bi bi-shield-check display-4"></i>
                            <p class="mt-2">No login history</p>
                        </div>
                    <?php else: ?>
                        <div class="login-history">
                            <?php foreach ($loginHistory as $login): ?>
                                <div class="activity-item <?php echo $login['success'] ? 'login-success' : 'login-failure'; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo $login['success'] ? 'Successful Login' : 'Failed Login'; ?></strong>
                                            <div class="text-muted small">IP: <?php echo htmlspecialchars($login['ip_address']); ?></div>
                                            <?php if (!$login['success'] && !empty($login['failure_reason'])): ?>
                                                <div class="text-danger small"><?php echo htmlspecialchars($login['failure_reason']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted"><?php echo time_ago($login['created_at']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-shield-check me-2"></i>Security Tips
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="small text-muted mb-0">
                        <li>Use a strong, unique password</li>
                        <li>Enable two-factor authentication if available</li>
                        <li>Never share your login credentials</li>
                        <li>Log out when using public computers</li>
                        <li>Regularly review your login history</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordMatch = document.getElementById('passwordMatch');
    const passwordForm = document.getElementById('passwordForm');

    // Password strength indicator
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        passwordStrength.className = 'password-strength';
        if (password.length === 0) {
            passwordStrength.style.width = '0%';
        } else if (strength <= 2) {
            passwordStrength.className += ' strength-weak';
        } else if (strength === 3) {
            passwordStrength.className += ' strength-fair';
        } else if (strength === 4) {
            passwordStrength.className += ' strength-good';
        } else {
            passwordStrength.className += ' strength-strong';
        }
    });

    // Password confirmation check
    confirmPassword.addEventListener('input', function() {
        if (newPassword.value !== this.value) {
            passwordMatch.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
        } else {
            passwordMatch.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
        }
    });

    // Form submission validation
    passwordForm.addEventListener('submit', function(e) {
        if (newPassword.value !== confirmPassword.value) {
            e.preventDefault();
            alert('Please make sure your passwords match.');
            confirmPassword.focus();
        }
        
        // Add loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Updating...';
        submitBtn.disabled = true;
    });
});
</script>

<?php admin_footer(); ?>