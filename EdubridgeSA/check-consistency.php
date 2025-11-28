<?php 
$pages = ['index.php', 'about.php', 'news.php', 'universities.php', 'tvet.php']; 
$consistent_classes = ['edu-header', 'hero-section', 'standard-section', 'standard-card']; 

foreach ($pages as $page) { 
    if (file_exists($page)) { 
        $content = file_get_contents($page); 
        $missing = []; 
        
        foreach ($consistent_classes as $class) { 
            if (strpos($content, $class) === false) { 
                $missing[] = $class; 
            } 
        } 
        
        echo "📄 $page: " . (empty($missing) ? "✅ Consistent" : "❌ Missing: " . implode(', ', $missing)) . "\n"; 
    } else { 
        echo "📄 $page: ❌ File not found\n"; 
    } 
} 
?>