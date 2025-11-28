<?php
$files_to_check = ['student-apply.php', 'apply_test.php', 'apply.php'];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (strpos($content, '<form') !== false && strpos($content, 'method="POST"') !== false) {
            echo "<p>Found form in: <strong>$file</strong></p>";
            
            // Extract form action
            preg_match('/<form[^>]*action="([^"]*)"/', $content, $matches);
            $action = $matches[1] ?? 'NOT FOUND';
            echo "<p>Form action: <strong>$action</strong></p>";
            
            if ($action !== 'handleApplicationSubmit.php') {
                echo "<p style='color: red;'>❌ WRONG ACTION - Should be: handleApplicationSubmit.php</p>";
            } else {
                echo "<p style='color: green;'>✅ CORRECT ACTION</p>";
            }
        }
    }
}
?>