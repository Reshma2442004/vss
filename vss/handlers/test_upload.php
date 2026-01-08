<?php
session_start();
require_once '../config/database.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>VSS Upload Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 VSS CSV Upload System Test</h1>
    <p>This page tests the CSV upload functionality and system requirements.</p>

    <?php
    $tests = [];
    
    // Test 1: PHP Configuration
    $tests[] = [
        'name' => 'PHP File Upload Settings',
        'status' => 'info',
        'details' => [
            'file_uploads' => ini_get('file_uploads') ? '✅ Enabled' : '❌ Disabled',
            'upload_max_filesize' => '📁 ' . ini_get('upload_max_filesize'),
            'post_max_size' => '📦 ' . ini_get('post_max_size'),
            'max_execution_time' => '⏱️ ' . ini_get('max_execution_time') . 's',
            'memory_limit' => '💾 ' . ini_get('memory_limit'),
            'max_file_uploads' => '📊 ' . ini_get('max_file_uploads') . ' files'
        ]
    ];
    
    // Test 2: Directory Structure
    $upload_dir = '../uploads/students/';
    $dir_exists = is_dir($upload_dir);
    $dir_writable = $dir_exists && is_writable($upload_dir);
    
    $tests[] = [
        'name' => 'Upload Directory',
        'status' => $dir_exists && $dir_writable ? 'success' : 'error',
        'details' => [
            'directory_path' => realpath($upload_dir) ?: $upload_dir,
            'exists' => $dir_exists ? '✅ Yes' : '❌ No',
            'writable' => $dir_writable ? '✅ Yes' : '❌ No',
            'permissions' => $dir_exists ? '🔐 ' . substr(sprintf('%o', fileperms($upload_dir)), -4) : 'N/A'
        ]
    ];
    
    // Test 3: Database Connection
    try {
        $db_test = $pdo->query("SELECT 1");
        $db_status = 'success';
        $db_details = ['connection' => '✅ Connected', 'version' => '🗄️ ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION)];
    } catch (Exception $e) {
        $db_status = 'error';
        $db_details = ['connection' => '❌ Failed: ' . $e->getMessage()];
    }
    
    $tests[] = [
        'name' => 'Database Connection',
        'status' => $db_status,
        'details' => $db_details
    ];
    
    // Test 4: Students Table Structure
    try {
        $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_ASSOC);
        $required_columns = [
            'id', 'grn', 'grn_no', 'name', 'first_name', 'middle_name', 'last_name',
            'mothers_name', 'email', 'contact', 'student_mobile', 'parents_mobile',
            'course', 'faculty', 'year', 'samiti_year', 'college_year', 'course_duration',
            'hostel_id', 'hostel_allocation', 'wing', 'floor', 'room_no', 'room_number', 'password'
        ];
        
        $existing_columns = array_column($columns, 'Field');
        $missing_columns = array_diff($required_columns, $existing_columns);
        
        $table_status = empty($missing_columns) ? 'success' : 'warning';
        $table_details = [
            'total_columns' => '📊 ' . count($existing_columns),
            'required_columns' => '✅ ' . count($required_columns),
            'missing_columns' => empty($missing_columns) ? '✅ None' : '⚠️ ' . implode(', ', $missing_columns)
        ];
        
    } catch (Exception $e) {
        $table_status = 'error';
        $table_details = ['error' => '❌ ' . $e->getMessage()];
    }
    
    $tests[] = [
        'name' => 'Students Table Structure',
        'status' => $table_status,
        'details' => $table_details
    ];
    
    // Test 5: Session Information
    $session_status = isset($_SESSION['user_id']) && isset($_SESSION['role']) ? 'success' : 'warning';
    $session_details = [
        'session_started' => session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive',
        'user_id' => isset($_SESSION['user_id']) ? '👤 ' . $_SESSION['user_id'] : '❌ Not set',
        'role' => isset($_SESSION['role']) ? '🎭 ' . $_SESSION['role'] : '❌ Not set',
        'hostel_id' => isset($_SESSION['hostel_id']) ? '🏠 ' . $_SESSION['hostel_id'] : '⚠️ Not set'
    ];
    
    $tests[] = [
        'name' => 'Session Information',
        'status' => $session_status,
        'details' => $session_details
    ];
    
    // Test 6: Sample CSV File
    $sample_file = '../assets/sample_students.csv';
    $sample_exists = file_exists($sample_file);
    
    $tests[] = [
        'name' => 'Sample CSV File',
        'status' => $sample_exists ? 'success' : 'warning',
        'details' => [
            'file_exists' => $sample_exists ? '✅ Available' : '❌ Missing',
            'file_path' => $sample_file,
            'file_size' => $sample_exists ? '📁 ' . number_format(filesize($sample_file)) . ' bytes' : 'N/A'
        ]
    ];
    
    // Display test results
    foreach ($tests as $test) {
        echo "<div class='test-result {$test['status']}'>";
        echo "<h3>🧪 {$test['name']}</h3>";
        foreach ($test['details'] as $key => $value) {
            echo "<div><strong>" . ucwords(str_replace('_', ' ', $key)) . ":</strong> $value</div>";
        }
        echo "</div>";
    }
    ?>
    
    <div class="test-result info">
        <h3>📋 Recommendations</h3>
        <ul>
            <li>Ensure all tests show ✅ (success) status</li>
            <li>If upload directory is missing, create it manually</li>
            <li>If database columns are missing, run the rector dashboard once to auto-create them</li>
            <li>Make sure you're logged in as a rector to test uploads</li>
            <li>Use the sample CSV format for testing</li>
        </ul>
    </div>
    
    <div class="test-result info">
        <h3>🔄 Quick Actions</h3>
        <p>
            <a href="../dashboards/rector.php" style="background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">← Back to Rector Dashboard</a>
            <a href="../assets/sample_students.csv" download style="background: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; margin-left: 10px;">📥 Download Sample CSV</a>
        </p>
    </div>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'rector'): ?>
    <div class="test-result success">
        <h3>✅ Ready to Test</h3>
        <p>You are logged in as a rector. You can now test CSV uploads on the main dashboard.</p>
    </div>
    <?php else: ?>
    <div class="test-result warning">
        <h3>⚠️ Login Required</h3>
        <p>Please log in as a rector to test CSV upload functionality.</p>
    </div>
    <?php endif; ?>
    
    <hr>
    <small>Generated on <?php echo date('Y-m-d H:i:s'); ?> | PHP <?php echo PHP_VERSION; ?></small>
</body>
</html>