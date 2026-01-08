<?php
require_once 'config/database.php';

try {
    // Add columns to users table for rector profile management
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS contact VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500) DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_notifications TINYINT(1) DEFAULT 1");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sms_notifications TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'light'");
    
    echo "<h2 style='color: green;'>✓ Database updated successfully!</h2>";
    echo "<p>Added columns for rector profile management:</p>";
    echo "<ul>";
    echo "<li>full_name - For rector's full name</li>";
    echo "<li>contact - For contact number</li>";
    echo "<li>location - For location information</li>";
    echo "<li>profile_photo - For profile photo path</li>";
    echo "<li>email_notifications - For email notification preferences</li>";
    echo "<li>sms_notifications - For SMS notification preferences</li>";
    echo "<li>theme - For UI theme preferences</li>";
    echo "</ul>";
    echo "<p><a href='dashboards/rector.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Rector Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Database update failed: " . $e->getMessage() . "</h2>";
}
?>