<?php
require_once 'config.php';
require_once 'session_config.php';
require_once __DIR__ . '/includes/upload_helper.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: student-login.php');
    exit();
}

// Generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST[CSRF_TOKEN_NAME]) || $_POST[CSRF_TOKEN_NAME] !== $_SESSION[CSRF_TOKEN_NAME]) {
        $message = 'Security token mismatch. Please try again.';
        $message_type = 'error';
    } else {
        try {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_preferences') {
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
                $newsletter_subscription = isset($_POST['newsletter_subscription']) ? 1 : 0;
                $application_updates = isset($_POST['application_updates']) ? 1 : 0;
                $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;
                $language_preference = $_POST['language_preference'] ?? 'en';
                $timezone = $_POST['timezone'] ?? 'Africa/Johannesburg';

                $stmt = $pdo->prepare(
                    "UPDATE users SET 
                        email_notifications = ?,
                        sms_notifications = ?,
                        newsletter_subscription = ?,
                        application_updates = ?,
                        marketing_emails = ?,
                        language_preference = ?,
                        timezone = ?,
                        updated_at = NOW()
                    WHERE student_id = ?"
                );
                $stmt->execute([
                    $email_notifications,
                    $sms_notifications,
                    $newsletter_subscription,
                    $application_updates,
                    $marketing_emails,
                    $language_preference,
                    $timezone,
                    $_SESSION['student_id']
                ]);

                $message = 'Your preferences have been updated successfully.';
                $message_type = 'success';
            } elseif ($action === 'upload_profile_picture') {
                // Handle profile picture upload via centralized helper
                if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                    $res = store_uploaded_file(
                        $_FILES['profile_picture'],
                        'profile_pictures',
                        ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        5 * 1024 * 1024
                    );

                    if (!$res['success']) {
                        $message = 'Upload failed: ' . h($res['error']);
                        $message_type = 'error';
                    } else {
                        $filepath = $res['path'];
                        $relativePath = str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $filepath);

                        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE student_id = ?");
                        $stmt->execute([$_SESSION['student_id']]);
                        $current_user = $stmt->fetch();

                        if ($current_user && !empty($current_user['profile_picture']) && file_exists($current_user['profile_picture'])) {
                            @unlink($current_user['profile_picture']);
                        }

                        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE student_id = ?");
                        $stmt->execute([$relativePath, $_SESSION['student_id']]);

                        $message = 'Profile picture updated successfully.';
                        $message_type = 'success';
                    }
                } else {
                    $message = 'Please select a valid image file.';
                    $message_type = 'error';
                }
            } elseif ($action === 'remove_profile_picture') {
                $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE student_id = ?");
                $stmt->execute([$_SESSION['student_id']]);
                $current_user = $stmt->fetch();

                if ($current_user && $current_user['profile_picture'] && file_exists($current_user['profile_picture'])) {
                    @unlink($current_user['profile_picture']);
                }

                $stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL, updated_at = NOW() WHERE student_id = ?");
                $stmt->execute([$_SESSION['student_id']]);

                $message = 'Profile picture removed successfully.';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Database error occurred. Please try again.';
            $message_type = 'error';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $message .= '<br>Debug: ' . $e->getMessage();
            }
        }
    }
}

