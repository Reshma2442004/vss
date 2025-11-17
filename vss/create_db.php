<?php
$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Connect to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hostel_management");
    echo "<p style='color: green;'>✓ Database 'hostel_management' created successfully!</p>";
    
    // Use the database
    $pdo->exec("USE hostel_management");
    
    // Now execute the SQL file content
    $sql_content = file_get_contents('vss.sql');
    
    // Remove the CREATE DATABASE and USE statements from the file content
    $sql_content = preg_replace('/CREATE DATABASE.*?;/', '', $sql_content);
    $sql_content = preg_replace('/USE.*?;/', '', $sql_content);
    
    // Split into individual queries and execute
    $queries = explode(';', $sql_content);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
        }
    }
    
    echo "<p style='color: green;'>✓ All tables and data created successfully!</p>";
    echo "<p><strong><a href='auth/login.php'>Go to Login Page</a></strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>