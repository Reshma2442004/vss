<?php
require_once 'config/database.php';
require_once 'AttendanceProcessor.php';

if ($_POST) {
    if (isset($_POST['generate_summary'])) {
        $processor = new AttendanceProcessor($pdo);
        $processor->generateDailySummary($_POST['date']);
        $success = "Daily summary generated for " . $_POST['date'];
    }
    
    if (isset($_POST['sync_now'])) {
        $output = shell_exec('php sync_biometric.php 2>&1');
        $success = "Sync completed: " . $output;
    }
}

// Get device status
$devices = $pdo->query("SELECT * FROM biometric_devices")->fetchAll();
$recentLogs = $pdo->query("SELECT al.*, s.name, s.grn FROM attendance_logs al JOIN students s ON al.student_id = s.id ORDER BY al.event_time DESC LIMIT 20")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Biometric Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel=\"stylesheet\">
</head>
<body>
<div class="container mt-4">
    <h2>Biometric System Administration</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Device Status</div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr><th>Device</th><th>IP</th><th>Type</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($devices as $device): ?>
                            <tr>
                                <td><?php echo $device['device_name']; ?></td>
                                <td><?php echo $device['device_ip']; ?></td>
                                <td><?php echo $device['device_type']; ?></td>
                                <td><span class="badge bg-<?php echo $device['status'] == 'active' ? 'success' : 'danger'; ?>"><?php echo $device['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Quick Actions</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <button type="submit" name="sync_now" class="btn btn-primary">Sync Now</button>
                    </form>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label>Generate Summary for Date:</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" name="generate_summary" class="btn btn-success">Generate Summary</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">Recent Attendance Logs</div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                    <tr><th>Time</th><th>Student</th><th>Type</th><th>Source</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><?php echo date('M d H:i', strtotime($log['event_time'])); ?></td>
                        <td><?php echo $log['name'] . ' (' . $log['grn'] . ')'; ?></td>
                        <td><span class="badge bg-info"><?php echo str_replace('_', ' ', $log['event_type']); ?></span></td>
                        <td><?php echo $log['source']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>