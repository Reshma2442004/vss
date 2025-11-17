<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect without database first
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hostel_management");
    $pdo->exec("USE hostel_management");
    
    // Read and execute SQL file
    $sql = file_get_contents('vss.sql');
    $pdo->exec($sql);
    
    echo "<h2 style='color: green;'>✓ Database setup completed successfully!</h2>";
    echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Database setup failed: " . $e->getMessage() . "</h2>";
}
?>