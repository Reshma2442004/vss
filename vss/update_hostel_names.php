<?php
require_once 'config/database.php';

try {
    // Update hostel names to new requirements
    $updates = [
        "UPDATE hostels SET name = 'Aapte girlshostel' WHERE name LIKE '%Aapte%'",
        "UPDATE hostels SET name = 'sumitra sadan girlshostel' WHERE name LIKE '%Sumitra%'",
        "UPDATE hostels SET name = 'P.D.karkhanis boys hostel' WHERE name LIKE '%Karkhanis%'",
        "UPDATE hostels SET name = 'Haribhaupathak Boys hostel' WHERE name LIKE '%Haribhaupathak%'",
        "UPDATE hostels SET name = 'lajpat sankul Boys hostel' WHERE name LIKE '%Lajpat%'",
        "UPDATE hostels SET name = 'Latika Jayvantrav Gaybote girls hostel' WHERE name LIKE '%Latika%' OR name LIKE '%Gaytonde%'"
    ];
    
    foreach ($updates as $sql) {
        $pdo->exec($sql);
    }
    
    echo "✅ Hostel names updated successfully!<br><br>";
    
    // Display updated hostels
    $hostels = $pdo->query("SELECT * FROM hostels ORDER BY id")->fetchAll();
    
    echo "<h3>Updated Hostel List:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Capacity</th><th>Location</th></tr>";
    
    foreach ($hostels as $hostel) {
        echo "<tr>";
        echo "<td>" . $hostel['id'] . "</td>";
        echo "<td>" . $hostel['name'] . "</td>";
        echo "<td>" . $hostel['capacity'] . "</td>";
        echo "<td>" . $hostel['location'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<a href='auth/register.php'>Go to Registration</a><br>";
    echo "<a href='auth/login.php'>Go to Login</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>