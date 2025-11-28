<?php
$files = ['student-apply.php', 'apply_test.php', 'simple_apply.php'];

foreach ($files as $file) {
    echo "<p>$file: ";
    if (file_exists($file)) {
        echo "EXISTS | ";
        echo is_readable($file) ? "READABLE" : "NOT READABLE";
    } else {
        echo "DOES NOT EXIST";
    }
    echo "</p>";
}
?>