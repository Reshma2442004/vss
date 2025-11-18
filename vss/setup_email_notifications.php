<?php
require_once 'config/database.php';

echo "<h2>Setting up Email Notification System</h2>";

try {
    // Add email fields to tables
    echo "<p>Adding email fields to tables...</p>";
    
    // Check if email column exists in staff table
    $result = $pdo->query("SHOW COLUMNS FROM staff LIKE 'email'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE staff ADD COLUMN email VARCHAR(100) AFTER contact");
        echo "✓ Added email field to staff table<br>";
    } else {
        echo "✓ Email field already exists in staff table<br>";
    }
    
    // Check if email column exists in users table
    $result = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER role");
        echo "✓ Added email field to users table<br>";
    } else {
        echo "✓ Email field already exists in users table<br>";
    }
    
    // Create email notifications log table
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_notifications_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        recipient_email VARCHAR(100) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        status ENUM('sent', 'failed') DEFAULT 'sent',
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient_email),
        INDEX idx_type (notification_type),
        INDEX idx_sent_at (sent_at)
    )");
    echo "✓ Created email notifications log table<br>";
    
    // Update sample staff emails
    echo "<p>Updating sample staff emails...</p>";
    
    $staff_emails = [
        ['role' => 'rector', 'hostel_id' => 1, 'email' => 'rector1@vss-hostel.com'],
        ['role' => 'rector', 'hostel_id' => 2, 'email' => 'rector2@vss-hostel.com'],
        ['role' => 'mess_head', 'hostel_id' => 1, 'email' => 'mess1@vss-hostel.com'],
        ['role' => 'mess_head', 'hostel_id' => 2, 'email' => 'mess2@vss-hostel.com'],
        ['role' => 'library_head', 'hostel_id' => 1, 'email' => 'library1@vss-hostel.com'],
        ['role' => 'library_head', 'hostel_id' => 2, 'email' => 'library2@vss-hostel.com'],
        ['role' => 'health_staff', 'hostel_id' => 1, 'email' => 'health1@vss-hostel.com'],
        ['role' => 'health_staff', 'hostel_id' => 2, 'email' => 'health2@vss-hostel.com'],
        ['role' => 'placement_staff', 'hostel_id' => 1, 'email' => 'placement1@vss-hostel.com'],
        ['role' => 'placement_staff', 'hostel_id' => 2, 'email' => 'placement2@vss-hostel.com'],
        ['role' => 'scholarship_staff', 'hostel_id' => 1, 'email' => 'scholarship1@vss-hostel.com'],
        ['role' => 'scholarship_staff', 'hostel_id' => 2, 'email' => 'scholarship2@vss-hostel.com']
    ];
    
    foreach ($staff_emails as $staff_email) {
        $stmt = $pdo->prepare("UPDATE staff SET email = ? WHERE role = ? AND hostel_id = ?");
        $stmt->execute([$staff_email['email'], $staff_email['role'], $staff_email['hostel_id']]);
        echo "✓ Updated {$staff_email['role']} email for hostel {$staff_email['hostel_id']}<br>";
    }
    
    echo "<h3>✅ Email Notification System Setup Complete!</h3>";
    echo "<p><strong>Features Added:</strong></p>";
    echo "<ul>";
    echo "<li>Email notifications for new complaints</li>";
    echo "<li>Email notifications for mess feedback</li>";
    echo "<li>Email notifications for scholarship updates</li>";
    echo "<li>Email notifications for leave applications</li>";
    echo "<li>Email notifications for library reminders</li>";
    echo "<li>Email notifications for event registrations</li>";
    echo "<li>Email notifications for placement updates</li>";
    echo "<li>Bulk email notification system</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>Configure your server's mail settings (SMTP)</li>";
    echo "<li>Update email addresses for all users</li>";
    echo "<li>Test the notification system</li>";
    echo "<li>Access the bulk notification interface at: <a href='send_notification.php'>send_notification.php</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>