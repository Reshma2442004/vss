<?php
require_once 'config/database.php';

try {
    // Clear existing hostels
    $pdo->exec("DELETE FROM hostels");
    
    // Insert new hostels
    $hostels = [
        ['Aapte girlshostel', 150, 'South Campus Block B'],
        ['sumitra sadan girlshostel', 180, 'East Campus Block C'],
        ['P.D.karkhanis boys hostel', 120, 'West Campus Block D'],
        ['Haribhaupathak Boys hostel', 160, 'North Campus Block F'],
        ['lajpat sankul Boys hostel', 220, 'Central Campus Block E'],
        ['Latika Jayvantrav Gaybote girls hostel', 200, 'North Campus Block A']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO hostels (name, capacity, location) VALUES (?, ?, ?)");
    
    foreach ($hostels as $hostel) {
        $stmt->execute($hostel);
    }
    
    echo "✅ Hostels updated successfully!<br>";
    echo "<a href='auth/register.php'>Go to Registration</a><br>";
    echo "<a href='auth/login.php'>Go to Login</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>