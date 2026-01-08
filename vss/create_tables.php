<?php
require_once 'config/database.php';

echo "<h2>Creating QR Attendance Tables...</h2>";

try {
    // Create QR attendance sessions table
    $sql1 = "CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_code VARCHAR(50) UNIQUE NOT NULL,
        meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL,
        date DATE NOT NULL,
        hostel_id INT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    )";
    
    $pdo->exec($sql1);
    echo "✅ qr_attendance_sessions table created successfully!<br>";
    
    // Create QR mess attendance table
    $sql2 = "CREATE TABLE IF NOT EXISTS qr_mess_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (session_id, student_id)
    )";
    
    $pdo->exec($sql2);
    echo "✅ qr_mess_attendance table created successfully!<br>";
    
    echo "<br><strong>All tables created! You can now use the QR attendance system.</strong><br>";
    echo "<a href='qr_attendance.php'>Go to QR Attendance</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>