// Fetch current user data
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, email, profile_picture,
               email_notifications, sms_notifications, newsletter_subscription,
               application_updates, marketing_emails, language_preference, timezone,
               created_at, last_login
        FROM users 
        WHERE student_id = ?
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        header('Location: student-login.php');
        exit();
    }
} catch (PDOException $e) {
    $message = 'Error loading user data.';
    $message_type = 'error';
    $user_data = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - EduBridge SA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --royal-blue: #1a5fb4;
            --royal-blue-light: #3584e4;
            --emerald-green: #26a269;
            --emerald-green-light: #33d17a;
            --gold: #f5c211;
            --gold-light: #f6d32d;
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
            --white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            min-height: 100vh;
            color: var(--gray-800);
        }

        /* Navigation */
        .nav-container {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav {
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
            text-decoration: none;
            color: var(--royal-blue);
            font-weight: 700;
            font-size: 1.5rem;
        }

        .logo img {
            height: 32px;
            width: auto;
            margin-right: 10px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--gray-700);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--royal-blue);
        }

        /* Main Content */
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            background: var(--white);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            color: var(--royal-blue);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid;
        }

        .alert.success {
            background: #d1fae5;
            border-color: var(--success);
            color: #065f46;
        }

        .alert.error {
            background: #fee2e2;
            border-color: var(--error);
            color: #991b1b;
        }

        /* Settings Sections */
        .settings-grid {
            display: grid;
            gap: 2rem;
        }

        .settings-section {
            background: var(--white);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            color: var(--gray-800);
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-description {
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        /* Profile Picture Section */
        .profile-picture-container {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        }

        elseif ($action ==='upload_profile_picture') {

            // Handle profile picture upload via centralized helper
            if (isset($_FILES['profile_picture'])) {
                $res =store_uploaded_file($_FILES['profile_picture'],
                    'profile_pictures',
                    ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                    MAX_FILE_SIZE);

                if ( !$res['success']) {
                    $message ='Upload failed: ' . h($res['error']);
                    $message_type ='error';
                }

                else {
                    $filepath =$res['path'];

                    // Get current profile picture to delete old one
                    $stmt =$pdo->prepare("SELECT profile_picture FROM users WHERE student_id = ?");
                    $stmt->execute([$_SESSION['student_id']]);
                    $current_user =$stmt->fetch();

                    // Delete old profile picture if it exists
                    if ($current_user && !empty($current_user['profile_picture']) && file_exists($current_user['profile_picture'])) {
                        @unlink($current_user['profile_picture']);
                    }

                    // Update database with new profile picture path (store relative path)
                    $relativePath =str_replace(realpath(__DIR__) . DIRECTORY_SEPARATOR, '', $filepath);
                    $stmt =$pdo->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE student_id = ?"
                    );
                    $stmt->execute([$relativePath, $_SESSION['student_id']]);

                    $message ='Profile picture updated successfully.';
                    $message_type ='success';
                }
            }

            else {
                $message ='Please select a valid image file.';
                $message_type ='error';

                .remove-picture-btn {
                    background: var(--error);
                    color: var(--white);
                    padding: 0.75rem 1.5rem;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                    font-weight: 500;
                }

                .remove-picture-btn:hover {
                    background: #dc2626;
                }

                /* Form Styles */
                .form-group {
                    margin-bottom: 1.5rem;
                }

                .form-group.checkbox-group {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 1rem;
                }

                label {
                    font-weight: 500;
                    color: var(--gray-700);
                    margin-bottom: 0.5rem;
                    display: block;
                }

                .checkbox-label {
                    margin-bottom: 0;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                input[type="checkbox"] {
                    width: 18px;
                    height: 18px;
                    accent-color: var(--royal-blue);
                }

                select {
                    width: 100%;
                    padding: 0.75rem;
                    border: 1px solid var(--gray-300);
                    border-radius: 5px;
                    font-size: 1rem;
                    transition: border-color 0.3s ease;
                }

                select:focus {
                    outline: none;
                    border-color: var(--royal-blue);
                    box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
                }

                .submit-btn {
                    background: var(--royal-blue);
                    color: var(--white);
                    padding: 0.75rem 2rem;
                    border: none;
                    border-radius: 5px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }

                .submit-btn:hover {
                    background: var(--royal-blue-light);
                }

                /* Responsive Design */
                @media (max-width: 768px) {
                    nav {
                        padding: 1rem;
                        flex-direction: column;
                        gap: 1rem;
                    }

                    .container {
                        padding: 0 1rem;
                    }

                    .page-header,
                    .settings-section {
                        padding: 1.5rem;
                    }

                    .profile-picture-container {
                        flex-direction: column;
                        text-align: center;
                    }
                }
    </style>
</head>

<body>
    <!-- Navigation -->
    <div class="nav-container">
        <nav>
            <a href="student-dashboard.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
            <ul class="nav-links">
                <li><a href="student-dashboard.php">Dashboard</a></li>
                <li><a href="student-apply.php">Apply</a></li>
                <li><a href="student-account.php">Account</a></li>
                <li><a href="student-security.php">Security</a></li>
                <li><a href="student-settings.php" class="active">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Settings</h1>
            <p class="page-subtitle">Manage your preferences and profile settings</p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($message_type); ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Profile Picture Section -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-user-circle"></i>
                    Profile Picture
                </h2>
                <p class="section-description">Upload or change your profile picture</p>

                <div class="profile-picture-container">
                    <?php if ($user_data['profile_picture'] && file_exists($user_data['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>"
                            alt="Profile Picture" class="profile-picture">
                    <?php else: ?>
                        <div class="profile-picture-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>

                    <div class="profile-picture-actions">
                        <form method="POST" action="student-settings.php" enctype="multipart/form-data" id="profilePictureForm">
                            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                            <input type="hidden" name="action" value="upload_profile_picture">

                            <label for="profile_picture" class="file-upload-btn">
                                <i class="fas fa-upload"></i> Choose Picture
                                <input type="file" id="profile_picture" name="profile_picture"
                                    accept="image/*" onchange="document.getElementById('profilePictureForm').submit();">
                            </label>
                        </form>

                        <?php if ($user_data['profile_picture']): ?>
                            <form method="POST" action="student-settings.php" style="display: inline;">
                                <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                                <input type="hidden" name="action" value="remove_profile_picture">
                                <button type="submit" class="remove-picture-btn"
                                    onclick="return confirm('Are you sure you want to remove your profile picture?')">
                                    <i class="fas fa-trash"></i> Remove Picture
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notification Preferences -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-bell"></i>
                    Notification Preferences
                </h2>
                <p class="section-description">Choose how you want to receive notifications</p>

                <form method="POST" action="student-settings.php">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="email_notifications" name="email_notifications"
                            <?php echo ($user_data['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="email_notifications" class="checkbox-label">
                            <i class="fas fa-envelope"></i>
                            Email Notifications
                        </label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="sms_notifications" name="sms_notifications"
                            <?php echo ($user_data['sms_notifications'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="sms_notifications" class="checkbox-label">
                            <i class="fas fa-sms"></i>
                            SMS Notifications
                        </label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="application_updates" name="application_updates"
                            <?php echo ($user_data['application_updates'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="application_updates" class="checkbox-label">
                            <i class="fas fa-file-alt"></i>
                            Application Status Updates
                        </label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="newsletter_subscription" name="newsletter_subscription"
                            <?php echo ($user_data['newsletter_subscription'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="newsletter_subscription" class="checkbox-label">
                            <i class="fas fa-newspaper"></i>
                            Newsletter Subscription
                        </label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="marketing_emails" name="marketing_emails"
                            <?php echo ($user_data['marketing_emails'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="marketing_emails" class="checkbox-label">
                            <i class="fas fa-bullhorn"></i>
                            Marketing Emails
                        </label>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Notification Preferences
                    </button>
                </form>
            </div>

            <!-- Language and Region -->
            <div class="settings-section">
                <h2 class="section-title">
                    <i class="fas fa-globe"></i>
                    Language & Region
                </h2>
                <p class="section-description">Set your language and timezone preferences</p>

                <form method="POST" action="student-settings.php">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                    <input type="hidden" name="action" value="update_preferences">

                    <div class="form-group">
                        <label for="language_preference">Language</label>
                        <select id="language_preference" name="language_preference">
                            <option value="en" <?php echo ($user_data['language_preference'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="af" <?php echo ($user_data['language_preference'] ?? 'en') === 'af' ? 'selected' : ''; ?>>Afrikaans</option>
                            <option value="zu" <?php echo ($user_data['language_preference'] ?? 'en') === 'zu' ? 'selected' : ''; ?>>Zulu</option>
                            <option value="xh" <?php echo ($user_data['language_preference'] ?? 'en') === 'xh' ? 'selected' : ''; ?>>Xhosa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone">
                            <option value="Africa/Johannesburg" <?php echo ($user_data['timezone'] ?? 'Africa/Johannesburg') === 'Africa/Johannesburg' ? 'selected' : ''; ?>>South Africa Standard Time</option>
                            <option value="Africa/Cape_Town" <?php echo ($user_data['timezone'] ?? 'Africa/Johannesburg') === 'Africa/Cape_Town' ? 'selected' : ''; ?>>Cape Town</option>
                            <option value="Africa/Durban" <?php echo ($user_data['timezone'] ?? 'Africa/Johannesburg') === 'Africa/Durban' ? 'selected' : ''; ?>>Durban</option>
                        </select>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Language & Region
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>