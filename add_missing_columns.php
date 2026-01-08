<?php
require_once 'vss/config/database.php';

echo "<h2>Add Missing Columns</h2>";

try {
    // Add reviewed_by column to existing tables
    $tables = ['leave_applications', 'mess_feedback', 'avalon_uploads'];
    
    foreach($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `reviewed_by` int(11) NULL");
            echo "<p style='color: green;'>✅ Added reviewed_by to $table</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p style='color: blue;'>ℹ️ reviewed_by already exists in $table</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ $table: " . $e->getMessage() . "</p>";
            }
        }
        
        try {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `reviewed_at` timestamp NULL");
            echo "<p style='color: green;'>✅ Added reviewed_at to $table</p>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p style='color: blue;'>ℹ️ reviewed_at already exists in $table</p>";
            } else {
                echo "<p style='color: orange;'>⚠️ $table: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<p style='color: blue;'>🎉 Column updates completed!</p>";
    echo "<p><a href='vss/dashboards/rector.php'>Go to Rector Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>