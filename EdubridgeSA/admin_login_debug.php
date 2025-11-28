<?php
/**
 * Debug Admin Login - Simple version to identify issues
 */

// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Session Status: " . session_status() . " -->";
echo "<!-- Session ID: " . session_id() . " -->";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Simple credentials
    $admin_username = 'admin';
    $admin_password = 'reset123';
    
    echo "<!-- Username: $username, Password provided: " . (!empty($password) ? 'YES' : 'NO') . " -->";
    
    if ($username === $admin_username && $password === $admin_password) {
        // Successful login
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        
        echo "<!-- Login successful, redirecting... -->";
        
        // Simple redirect without fancy headers
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials. Please try admin/reset123';
        echo "<!-- Login failed -->";
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    session_start();
    $message = 'You have been logged out.';
}

// Check if logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login Debug - EduBridgeSA</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 500px; 
            margin: 50px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .container { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group { 
            margin: 15px 0; 
        }
        label { 
            display: block; 
            margin-bottom: 5px; 
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            box-sizing: border-box;
        }
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 10px; 
            border-radius: 5px; 
            margin: 10px 0;
        }
        .debug-info {
            background: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Admin Login (Debug)</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($message)): ?>
            <div class="success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($is_logged_in): ?>
            <div class="success">
                <h3>✅ Logged In Successfully!</h3>
                <p>Username: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                <p>Login Time: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
                <a href="admin_dashboard.php" class="btn">Go to Dashboard</a>
                <a href="?action=logout" class="btn" style="background: #dc3545;">Logout</a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" value="reset123" required>
                </div>
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Session Status: <?php echo session_status(); ?><br>
                Session ID: <?php echo session_id(); ?><br>
                PHP Version: <?php echo PHP_VERSION; ?><br>
                Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 5px;">
                <strong>Test Credentials:</strong><br>
                Username: <code>admin</code><br>
                Password: <code>reset123</code>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>