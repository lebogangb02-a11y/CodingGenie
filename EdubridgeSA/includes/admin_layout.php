<?php
// Shared admin layout components with Bootstrap 5 and dark mode
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';

function admin_require_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: /admin_login.php');
        exit;
    }
}

function admin_header(string $title = 'Admin Dashboard') {
    $username = $_SESSION['admin_username'] ?? 'Admin';
    $role = $_SESSION['admin_role'] ?? 'super';
    
    // Get unread notification count
    $unreadCount = 0;
    if (isset($pdo) && function_exists('getUnreadNotificationCount')) {
        $unreadCount = getUnreadNotificationCount($pdo, $username);
    }
    
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>" . htmlspecialchars($title) . " - EduBridgeSA</title>";
    echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>";
    echo "<link href='/css/admin.css' rel='stylesheet'>";
    echo "<link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css'>";
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css' integrity='sha512-Sl3kBf8g4R2fNw3wQeCw2gk6o8uGZx6nM5f7Mhq5c7H9HkBfYpQ0wH8jG9qEwF1b6Fv7Uu5mHfWmA5lG3jz7Vg==' crossorigin='anonymous' referrerpolicy='no-referrer'/>";
    echo "</head><body class='bg-light'>";
    echo "<div class='d-flex'>";
    // Sidebar
    echo "<nav id='sidebar' class='bg-dark text-white p-3 min-vh-100' style='width:260px;'>";
    echo "<div class='d-flex align-items-center mb-4'><i class='bi bi-mortarboard fs-3 me-2'></i><span class='fs-5 fw-bold'>EduBridgeSA</span></div>";
    echo "<div class='mb-3 small'>" . htmlspecialchars($username) . "<span class='badge bg-secondary ms-2'>" . htmlspecialchars(ucfirst($role)) . "</span></div>";
    echo "<ul class='nav nav-pills flex-column gap-1'>";
    echo "<li class='nav-item'><a class='nav-link text-white' href='/admin_dashboard.php'><i class='bi bi-speedometer2 me-2'></i>Dashboard</a></li>";
    echo "<li class='nav-item'><a class='nav-link text-white' href='/manage_students.php'><i class='bi bi-people me-2'></i>Manage Students</a></li>";
    
    // MESSAGES LINK WITH NOTIFICATION BADGE
    echo "<li class='nav-item'>";
    echo "<a class='nav-link text-white' href='/admin_messages.php'>";
    echo "<i class='bi bi-chat-dots me-2'></i>Messages & Notifications";
    if ($unreadCount > 0): 
        echo "<span class='badge bg-danger rounded-pill float-end'>" . $unreadCount . "</span>";
    endif;
    echo "</a>";
    echo "</li>";
    
    echo "<li class='nav-item'><a class='nav-link text-white' href='/manage_admins.php'><i class='bi bi-shield-lock me-2'></i>Manage Admins</a></li>";
    echo "<li class='nav-item'><a class='nav-link text-white' href='/email_preview.php'><i class='bi bi-envelope-paper me-2'></i>Email Logs</a></li>";
    echo "<li class='nav-item'><a class='nav-link text-white' href='/verify_system_status.php'><i class='bi bi-activity me-2'></i>System Status</a></li>";
    echo "<li class='nav-item mt-3'><a class='nav-link text-white' href='/logout.php'><i class='bi bi-box-arrow-right me-2'></i>Logout</a></li>";
    echo "<div class='form-check form-switch mt-4'><input class='form-check-input' type='checkbox' id='darkModeToggle'><label class='form-check-label' for='darkModeToggle'>Dark Mode</label></div>";
    echo "</ul></nav>";
    // Content wrapper
    echo "<main class='flex-grow-1 p-4'>";
    echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
    echo "<h1 class='h4 m-0'>" . htmlspecialchars($title) . "</h1>";
    echo "<form class='d-flex' method='get' action='/manage_students.php'><input class='form-control me-2' type='search' placeholder='Search students...' name='q'><button class='btn btn-outline-primary' type='submit'><i class='bi bi-search'></i></button></form>";
    echo "</div>";
}

function admin_footer() {
    echo "</main></div>"; // close content and wrapper
    echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js'></script>";
    echo "<script src='/assets/js/admin.js'></script>";
    echo "</body></html>";
}