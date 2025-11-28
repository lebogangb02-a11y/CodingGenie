<?php
/**
 * Logout Handler for EduBridge SA
 * Destroys session and redirects to home
 */

require_once 'session_config.php';  // Starts session if needed
require_once 'auth.php';  // For logout()

// Call logout function
logout();  // This handles everything: unset, destroy, redirect

// Fallback (rarely reached)
session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');  // Clear session cookie

header('Location: https://edubridgesa.co.za/?logged_out=1');
exit();
?>