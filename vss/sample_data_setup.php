<?php
require_once 'config/database.php';

// Create sample users with proper password hashing
$password = password_hash('admin123', PASSWORD_DEFAULT);

try {
    // Sample users for testing
    $users = [
        ['student1', $password, 'student', 1],
        ['studenthead1', $password, 'student_head', 1],
        ['messhead1', $password, 'mess_head', 1],
        ['rector1', $password, 'rector', 1],
        ['admin', $password, 'super_admin', null]
    ];
    
    foreach ($users as $user) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, role, hostel_id) VALUES (?, ?, ?, ?)");
        $stmt->execute($user);
    }
    
    // Sample students with fingerprint IDs
    $students = [
        [1, 'STU001', 'John Doe', 'john@example.com', '9876543210', 1001, 'Computer Science', 2, 1, 1],
        [2, 'STU002', 'Jane Smith', 'jane@example.com', '9876543211', 1002, 'Electronics', 3, 1, 2],
        [3, 'STU003', 'Bob Johnson', 'bob@example.com', '9876543212', 1003, 'Mechanical', 1, 1, 3]
    ];
    
    foreach ($students as $student) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO students (user_id, grn, name, email, contact, finger_id, course, year, hostel_id, room_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($student);
    }
    
    // Sample attendance logs for testing
    $logs = [
        [1, 1001, date('Y-m-d 08:30:00'), 'mess_morning', 1],
        [1, 1001, date('Y-m-d 19:30:00'), 'mess_night', 1],
        [1, 1001, date('Y-m-d 20:15:00'), 'hostel_checkin', 2],
        [2, 1002, date('Y-m-d 08:45:00'), 'mess_morning', 1],
        [2, 1002, date('Y-m-d 21:30:00'), 'hostel_checkin', 2]
    ];
    
    foreach ($logs as $log) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO attendance_logs (student_id, finger_id, event_time, event_type, device_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($log);
    }
    
    echo "✅ Sample data created successfully!\n";
    echo "Login credentials:\n";
    echo "- Student: student1 / admin123\n";
    echo "- Student Head: studenthead1 / admin123\n";
    echo "- Mess Head: messhead1 / admin123\n";
    echo "- Rector: rector1 / admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>