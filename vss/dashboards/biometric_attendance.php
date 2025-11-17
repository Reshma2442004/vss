<?php
session_start();
require_once '../config/database.php';

// Check user role and get hostel access
$hostelId = null;
$canViewAll = false;

if ($_SESSION['role'] === 'super_admin') {
    $canViewAll = true;
} elseif (in_array($_SESSION['role'], ['rector', 'student_head'])) {
    // Get hostel from staff table
    $stmt = $pdo->prepare("SELECT hostel_id FROM staff WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $staff = $stmt->fetch();
    $hostelId = $staff['hostel_id'] ?? 1;
}

if (!$canViewAll && !$hostelId) {
    die("Access denied");
}

// Get attendance data
$currentDate = date('Y-m-d');
$whereClause = $canViewAll ? "" : "WHERE s.hostel_id = {$hostelId}";

$attendanceQuery = "
    SELECT s.grn, s.name, h.name as hostel_name,
           COALESCE(ats.morning_meal, 'Absent') as morning_meal,
           COALESCE(ats.night_meal, 'Absent') as night_meal,
           COALESCE(ats.hostel, 'Absent') as hostel_attendance,
           ats.date
    FROM students s
    JOIN hostels h ON s.hostel_id = h.id
    LEFT JOIN attendance_summary ats ON s.id = ats.student_id AND ats.date = ?
    {$whereClause}
    ORDER BY s.hostel_id, s.grn
";

$stmt = $pdo->prepare($attendanceQuery);
$stmt->execute([$currentDate]);
$attendanceData = $stmt->fetchAll();

// Get statistics
$statsQuery = "
    SELECT h.name as hostel_name,
           COUNT(s.id) as total_students,
           SUM(CASE WHEN ats.morning_meal = 'Present' THEN 1 ELSE 0 END) as morning_present,
           SUM(CASE WHEN ats.night_meal = 'Present' THEN 1 ELSE 0 END) as night_present,
           SUM(CASE WHEN ats.hostel = 'Present' THEN 1 ELSE 0 END) as hostel_present,
           SUM(CASE WHEN ats.hostel = 'Late' THEN 1 ELSE 0 END) as hostel_late
    FROM students s
    JOIN hostels h ON s.hostel_id = h.id
    LEFT JOIN attendance_summary ats ON s.id = ats.student_id AND ats.date = ?
    {$whereClause}
    GROUP BY h.id, h.name
";

$stmt = $pdo->prepare($statsQuery);
$stmt->execute([$currentDate]);
$stats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Attendance - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#">
                <i class="fas fa-fingerprint me-2"></i>Biometric Attendance
            </a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-white" href="../biometric/attendance_reports.php">
                        <i class="fas fa-chart-bar me-1"></i>Reports
                    </a>
                </li>
                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../biometric/device_management.php">
                        <i class="fas fa-cogs me-1"></i>Devices
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../biometric/fingerprint_enrollment.php">
                        <i class="fas fa-fingerprint me-1"></i>Login Setup
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../biometric/face_enrollment.php">
                        <i class="fas fa-user-circle me-1"></i>Face Attendance
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link text-white" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <?php foreach ($stats as $stat): ?>
            <div class="col-md-<?php echo $canViewAll ? '6' : '12'; ?> mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><?php echo $stat['hostel_name']; ?> - Today's Attendance</h5>
                    </div>
                    <div class="card-content">
                        <div class="row text-center">
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number"><?php echo $stat['total_students']; ?></div>
                                    <div class="stat-label">Total Students</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number text-success"><?php echo $stat['morning_present']; ?></div>
                                    <div class="stat-label">Morning Meal</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number text-info"><?php echo $stat['night_present']; ?></div>
                                    <div class="stat-label">Night Meal</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number text-primary"><?php echo $stat['hostel_present']; ?></div>
                                    <div class="stat-label">Hostel Present</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number text-warning"><?php echo $stat['hostel_late']; ?></div>
                                    <div class="stat-label">Hostel Late</div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-number text-danger"><?php echo $stat['total_students'] - $stat['hostel_present'] - $stat['hostel_late']; ?></div>
                                    <div class="stat-label">Hostel Absent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Attendance Table -->
        <div class="modern-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3>Today's Attendance Details (<?php echo date('M d, Y'); ?>)</h3>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="syncDevices()">
                        <i class="fas fa-sync-alt me-1"></i>Sync Devices
                    </button>
                    <button class="btn btn-success btn-sm" onclick="exportData()">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                </div>
            </div>
            <div class="card-content">
                <div class="modern-table-container" style="max-height: 600px; overflow-y: auto;">
                    <table class="modern-table">
                        <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                            <tr>
                                <th>GRN</th>
                                <th>Student Name</th>
                                <?php if ($canViewAll): ?><th>Hostel</th><?php endif; ?>
                                <th>Morning Meal</th>
                                <th>Night Meal</th>
                                <th>Hostel Attendance</th>
                                <th>Auth Method</th>
                                <th>Overall Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceData as $record): ?>
                            <tr>
                                <td><strong><?php echo $record['grn']; ?></strong></td>
                                <td><?php echo $record['name']; ?></td>
                                <?php if ($canViewAll): ?><td><?php echo $record['hostel_name']; ?></td><?php endif; ?>
                                <td>
                                    <span class="status-badge <?php echo $record['morning_meal'] === 'Present' ? 'success' : 'danger'; ?>">
                                        <?php echo $record['morning_meal']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $record['night_meal'] === 'Present' ? 'success' : 'danger'; ?>">
                                        <?php echo $record['night_meal']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $record['hostel_attendance'] === 'Present' ? 'success' : ($record['hostel_attendance'] === 'Late' ? 'warning' : 'danger'); ?>">
                                        <?php echo $record['hostel_attendance']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    // Get latest auth method for this student today
                                    $authStmt = $pdo->prepare("SELECT auth_method FROM attendance_logs WHERE student_id = (SELECT id FROM students WHERE grn = ?) AND DATE(event_time) = ? ORDER BY event_time DESC LIMIT 1");
                                    $authStmt->execute([$record['grn'], $currentDate]);
                                    $authMethod = $authStmt->fetchColumn() ?: 'N/A';
                                    $authIcon = $authMethod === 'face' ? 'fa-user-circle' : ($authMethod === 'fingerprint' ? 'fa-fingerprint' : ($authMethod === 'password' ? 'fa-key' : 'fa-id-card'));
                                    $authColor = $authMethod === 'face' ? 'primary' : ($authMethod === 'fingerprint' ? 'success' : ($authMethod === 'password' ? 'info' : 'secondary'));
                                    ?>
                                    <span class="badge bg-<?php echo $authColor; ?>">
                                        <i class="fas <?php echo $authIcon; ?> me-1"></i><?php echo ucfirst($authMethod); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $overallStatus = 'Full';
                                    $statusClass = 'success';
                                    
                                    if ($record['morning_meal'] === 'Absent' || $record['night_meal'] === 'Absent' || $record['hostel_attendance'] === 'Absent') {
                                        $overallStatus = 'Partial';
                                        $statusClass = 'warning';
                                    }
                                    
                                    if ($record['morning_meal'] === 'Absent' && $record['night_meal'] === 'Absent' && $record['hostel_attendance'] === 'Absent') {
                                        $overallStatus = 'Absent';
                                        $statusClass = 'danger';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $overallStatus; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function syncDevices() {
        if (confirm('Sync all biometric devices? This may take a few minutes.')) {
            fetch('../biometric/sync_attendance.php')
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    }
    
    function exportData() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../biometric/attendance_reports.php';
        
        const fields = {
            action: 'export_csv',
            hostel_id: <?php echo $hostelId ?: 'null'; ?>,
            start_date: '<?php echo $currentDate; ?>',
            end_date: '<?php echo $currentDate; ?>'
        };
        
        Object.keys(fields).forEach(key => {
            if (fields[key] !== null) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }
        });
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    // Auto-refresh every 5 minutes
    setTimeout(() => location.reload(), 300000);
    </script>
</body>
</html>