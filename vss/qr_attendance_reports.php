<?php
require_once 'config/database.php';
require_once 'includes/db_check.php';
session_start();

if ($_SESSION['role'] != 'rector') {
    header('Location: auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Get attendance data
$date_filter = $_GET['date'] ?? date('Y-m-d');

$attendance_query = $pdo->prepare("
    SELECT 
        qas.meal_type,
        qas.date,
        qas.created_at as session_time,
        COUNT(qma.id) as total_attendance,
        GROUP_CONCAT(CONCAT(s.name, ' (', s.grn, ')') SEPARATOR ', ') as students
    FROM qr_attendance_sessions qas
    LEFT JOIN qr_mess_attendance qma ON qas.id = qma.session_id
    LEFT JOIN students s ON qma.student_id = s.id
    WHERE qas.hostel_id = ? AND qas.date = ?
    GROUP BY qas.id
    ORDER BY qas.created_at DESC
");
$attendance_query->execute([$hostel_id, $date_filter]);
$attendance_data = $attendance_query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-info">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-chart-bar me-2"></i>QR Attendance Reports</a>
            <div>
                <a href="qr_attendance.php" class="btn btn-outline-light me-2">Generate QR</a>
                <a href="dashboards/rector.php" class="btn btn-outline-light">Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5><i class="fas fa-calendar me-2"></i>Attendance Report</h5>
                            <form method="GET" class="d-flex">
                                <input type="date" class="form-control me-2" name="date" value="<?php echo $date_filter; ?>">
                                <button type="submit" class="btn btn-primary">Filter</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if(empty($attendance_data)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <h5>No attendance data for <?php echo date('M d, Y', strtotime($date_filter)); ?></h5>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Meal Type</th>
                                            <th>Session Time</th>
                                            <th>Total Students</th>
                                            <th>Students Present</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($attendance_data as $record): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $record['meal_type'] == 'breakfast' ? 'warning' : ($record['meal_type'] == 'lunch' ? 'success' : 'primary'); ?>">
                                                    <?php echo ucfirst($record['meal_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('h:i A', strtotime($record['session_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $record['total_attendance']; ?></span>
                                            </td>
                                            <td>
                                                <?php if($record['students']): ?>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="showStudents('<?php echo addslashes($record['students']); ?>')">
                                                        View Students
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">No attendance</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Modal -->
    <div class="modal fade" id="studentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Students Present</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="studentsList"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showStudents(students) {
            const studentsList = students.split(', ');
            let html = '<ul class="list-group">';
            studentsList.forEach(student => {
                html += `<li class="list-group-item">${student}</li>`;
            });
            html += '</ul>';
            
            document.getElementById('studentsList').innerHTML = html;
            new bootstrap.Modal(document.getElementById('studentsModal')).show();
        }
    </script>
</body>
</html>