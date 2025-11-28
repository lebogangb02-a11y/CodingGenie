<?php
// admin_dashboard.php - Enhanced Admin Dashboard

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

// NOW CONTINUE WITH YOUR EXACT ORIGINAL CODE
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/config.php';

// Handle CSV Export FIRST (before any HTML output)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // We'll handle this after building the pendingRows data
    $do_csv_export = true;
} else {
    $do_csv_export = false;
}

// Verify DB connection
$db_error = null;
$pdo_ok = isset($pdo) && $pdo instanceof PDO;
if (!$pdo_ok) {
    $db_error = 'Database connection failed. Please check config.php credentials.';
}

// Initialize stats
$stats = [
    'today' => 0, 'month' => 0, 'year' => 0,
    'pending' => 0, 'approved' => 0, 'rejected' => 0,
    'total' => 0
];

$stats_error = null;
$tableCount = 0;
$applicationsTableExists = false;
$createdCol = null;
$statusCol = null;

// Recent activity and notifications
$recentActivity = [];
$unreadMessages = 0;
$activeChats = 0;

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($pdo_ok) {
    try {
        // Tables and existence check
        $tableCount = (int)$pdo->query('SHOW TABLES')->rowCount();
        $applicationsTableExists = $pdo->query("SHOW TABLES LIKE 'applications'")->rowCount() > 0;

        if ($applicationsTableExists) {
            // Discover columns safely
            $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
            $createdCandidates = ['created_at','submitted_at','created_on','date_created','timestamp'];
            $statusCandidates = ['status','application_status'];
            foreach ($createdCandidates as $c) { if (in_array($c, $cols, true)) { $createdCol = $c; break; } }
            foreach ($statusCandidates as $s) { if (in_array($s, $cols, true)) { $statusCol = $s; break; } }

            // Totals
            $stats['total'] = (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();

            // Date-based stats if we found a created column
            if ($createdCol) {
                $stats['today'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE DATE(`$createdCol`) = CURDATE()")->fetchColumn();
                $stats['month'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE MONTH(`$createdCol`) = MONTH(CURDATE()) AND YEAR(`$createdCol`) = YEAR(CURDATE())")->fetchColumn();
                $stats['year'] = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE YEAR(`$createdCol`) = YEAR(CURDATE())")->fetchColumn();
            }

            // Status counts if we found a status column
            if ($statusCol) {
                $stmt = $pdo->query("SELECT `$statusCol` AS s, COUNT(*) AS c FROM applications GROUP BY `$statusCol`");
                $statusCounts = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $key = strtolower(trim($row['s'] ?? ''));
                    if ($key !== '') { $statusCounts[$key] = (int)$row['c']; }
                }
                $stats['pending'] = ($statusCounts['pending'] ?? 0) + ($statusCounts['under_review'] ?? 0) + ($statusCounts['review'] ?? 0) + ($statusCounts['awaiting_review'] ?? 0);
                // Treat Accepted as Approved for dashboard rollups
                $stats['approved'] = ($statusCounts['approved'] ?? 0) + ($statusCounts['accepted'] ?? 0);
                $stats['rejected'] = ($statusCounts['rejected'] ?? 0) + ($statusCounts['declined'] ?? 0);
            }
            
            // Get recent activity (create table if not exists)
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_username VARCHAR(100) NOT NULL,
                    action VARCHAR(255) NOT NULL,
                    details TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at)
                )");
                
                // Fetch recent activity
                $stmt = $pdo->query("SELECT admin_username, action, details, created_at FROM admin_activity_logs ORDER BY created_at DESC LIMIT 10");
                $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Silently fail for activity logs
            }
            
            // Check for admin messages table
            try {
                $messagesTableExists = $pdo->query("SHOW TABLES LIKE 'admin_messages'")->rowCount() > 0;
                if ($messagesTableExists) {
                    $unreadMessages = (int)$pdo->query("SELECT COUNT(*) FROM admin_messages WHERE is_read = 0")->fetchColumn();
                }
            } catch (Exception $e) {
                // Silently fail for messages
            }
            
            // Check for active chat conversations
            try {
                $chatTableExists = $pdo->query("SHOW TABLES LIKE 'chat_conversations'")->rowCount() > 0;
                if ($chatTableExists) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT c.id) as active_chats 
                        FROM chat_conversations c 
                        LEFT JOIN chat_messages m ON c.id = m.conversation_id 
                        WHERE c.status IN ('active', 'escalated') 
                        AND m.sender_type IN ('student', 'bot') 
                        AND m.is_read = FALSE
                    ");
                    $stmt->execute();
                    $activeChats = $stmt->fetchColumn();
                }
            } catch (Exception $e) {
                // Silently fail for chat stats
            }
            
        } else {
            $stats_error = 'Applications table not found.';
        }
    } catch (Throwable $e) {
        $stats_error = 'Could not load statistics: ' . $e->getMessage();
    }
}

