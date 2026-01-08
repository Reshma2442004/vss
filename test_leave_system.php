<?php
require_once 'vss/config/database.php';

echo "<h2>Leave System Test & Setup</h2>";

try {
    // Ensure leave_applications table exists with all required columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS `leave_applications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `leave_type` varchar(50) NOT NULL,
        `start_date` date NOT NULL,
        `end_date` date NOT NULL,
        `reason` text NOT NULL,
        `status` enum('pending','approved','rejected') DEFAULT 'pending',
        `reviewed_by` int(11) NULL,
        `reviewed_at` timestamp NULL,
        `applied_at` timestamp DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    echo "<p style='color: green;'>✅ leave_applications table ready</p>";
    
    // Check if users table exists for reviewed_by reference
    $users_check = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($users_check->rowCount() == 0) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('student','rector','admin') NOT NULL,
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "<p style='color: green;'>✅ users table created</p>";
    }
    
    // Insert test data if no leave applications exist
    $count_check = $pdo->query("SELECT COUNT(*) as count FROM leave_applications")->fetch();
    if ($count_check['count'] == 0) {
        // Get first student ID
        $student_check = $pdo->query("SELECT id FROM students LIMIT 1")->fetch();
        if ($student_check) {
            $pdo->exec("INSERT INTO leave_applications (student_id, leave_type, start_date, end_date, reason, status) VALUES 
                ({$student_check['id']}, 'sick', '2025-01-15', '2025-01-17', 'Medical checkup and recovery', 'pending'),
                ({$student_check['id']}, 'home', '2025-01-20', '2025-01-22', 'Family function attendance', 'pending')");
            echo "<p style='color: blue;'>📝 Added sample leave applications</p>";
        }
    }
    
    echo "<p style='color: green;'>🎉 Leave system is ready!</p>";
    echo "<p><a href='vss/dashboards/student.php'>Student Dashboard</a> | <a href='vss/dashboards/rector.php'>Rector Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>