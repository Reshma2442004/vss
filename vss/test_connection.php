<?php
require_once 'config/database.php';

try {
    echo "✅ Database connection successful!<br>";
    
    // Test if hostels table exists
    $result = $pdo->query("SHOW TABLES LIKE 'hostels'");
    if ($result->rowCount() > 0) {
        echo "✅ Hostels table exists<br>";
        
        // Show current hostels
        $hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
        echo "<h3>Current Hostels:</h3>";
        foreach ($hostels as $hostel) {
            echo "- " . $hostel['name'] . "<br>";
        }
    } else {
        echo "❌ Hostels table not found. Please run the database setup first.<br>";
        echo "<a href='vss.sql'>Download SQL file</a> and import it in phpMyAdmin";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>