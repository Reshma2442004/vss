<?php
require_once 'vss/config/database.php';

echo "<h2>🔧 Remove Duplicate Students</h2>";

try {
    // Find duplicates based on name and email
    $duplicates_query = $pdo->query("
        SELECT name, email, COUNT(*) as count, GROUP_CONCAT(id) as ids, hostel_id
        FROM students 
        GROUP BY name, email, hostel_id
        HAVING COUNT(*) > 1
    ");
    $duplicates = $duplicates_query->fetchAll();
    
    if (empty($duplicates)) {
        echo "<p>✅ No duplicate students found!</p>";
        exit;
    }
    
    echo "<p>Found " . count($duplicates) . " sets of duplicate students:</p>";
    
    $total_removed = 0;
    foreach ($duplicates as $duplicate) {
        $ids = explode(',', $duplicate['ids']);
        echo "<p><strong>{$duplicate['name']}</strong> ({$duplicate['email']}) - {$duplicate['count']} duplicates</p>";
        
        // Keep the first record, remove the rest
        $keep_id = array_shift($ids);
        echo "<p>Keeping ID: {$keep_id}, Removing IDs: " . implode(', ', $ids) . "</p>";
        
        foreach ($ids as $id) {
            $delete_stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $delete_stmt->execute([$id]);
            $total_removed++;
        }
    }
    
    echo "<h3>✅ Removed {$total_removed} duplicate students!</h3>";
    echo "<p><a href='vss/dashboards/rector.php'>Back to Rector Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>