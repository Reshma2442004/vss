<?php
require_once 'config/database.php';
require_once 'HikvisionAPI.php';
require_once 'AttendanceProcessor.php';

// Get all active devices
$stmt = $pdo->prepare("SELECT * FROM biometric_devices WHERE status = 'active'");
$stmt->execute();
$devices = $stmt->fetchAll();

$totalProcessed = 0;

foreach ($devices as $device) {
    $api = new HikvisionAPI($device['device_ip'], $device['username'], $device['password'], $pdo);
    
    // Fetch logs from last 24 hours
    $startTime = date('Y-m-d\TH:i:s', strtotime('-24 hours'));
    $endTime = date('Y-m-d\TH:i:s');
    
    $logs = $api->fetchAttendanceLogs($startTime, $endTime);
    
    if ($logs) {
        $processed = $api->processLogs($logs, $device['id']);
        $totalProcessed += $processed;
        echo "Device {$device['device_name']}: {$processed} logs processed\n";
    } else {
        echo "Device {$device['device_name']}: Connection failed\n";
    }
}

// Generate daily summary for yesterday and today
$processor = new AttendanceProcessor($pdo);
$processor->generateDailySummary(date('Y-m-d', strtotime('-1 day')));
$processor->generateDailySummary(date('Y-m-d'));

echo "Total logs processed: {$totalProcessed}\n";
echo "Daily summaries updated\n";
?>