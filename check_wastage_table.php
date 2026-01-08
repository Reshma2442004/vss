<?php
require_once 'vss/config/database.php';

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'food_wastage'");
    if ($stmt->rowCount() == 0) {
        echo "Table 'food_wastage' does not exist.\n";
        exit;
    }
    
    // Check table structure
    echo "Table structure:\n";
    $stmt = $pdo->query("DESCRIBE food_wastage");
    $columns = $stmt->fetchAll();
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>