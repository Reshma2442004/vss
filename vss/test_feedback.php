<?php
require_once 'config/database.php';

echo "<h2>Feedback System Test</h2>";

// Test 1: Check if feedback table exists with all columns
try {
    $stmt = $pdo->query("DESCRIBE mess_feedback");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Feedback table exists with columns: " . implode(', ', $columns) . "<br>";
} catch (Exception $e) {
    echo "❌ Feedback table issue: " . $e->getMessage() . "<br>";
}

// Test 2: Check recent feedback submissions
try {
    $stmt = $pdo->query("
        SELECT mf.*, s.name as student_name 
        FROM mess_feedback mf 
        JOIN students s ON mf.student_id = s.id 
        ORDER BY mf.created_at DESC 
        LIMIT 5
    ");
    $feedback = $stmt->fetchAll();
    
    echo "<h3>Recent Feedback:</h3>";
    if (empty($feedback)) {
        echo "No feedback found. Submit some feedback from student dashboard.<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Student</th><th>Type</th><th>Subject</th><th>Category</th><th>Priority</th><th>Rating</th><th>Status</th><th>Date</th></tr>";
        foreach ($feedback as $f) {
            echo "<tr>";
            echo "<td>#" . str_pad($f['id'], 4, '0', STR_PAD_LEFT) . "</td>";
            echo "<td>" . $f['student_name'] . "</td>";
            echo "<td>" . $f['feedback_type'] . "</td>";
            echo "<td>" . $f['subject'] . "</td>";
            echo "<td>" . ($f['category'] ?? 'N/A') . "</td>";
            echo "<td>" . ($f['priority'] ?? 'medium') . "</td>";
            echo "<td>" . $f['rating'] . "/5</td>";
            echo "<td>" . $f['status'] . "</td>";
            echo "<td>" . date('M d, Y H:i', strtotime($f['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching feedback: " . $e->getMessage() . "<br>";
}

// Test 3: Dashboard access links
echo "<h3>Dashboard Access:</h3>";
echo "<a href='dashboards/student.php' target='_blank'>Student Dashboard</a> - Submit feedback here<br>";
echo "<a href='dashboards/mess_head.php' target='_blank'>Mess Head Dashboard</a> - View and manage feedback<br>";
echo "<a href='dashboards/student_head.php' target='_blank'>Student Head Dashboard</a> - Monitor feedback<br>";
echo "<a href='dashboards/rector.php' target='_blank'>Rector Dashboard</a> - Oversee all feedback<br>";

echo "<h3>Test Instructions:</h3>";
echo "1. Login as student (student1/admin123)<br>";
echo "2. Go to Mess Feedback section<br>";
echo "3. Fill form and submit<br>";
echo "4. Check success message appears<br>";
echo "5. Login as mess_head (messhead1/admin123)<br>";
echo "6. Verify feedback appears in their dashboard<br>";
?>