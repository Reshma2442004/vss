<?php
require_once 'config/database.php';
require_once 'AttendanceProcessor.php';

$processor = new AttendanceProcessor($pdo);

// Handle report generation
if ($_GET['action'] == 'export' && $_GET['hostel_id'] && $_GET['start_date'] && $_GET['end_date']) {
    $report = $processor->getAttendanceReport($_GET['hostel_id'], $_GET['start_date'], $_GET['end_date']);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $_GET['start_date'] . '_to_' . $_GET['end_date'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student Name', 'GRN', 'Date', 'Morning Meal', 'Night Meal', 'Hostel']);
    
    foreach ($report as $row) {
        fputcsv($output, [$row['name'], $row['grn'], $row['date'], $row['morning_meal'], $row['night_meal'], $row['hostel']]);
    }
    
    fclose($output);
    exit;
}

// Get hostels for dropdown
$hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Biometric Attendance Reports</h2>
    
    <div class="card">
        <div class="card-header">Generate Report</div>
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <label>Hostel:</label>
                        <select name="hostel_id" class="form-control" required>
                            <option value="">Select Hostel</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Start Date:</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>End Date:</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label><br>
                        <button type="submit" name="action" value="view" class="btn btn-primary">View Report</button>
                        <button type="submit" name="action" value="export" class="btn btn-success">Export CSV</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($_GET['action'] == 'view' && $_GET['hostel_id']): ?>
    <div class="card mt-4">
        <div class="card-header">Attendance Report</div>
        <div class="card-body">
            <?php 
            $report = $processor->getAttendanceReport($_GET['hostel_id'], $_GET['start_date'], $_GET['end_date']);
            ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>GRN</th>
                        <th>Date</th>
                        <th>Morning Meal</th>
                        <th>Night Meal</th>
                        <th>Hostel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report as $row): ?>
                    <tr>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['grn']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                        <td><span class="badge bg-<?php echo $row['morning_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $row['morning_meal']; ?></span></td>
                        <td><span class="badge bg-<?php echo $row['night_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $row['night_meal']; ?></span></td>
                        <td><span class="badge bg-<?php echo $row['hostel'] == 'Present' ? 'success' : ($row['hostel'] == 'Late' ? 'warning' : 'danger'); ?>"><?php echo $row['hostel']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>