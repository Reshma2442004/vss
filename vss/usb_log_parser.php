<?php
require_once 'config/database.php';

if ($_POST && isset($_FILES['log_file'])) {
    $file = $_FILES['log_file']['tmp_name'];
    $deviceId = $_POST['device_id'];
    
    if (!$file || !$deviceId) {
        die('File and device selection required');
    }
    
    $processed = 0;
    $handle = fopen($file, 'r');
    
    while (($line = fgets($handle)) !== false) {
        // Parse Hikvision log format: UserID,DateTime,Status
        $parts = explode(',', trim($line));
        if (count($parts) < 2) continue;
        
        $fingerId = trim($parts[0]);
        $eventTime = trim($parts[1]);
        
        // Get student by finger_id
        $stmt = $pdo->prepare("SELECT id FROM students WHERE finger_id = ?");
        $stmt->execute([$fingerId]);
        $student = $stmt->fetch();
        
        if (!$student) continue;
        
        // Determine event type
        $hour = date('H', strtotime($eventTime));
        $stmt = $pdo->prepare("SELECT device_type FROM biometric_devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();
        
        if ($device['device_type'] == 'mess') {
            $eventType = ($hour >= 8 && $hour <= 10) ? 'mess_morning' : 'mess_night';
        } else {
            $eventType = 'hostel_checkin';
        }
        
        // Insert log
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO attendance_logs 
            (student_id, finger_id, event_time, event_type, device_id, source) 
            VALUES (?, ?, ?, ?, ?, 'manual')
        ");
        $stmt->execute([$student['id'], $fingerId, $eventTime, $eventType, $deviceId]);
        $processed++;
    }
    
    fclose($handle);
    echo "Processed {$processed} records";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>USB Log Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3>Import Biometric Logs from USB</h3>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Select Device</label>
            <select name="device_id" class="form-control" required>
                <?php
                $stmt = $pdo->prepare("SELECT * FROM biometric_devices");
                $stmt->execute();
                while ($device = $stmt->fetch()) {
                    echo "<option value='{$device['id']}'>{$device['device_name']}</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Log File (CSV/TXT)</label>
            <input type="file" name="log_file" class="form-control" accept=".csv,.txt" required>
        </div>
        <button type="submit" class="btn btn-primary">Import Logs</button>
    </form>
</div>
</body>
</html>