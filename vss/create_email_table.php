<?php
require_once 'config/database.php';

try {
    // Create email logs table
    $sql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed') DEFAULT 'sent',
        error_message TEXT NULL,
        INDEX idx_sender (sender_id),
        INDEX idx_recipient (recipient_id),
        INDEX idx_sent_at (sent_at)
    )";
    
    $pdo->exec($sql);
    
    echo "<h2 style='color: green;'>✓ Email logs table created successfully!</h2>";
    echo "<p>The email_logs table has been created to track all email communications.</p>";
    echo "<p><a href='dashboards/super_admin.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>✗ Table creation failed: " . $e->getMessage() . "</h2>";
}
?>