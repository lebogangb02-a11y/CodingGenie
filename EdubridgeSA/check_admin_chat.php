<?php
// check_admin_chat.php
if (file_exists('admin_chat.php')) {
    echo "<pre>";
    echo "=== Admin Chat Analysis ===\n\n";
    
    $content = file_get_contents('admin_chat.php');
    
    // Check for key features
    $features = [
        'conversation' => strpos($content, 'conversation') !== false,
        'message' => strpos($content, 'message') !== false,
        'student' => strpos($content, 'student') !== false,
        'bot' => strpos($content, 'bot') !== false,
        'ajax' => strpos($content, 'ajax') !== false,
        'real-time' => (strpos($content, 'setInterval') !== false || strpos($content, 'setTimeout') !== false)
    ];
    
    echo "Features Detected:\n";
    foreach ($features as $feature => $exists) {
        echo "  " . ($exists ? "✅" : "❌") . " $feature\n";
    }
    
    echo "\nFile Structure:\n";
    $lines = file('admin_chat.php');
    $total_lines = count($lines);
    echo "Total Lines: $total_lines\n";
    
    // Show important sections
    $sections = [
        'Database' => false,
        'HTML Structure' => false,
        'JavaScript' => false,
        'PHP Processing' => false
    ];
    
    foreach ($lines as $i => $line) {
        if (strpos($line, 'SELECT') !== false || strpos($line, 'INSERT') !== false) $sections['Database'] = true;
        if (strpos($line, '<html') !== false || strpos($line, '<div') !== false) $sections['HTML Structure'] = true;
        if (strpos($line, 'script') !== false || strpos($line, 'function') !== false) $sections['JavaScript'] = true;
        if (strpos($line, '<?php') !== false || strpos($line, '$_') !== false) $sections['PHP Processing'] = true;
    }
    
    foreach ($sections as $section => $found) {
        echo "  " . ($found ? "✅" : "❌") . " $section\n";
    }
    
    echo "</pre>";
} else {
    echo "admin_chat.php not found";
}
?>