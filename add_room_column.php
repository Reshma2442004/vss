<?php
require_once 'vss/config/database.php';

try {
    // Add room_no column if it doesn't exist
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS room_no VARCHAR(20) DEFAULT NULL");
    echo "Room number column added successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>