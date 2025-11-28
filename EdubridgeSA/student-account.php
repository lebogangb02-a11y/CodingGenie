<?php
require_once 'config.php';
require_once 'session_config.php';
require_once 'security-utils.php';
require_once 'profile-utils.php';

// Check if user is logged in
// Allow safe local preview without login (only on localhost with preview flag)
$isPreview = false;
if ((($_SERVER['HTTP_HOST'] ?? '') === 'localhost:8000') && isset($_GET['preview'])) {
    $isPreview = true;
    if (empty($_SESSION['student_id'])) {
        $_SESSION['student_id'] = 'PREVIEW0001';
    }
}

// Check if user is logged in unless in local preview mode
if (!$isPreview && !isLoggedIn()) {
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
    // CSRF protection (constant-time comparison)
    $postedToken = isset($_POST[CSRF_TOKEN_NAME]) ? (string)$_POST[CSRF_TOKEN_NAME] : '';
    $sessionToken = isset($_SESSION[CSRF_TOKEN_NAME]) ? (string)$_SESSION[CSRF_TOKEN_NAME] : '';
    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        $message = 'Security token mismatch. Please try again.';
        $message_type = 'error';
    } else {
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

            // Determine which form posted
            $form_type = $_POST['form_type'] ?? 'account_info';

            // Handle profile picture upload
            if ($form_type === 'profile_picture' && isset($_FILES['profile_picture'])) {
                $result = handleProfilePictureUpload($_FILES['profile_picture'], $_SESSION['student_id']);
                if ($result['success']) {
                    $message = 'Profile picture updated successfully!';
                    $message_type = 'success';
                    // Update current page's data so the image reflects immediately
                    $user_data['profile_picture'] = $result['path'] ?? ($user_data['profile_picture'] ?? null);
                    // Keep CSRF token stable to avoid mismatch on subsequent submissions
                } else {
                    $message = $result['error'] ?? 'Upload failed.';
                    $message_type = 'error';
                }
            } else {
            // Validate input
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $date_of_birth = trim($_POST['date_of_birth'] ?? '');
            $id_number = trim($_POST['id_number'] ?? '');
            $school_university = trim($_POST['school_university'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $province = trim($_POST['province'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');

            $errors = [];

            if (empty($first_name)) {
                $errors[] = 'First name is required.';
            }
            if (empty($last_name)) {
                $errors[] = 'Last name is required.';
            }
            if (!empty($phone) && !validate_sa_phone($phone)) {
                $errors[] = 'Please enter a valid South African phone number.';
            }

            if (!empty($postal_code) && !validate_sa_postal_code($postal_code)) {
                $errors[] = 'Postal code must be a 4-digit number.';
            }

            if (!empty($id_number) && !validate_sa_id($id_number)) {
                $errors[] = 'Please enter a valid South African ID number.';
            }

            if (!empty($date_of_birth) && !validate_sa_date($date_of_birth)) {
                $errors[] = 'Please enter a valid date of birth.';
            }

            if (empty($errors)) {
                // Update user information
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, phone = ?, school_university = ?,
                        address = ?, city = ?, province = ?, postal_code = ?, date_of_birth = ?, id_number = ?, updated_at = NOW()
                    WHERE student_id = ?
                ");
                
                // Normalize common city typo: Zeenust -> Zeerust
                if (strcasecmp($city, 'Zeenust') === 0) { $city = 'Zeerust'; }

                $stmt->execute([
                    $first_name, $last_name, $phone, $school_university,
                    $address, $city, $province, $postal_code,
                    !empty($date_of_birth) ? $date_of_birth : null,
                    !empty($id_number) ? $id_number : null,
                    $_SESSION['student_id']
                ]);

                // Update session variables
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;

                $message = 'Your account information has been updated successfully.';
                $message_type = 'success';
                // Keep CSRF token stable to prevent mismatch
            } else {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
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
        SELECT first_name, last_name, email, phone, date_of_birth, id_number, 
               school_university, address, city, province, postal_code, 
               profile_picture, created_at, last_login
        FROM users 
        WHERE student_id = ?
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        if ($isPreview) {
            $user_data = [
                'first_name' => 'Bongani',
                'last_name' => 'DIKGANG',
                'email' => 'preview@example.com',
                'phone' => '078 323 0529',
                'date_of_birth' => '1995-06-12',
                'id_number' => '9506125800081',
                'school_university' => 'EduBridge Institute',
                'address' => '10033 MATLAPANA SECTION',
                'city' => 'Zeerust',
                'province' => 'North West',
                'postal_code' => '2868',
                'profile_picture' => null,
                'created_at' => date('Y-m-d'),
                'last_login' => date('Y-m-d H:i:s')
            ];
        } else {
            header('Location: student-login.php');
            exit();
        }
    }

} catch (PDOException $e) {
    $message = 'Error loading account information.';
    $message_type = 'error';
    $user_data = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - EduBridge SA</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1a5fb4;
            --primary-dark: #155a9e;
            --secondary-color: #26a269;
            --accent-color: #f66151;
            --background-color: #f8f9fa;
            --surface-color: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

        /* Dark mode (optional toggle) */
        body.dark-mode {
            --background-color: #0f172a;
            --surface-color: #0b1220;
            --text-primary: #e5e7eb;
            --text-secondary: #9ca3af;
            --border-color: #1f2937;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: var(--surface-color);
            padding: 1rem 0;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        .logo img {
            height: 28px;
            width: auto;
            margin-right: 0.5rem;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
            transition: var(--transition);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--primary-color);
            color: white;
        }

        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Unified two-column layout */
        .account-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Profile header */
        .profile-header {
            display: grid;
            grid-template-columns: 150px 1fr;
            align-items: center;
            gap: 1.5rem;
            background: linear-gradient(135deg, #f0f4ff, #ffffff);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .profile-picture-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 0;
        }

        .profile-picture {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
        }

        .profile-picture:hover .profile-overlay {
            opacity: 1;
        }

        .upload-btn {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 600;
            box-shadow: var(--shadow-sm);
        }

        .profile-info h1 {
            margin: 0;
            font-size: 1.75rem;
            color: var(--text-primary);
        }

        .profile-info .student-id {
            color: var(--text-secondary);
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Account Card */
        .account-card {
            background: var(--surface-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .profile-picture {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .student-id {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .card-body {
            padding: 2rem;
        }

        /* Message */
        .message {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--surface-color);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 95, 180, 0.1);
        }

        .form-input:disabled {
            background: #f8f9fa;
            color: var(--text-secondary);
            cursor: not-allowed;
        }

        .form-input:invalid {
            border-color: #e53e3e;
        }

        .readonly-field {
            background: #f8f9fa;
            color: var(--text-secondary);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Info Section */
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .main-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .profile-header {
                grid-template-columns: 1fr;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="student-dashboard.php" class="logo">
                <img src="images/logo.png.jpg" alt="EduBridge SA" onerror="this.onerror=null;this.src='images/logo.png';">
                <span>EduBridge SA</span>
            </a>
            <ul class="nav-links">
                <li><a href="student-dashboard.php">Dashboard</a></li>
                <li><a href="student-apply.php">Apply</a></li>
                <li><a href="student-account.php" class="active">Account</a></li>
                <li><a href="student-security.php">Security</a></li>
                <li><a href="student-settings.php">Settings</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
            <button id="dark-toggle" class="btn btn-sm btn-outline-secondary" type="button" aria-label="Toggle dark mode">🌙</button>
        </div>
    </nav>

    <!-- Toast container (Bootstrap) -->
    <div id="toast-container" class="position-fixed top-0 end-0 p-3" style="z-index: 1100;"></div>

    <!-- Main Content -->
    <div class="main-container">
        <div class="page-header">
            <h1 class="page-title">My Account</h1>
            <p class="page-subtitle">Manage your personal information and account details</p>
        </div>

        <!-- Profile Header Upload Form -->
        <form id="profile-upload-form" method="POST" action="student-account.php" enctype="multipart/form-data" style="margin-bottom: 1.5rem;">
            <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
            <input type="hidden" name="form_type" value="profile_picture">
            <div class="profile-header">
                <div class="profile-picture-container" id="profile-drop-zone">
                    <div class="profile-picture">
                        <img id="profile-image" src="<?php echo getProfilePicture($user_data); ?>?v=<?php echo time(); ?>" alt="Profile" onerror="this.onerror=null;this.src='images/default-avatar.png';">
                        <div class="profile-overlay">
                            <input type="file" id="profile-upload" name="profile_picture" accept="image/*" style="display: none;">
                            <button type="button" class="upload-btn" id="upload-btn">
                                📷 Change Photo
                            </button>
                        </div>
                    </div>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? '')); ?></h1>
                    <p class="student-id">Student ID: <?php echo htmlspecialchars($_SESSION['student_id'] ?? ''); ?></p>
                </div>
            </div>
        </form>
        <div class="account-container">
            <!-- Summary card (left column) -->
            <div class="account-card">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?php echo getProfilePicture($user_data); ?>" alt="Avatar" class="rounded-circle" style="width:48px;height:48px;object-fit:cover;border:2px solid rgba(255,255,255,0.7);">
                        <h2 class="m-0">Quick Info</h2>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Progress indicator -->
                    <?php
                        $allFields = ['phone','date_of_birth','id_number','school_university','address','city','province','postal_code','profile_picture'];
                        $filled = 0;
                        foreach ($allFields as $f) { if (!empty($user_data[$f])) $filled++; }
                        $percent = (int)round(($filled / max(count($allFields),1)) * 100);
                    ?>
                    <div class="mb-4">
                        <label class="form-label" style="font-weight:600;">Profile Completion</label>
                        <div class="progress" role="progressbar" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100" style="height:10px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $percent; ?>% complete</small>
                    </div>

                    <div class="info-section">
                        <h3 style="margin-bottom: 1rem; color: var(--primary-color);">Account Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value"><?php echo htmlspecialchars($user_data['email'] ?? ''); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date of Birth</span>
                                <span class="info-value"><?php echo !empty($user_data['date_of_birth']) ? date('F j, Y', strtotime($user_data['date_of_birth'])) : 'Not provided'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">ID Number</span>
                                <span class="info-value"><?php echo !empty($user_data['id_number']) ? htmlspecialchars($user_data['id_number']) : 'Not provided'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Member Since</span>
                                <span class="info-value"><?php echo !empty($user_data['created_at']) ? date('F j, Y', strtotime($user_data['created_at'])) : 'Unknown'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Last Login</span>
                                <span class="info-value"><?php echo !empty($user_data['last_login']) ? date('F j, Y g:i A', strtotime($user_data['last_login'])) : 'Never'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit card (right column) -->
            <div class="account-card">
                <div class="card-body">
                    <h3 style="margin-bottom: 1.5rem; color: var(--primary-color);">Edit Personal Information</h3>
                    <form method="POST" action="student-account.php" id="account-info-form" class="needs-validation" novalidate>
                        <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo $_SESSION[CSRF_TOKEN_NAME]; ?>">
                        <input type="hidden" name="form_type" value="account_info">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" id="first_name" name="first_name" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($user_data['first_name'] ?? ''); ?>" required>
                                    <label for="first_name">First Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($user_data['last_name'] ?? ''); ?>" required>
                                    <label for="last_name">Last Name *</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" disabled>
                                    <label for="email">Email Address</label>
                                </div>
                                <small class="text-muted">Email cannot be changed. Contact support if needed.</small>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" id="phone" name="phone" class="form-control" placeholder="Phone" inputmode="tel" value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>">
                                    <label for="phone">Phone Number</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" placeholder="Date of Birth" value="<?php echo !empty($user_data['date_of_birth']) ? date('Y-m-d', strtotime($user_data['date_of_birth'])) : ''; ?>">
                                    <label for="date_of_birth">Date of Birth</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" id="id_number" name="id_number" class="form-control" inputmode="numeric" maxlength="13" placeholder="ID Number" value="<?php echo htmlspecialchars($user_data['id_number'] ?? ''); ?>">
                                    <label for="id_number">ID Number</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" id="school_university" name="school_university" class="form-control" placeholder="School/University" value="<?php echo htmlspecialchars($user_data['school_university'] ?? ''); ?>">
                                    <label for="school_university">School/University</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating">
                                    <input type="text" id="address" name="address" class="form-control" placeholder="Address" value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>">
                                    <label for="address">Address</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" id="city" name="city" class="form-control" placeholder="City" value="<?php $cityVal = $user_data['city'] ?? ''; echo htmlspecialchars(strcasecmp($cityVal, 'Zeenust') === 0 ? 'Zeerust' : $cityVal); ?>">
                                    <label for="city">City</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <select id="province" name="province" class="form-select" placeholder="Province">
                                        <option value="">Select Province</option>
                                        <option value="Eastern Cape" <?php echo ($user_data['province'] ?? '') === 'Eastern Cape' ? 'selected' : ''; ?>>Eastern Cape</option>
                                        <option value="Free State" <?php echo ($user_data['province'] ?? '') === 'Free State' ? 'selected' : ''; ?>>Free State</option>
                                        <option value="Gauteng" <?php echo ($user_data['province'] ?? '') === 'Gauteng' ? 'selected' : ''; ?>>Gauteng</option>
                                        <option value="KwaZulu-Natal" <?php echo ($user_data['province'] ?? '') === 'KwaZulu-Natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
                                        <option value="Limpopo" <?php echo ($user_data['province'] ?? '') === 'Limpopo' ? 'selected' : ''; ?>>Limpopo</option>
                                        <option value="Mpumalanga" <?php echo ($user_data['province'] ?? '') === 'Mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
                                        <option value="Northern Cape" <?php echo ($user_data['province'] ?? '') === 'Northern Cape' ? 'selected' : ''; ?>>Northern Cape</option>
                                        <option value="North West" <?php echo ($user_data['province'] ?? '') === 'North West' ? 'selected' : ''; ?>>North West</option>
                                        <option value="Western Cape" <?php echo ($user_data['province'] ?? '') === 'Western Cape' ? 'selected' : ''; ?>>Western Cape</option>
                                    </select>
                                    <label for="province">Province</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-floating">
                                    <input type="text" id="postal_code" name="postal_code" class="form-control" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" title="Enter 4 digits, e.g., 2868" placeholder="Postal Code" value="<?php echo htmlspecialchars($user_data['postal_code'] ?? ''); ?>">
                                    <label for="postal_code">Postal Code</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions d-flex gap-2 justify-content-end mt-3 border-top pt-3">
                            <a href="student-dashboard.php" class="btn btn-secondary w-100 w-md-auto">Cancel</a>
                            <button type="submit" class="btn btn-primary w-100 w-md-auto">Update Information</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Form validation for the account info form only
        const accountForm = document.getElementById('account-info-form');
        accountForm && accountForm.addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            
            if (!firstName || !lastName) {
                e.preventDefault();
                alert('First name and last name are required.');
                return false;
            }
            
            // Show loading state
            const submitBtn = accountForm.querySelector('button[type="submit"]');
            submitBtn.textContent = 'Updating...';
            submitBtn.disabled = true;
        });

        // Phone number formatting: allow 0XXXXXXXXX or +27XXXXXXXXX
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let raw = e.target.value.replace(/[^\d+]/g, '');
            if (raw.startsWith('+')) {
                raw = '+' + raw.replace(/\+/g, '').replace(/[^\d]/g, '');
            }
            // Format: keep as compact digits or +27#########
            e.target.value = raw;
        });

        // Postal code clamp to 4 digits
        const postalInput = document.getElementById('postal_code');
        postalInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0,4);
        });

        // Simple SA ID mask: numeric max 13
        const idInput = document.getElementById('id_number');
        if (idInput) {
            idInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0,13);
            });
        }

        // Profile upload interactions
        const uploadBtn = document.getElementById('upload-btn');
        const uploadInput = document.getElementById('profile-upload');
        const uploadForm = document.getElementById('profile-upload-form');
        const profileImg = document.getElementById('profile-image');
        const dropZone = document.getElementById('profile-drop-zone');

        function validateImage(file) {
            const allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowed.includes(file.type)) return 'Use JPG, PNG, or WEBP';
            if (file.size > 5 * 1024 * 1024) return 'Max size 5MB';
            return null;
        }

        function previewAndSubmit(file) {
            const err = validateImage(file);
            if (err) { alert(err); return; }
            const reader = new FileReader();
            reader.onload = (e) => { profileImg.src = e.target.result; };
            reader.readAsDataURL(file);
            // submit after short delay so preview shows quickly
            setTimeout(() => uploadForm.submit(), 300);
        }

        if (uploadBtn && uploadInput && uploadForm) {
            uploadBtn.addEventListener('click', () => uploadInput.click());
            uploadInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) previewAndSubmit(e.target.files[0]);
            });
        }

        if (dropZone) {
            ['dragenter','dragover'].forEach(evt => dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragging');
            }));
            ['dragleave','drop'].forEach(evt => dropZone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragging');
            }));
            dropZone.addEventListener('drop', (e) => {
                const file = e.dataTransfer.files && e.dataTransfer.files[0];
                if (file) previewAndSubmit(file);
            });
        }

        // Dark mode toggle
        const darkToggle = document.getElementById('dark-toggle');
        if (darkToggle) {
            darkToggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
            });
        }

        // Bootstrap toast alerts for server messages
        const msg = <?php echo json_encode($message); ?>;
        const msgType = <?php echo json_encode($message_type); ?>;
        if (msg) {
            const container = document.getElementById('toast-container');
            const variant = (msgType === 'error') ? 'text-bg-danger' : 'text-bg-success';
            const icon = (msgType === 'error') ? '⚠️' : '✅';
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center ${variant} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${icon} ${msg}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>`;
            container.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl, { autohide: true, delay: 3000 });
            toast.show();
        }
    </script>
</body>
</html>