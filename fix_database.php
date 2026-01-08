<?php
// Simple web-based database fix
require_once 'vss/config/database.php';

echo "<h2>Database Fix Tool</h2>";

try {
    // Create leave_applications table
    $sql = "CREATE TABLE IF NOT EXISTS `leave_applications` (
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
      KEY `status` (`status`),
      KEY `applied_at` (`applied_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ leave_applications table created successfully!</p>";
    
    // Create avalon_uploads table
    $avalon_sql = "CREATE TABLE IF NOT EXISTS `avalon_uploads` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `title` varchar(255) NOT NULL,
      `description` text,
      `file_name` varchar(255) NOT NULL,
      `file_path` varchar(500) NOT NULL,
      `file_size` int(11) NOT NULL,
      `uploaded_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `student_id` (`student_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Create mess_feedback table if missing
    $feedback_sql = "CREATE TABLE IF NOT EXISTS `mess_feedback` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `student_id` int(11) NOT NULL,
      `feedback_type` enum('complaint','suggestion','compliment') NOT NULL,
      `subject` varchar(255) NOT NULL,
      `message` text NOT NULL,
      `rating` int(1) DEFAULT 1,
      `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
      `reviewed_by` int(11) NULL,
      `reviewed_at` timestamp NULL,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `student_id` (`student_id`),
      KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($feedback_sql);
    echo "<p style='color: green;'>✅ mess_feedback table created successfully!</p>";
    
    $pdo->exec($avalon_sql);
    echo "<p style='color: green;'>✅ avalon_uploads table created successfully!</p>";
    
    echo "<p><strong>Table created with columns:</strong></p>";
    echo "<ul>";
    echo "<li>id (Primary Key)</li>";
    echo "<li>student_id (Foreign Key)</li>";
    echo "<li>leave_type</li>";
    echo "<li>start_date</li>";
    echo "<li>end_date</li>";
    echo "<li>reason</li>";
    echo "<li>status (pending/approved/rejected)</li>";
    echo "<li>reviewed_by</li>";
    echo "<li>reviewed_at</li>";
    echo "<li>applied_at</li>";
    echo "<li>updated_at</li>";
    echo "</ul>";
    
    echo "<p><strong>avalon_uploads table created with columns:</strong></p>";
    echo "<ul>";
    echo "<li>id (Primary Key)</li>";
    echo "<li>student_id (Foreign Key)</li>";
    echo "<li>title</li>";
    echo "<li>description</li>";
    echo "<li>file_name</li>";
    echo "<li>file_path</li>";
    echo "<li>file_size</li>";
    echo "<li>uploaded_at</li>";
    echo "</ul>";
    
    echo "<p><strong>mess_feedback table created with columns:</strong></p>";
    echo "<ul>";
    echo "<li>id (Primary Key)</li>";
    echo "<li>student_id (Foreign Key)</li>";
    echo "<li>feedback_type</li>";
    echo "<li>subject</li>";
    echo "<li>message</li>";
    echo "<li>rating</li>";
    echo "<li>status</li>";
    echo "<li>reviewed_by</li>";
    echo "<li>reviewed_at</li>";
    echo "<li>created_at</li>";
    echo "</ul>";
    
    echo "<p style='color: blue;'>🎉 Database setup completed! You can now use the rector dashboard without errors.</p>";
    echo "<p><a href='vss/dashboards/rector.php'>Go to Rector Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error creating table: " . $e->getMessage() . "</p>";
}
?>