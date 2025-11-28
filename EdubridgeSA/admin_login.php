<?php
/**
 * Admin Login - EduBridge SA
 * Now with proper database authentication and debug info
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config
require_once __DIR__ . '/config.php';

// Handle login form submission
if ($_POST['action'] ?? '' === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        // Database connection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        // Look for admin in database
        $stmt = $pdo->prepare("SELECT id, name, username, email, role, password_hash FROM admins WHERE (username = ? OR email = ?) AND role IN ('super', 'admin', 'staff')");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();
        
        if ($admin) {
            // Debug: Show what we're comparing
            $hashed_input = hash('sha256', $password . AUTH_SALT);
            $debug_info = "
                <div class='alert alert-info'>
                    <strong>Debug Information:</strong><br>
                    Username: " . htmlspecialchars($username) . "<br>
                    Password: " . htmlspecialchars($password) . "<br>
                    AUTH_SALT: " . htmlspecialchars(AUTH_SALT) . "<br>
                    Input Hash: " . htmlspecialchars($hashed_input) . "<br>
                    Stored Hash: " . htmlspecialchars($admin['password_hash']) . "<br>
                    Match: " . ($hashed_input === $admin['password_hash'] ? 'YES' : 'NO') . "
                </div>
            ";
            
            // Check password with custom hashing
            if ($hashed_input === $admin['password_hash']) {
                // Successful login
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                $_SESSION['user_id'] = $admin['id'];
                
                // Update last login
                $update_stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$admin['id']]);
                
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "Admin user not found.";
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle logout
if ($_GET['action'] ?? '' === 'logout') {
    session_destroy();
    session_start();
    $message = "Logged out successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-card">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Admin Portal</h4>
                        <small>EduBridgeSA Management System</small>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($debug_info)) echo $debug_info; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($message)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            
                            <div class="mb-3">
                                <label class="form-label">Username or Email</label>
                                <input type="text" name="username" class="form-control" required 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? 'superadmin'); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required 
                                       value="<?php echo htmlspecialchars($_POST['password'] ?? 'password'); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">Login to Admin Panel</button>
                        </form>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6>Test Credentials (try these):</h6>
                            <small class="text-muted">
                                • <strong>superadmin</strong> / password<br>
                                • <strong>superadmin</strong> / admin123<br>
                                • <strong>admin</strong> / password<br>
                                • <strong>staff</strong> / password
                            </small>
                            
                            <div class="mt-3">
                                <small class="text-danger">
                                    <strong>Note:</strong> If passwords don't work, run this SQL to reset:<br>
                                    <code>UPDATE admins SET password_hash = '5e884898da28047151d0e56f8dc6292773603d0d6aabbdd62a11ef721d1542d8' WHERE id IN (1,2,3);</code>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-white">
                        &copy; <?php echo date('Y'); ?> EduBridgeSA | Debug Login System
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>