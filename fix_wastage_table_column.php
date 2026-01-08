<?php
require_once 'vss/config/database.php';

try {
    echo "Checking food_wastage table structure...\n";
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'food_wastage'");
    if ($stmt->rowCount() == 0) {
        echo "Error: Table 'food_wastage' does not exist. Please create it first.\n";
        exit;
    }
    
    // Check if created_at column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM food_wastage LIKE 'created_at'");
    if ($stmt->rowCount() == 0) {
        echo "Adding missing 'created_at' column...\n";
        
        // Add the created_at column
        $pdo->exec("ALTER TABLE food_wastage ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        
        // Update existing records
        $pdo->exec("UPDATE food_wastage SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL");
        
        echo "Successfully added 'created_at' column to food_wastage table.\n";
    } else {
        echo "'created_at' column already exists in food_wastage table.\n";
    }
    
    // Show final table structure
    echo "\nCurrent table structure:\n";
    $stmt = $pdo->query("DESCRIBE food_wastage");
    $columns = $stmt->fetchAll();
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    echo "\nTable fix completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>