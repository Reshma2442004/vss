<?php
require_once 'vss/config/database.php';

echo "<h2>Leave System Debug</h2>";

try {
    // Check if leave_applications table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'leave_applications'")->fetch();
    if (!$table_check) {
        echo "<p style='color: red;'>❌ leave_applications table does not exist!</p>";
        echo "<p><a href='test_leave_system.php'>Run Setup Script</a></p>";
        exit;
    }
    echo "<p style='color: green;'>✅ leave_applications table exists</p>";
    
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $pdo->query("DESCRIBE leave_applications")->fetchAll();
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if there are any leave applications
    $count = $pdo->query("SELECT COUNT(*) as count FROM leave_applications")->fetch();
    echo "<h3>Leave Applications Count: {$count['count']}</h3>";
    
    if ($count['count'] > 0) {
        echo "<h3>Sample Leave Applications:</h3>";
        $leaves = $pdo->query("SELECT la.*, s.name as student_name, s.grn, s.hostel_id FROM leave_applications la JOIN students s ON la.student_id = s.id LIMIT 5")->fetchAll();
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Student</th><th>Hostel ID</th><th>Leave Type</th><th>Status</th><th>Applied At</th></tr>";
        foreach($leaves as $leave) {
            echo "<tr>";
            echo "<td>{$leave['id']}</td>";
            echo "<td>{$leave['student_name']} ({$leave['grn']})</td>";
            echo "<td>{$leave['hostel_id']}</td>";
            echo "<td>{$leave['leave_type']}</td>";
            echo "<td>{$leave['status']}</td>";
            echo "<td>{$leave['applied_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠️ No leave applications found. Students need to apply for leave first.</p>";
        
        // Check if students exist
        $student_count = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch();
        echo "<p>Students in database: {$student_count['count']}</p>";
        
        if ($student_count['count'] > 0) {
            // Insert sample leave application
            $student = $pdo->query("SELECT id, hostel_id FROM students LIMIT 1")->fetch();
            if ($student) {
                $pdo->exec("INSERT INTO leave_applications (student_id, leave_type, start_date, end_date, reason, status) VALUES 
                    ({$student['id']}, 'sick', '2025-01-15', '2025-01-17', 'Medical checkup and recovery', 'pending')");
                echo "<p style='color: green;'>✅ Added sample leave application</p>";
            }
        }
    }
    
    // Check session variables that rector dashboard uses
    echo "<h3>Session Check:</h3>";
    session_start();
    if (isset($_SESSION['hostel_id'])) {
        echo "<p>Rector Hostel ID: {$_SESSION['hostel_id']}</p>";
        
        // Check leave applications for this hostel
        $hostel_leaves = $pdo->prepare("SELECT COUNT(*) as count FROM leave_applications la JOIN students s ON la.student_id = s.id WHERE s.hostel_id = ?");
        $hostel_leaves->execute([$_SESSION['hostel_id']]);
        $hostel_count = $hostel_leaves->fetch();
        echo "<p>Leave applications for this hostel: {$hostel_count['count']}</p>";
    } else {
        echo "<p style='color: red;'>❌ No hostel_id in session. Please login as rector first.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
}
?>