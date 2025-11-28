<?php
require_once 'session_config.php';
require_once 'config.php';

// Start session and check if user is logged in
// Session is initialized via session_config.php; no manual session_start needed.

// Simple authentication check
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header('Location: student-login.php');
    exit();
}

// Get user data from session
$username = $_SESSION['student_name'] ?? 'Student';
$email = $_SESSION['student_email'] ?? '';
$reference_number = $_SESSION['reference_number'] ?? 'APP2025000000';
$application_status = $_SESSION['application_status'] ?? 'draft';
$student_id = $_SESSION['student_id'] ?? '';

// Generate canonical Upload Documents URL for this session
$upload_docs_url = 'document_upload.php';
try {
    $contactEmail = $_SESSION['student_email'] ?? $_SESSION['email'] ?? null;
    $resolvedRef = null;
    if (!empty($reference_number)) { $resolvedRef = $reference_number; }
    if (!$resolvedRef && $contactEmail) {
        $stmt = $pdo->prepare("SELECT reference_number FROM applications WHERE email_address = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
        $stmt->execute([$contactEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['reference_number'])) { $resolvedRef = $row['reference_number']; }
    }
    if (!$resolvedRef) {
        $sessionRef = $_SESSION['reference_number'] ?? null;
        if ($sessionRef) { $resolvedRef = $sessionRef; }
    }
    if ($resolvedRef) {
        $upload_docs_url = 'document_upload.php?ref=' . urlencode($resolvedRef);
    }
} catch (PDOException $e) {
    error_log('student-dashboard upload link error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - EduBridgeSA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Dashboard Styles */
        :root {
            --primary: #1a5fb4;
            --secondary: #2e7d32;
            --light: #f5f7fa;
            --dark: #1a237e;
            --accent: #ff9800;
            --royal-blue: #1e3a8a;
            --royal-blue-light: #3b82f6;
            --emerald-green: #059669;
            --gold: #f59e0b;
            --white: #ffffff;
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

        /* Dashboard Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 25px;
            padding: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Main Content Area */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        /* Card Components */
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 0;
            border: 1px solid var(--gray-200);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        /* Welcome Section */
        .welcome-section h1 {
            font-size: 2.2rem;
            color: var(--gray-800);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-section p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        /* Progress Section */
        .progress-section {
            text-align: center;
        }

        .status-badge {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
            border: 1px solid #bbdefb;
            text-transform: uppercase;
        }

        .progress-percentage {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-800);
            margin: 10px 0;
        }

        .progress-label {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }

        /* Progress Bar Design */
        .progress-container {
            background: var(--gray-200);
            border-radius: 25px;
            height: 20px;
            margin: 20px 0;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--emerald-green), #10b981);
            height: 100%;
            border-radius: 25px;
            width: 20%;
            transition: width 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
            background-image: linear-gradient(
                -45deg,
                rgba(255, 255, 255, 0.2) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0.2) 75%,
                transparent 75%,
                transparent
            );
            background-size: 50px 50px;
            animation: move 2s linear infinite;
        }

        @keyframes move {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 50px 50px;
            }
        }

        /* Motivator Section */
        .motivator-section {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
            text-align: center;
        }

        .motivator-section p {
            font-size: 1.2rem;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .motivator-author {
            font-style: italic;
            opacity: 0.9;
            font-size: 1rem;
        }

        /* Progress Steps Design */
        .progress-steps-container {
            position: relative;
            margin: 30px 0;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 0;
            /* Ensure this block never blocks clicks below */
            pointer-events: none;
        }

        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gray-300);
            z-index: 1;
            border-radius: 2px;
            /* Make the decorative line ignore pointer events */
            pointer-events: none;
        }

        .step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }

        .step.completed .step-icon {
            background: var(--emerald-green);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }

        .step.current .step-icon {
            background: var(--royal-blue-light);
            color: white;
            border: 3px solid #93c5fd;
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.4);
            transform: scale(1.1);
        }

        .step.locked .step-icon {
            background: var(--gray-400);
            color: white;
            opacity: 0.6;
        }

        .step-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-top: 8px;
        }

        .step.completed .step-label {
            color: var(--emerald-green);
        }

        .step.current .step-label {
            color: var(--royal-blue-light);
            font-weight: 700;
        }

        /* Action Buttons */
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
            /* Lift above any decorative overlays */
            position: relative;
            z-index: 100;
            pointer-events: auto;
        }

        .btn {
            padding: 15px 20px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            /* Ensure buttons can always be clicked */
            pointer-events: auto;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .quick-stats {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--royal-blue);
            margin: 10px 0;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 1rem;
            font-weight: 600;
        }

        .stat-desc {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        /* User Info */
        .user-info {
            background: linear-gradient(135deg, var(--royal-blue) 0%, var(--royal-blue-light) 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }

        .user-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-email {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .user-ref {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Logout Button */
        .logout-btn {
            background: #dc2626;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            width: 100%;
        }

        .logout-btn:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .dashboard-container {
                grid-template-columns: 1fr;
                padding: 20px;
                gap: 20px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .progress-steps {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .progress-steps::before {
                display: none;
            }
            
            .step {
                flex: 0 0 calc(50% - 20px);
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
                gap: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .welcome-section h1 {
                font-size: 1.8rem;
            }
            
            .progress-percentage {
                font-size: 2.5rem;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
        }

        /* Animation for progress */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(5, 150, 105, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0);
            }
        }

        .progress-section {
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        /* NUCLEAR CSS FIX - Add this to your dashboard styles */
        a[href="edubridge_wizard.php"] {
            pointer-events: auto !important;
            cursor: pointer !important;
            position: relative !important;
            z-index: 10000 !important;
        }
        /* Remove any overlays blocking clicks */
        .action-buttons {
            pointer-events: auto !important;
            position: relative !important;
            z-index: 1000 !important;
        }

        .card {
            pointer-events: auto !important;
        }

        /* Notifications Dropdown Styles */
        .notif-dropdown-wrapper { position: relative; }
        .notif-dropdown { position: absolute; top: 8px; right: 8px; width: 340px; background: #fff; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,0.15); border: 1px solid var(--gray-200); transform-origin: top right; transform: scale(0.98) translateY(-8px); opacity: 0; pointer-events: none; transition: opacity .18s ease, transform .18s ease; z-index: 50; }
        .notif-dropdown.show { opacity: 1; transform: scale(1) translateY(0); pointer-events: auto; }
        .notif-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--gray-200); }
        .notif-header h4 { font-size: 0.95rem; color: var(--gray-800); }
        .notif-list { max-height: 300px; overflow-y: auto; }
        .notif-item { display: grid; grid-template-columns: 28px 1fr auto; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--gray-100); }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: var(--gray-50); }
        .notif-icon { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:8px; }
        .notif-icon.deadline { background:#fff7ed; color:#f59e0b; }
        .notif-icon.message { background:#eef2ff; color:#1e3a8a; }
        .notif-icon.document { background:#ecfeff; color:#0891b2; }
        .notif-icon.system { background:#f0fdf4; color:#047857; }
        .notif-title { font-weight:600; color:var(--gray-800); font-size:0.95rem; }
        .notif-desc { color:var(--gray-600); font-size:0.85rem; margin-top:4px; }
        .notif-meta { display:flex; align-items:center; gap:8px; font-size:0.8rem; color:var(--gray-500); margin-top:6px; }
        .notif-action a { font-size:0.8rem; color:var(--royal-blue); text-decoration:none; }
        .notif-action a:hover { text-decoration:underline; }
        .quick-stats.clickable { cursor: pointer; }
        .quick-stats .icon-area { display:flex; align-items:center; justify-content:center; gap:8px; }
        .quick-stats .icon-area i { cursor:pointer; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="card welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                <p>Your journey to university starts here. Let's make it amazing! ✨</p>
            </div>

            <!-- Progress Section -->
            <div class="card progress-section">
                <div class="status-badge"><?php echo strtoupper(htmlspecialchars($application_status)); ?></div>
                <div class="progress-percentage">20%</div>
                <div class="progress-label">Complete</div>
                
                <div class="progress-container">
                    <div class="progress-bar"></div>
                </div>
                
                <p>You're on fire! Keep chasing those dreams!</p>
                <p class="motivator-author">- EduBridge Motivator</p>
            </div>

            <!-- Progress Steps -->
            <div class="card">
                <div class="progress-steps-container">
                    <div class="progress-steps">
                        <div class="step completed">
                            <div class="step-icon">✓</div>
                            <div class="step-label">First Launch</div>
                        </div>
                        <div class="step locked">
                            <div class="step-icon">🔒</div>
                            <div class="step-label">Paper Master</div>
                        </div>
                        <div class="step locked">
                            <div class="step-icon">🔒</div>
                            <div class="step-label">On My Way</div>
                        </div>
                        <div class="step completed">
                            <div class="step-icon">✓</div>
                            <div class="step-label">All About You</div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="edubridge_wizard.php" class="btn btn-primary">
                        <i class="fas fa-rocket"></i>
                        Apply Now
                    </a>
                    <a href="<?php echo htmlspecialchars($upload_docs_url); ?>" class="btn btn-secondary">
                        <i class="fas fa-upload"></i>
                        Upload Documents
                    </a>
                    <a href="find-application.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i>
                        Find Application
                    </a>
                    <a href="track-progress.php" class="btn btn-secondary">
                        <i class="fas fa-chart-line"></i>
                        Track Progress
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- User Info Card -->
            <div class="card user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                <div class="user-ref">Ref: <?php echo htmlspecialchars($reference_number); ?></div>
            </div>

            <!-- Quick Stats -->
            <div class="card quick-stats clickable" id="notifications-card" role="button" aria-label="Open notifications">
                <div class="icon-area">
                    <i id="notifCardIcon" class="fas fa-bell text-warning" aria-label="Preview notifications"></i>
                    <div class="stat-number"><span id="notif-unread">0</span></div>
                </div>
                <div class="stat-label">Notifications</div>
                <div class="stat-desc">Unread messages and deadlines</div>
                <div class="notif-dropdown-wrapper" id="notifDropdownWrapper">
                    <div class="notif-dropdown" id="notifDropdown" aria-label="Notifications quick preview">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <a href="#" id="notifDropdownMarkAll">Mark all as read</a>
                        </div>
                        <div class="notif-list" id="notifList"></div>
                    </div>
                </div>
            </div>
            
            <div class="card quick-stats">
                <div class="stat-number">4</div>
                <div class="stat-label">Documents Required</div>
                <div class="stat-desc">Pending upload</div>
            </div>

            <div class="card quick-stats">
                <div class="stat-number">1</div>
                <div class="stat-label">Applications</div>
                <div class="stat-desc">In progress</div>
            </div>

            <div class="card" id="quick-links">
                <h3 style="margin-bottom:12px;color:var(--gray-800)">Quick Links</h3>
                <div class="action-buttons" style="grid-template-columns:1fr; gap:10px;">
                    <a href="student-account.php" class="btn btn-secondary"><i class="fas fa-user-cog"></i> Edit Profile</a>
                    <a href="edubridge_wizard.php" class="btn btn-primary"><i class="fas fa-rocket"></i> Apply Now</a>
                    <a href="application_status.php" class="btn btn-secondary"><i class="fas fa-clipboard-list"></i> Check Application Status</a>
                    <a href="<?php echo htmlspecialchars($upload_docs_url); ?>" class="btn btn-secondary"><i class="fas fa-upload"></i> Upload Documents</a>
                </div>
            </div>

            <!-- Logout Button -->
            <a href="auth.php?action=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </div>

    <script>
    // SIMPLE DASHBOARD JAVASCRIPT - REPLACE YOUR CURRENT JS WITH THIS 
    document.addEventListener('DOMContentLoaded', function() { 
        console.log('🚀 Dashboard loaded - applying emergency fixes'); 
        
        // Apply Now button - FORCE navigation 
        const applyNowButton = document.querySelector('a[href="edubridge_wizard.php"]'); 
        if (applyNowButton) { 
            console.log('✅ Apply Now button found'); 
            const newButton = document.createElement('a'); 
            newButton.href = 'edubridge_wizard.php'; 
            newButton.className = 'btn btn-primary'; 
            newButton.innerHTML = '<i class="fas fa-rocket"></i> Apply Now'; 
            newButton.style.pointerEvents = 'auto'; 
            newButton.style.cursor = 'pointer'; 
            newButton.style.zIndex = '10000'; 
            newButton.style.position = 'relative'; 
            applyNowButton.parentNode.replaceChild(newButton, applyNowButton); 
            newButton.addEventListener('click', function(e) { 
                console.log('🎯 Apply Now clicked - FORCING navigation'); 
                window.location.href = 'edubridge_wizard.php'; 
                return false; 
            }); 
            console.log('✅ Apply Now button replaced with working version'); 
        } else {
            console.warn('⚠️ Apply Now button not found');
        }
        
        // Debug what's happening with the button 
        const btn = document.querySelector('a[href="edubridge_wizard.php"]');
        if (btn) {
            console.log('Button styles:', window.getComputedStyle(btn));
            console.log('Pointer events:', window.getComputedStyle(btn).pointerEvents);
            console.log('Cursor:', window.getComputedStyle(btn).cursor);
            console.log('Z-index:', window.getComputedStyle(btn).zIndex);
        }
    });
    </script>
    <script>
    // Notifications preview + navigation
    document.addEventListener('DOMContentLoaded', function() {
        const card = document.getElementById('notifications-card');
        const icon = document.getElementById('notifCardIcon');
        const dropdown = document.getElementById('notifDropdown');
        const dropdownWrapper = document.getElementById('notifDropdownWrapper');
        const listEl = document.getElementById('notifList');
        const markAllBtn = document.getElementById('notifDropdownMarkAll');
        const unreadEl = document.getElementById('notif-unread');

        const LS_KEY = 'edubridge_notifications_v1';

        function seedData() {
            const existing = JSON.parse(localStorage.getItem(LS_KEY) || '[]');
            if (existing.length) return existing;
            const now = Date.now();
            const samples = [
                { id: 'n1', type: 'deadline', title: 'Upcoming submission', desc: 'Application deadline in 3 days.', ts: now - 60*1000, read: false, priority: 'high' },
                { id: 'n2', type: 'message', title: 'Welcome to EduBridge SA', desc: 'We’re excited to guide you.', ts: now - 2*60*60*1000, read: false, priority: 'low' },
                { id: 'n3', type: 'document', title: 'Document request', desc: 'Proof of residence required.', ts: now - 24*60*60*1000, read: true, priority: 'medium' },
                { id: 'n4', type: 'system', title: 'Dark mode update', desc: 'New theme options are available.', ts: now - 3*24*60*60*1000, read: true, priority: 'low' }
            ];
            localStorage.setItem(LS_KEY, JSON.stringify(samples));
            return samples;
        }

        function load() { return JSON.parse(localStorage.getItem(LS_KEY) || '[]'); }
        function save(items) { localStorage.setItem(LS_KEY, JSON.stringify(items)); }
        function relTime(ts) {
            const s = Math.floor((Date.now() - ts)/1000);
            if (s < 60) return `${s}s ago`; const m = Math.floor(s/60);
            if (m < 60) return `${m}m ago`; const h = Math.floor(m/60);
            if (h < 24) return `${h}h ago`; const d = Math.floor(h/24);
            return `${d}d ago`;
        }

        function render() {
            const items = load();
            const unread = items.filter(i => !i.read).length;
            unreadEl.textContent = unread;
            listEl.innerHTML = items.map(i => {
                const iconClass = i.type === 'deadline' ? 'deadline' : i.type === 'message' ? 'message' : i.type === 'document' ? 'document' : 'system';
                const actionText = i.read ? 'Mark unread' : 'Mark read';
                return `
                <div class="notif-item ${i.read ? '' : 'unread'}" data-id="${i.id}">
                    <div class="notif-icon ${iconClass}"><i class="fas fa-${i.type === 'deadline' ? 'calendar-alt' : i.type === 'message' ? 'envelope' : i.type === 'document' ? 'file-alt' : 'cog'}"></i></div>
                    <div>
                        <div class="notif-title">${i.title}</div>
                        <div class="notif-desc">${i.desc}</div>
                        <div class="notif-meta"><span>${relTime(i.ts)}</span><span>${i.priority}</span></div>
                    </div>
                    <div class="notif-action"><a href="#" data-act="toggle">${actionText}</a></div>
                </div>`;
            }).join('');
        }

        function toggleItem(id) {
            const items = load();
            const idx = items.findIndex(n => n.id === id);
            if (idx !== -1) {
                items[idx].read = !items[idx].read;
                save(items); render();
            }
        }

        // Seed and initial render
        seedData(); render();

        // Interactions
        listEl.addEventListener('click', (e) => {
            const act = e.target.getAttribute('data-act');
            if (act === 'toggle') {
                e.preventDefault();
                const id = e.target.closest('.notif-item').getAttribute('data-id');
                toggleItem(id);
            }
        });

        markAllBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const items = load().map(i => ({...i, read: true}));
            save(items); render();
        });

        function openDropdown(){ dropdown.classList.add('show'); }
        function closeDropdown(){ dropdown.classList.remove('show'); }

        icon.addEventListener('click', (e) => {
            e.stopPropagation(); e.preventDefault();
            if (dropdown.classList.contains('show')) closeDropdown(); else openDropdown();
        });

        let hoverOpenTimer=null, hoverCloseTimer=null;
        card.addEventListener('mouseenter', () => {
            clearTimeout(hoverCloseTimer);
            hoverOpenTimer = setTimeout(() => openDropdown(), 250);
        });
        card.addEventListener('mouseleave', () => {
            clearTimeout(hoverOpenTimer);
            hoverCloseTimer = setTimeout(() => closeDropdown(), 250);
        });

        // Card click navigates to full notifications page
        card.addEventListener('click', (e) => {
            if (e.target.closest('#notifCardIcon')) return;
            window.location.href = 'notifications.html';
        });

        // Accessibility
        card.addEventListener('keydown', (e) => {
            if ((e.key === 'Enter' || e.key === ' ') && !e.altKey) {
                e.preventDefault(); window.location.href = 'notifications.html';
            } else if (e.key === 'Enter' && e.altKey) {
                e.preventDefault(); if (dropdown.classList.contains('show')) closeDropdown(); else openDropdown();
            }
        });
    });
    </script>
    <!-- Emergency link removed -->
</body>
</html>