<?php
// Temporary access bypass for analysis
session_start();
$_SESSION['student_logged_in'] = true;
$_SESSION['student_id'] = 1;
$_SESSION['student_email'] = 'analyzer@edubridge.com';
$_SESSION['application_status'] = 'draft';

header('Location: analyze_application_structure.php');
exit();
?>