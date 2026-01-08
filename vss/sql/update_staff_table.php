<?php
require_once '../config/database.php';

try {
    // Check if csv_data column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM staff LIKE 'csv_data'");
    if ($stmt->rowCount() == 0) {
        // Add csv_data column
        $pdo->exec("ALTER TABLE staff ADD COLUMN csv_data TEXT DEFAULT NULL");
        echo "✅ Added csv_data column to staff table\n";
    } else {
        echo "ℹ️ csv_data column already exists\n";
    }
    
    echo "✅ Staff table updated successfully\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>