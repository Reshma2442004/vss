<?php
require_once 'config/database.php';

try {
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin' AND role = 'super_admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, 'super_admin', NULL)");
        $stmt->execute(['admin', md5('admin123')]);
        echo "<h2 style='color: green;'>✓ Admin user created successfully!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    } else {
        echo "<h2 style='color: blue;'>✓ Admin user already exists!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
    }
    
    // Also create admin@vsshostel.edu version for compatibility
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin@vsshostel.edu' AND role = 'super_admin'");
    $stmt->execute();
    $admin_email = $stmt->fetch();
    
    if (!$admin_email) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, 'super_admin', NULL)");
        $stmt->execute(['admin@vsshostel.edu', md5('admin123')]);
        echo "<p>✓ Email-based admin user also created for compatibility</p>";
    }
    
    echo "<p><a href='auth/admin_login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Setup failed: " . $e->getMessage() . "</h2>";
}
?>