<?php
// fix_form_actions.php - Auto-fix form actions
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

$filesToFix = [
    'student_profile.php',
    'manage_admins.php',
    'add_student.php'
];

$results = [];

foreach ($filesToFix as $file) {
    if (!file_exists($file)) {
        $results[$file] = '❌ File not found';
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Fix form actions
    $fixes = [
        // Fix forms without action attributes
        '/<form method="post"(?!.*action=)/' => '<form method="post" action="' . $file . '"',
        
        // Fix forms with empty actions
        '/<form method="post" action=""/' => '<form method="post" action="' . $file . '"',
        
        // Fix forms with # actions
        '/<form method="post" action="#"/' => '<form method="post" action="' . $file . '"',
    ];
    
    foreach ($fixes as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        $results[$file] = '✅ Fixed form actions';
    } else {
        $results[$file] = '✅ Already correct';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Form Actions - EduBridgeSA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Form Actions Fix</h1>
    
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Fix Results</h5>
        </div>
        <div class="card-body">
            <?php foreach ($results as $file => $result): ?>
                <div class="mb-2">
                    <strong><?= htmlspecialchars($file) ?>:</strong> 
                    <?= $result ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="admin_dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        <a href="full_system_check.php" class="btn btn-outline-secondary">Run System Check Again</a>
    </div>
</div>
</body>
</html>