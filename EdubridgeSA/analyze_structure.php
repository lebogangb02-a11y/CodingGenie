<?php
// Save as analyze_structure.php
echo "=== FILE STRUCTURE ANALYSIS ===\n\n";

function scanDirRecursive($path, $depth = 0, $maxDepth = 3) {
    if ($depth > $maxDepth) return;
    
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $fullPath = $path . '/' . $item;
        $indent = str_repeat("  ", $depth);
        
        if (is_dir($fullPath)) {
            echo $indent . "📁 " . $item . "/\n";
            scanDirRecursive($fullPath, $depth + 1, $maxDepth);
        } else {
            $ext = pathinfo($item, PATHINFO_EXTENSION);
            echo $indent . "📄 " . $item . " (." . $ext . ")\n";
        }
    }
}

scanDirRecursive(__DIR__);
echo "\n=== ANALYSIS COMPLETE ===\n";
?>