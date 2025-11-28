<?php
require_once 'config.php';
echo "CSRF_TOKEN_NAME constant: " . (defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'NOT DEFINED');
?>