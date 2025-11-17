<?php
// Quick test script to verify implementation
require_once 'config/database.php';

echo "<h2>HMS Implementation Test</h2>";

// Test 1: Database Connection
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];
    echo "✅ Database connected - {$userCount} users found<br>";
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Check required tables
$tables = ['students', 'biometric_devices', 'attendance_logs', 'attendance_summary', 'mess_feedback'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch()['count'];
        echo "✅ Table {$table} exists - {$count} records<br>";
    } catch (Exception $e) {
        echo "❌ Table {$table} missing<br>";
    }
}

// Test 3: Check biometric classes
if (file_exists('HikvisionAPI.php') && file_exists('AttendanceProcessor.php')) {
    echo "✅ Biometric classes available<br>";
} else {
    echo "❌ Biometric classes missing<br>";
}

// Test 4: Check dashboard files
$dashboards = ['student.php', 'student_head.php', 'mess_head.php', 'rector.php'];
foreach ($dashboards as $dashboard) {
    if (file_exists("dashboards/{$dashboard}")) {
        echo "✅ Dashboard {$dashboard} exists<br>";
    } else {
        echo "❌ Dashboard {$dashboard} missing<br>";
    }
}

// Test 5: Sample data check
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM students WHERE finger_id IS NOT NULL");
    $fingerprintCount = $stmt->fetch()['count'];
    echo "✅ Students with fingerprints: {$fingerprintCount}<br>";
} catch (Exception $e) {
    echo "❌ Fingerprint data error<br>";
}

echo "<br><h3>Next Steps:</h3>";
echo "1. Configure device IPs in biometric_devices table<br>";
echo "2. Add finger_id to students<br>";
echo "3. Run: php sync_biometric.php<br>";
echo "4. Setup Windows Task Scheduler<br>";
echo "5. Test dashboards with login credentials<br>";
?>