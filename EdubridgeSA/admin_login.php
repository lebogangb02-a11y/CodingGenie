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
            // Prefer modern password_hash()/password_verify(). Support legacy SHA256 hashes by upgrading them.
            $storedHash = $admin['password_hash'] ?? '';

            $passwordOk = false;
            if (!empty($storedHash) && password_verify($password, $storedHash)) {
                $passwordOk = true;
            } else {
                // Legacy fallback: SHA256(secret-salt)
                $legacyHash = hash('sha256', $password . (defined('AUTH_SALT') ? AUTH_SALT : ''));
                if (!empty($storedHash) && hash_equals($legacyHash, $storedHash)) {
                    // Rehash with password_hash() for future logins
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $rehashStmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
                        $rehashStmt->execute([$newHash, $admin['id']]);
                    } catch (Exception $e) {
                        // Non-fatal: continue with login
                        error_log('Failed to rehash admin password: ' . $e->getMessage());
                    }
                    $passwordOk = true;
                }
            }

            if ($passwordOk) {
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
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
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
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-lg">Login to Admin Panel</button>
                        </form>

                        <div class="mt-4 p-3 bg-light rounded">
                            <h6>Administrator Access</h6>
                            <small class="text-muted">Contact your system administrator to manage admin accounts or reset passwords.</small>
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