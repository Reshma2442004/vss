<?php
require_once 'vss/config/database.php';

echo "<h2>🔧 One-Time Database Fix</h2>";

try {
    // Add missing columns
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS contact VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS course VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS year INT DEFAULT 1");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
    
    echo "<p>✅ Database structure fixed!</p>";
    echo "<p><a href='vss/dashboards/rector.php'>Go to Rector Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>