<?php
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    $pdo->query('SELECT 1');
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test tables
    $tables = ['users', 'hostels', 'students', 'rooms', 'staff', 'attendance', 'mess_attendance', 'books', 'book_issues', 'events', 'event_registrations', 'health_records', 'scholarships'];
    
    echo "<h3>Table Structure Test:</h3>";
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<p style='color: green;'>✓ Table '$table' exists with $count records</p>";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Table '$table' error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Test sample data
    echo "<h3>Sample Data Test:</h3>";
    $users = $pdo->query("SELECT username, role FROM users LIMIT 5")->fetchAll();
    foreach ($users as $user) {
        echo "<p>User: {$user['username']} - Role: {$user['role']}</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}
?>