<?php
require_once 'vss/config/database.php';

try {
    // Fix existing students with year 0
    $pdo->exec("UPDATE students SET year = 1 WHERE year = 0 OR year IS NULL");
    
    // Add room_no column if missing
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS room_no VARCHAR(20) DEFAULT NULL");
    
    echo "Fixed existing student data!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>