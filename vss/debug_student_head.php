<?php
session_start();
require_once 'config/database.php';

echo "<h2>Debug Student Head Login</h2>";

// Check session
echo "<h3>Session Data:</h3>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
echo "Hostel ID: " . ($_SESSION['hostel_id'] ?? 'Not set') . "<br>";

// Check if user exists in database
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    echo "<h3>User Database Record:</h3>";
    if ($user) {
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
        echo "Hostel ID: " . $user['hostel_id'] . "<br>";
    } else {
        echo "User not found in database!<br>";
    }
}

// Check hostel data
echo "<h3>Available Hostels:</h3>";
$hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
foreach ($hostels as $hostel) {
    echo "ID: " . $hostel['id'] . " - Name: " . $hostel['name'] . "<br>";
}

// Check student head users
echo "<h3>Student Head Users:</h3>";
$student_heads = $pdo->query("SELECT * FROM users WHERE role = 'student_head'")->fetchAll();
foreach ($student_heads as $head) {
    echo "ID: " . $head['id'] . " - Username: " . $head['username'] . " - Hostel ID: " . $head['hostel_id'] . "<br>";
}

// Test basic query
if (isset($_SESSION['hostel_id']) && $_SESSION['hostel_id']) {
    echo "<h3>Test Queries for Hostel ID " . $_SESSION['hostel_id'] . ":</h3>";
    
    try {
        $total_students = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_id = ?");
        $total_students->execute([$_SESSION['hostel_id']]);
        echo "Total Students: " . $total_students->fetchColumn() . "<br>";
        
        $total_staff = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE hostel_id = ?");
        $total_staff->execute([$_SESSION['hostel_id']]);
        echo "Total Staff: " . $total_staff->fetchColumn() . "<br>";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "<h3>No hostel_id in session - cannot run queries</h3>";
}
?>