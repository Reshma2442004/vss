<?php
require_once 'config/database.php';

try {
    // Disable foreign key checks temporarily
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Create biometric_devices table
    $pdo->exec("CREATE TABLE IF NOT EXISTS biometric_devices (
        device_id INT PRIMARY KEY AUTO_INCREMENT,
        device_name VARCHAR(100) NOT NULL,
        device_ip VARCHAR(15) NOT NULL,
        device_port INT DEFAULT 80,
        username VARCHAR(50) DEFAULT 'admin',
        password VARCHAR(100) NOT NULL,
        device_type ENUM('mess', 'hostel') NOT NULL,
        hostel_id INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_sync TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create attendance_logs table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_logs (
        log_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        grn VARCHAR(20) NOT NULL,
        event_time DATETIME NOT NULL,
        event_type ENUM('mess_morning', 'mess_night', 'hostel_checkin') NOT NULL,
        device_id INT NOT NULL,
        auth_method ENUM('face', 'fingerprint', 'password', 'card', 'manual') DEFAULT 'face',
        source ENUM('biometric', 'manual') DEFAULT 'biometric',
        raw_data TEXT,
        processed BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create attendance_summary table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_summary (
        summary_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        date DATE NOT NULL,
        morning_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
        night_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
        hostel ENUM('Present', 'Absent', 'Late') DEFAULT 'Absent',
        hostel_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Create student_fingerprints table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_fingerprints (
        fingerprint_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        grn VARCHAR(20) NOT NULL,
        finger_index INT NOT NULL,
        template_data LONGTEXT,
        device_id INT,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");

    // Create student_faces table
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_faces (
        face_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        grn VARCHAR(20) NOT NULL,
        face_template LONGTEXT,
        photo_path VARCHAR(255),
        device_id INT,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'inactive') DEFAULT 'active'
    )");

    // Create attendance_windows table
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_windows (
        window_id INT PRIMARY KEY AUTO_INCREMENT,
        hostel_id INT NOT NULL,
        window_type ENUM('mess_morning', 'mess_night', 'hostel_checkin') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        late_threshold_minutes INT DEFAULT 30,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Create general_feedback table
    $pdo->exec("CREATE TABLE IF NOT EXISTS general_feedback (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        feedback_category ENUM('library', 'event', 'staff') NOT NULL,
        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        reviewed_by INT NULL,
        response_message TEXT
    )");

    // Update mess_feedback table structure
    $pdo->exec("DROP TABLE IF EXISTS mess_feedback");
    $pdo->exec("CREATE TABLE mess_feedback (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(50),
        message TEXT NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        reviewed_by INT NULL,
        response_message TEXT
    )");

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "✅ All biometric and feedback tables created successfully!<br>";
    echo "<a href='dashboards/student.php'>Go to Student Dashboard</a><br>";
    echo "<a href='biometric/integration_checklist.php'>Go to Integration Checklist</a>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>