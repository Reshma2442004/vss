<?php
require_once 'vss/config/database.php';

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
      `applied_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `student_id` (`student_id`),
      KEY `status` (`status`),
      KEY `applied_at` (`applied_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ leave_applications table created successfully!\n";
    
    // Add foreign key constraint if students table exists
    try {
        $fk_sql = "ALTER TABLE `leave_applications` 
                   ADD CONSTRAINT `fk_leave_student` 
                   FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE";
        $pdo->exec($fk_sql);
        echo "✅ Foreign key constraint added successfully!\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️  Foreign key constraint already exists.\n";
        } else {
            echo "⚠️  Could not add foreign key constraint: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 Database setup completed! You can now use the rector dashboard without errors.\n";
    
} catch (PDOException $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
?>