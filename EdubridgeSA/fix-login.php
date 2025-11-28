<?php
// Try different config paths - one should work:
$config_paths = [
    'config/database.php',
    '../config/database.php', 
    '../../config/database.php',
    'config.php',
    '../config.php',
    '../../config.php',
    'includes/config.php',
    'admin/config.php',
    'db/config.php'
];

foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        echo "✅ Found config at: $path";
        break;
    }
}
?>