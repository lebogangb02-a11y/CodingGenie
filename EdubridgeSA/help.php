<?php
require_once 'session_config.php';
// Redirect helper/legacy link to the Support Center
if (isset($_SESSION['student_logged_in']) && $_SESSION['student_logged_in'] === true) {
    header('Location: support.php');
} else {
    header('Location: student-login.php?next=support.php');
}
exit();