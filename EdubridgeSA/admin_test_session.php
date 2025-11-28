<?php
/**
 * Admin Session Test Page
 * For testing admin session functionality
 */

require_once 'session_config.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = intval($_POST['test_admin_id']);
    
    try {
        $admin = getAdminById($admin_id);
        if ($admin) {
            setAdminLoginSession($admin);
            header('Location: admin_diagnostic.php?success=true&admin_id=' . $admin_id);
            exit();
        } else {
            header('Location: admin_diagnostic.php?error=admin_not_found');
            exit();
        }
    } catch (Exception $e) {
        header('Location: admin_diagnostic.php?error=' . urlencode($e->getMessage()));
        exit();
    }
}

header('Location: admin_diagnostic.php');
exit();
?>