// Handle bulk actions (approve/reject)
if ($pdo_ok && isset($_POST['bulk_action']) && isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $bulk_error = "Security token validation failed.";
    } else {
        try {
            $bulkAction = $_POST['bulk_action'];
            $ids = array_filter(array_map('intval', $_POST['selected_ids']));
            if (!empty($ids)) {
                $targetStatus = ($bulkAction === 'approve') ? 'Accepted' : (($bulkAction === 'reject') ? 'Rejected' : null);
                if ($targetStatus) {
                    // Discover status column
                    $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
                    $statusCol = in_array('status', $cols, true) ? 'status' : (in_array('application_status', $cols, true) ? 'application_status' : 'status');
                    // Build placeholder list securely
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $sql = "UPDATE applications SET `$statusCol` = ?, updated_at = NOW() WHERE id IN ($placeholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge([$targetStatus], $ids));

                    // Log activity
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_activity_logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            admin_username VARCHAR(100) NOT NULL,
                            action VARCHAR(255) NOT NULL,
                            details TEXT,
                            ip_address VARCHAR(45),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX idx_created_at (created_at)
                        )");
                        $logStmt = $pdo->prepare('INSERT INTO admin_activity_logs (admin_username, action, details, ip_address) VALUES (?, ?, ?, ?)');
                        $logStmt->execute([
                            ($_SESSION['admin_username'] ?? 'admin'),
                            'Bulk ' . ucfirst($bulkAction),
                            'Updated ' . count($ids) . ' application(s) to ' . $targetStatus,
                            ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
                        ]);
                    } catch (Throwable $e) { /* ignore */ }

                    $bulk_success = 'Bulk action completed: ' . htmlspecialchars($targetStatus) . ' (' . count($ids) . ' applications updated).';
                }
            }
        } catch (Throwable $e) {
            $bulk_error = 'Bulk action failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Build Pending Applications query with filters and pagination
$q = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? 'Pending'; // default Pending
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25; $offset = ($page - 1) * $limit;

$pendingRows = [];
$totalRows = 0;

if ($pdo_ok && $applicationsTableExists) {
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM applications')->fetchAll(PDO::FETCH_COLUMN, 0);
        $createdCol = in_array('created_at', $cols, true) ? 'created_at' : (in_array('submitted_at', $cols, true) ? 'submitted_at' : 'created_at');
        $statusCol  = in_array('status', $cols, true) ? 'status' : (in_array('application_status', $cols, true) ? 'application_status' : 'status');
        $refCol     = in_array('reference_number', $cols, true) ? 'reference_number' : (in_array('application_ref', $cols, true) ? 'application_ref' : 'id');
        $emailCol   = in_array('email_address', $cols, true) ? 'email_address' : (in_array('email', $cols, true) ? 'email' : 'email_address');
        $phoneCol   = in_array('cellphone_number', $cols, true) ? 'cellphone_number' : (in_array('phone', $cols, true) ? 'phone' : 'cellphone_number');

        // Name expression: build only from columns that exist to avoid SQL errors
        $hasFull  = in_array('full_name', $cols, true);
        $hasSur   = in_array('surname', $cols, true);
        $hasFirst = in_array('first_name', $cols, true);
        $hasLast  = in_array('last_name', $cols, true);
        $hasAppl  = in_array('applicant_name', $cols, true);

        $nameParts = [];
        if ($hasFull && $hasSur) { $nameParts[] = "CONCAT(`full_name`, ' ', `surname`)"; }
        if ($hasFirst && $hasLast) { $nameParts[] = "CONCAT(`first_name`, ' ', `last_name`)"; }
        if ($hasAppl) { $nameParts[] = "`applicant_name`"; }
        if ($hasFull) { $nameParts[] = "`full_name`"; }
        if ($hasFirst) { $nameParts[] = "`first_name`"; }
        if ($hasLast) { $nameParts[] = "`last_name`"; }
        $nameExpr = !empty($nameParts) ? ('COALESCE(' . implode(', ', $nameParts) . ')') : "'-'";

        // Program expression: choose first available column
        $programCandidates = [];
        if (in_array('program_choice_1', $cols, true)) { $programCandidates[] = '`program_choice_1`'; }
        if (in_array('program', $cols, true)) { $programCandidates[] = '`program`'; }
        if (in_array('first_choice_program', $cols, true)) { $programCandidates[] = '`first_choice_program`'; }
        if (in_array('intended_course', $cols, true)) { $programCandidates[] = '`intended_course`'; }
        if (in_array('course_name', $cols, true)) { $programCandidates[] = '`course_name`'; }
        $programExpr = !empty($programCandidates) ? ('COALESCE(' . implode(', ', $programCandidates) . ')') : 'NULL';

        // Build WHERE
        $where = [];
        $params = [];
        if ($filter && strtolower($filter) !== 'all') {
            if (strtolower($filter) === 'approved') {
                $where[] = "LOWER(`$statusCol`) IN ('approved','accepted')";
            } elseif (strtolower($filter) === 'rejected') {
                $where[] = "LOWER(`$statusCol`) = 'rejected'";
            } else { // Pending
                $where[] = "`$statusCol` = 'Pending'"; // exact match per requirement
            }
        }
        if ($q !== '') {
            $where[] = "( `$refCol` LIKE ? OR $nameExpr LIKE ? OR `$emailCol` LIKE ? )";
            $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
        }
        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        // Count total
        $countSql = "SELECT COUNT(*) FROM applications $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        // Page results
        $sql = "SELECT id, `$refCol` AS ref, $nameExpr AS applicant_name, $programExpr AS program, `$emailCol` AS email, `$phoneCol` AS phone, `$statusCol` AS status, `$createdCol` AS created_at
                FROM applications
                $whereSql
                ORDER BY `$createdCol` DESC
                LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Handle CSV Export AFTER building pendingRows
        if ($do_csv_export) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="applications_export_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference','Name','Program','Email','Phone','Status','Created At']);
            foreach ($pendingRows as $r) {
                fputcsv($out, [
                    $r['ref'] ?? '',
                    $r['applicant_name'] ?? '',
                    $r['program'] ?? '',
                    $r['email'] ?? '',
                    $r['phone'] ?? '',
                    $r['status'] ?? '',
                    $r['created_at'] ?? ''
                ]);
            }
            fclose($out);
            exit;
        }
        
    } catch (Throwable $e) {
        $query_error = 'Failed to load applications: ' . htmlspecialchars($e->getMessage());
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
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EduBridgeSA Student Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        
        .navbar {
            height: var(--header-height);
        }
        
        .stats-card {
            border: none;
            border-radius: 15px;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        
        .activity-timeline {
            position: relative;
        }
        
        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-marker {
            width: 12px;
            height: 12px;
            background: #3498db;
            border-radius: 50%;
            margin-top: 5px;
        }
        
        .sidebar.collapsed {
            width: 70px;
        }
        
        .sidebar.collapsed + .main-content {
            margin-left: 70px;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 12px 20px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s;
            position: relative;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar .nav-link .badge {
            position: absolute;
            top: 8px;
            right: 20px;
            font-size: 0.6rem;
            padding: 2px 5px;
        }
        
        [data-bs-theme="dark"] {
            --bs-body-bg: #1a1a1a;
            --bs-body-color: #fff;
        }
        
        .search-box {
            max-width: 400px;
        }
        
        /* Pending Applications table helpers */
        .status-badge { font-size: 0.85rem; }
        .table-sm td, .table-sm th { padding: .5rem; }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .btn-group {
                flex-wrap: wrap;
            }
            
            .search-box {
                max-width: 200px;
            }
        }
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
            <a href="admin_dashboard.php" class="nav-link active">
                <i class="bi bi-speedometer2 me-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_students.php" class="nav-link">
                <i class="bi bi-people me-3"></i>
                <span>Manage Students</span>
            </a>
            <a href="add_student.php" class="nav-link">
                <i class="bi bi-person-plus me-3"></i>
                <span>Add Student</span>
            </a>
            
            <!-- Chat Support Section -->
            <div class="mt-2 mb-2 ps-2">
                <small class="text-white-50">SUPPORT</small>
            </div>
            <a href="admin_chat.php" class="nav-link">
                <i class="bi bi-chat-dots me-3"></i>
                <span>Chat Support</span>
                <?php if ($activeChats > 0): ?>
                    <span class="badge bg-danger"><?php echo $activeChats; ?></span>
                <?php endif; ?>
            </a>
            
            <div class="mt-2 mb-2 ps-2">
                <small class="text-white-50">SYSTEM</small>
            </div>
            <a href="manage_admins.php" class="nav-link">
                <i class="bi bi-shield-lock me-3"></i>
                <span>Manage Admins</span>
            </a>
            <a href="email_logs.php" class="nav-link">
                <i class="bi bi-envelope me-3"></i>
                <span>Email Logs</span>
            </a>
            <a href="system_status.php" class="nav-link">
                <i class="bi bi-graph-up me-3"></i>
                <span>System Status</span>
            </a>
            <a href="export_tools.php" class="nav-link">
                <i class="bi bi-download me-3"></i>
                <span>Export Tools</span>
            </a>
            <a href="audit_logs.php" class="nav-link">
                <i class="bi bi-clock-history me-3"></i>
                <span>Audit Logs</span>
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
                    
                    <!-- Notifications -->
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadMessages > 0 || $activeChats > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $unreadMessages + $activeChats; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if ($unreadMessages > 0): ?>
                                <a class="dropdown-item" href="admin_messages.php">
                                    <i class="bi bi-envelope me-2"></i>
                                    <?php echo $unreadMessages; ?> unread messages
                                </a>
                            <?php endif; ?>
                            <?php if ($activeChats > 0): ?>
                                <a class="dropdown-item" href="admin_chat.php">
                                    <i class="bi bi-chat-dots me-2"></i>
                                    <?php echo $activeChats; ?> active chats
                                </a>
                            <?php endif; ?>
                            <?php if ($unreadMessages === 0 && $activeChats === 0): ?>
                                <span class="dropdown-item text-muted">No new notifications</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- User Menu -->
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-2"></i>
                            <?php echo htmlspecialchars($username); ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a class="dropdown-item" href="admin_profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                            <a class="dropdown-item" href="admin_messages.php">
                                <i class="bi bi-envelope me-2"></i>Messages
                            </a>
                            <a class="dropdown-item" href="admin_chat.php">
                                <i class="bi bi-chat-dots me-2"></i>Chat Support
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
            <!-- Alerts -->
            <?php if ($db_error): ?>
                <div class="alert alert-danger">
                    <h5>Database Error:</h5>
                    <?php echo htmlspecialchars($db_error); ?>
                </div>
            <?php endif; ?>

            <?php if ($stats_error): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($stats_error); ?></div>
            <?php endif; ?>

            <?php if (isset($bulk_success)): ?>
                <div class="alert alert-success"><?php echo $bulk_success; ?></div>
            <?php endif; ?>

            <?php if (isset($bulk_error)): ?>
                <div class="alert alert-danger"><?php echo $bulk_error; ?></div>
            <?php endif; ?>

            <?php if (isset($query_error)): ?>
                <div class="alert alert-danger"><?php echo $query_error; ?></div>
            <?php endif; ?>

            <?php if ($pdo_ok && !$db_error): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>Successfully connected to database: <?php echo htmlspecialchars(DB_NAME); ?>
                </div>
            <?php endif; ?>

            <!-- Quick Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Total Applications</h6>
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small>All time applications</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-dark-50">Pending</h6>
                                    <h2 class="mb-0"><?php echo $stats['pending']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small>Awaiting review</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Approved</h6>
                                    <h2 class="mb-0"><?php echo $stats['approved']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small>Successful applications</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-md-6">
                    <div class="card stats-card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-white-50">Rejected</h6>
                                    <h2 class="mb-0"><?php echo $stats['rejected']; ?></h2>
                                </div>
                                <div class="stats-icon">
                                    <i class="bi bi-x-circle-fill"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <small>Unsuccessful applications</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time-based Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-day text-primary fs-1"></i>
                            <h4 class="mt-2"><?php echo $stats['today']; ?></h4>
                            <p class="text-muted">Today's Applications</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-month text-info fs-1"></i>
                            <h4 class="mt-2">
                                <?php echo $stats['month']; ?>
                                <span class="badge bg-info ms-2"><?php echo date('F'); ?></span>
                            </h4>
                            <p class="text-muted">This Month</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <i class="bi bi-calendar-check text-success fs-1"></i>
                            <h4 class="mt-2"><?php echo $stats['year']; ?></h4>
                            <p class="text-muted">This Year</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Applications Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Applications</h5>
                    <div class="d-flex gap-2">
                        <form class="d-flex" method="get" action="admin_dashboard.php">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter); ?>">
                            <input type="text" class="form-control form-control-sm" name="q" placeholder="Search by ref, name or email" value="<?php echo htmlspecialchars($q); ?>">
                            <button class="btn btn-sm btn-outline-secondary ms-2" type="submit"><i class="bi bi-search"></i></button>
                        </form>
                        <div class="btn-group btn-group-sm" role="group">
                            <a class="btn btn-outline-secondary <?php echo (strtolower($filter)==='all'?'active':''); ?>" href="?status=All">All</a>
                            <a class="btn btn-outline-warning <?php echo (strtolower($filter)==='pending'?'active':''); ?>" href="?status=Pending">Pending</a>
                            <a class="btn btn-outline-success <?php echo (strtolower($filter)==='approved'?'active':''); ?>" href="?status=Approved">Approved</a>
                            <a class="btn btn-outline-danger <?php echo (strtolower($filter)==='rejected'?'active':''); ?>" href="?status=Rejected">Rejected</a>
                        </div>
                        <a class="btn btn-sm btn-outline-info" href="?export=csv&status=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($q); ?>">
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form method="post" id="bulk-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:32px"><input type="checkbox" id="select-all"></th>
                                        <th>Reference</th>
                                        <th>Name</th>
                                        <th>Program</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Application Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingRows)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-3">No applications found.</td></tr>
                                    <?php else: foreach ($pendingRows as $row): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_ids[]" value="<?php echo (int)$row['id']; ?>"></td>
                                            <td><?php echo htmlspecialchars($row['ref']); ?></td>
                                            <td><?php echo htmlspecialchars($row['applicant_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['program'] ?? '-'); ?></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($row['email'] ?? '-'); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($row['phone'] ?? ''); ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $s = strtolower($row['status'] ?? '');
                                                $cls = 'bg-secondary';
                                                $text = 'text-dark';
                                                
                                                if ($s === 'pending' || $s === 'under_review' || $s === 'awaiting_review') {
                                                    $cls = 'bg-warning';
                                                    $text = 'text-dark';
                                                } elseif ($s === 'approved' || $s === 'accepted') {
                                                    $cls = 'bg-success';
                                                    $text = 'text-white';
                                                } elseif ($s === 'rejected' || $s === 'declined') {
                                                    $cls = 'bg-danger';
                                                    $text = 'text-white';
                                                } elseif (strpos($s, 'submitted') !== false) {
                                                    $cls = 'bg-info';
                                                    $text = 'text-white';
                                                }
                                                ?>
                                                <span class="badge status-badge <?php echo $cls . ' ' . $text; ?>">
                                                    <?php echo htmlspecialchars($row['status'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" target="_blank" href="application_status.php?ref=<?php echo urlencode($row['ref']); ?>">View Details</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <div>
                                <button class="btn btn-sm btn-success" name="bulk_action" value="approve" type="submit"><i class="bi bi-check-circle me-1"></i>Approve</button>
                                <button class="btn btn-sm btn-danger ms-2" name="bulk_action" value="reject" type="submit"><i class="bi bi-x-circle me-1"></i>Reject</button>
                            </div>
                            <div>
                                <?php
                                $totalPages = max(1, (int)ceil($totalRows / $limit));
                                $base = 'admin_dashboard.php?status=' . urlencode($filter) . '&q=' . urlencode($q) . '&page=';
                                ?>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item <?php echo ($page<=1?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo $base . max(1, $page-1); ?>">
                                                <i class="bi bi-chevron-left"></i> Prev
                                            </a>
                                        </li>
                                        
                                        <?php
                                        // Show page numbers
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <li class="page-item <?php echo ($i == $page ? 'active' : ''); ?>">
                                                <a class="page-link" href="<?php echo $base . $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo ($page>=$totalPages?'disabled':''); ?>">
                                            <a class="page-link" href="<?php echo $base . min($totalPages, $page+1); ?>">
                                                Next <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Activity -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-clock-history me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-timeline">
                                <?php if (empty($recentActivity)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2">No recent activity</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <div class="activity-item d-flex">
                                            <div class="activity-marker"></div>
                                            <div class="activity-content flex-grow-1 ms-3">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($activity['admin_username']); ?></strong>
                                                        <span class="text-muted"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                        <?php if (!empty($activity['details'])): ?>
                                                            <div class="small text-muted mt-1"><?php echo htmlspecialchars($activity['details']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo time_ago($activity['created_at']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & System Status -->
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-lightning me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="manage_students.php" class="btn btn-outline-primary btn-lg text-start">
                                    <i class="bi bi-people me-2"></i>Manage Students
                                </a>
                                <a href="add_student.php" class="btn btn-outline-success btn-lg text-start">
                                    <i class="bi bi-person-plus me-2"></i>Add New Student
                                </a>
                                <a href="admin_chat.php" class="btn btn-outline-info btn-lg text-start">
                                    <i class="bi bi-chat-dots me-2"></i>Chat Support
                                    <?php if ($activeChats > 0): ?>
                                        <span class="badge bg-danger float-end"><?php echo $activeChats; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="export_tools.php" class="btn btn-outline-warning btn-lg text-start">
                                    <i class="bi bi-download me-2"></i>Export Data
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-check-circle me-2"></i>System Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="system-status">
                                <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                    <span>Database</span>
                                    <span class="badge bg-success">Online</span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                    <span>Applications Table</span>
                                    <span class="badge <?php echo $applicationsTableExists ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo $applicationsTableExists ? 'Found' : 'Missing'; ?>
                                    </span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                    <span>Chat System</span>
                                    <span class="badge <?php echo $activeChats >= 0 ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $activeChats >= 0 ? 'Active' : 'Disabled'; ?>
                                    </span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center mb-2">
                                    <span>Total Tables</span>
                                    <span class="badge bg-info"><?php echo $tableCount; ?></span>
                                </div>
                                <div class="status-item d-flex justify-content-between align-items-center">
                                    <span>Last Check</span>
                                    <span class="badge bg-secondary"><?php echo date('H:i:s'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
        });

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

        // Select All Checkbox
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Bulk Form Loading State
        const bulkForm = document.getElementById('bulk-form');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                const submitButtons = this.querySelectorAll('button[type="submit"]');
                submitButtons.forEach(btn => {
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
                    
                    // Restore after 5 seconds if still disabled (fallback)
                    setTimeout(() => {
                        if (btn.disabled) {
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    }, 5000);
                });
            });
        }

        // Mobile sidebar for small screens
        if (window.innerWidth < 768) {
            document.querySelector('.sidebar').classList.remove('collapsed');
        }
    </script>
</body>
</html>