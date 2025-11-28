<?php
// file_explorer.php - Check what files exist
session_start();
echo "<h3>File Explorer - Checking Server Files</h3>";
echo "<style>body { font-family: Arial; margin: 20px; } .file { padding: 5px; } .exists { color: green; } .missing { color: red; }</style>";

$filesToCheck = [
    'admin_dashboard.php',
    'manage_students.php', 
    'student_profile.php',
    'add_student.php',
    'admin_login.php',
    'config.php',
    'session_config.php',
    'controllers/StudentController.php',
    'includes/admin_layout.php'
];

foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        echo "<div class='file exists'>✅ $file - EXISTS</div>";
    } else {
        echo "<div class='file missing'>❌ $file - MISSING</div>";
    }
}

echo "<hr><h4>Current Directory Contents:</h4>";
$files = scandir('.');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        $type = is_dir($file) ? '📁' : '📄';
        echo "<div>$type $file</div>";
    }
}
?>