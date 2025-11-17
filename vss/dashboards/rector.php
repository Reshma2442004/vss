<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Handle form submissions
if ($_POST) {
    if (isset($_POST['allocate_room'])) {
        $stmt = $pdo->prepare("UPDATE students SET room_id = ? WHERE id = ?");
        $stmt->execute([$_POST['room_id'], $_POST['student_id']]);
        $success = "Room allocated successfully";
    }
    
    if (isset($_POST['mark_attendance'])) {
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
        $stmt->execute([$_POST['student_id'], date('Y-m-d'), $_POST['status'], $_POST['status']]);
        $success = "Attendance marked successfully";
    }
}

// Fetch data for rector's hostel
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this rector");
}

$students = $pdo->prepare("SELECT s.*, r.room_number FROM students s LEFT JOIN rooms r ON s.room_id = r.id WHERE s.hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

$rooms = $pdo->prepare("SELECT * FROM rooms WHERE hostel_id = ?");
$rooms->execute([$hostel_id]);
$rooms_list = $rooms->fetchAll();

$staff = $pdo->prepare("SELECT * FROM staff WHERE hostel_id = ?");
$staff->execute([$hostel_id]);
$staff_list = $staff->fetchAll();

// Get attendance statistics
$attendance_stats = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
    FROM attendance a 
    JOIN students s ON a.student_id = s.id 
    WHERE s.hostel_id = ? AND a.date = CURDATE()");
$attendance_stats->execute([$hostel_id]);
$attendance_data = $attendance_stats->fetch();

// Get mess feedback for this hostel only
try {
    $feedback_query = $pdo->prepare("
        SELECT mf.*, s.name as student_name, s.grn 
        FROM mess_feedback mf 
        JOIN students s ON mf.student_id = s.id 
        LEFT JOIN rooms r ON s.room_id = r.id 
        WHERE (r.hostel_id = ? OR r.hostel_id IS NULL)
        ORDER BY mf.id DESC
    ");
    $feedback_query->execute([$hostel_id]);
    $feedback_list = $feedback_query->fetchAll() ?: [];
} catch (Exception $e) {
    // If table doesn't exist, show all feedback
    try {
        $feedback_query = $pdo->prepare("
            SELECT mf.*, s.name as student_name, s.grn 
            FROM mess_feedback mf 
            JOIN students s ON mf.student_id = s.id 
            ORDER BY mf.id DESC
        ");
        $feedback_query->execute();
        $feedback_list = $feedback_query->fetchAll() ?: [];
    } catch (Exception $e2) {
        $feedback_list = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rector Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-university me-2"></i>Rector Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 30 30%27%3e%3cpath stroke=%27rgba%28255, 255, 255, 0.8%29%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 stroke-width=%272%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#students" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-graduate me-2"></i>Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#rooms" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-door-open me-2"></i>Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#staff" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-users-cog me-2"></i>Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#reports" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-chart-line me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-comments me-2"></i>Mess Feedback
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" style="padding: 0.75rem 1rem; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-circle me-2" style="font-size: 1.2rem;"></i>
                            <span><?php echo $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 12px; padding: 0.5rem 0;">
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-user-edit me-2 text-primary"></i>Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-cog me-2 text-secondary"></i>Settings</a></li>
                            <li><hr class="dropdown-divider mx-2" style="margin: 0.5rem 0;"></li>
                            <li><a class="dropdown-item py-2 px-3 text-danger" href="../auth/logout.php" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-body text-center py-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-primary rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-university text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;"><?php echo $hostel_info['name']; ?></h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?></p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-primary mb-0"><?php echo count($students_list); ?></h4>
                                    <small class="text-muted">Current Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-success mb-0"><?php echo $hostel_info['capacity']; ?></h4>
                                    <small class="text-muted">Total Capacity</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-info mb-0"><?php echo count($rooms_list); ?></h4>
                                    <small class="text-muted">Available Rooms</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning mb-0"><?php echo count($staff_list); ?></h4>
                                <small class="text-muted">Staff Members</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
        <!-- Quick Actions & Analytics -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students_list); ?></div>
                        <div class="stat-label">Active Students</div>
                        <div class="stat-meta">Out of <?php echo $hostel_info['capacity']; ?> capacity</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($rooms_list); ?></div>
                        <div class="stat-label">Total Rooms</div>
                        <div class="stat-meta">Room management</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($staff_list); ?></div>
                        <div class="stat-label">Staff Members</div>
                        <div class="stat-meta">Active personnel</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_data['present_count'] ?? 0; ?></div>
                        <div class="stat-label">Present Today</div>
                        <div class="stat-meta">Daily attendance</div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Management Tools -->
        <div id="rooms" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-bed me-2"></i>Room Allocation Management</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user me-1"></i>Select Student</label>
                                <select class="form-input" name="student_id" required>
                                    <option value="">Choose a student...</option>
                                    <?php foreach($students_list as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['name']; ?> (<?php echo $student['grn']; ?>) - <?php echo $student['course']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-door-open me-1"></i>Select Room</label>
                                <select class="form-input" name="room_id" required>
                                    <option value="">Choose a room...</option>
                                    <?php foreach($rooms_list as $room): ?>
                                        <option value="<?php echo $room['id']; ?>">
                                            Room <?php echo $room['room_number']; ?> (Capacity: <?php echo $room['capacity']; ?> beds)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="allocate_room" class="btn btn-primary w-100">
                                <i class="fas fa-home me-2"></i>Allocate Room
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i>Attendance Management</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-user-graduate me-1"></i>Select Student</label>
                                <select class="form-input" name="student_id" required>
                                    <option value="">Choose a student...</option>
                                    <?php foreach($students_list as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-clipboard-check me-1"></i>Attendance Status</label>
                                <select class="form-input" name="status" required>
                                    <option value="">Select status...</option>
                                    <option value="present"><i class="fas fa-check"></i> Present</option>
                                    <option value="absent"><i class="fas fa-times"></i> Absent</option>
                                </select>
                            </div>
                            <button type="submit" name="mark_attendance" class="btn btn-success w-100">
                                <i class="fas fa-check-circle me-2"></i>Mark Attendance
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Students Management -->
        <div id="students" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users me-2"></i>Students Management</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Export</button>
                            <button class="btn btn-sm btn-primary"><i class="fas fa-user-plus me-1"></i>Add Student</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-id-card me-1"></i>GRN</th>
                                        <th><i class="fas fa-user me-1"></i>Student Details</th>
                                        <th><i class="fas fa-graduation-cap me-1"></i>Academic Info</th>
                                        <th><i class="fas fa-home me-1"></i>Room Status</th>
                                        <th><i class="fas fa-phone me-1"></i>Contact</th>
                                        <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students_list as $student): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $student['grn']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo $student['name']; ?></div>
                                                    <small class="text-muted"><?php echo $student['email'] ?? 'No email'; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?php echo $student['course']; ?></div>
                                                <small class="text-muted">Year <?php echo $student['year']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($student['room_number']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-door-open me-1"></i>Room <?php echo $student['room_number']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>Not Allocated
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="fas fa-phone me-1"></i><?php echo $student['contact'] ?? 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Edit Details">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Contact">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Staff Management -->
        <div id="staff" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users-cog me-2"></i>Hostel Staff Directory</h5>
                        <button class="btn btn-sm btn-success"><i class="fas fa-user-plus me-1"></i>Add Staff</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i>Staff Member</th>
                                        <th><i class="fas fa-id-badge me-1"></i>Role</th>
                                        <th><i class="fas fa-phone me-1"></i>Contact</th>
                                        <th><i class="fas fa-calendar me-1"></i>Status</th>
                                        <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($staff_list as $staff_member): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $role_icons = [
                                                    'mess_head' => 'fas fa-utensils',
                                                    'library_head' => 'fas fa-book',
                                                    'health_staff' => 'fas fa-user-md',
                                                    'vvk_staff' => 'fas fa-lightbulb',
                                                    'placement_staff' => 'fas fa-briefcase',
                                                    'ed_cell_staff' => 'fas fa-chalkboard-teacher',
                                                    'scholarship_staff' => 'fas fa-award',
                                                    'student_head' => 'fas fa-users',
                                                    'rector' => 'fas fa-university'
                                                ];
                                                $icon = $role_icons[$staff_member['role']] ?? 'fas fa-user-tie';
                                                ?>
                                                <div class="bg-primary rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="<?php echo $icon; ?> text-white"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo $staff_member['name']; ?></div>
                                                    <small class="text-muted">Staff ID: <?php echo $staff_member['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $staff_member['role'])); ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone me-1 text-primary"></i><?php echo $staff_member['contact']; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" title="View Profile">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Contact">
                                                    <i class="fas fa-phone"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Message">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Biometric Attendance Reports -->
        <div id="biometric" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-fingerprint me-2"></i>Mess Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        require_once '../AttendanceProcessor.php';
                        $processor = new AttendanceProcessor($pdo);
                        $messReport = $processor->getAttendanceReport($hostel_id, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
                        ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Morning</th>
                                        <th>Night</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($messReport as $record): ?>
                                    <tr>
                                        <td><?php echo $record['name']; ?></td>
                                        <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                        <td><span class="badge bg-<?php echo $record['morning_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $record['morning_meal']; ?></span></td>
                                        <td><span class="badge bg-<?php echo $record['night_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $record['night_meal']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-home me-2"></i>Hostel Attendance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($messReport as $record): ?>
                                    <tr>
                                        <td><?php echo $record['name']; ?></td>
                                        <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $record['hostel'] == 'Present' ? 'success' : ($record['hostel'] == 'Late' ? 'warning' : 'danger'); ?>">
                                                <?php echo $record['hostel']; ?>
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
        </div>
        
        <!-- Scholarship Management -->
        <div id="scholarships" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-award me-2"></i>Scholarship Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $scholarships_query = $pdo->prepare("
                            SELECT sc.*, s.name as student_name, s.grn 
                            FROM scholarships sc 
                            JOIN students s ON sc.student_id = s.id 
                            WHERE s.hostel_id = ? 
                            ORDER BY sc.applied_date DESC
                        ");
                        $scholarships_query->execute([$hostel_id]);
                        $scholarships_list = $scholarships_query->fetchAll();
                        ?>
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Scholarship</th>
                                        <th>Amount</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($scholarships_list)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No scholarship applications</td></tr>
                                    <?php else: ?>
                                        <?php foreach($scholarships_list as $scholarship): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $scholarship['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $scholarship['grn']; ?></small>
                                            </td>
                                            <td><?php echo $scholarship['scholarship_name']; ?></td>
                                            <td>₹<?php echo number_format($scholarship['amount']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($scholarship['applied_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $scholarship['status'] == 'approved' ? 'success' : ($scholarship['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($scholarship['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($scholarship['status'] == 'applied'): ?>
                                                <button class="btn btn-sm btn-success me-1" onclick="updateScholarshipStatus(<?php echo $scholarship['id']; ?>, 'approved')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="updateScholarshipStatus(<?php echo $scholarship['id']; ?>, 'rejected')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mess Feedback Section -->
        <div id="feedback" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-comments me-2"></i>Mess Feedback from Students</h5>
                        <span class="badge bg-primary"><?php echo count($feedback_list); ?> Total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedback_list)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">No feedback received yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($feedback_list as $feedback): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y'); ?></td>
                                            <td>
                                                <strong><?php echo $feedback['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $feedback['grn']; ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $type_colors = ['complaint' => 'danger', 'suggestion' => 'warning', 'compliment' => 'success'];
                                                $color = $type_colors[$feedback['feedback_type']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($feedback['feedback_type']); ?></span>
                                            </td>
                                            <td><?php echo $feedback['subject']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $feedback['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo $feedback['rating']; ?>/5</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_colors = ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success'];
                                                $status_color = $status_colors[$feedback['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($feedback['status']); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="viewFeedback(<?php echo $feedback['id']; ?>, '<?php echo addslashes($feedback['message']); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if($feedback['status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-success me-1" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved')">
                                                    <i class="fas fa-check-double"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div class="modal fade" id="viewFeedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="feedbackMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View feedback function
        function viewFeedback(id, message) {
            document.getElementById('feedbackMessage').textContent = message;
            new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
        }
        
        // Update feedback status
        function updateFeedbackStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_feedback_status');
            formData.append('feedback_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Feedback status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // Update scholarship status
        function updateScholarshipStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_scholarship_status');
            formData.append('scholarship_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Scholarship status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Add active class to navigation items on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('[id]');
            const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>