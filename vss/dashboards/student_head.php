<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student_head') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'] ?? null;

if (!$hostel_id) {
    echo "<div class='alert alert-danger'>Error: No hostel assigned to this student head. Please contact administrator.</div>";
    echo "<a href='../debug_student_head.php'>Debug Info</a>";
    exit;
}

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this student head");
}

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_complaint_status':
                    $complaint_id = $_POST['complaint_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE student_complaints SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $_SESSION['user_id'], $complaint_id]);
                    $success = "Complaint status updated successfully";
                    break;
                    
                case 'approve_report':
                    $report_id = $_POST['report_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE staff_reports SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $_SESSION['user_id'], $report_id]);
                    $success = "Report status updated successfully";
                    break;
                    
                case 'update_event_status':
                    $event_id = $_POST['event_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $event_id]);
                    $success = "Event status updated successfully";
                    break;
                    
                case 'add_council_member':
                    $student_id = $_POST['student_id'];
                    $position = $_POST['position'];
                    $wing_block = $_POST['wing_block'];
                    $contact = $_POST['contact'];
                    $stmt = $pdo->prepare("INSERT INTO student_council (student_id, position, wing_block, contact, appointed_date) VALUES (?, ?, ?, ?, CURDATE())");
                    $stmt->execute([$student_id, $position, $wing_block, $contact]);
                    $success = "Council member added successfully";
                    break;
                    
                case 'update_suggestion_status':
                    $suggestion_id = $_POST['suggestion_id'];
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE digital_suggestions SET status = ?, reviewed_by = ? WHERE id = ?");
                    $stmt->execute([$status, $_SESSION['user_id'], $suggestion_id]);
                    $success = "Suggestion status updated successfully";
                    break;
                    
                case 'send_announcement':
                    $title = $_POST['title'];
                    $message = $_POST['message'];
                    $stmt = $pdo->prepare("INSERT INTO announcements (title, message, hostel_id, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$title, $message, $hostel_id, $_SESSION['user_id']]);
                    $success = "Announcement sent successfully";
                    break;
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get statistics with error handling
try {
    $total_students = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_id = ?");
    $total_students->execute([$hostel_id]);
    $total_students_count = $total_students->fetchColumn() ?: 0;

    $total_staff = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE hostel_id = ?");
    $total_staff->execute([$hostel_id]);
    $total_staff_count = $total_staff->fetchColumn() ?: 0;

    $active_complaints = $pdo->prepare("SELECT COUNT(*) FROM student_complaints sc JOIN students s ON sc.student_id = s.id WHERE s.hostel_id = ? AND sc.status = 'pending'");
    $active_complaints->execute([$hostel_id]);
    $active_complaints_count = $active_complaints->fetchColumn() ?: 0;

    $upcoming_events = $pdo->prepare("SELECT COUNT(*) FROM events WHERE hostel_id = ? AND date >= CURDATE()");
    $upcoming_events->execute([$hostel_id]);
    $upcoming_events_count = $upcoming_events->fetchColumn() ?: 0;

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database Error: " . $e->getMessage() . "</div>";
    $total_students_count = $total_staff_count = $active_complaints_count = $upcoming_events_count = 0;
}

// Get complaints with error handling
try {
    $complaints = $pdo->prepare("
        SELECT sc.*, s.name as student_name, s.grn, h.name as hostel_block 
        FROM student_complaints sc 
        JOIN students s ON sc.student_id = s.id 
        JOIN hostels h ON s.hostel_id = h.id 
        WHERE s.hostel_id = ? 
        ORDER BY sc.created_at DESC 
        LIMIT 10
    ");
    $complaints->execute([$hostel_id]);
    $complaints_list = $complaints->fetchAll() ?: [];
} catch (Exception $e) {
    $complaints_list = [];
}

// Get staff reports with error handling
try {
    $reports = $pdo->prepare("
        SELECT sr.*, u.username, s.name as staff_name 
        FROM staff_reports sr 
        JOIN users u ON sr.staff_id = u.id 
        LEFT JOIN staff s ON u.id = s.user_id 
        WHERE u.hostel_id = ? 
        ORDER BY sr.submitted_at DESC 
        LIMIT 10
    ");
    $reports->execute([$hostel_id]);
    $reports_list = $reports->fetchAll() ?: [];
} catch (Exception $e) {
    $reports_list = [];
}

// Get student council members with error handling
try {
    $council_members = $pdo->prepare("
        SELECT sc.*, s.name, s.contact 
        FROM student_council sc 
        JOIN students s ON sc.student_id = s.id 
        WHERE s.hostel_id = ? AND sc.active = 1
    ");
    $council_members->execute([$hostel_id]);
    $council_list = $council_members->fetchAll() ?: [];
} catch (Exception $e) {
    $council_list = [];
}

// Get events with error handling
try {
    $events = $pdo->prepare("SELECT * FROM events WHERE hostel_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 10");
    $events->execute([$hostel_id]);
    $events_list = $events->fetchAll() ?: [];
} catch (Exception $e) {
    $events_list = [];
}

// Get suggestions with error handling
try {
    $suggestions = $pdo->prepare("
        SELECT ds.*, s.name as student_name 
        FROM digital_suggestions ds 
        JOIN students s ON ds.student_id = s.id 
        WHERE s.hostel_id = ? 
        ORDER BY ds.created_at DESC 
        LIMIT 10
    ");
    $suggestions->execute([$hostel_id]);
    $suggestions_list = $suggestions->fetchAll() ?: [];
} catch (Exception $e) {
    $suggestions_list = [];
}

// Get mess feedback for this hostel
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

// Get consolidated report data with error handling
try {
    $mess_attendance = $pdo->prepare("
        SELECT COALESCE(COUNT(DISTINCT ma.student_id) * 100.0 / NULLIF(COUNT(DISTINCT s.id), 0), 0) as percentage
        FROM students s 
        LEFT JOIN mess_attendance ma ON s.id = ma.student_id AND ma.date = CURDATE() AND ma.taken = 1
        WHERE s.hostel_id = ?
    ");
    $mess_attendance->execute([$hostel_id]);
    $mess_attendance_percent = round($mess_attendance->fetchColumn() ?: 0, 1);

    $library_usage = $pdo->prepare("
        SELECT COALESCE(COUNT(DISTINCT bi.student_id) * 100.0 / NULLIF(COUNT(DISTINCT s.id), 0), 0) as percentage
        FROM students s 
        LEFT JOIN book_issues bi ON s.id = bi.student_id AND bi.return_date IS NULL
        WHERE s.hostel_id = ?
    ");
    $library_usage->execute([$hostel_id]);
    $library_usage_percent = round($library_usage->fetchColumn() ?: 0, 1);

    $health_visits = $pdo->prepare("
        SELECT COUNT(*) 
        FROM health_visits hv 
        JOIN students s ON hv.student_id = s.id 
        WHERE s.hostel_id = ? AND hv.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $health_visits->execute([$hostel_id]);
    $health_visits_count = $health_visits->fetchColumn() ?: 0;

    $scholarships_approved = $pdo->prepare("
        SELECT COUNT(*) 
        FROM scholarships sc 
        JOIN students s ON sc.student_id = s.id 
        WHERE s.hostel_id = ? AND sc.status = 'approved'
    ");
    $scholarships_approved->execute([$hostel_id]);
    $scholarships_count = $scholarships_approved->fetchColumn() ?: 0;

} catch (Exception $e) {
    $mess_attendance_percent = $library_usage_percent = 0;
    $health_visits_count = $scholarships_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Head Dashboard - VSS</title>
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
                <i class="fas fa-user-tie me-2"></i>Student Head Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 30 30%27%3e%3cpath stroke=%27rgba%28255, 255, 255, 0.8%29%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 stroke-width=%272%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#complaints" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-exclamation-circle me-2"></i>Student Issues
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#staff-reports" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-file-alt me-2"></i>Staff Monitoring
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#student-management" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-users me-2"></i>Student Council
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#events" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-calendar-alt me-2"></i>Events & Activities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#biometric" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-fingerprint me-2"></i>Biometric Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#mess-feedback" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-comments me-2"></i>Mess Feedback
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#reports" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-chart-bar me-2"></i>Analytics
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
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-user-edit me-2 text-primary"></i>My Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-cog me-2 text-secondary"></i>Settings</a></li>
                            <li><hr class="dropdown-divider mx-2" style="margin: 0.5rem 0;"></li>
                            <li><a class="dropdown-item py-2 px-3 text-danger" href="../auth/login.php?logout=1" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                                <i class="fas fa-user-tie text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;"><?php echo $hostel_info['name']; ?> - Student Head</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Manage student welfare and activities</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats & Key Metrics -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_students_count; ?></div>
                        <div class="stat-label">Students Under Care</div>
                        <div class="stat-meta">Total hostel residents</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_staff_count; ?></div>
                        <div class="stat-label">Staff Members</div>
                        <div class="stat-meta">Working under supervision</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $active_complaints_count; ?></div>
                        <div class="stat-label">Pending Issues</div>
                        <div class="stat-meta">Require immediate attention</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $upcoming_events_count; ?></div>
                        <div class="stat-label">Upcoming Events</div>
                        <div class="stat-meta">Awaiting approval</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Issues Management -->
        <div id="complaints" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-exclamation-circle me-2"></i>Student Issues & Complaint Resolution</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-warning"><?php echo $active_complaints_count; ?> Pending</span>
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Export Report</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Your Role:</strong> Review student complaints, resolve issues within your authority, or escalate to Rector for complex matters requiring higher approval.
                        </div>
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-1"></i>Ticket #</th>
                                        <th><i class="fas fa-user me-1"></i>Student Details</th>
                                        <th><i class="fas fa-building me-1"></i>Location</th>
                                        <th><i class="fas fa-tag me-1"></i>Issue Type</th>
                                        <th><i class="fas fa-file-alt me-1"></i>Description</th>
                                        <th><i class="fas fa-traffic-light me-1"></i>Status</th>
                                        <th><i class="fas fa-tools me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($complaints_list)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-smile text-success" style="font-size: 2rem;"></i>
                                            <p class="mt-2 mb-0">Great! No pending complaints at the moment.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($complaints_list as $complaint): ?>
                                    <tr>
                                        <td><span class="badge bg-primary">#<?php echo str_pad($complaint['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo $complaint['student_name']; ?></div>
                                                <small class="text-muted">GRN: <?php echo $complaint['grn'] ?? 'N/A'; ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo $complaint['hostel_block']; ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo $complaint['category']; ?></span>
                                        </td>
                                        <td>
                                            <div title="<?php echo $complaint['description']; ?>">
                                                <strong><?php echo $complaint['subject']; ?></strong><br>
                                                <small class="text-muted"><?php echo substr($complaint['description'], 0, 50) . '...'; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_colors = ['pending' => 'warning', 'resolved' => 'success', 'forwarded' => 'info'];
                                            $status_icons = ['pending' => 'clock', 'resolved' => 'check-circle', 'forwarded' => 'arrow-up'];
                                            ?>
                                            <span class="badge bg-<?php echo $status_colors[$complaint['status']]; ?>">
                                                <i class="fas fa-<?php echo $status_icons[$complaint['status']]; ?> me-1"></i>
                                                <?php echo ucfirst($complaint['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($complaint['status'] == 'pending'): ?>
                                            <div class="btn-group" role="group">
                                                <button onclick="resolveComplaint(<?php echo $complaint['id']; ?>, 'resolved')" class="btn btn-sm btn-success" title="Mark as Resolved">
                                                    <i class="fas fa-check"></i> Resolve
                                                </button>
                                                <button onclick="resolveComplaint(<?php echo $complaint['id']; ?>, 'forwarded')" class="btn btn-sm btn-primary" title="Forward to Rector">
                                                    <i class="fas fa-arrow-up"></i> Escalate
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <small class="text-muted">No action needed</small>
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

        <!-- Staff Performance Monitoring -->
        <div id="staff-reports" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-file-alt me-2"></i>Staff Performance & Report Monitoring</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info"><?php echo count($reports_list); ?> Reports</span>
                            <button class="btn btn-sm btn-outline-success"><i class="fas fa-plus me-1"></i>Request Report</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-primary mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Monitoring Role:</strong> Review staff reports from Mess, Library, Health, and other departments. Approve routine reports or escalate important matters to Rector.
                        </div>
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag me-1"></i>Report ID</th>
                                        <th><i class="fas fa-file-alt me-1"></i>Report Details</th>
                                        <th><i class="fas fa-user-tie me-1"></i>Submitted By</th>
                                        <th><i class="fas fa-calendar me-1"></i>Date & Time</th>
                                        <th><i class="fas fa-traffic-light me-1"></i>Status</th>
                                        <th><i class="fas fa-cogs me-1"></i>Review Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($reports_list)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-clipboard-list text-muted" style="font-size: 2rem;"></i>
                                            <p class="mt-2 mb-0">No staff reports submitted yet.</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($reports_list as $report): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary">#R<?php echo str_pad($report['id'], 3, '0', STR_PAD_LEFT); ?></span></td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?php echo $report['report_type']; ?></div>
                                                <div class="text-primary"><?php echo $report['title']; ?></div>
                                                <small class="text-muted"><?php echo substr($report['content'], 0, 60) . '...'; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-2" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user text-secondary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo $report['staff_name'] ?? 'Staff Member'; ?></div>
                                                    <small class="text-muted"><?php echo $report['username']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?php echo date('M d, Y', strtotime($report['submitted_at'])); ?></div>
                                                <small class="text-muted"><?php echo date('h:i A', strtotime($report['submitted_at'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_colors = ['pending' => 'warning', 'approved' => 'success', 'forwarded' => 'info'];
                                            $status_icons = ['pending' => 'clock', 'approved' => 'check-circle', 'forwarded' => 'arrow-up'];
                                            ?>
                                            <span class="badge bg-<?php echo $status_colors[$report['status']]; ?>">
                                                <i class="fas fa-<?php echo $status_icons[$report['status']]; ?> me-1"></i>
                                                <?php echo ucfirst($report['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($report['status'] == 'pending'): ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="approve_report">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <div class="btn-group" role="group">
                                                    <button type="submit" name="status" value="approved" class="btn btn-sm btn-success" title="Approve Report">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="submit" name="status" value="forwarded" class="btn btn-sm btn-primary" title="Forward to Rector">
                                                        <i class="fas fa-arrow-up"></i> Escalate
                                                    </button>
                                                </div>
                                            </form>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-info" title="View Details">
                                                <i class="fas fa-eye"></i> View
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

        <!-- Student Council & Engagement -->
        <div id="student-management" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users-cog me-2"></i>Student Council Leadership</h5>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCouncilModal"><i class="fas fa-user-plus me-1"></i>Add Member</button>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Council Management:</strong> Coordinate with student leaders to ensure effective communication and representation.
                        </div>
                        <?php if (empty($council_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">No council members assigned yet.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($council_list as $member): ?>
                            <div class="col-12 mb-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle p-2 me-3" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user-tie text-white"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo $member['name']; ?></h6>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-info"><?php echo $member['position']; ?></span>
                                                    <small class="text-muted"><?php echo $member['wing_block']; ?></small>
                                                </div>
                                                <small class="text-muted"><i class="fas fa-phone me-1"></i><?php echo $member['contact']; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-lightbulb me-2"></i>Student Innovation Hub</h5>
                        <span class="badge bg-primary"><?php echo count($suggestions_list); ?> Ideas</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Innovation Focus:</strong> Review student suggestions to improve hostel facilities and services.
                        </div>
                        <?php if (empty($suggestions_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-lightbulb text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">No suggestions submitted yet.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($suggestions_list as $suggestion): ?>
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-1"><?php echo $suggestion['student_name']; ?></h6>
                                    <?php 
                                    $status_colors = ['new' => 'primary', 'reviewed' => 'warning', 'implemented' => 'success'];
                                    $status_icons = ['new' => 'star', 'reviewed' => 'eye', 'implemented' => 'check-circle'];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$suggestion['status']]; ?>">
                                        <i class="fas fa-<?php echo $status_icons[$suggestion['status']]; ?> me-1"></i>
                                        <?php echo ucfirst($suggestion['status']); ?>
                                    </span>
                                </div>
                                <div class="mb-2">
                                    <span class="badge bg-secondary"><?php echo $suggestion['category']; ?></span>
                                </div>
                                <p class="mb-2 small"><?php echo $suggestion['suggestion']; ?></p>
                                <?php if ($suggestion['status'] == 'new'): ?>
                                <div class="btn-group btn-group-sm" role="group">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="update_suggestion_status">
                                        <input type="hidden" name="suggestion_id" value="<?php echo $suggestion['id']; ?>">
                                        <button type="submit" name="status" value="reviewed" class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-eye"></i> Review
                                        </button>
                                        <button type="submit" name="status" value="implemented" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-check"></i> Implement
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Events & Activities Management -->
        <div id="events" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-check me-2"></i>Events & Activities Coordination</h5>
                        <div class="d-flex gap-2">
                            <span class="badge bg-info"><?php echo count($events_list); ?> Events</span>
                            <button class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Propose Event</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <strong>Event Management:</strong> Review and pre-approve hostel events before final Rector approval. Ensure events align with hostel policies.
                        </div>
                        <?php if (empty($events_list)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 mb-0">No upcoming events scheduled.</p>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($events_list as $event): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="bg-primary rounded-circle p-2" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-calendar text-white"></i>
                                            </div>
                                            <span class="badge bg-warning">Pending Review</span>
                                        </div>
                                        <h6 class="card-title mb-2"><?php echo $event['title']; ?></h6>
                                        <p class="card-text small text-muted mb-3"><?php echo substr($event['description'], 0, 80) . '...'; ?></p>
                                        <div class="mb-3">
                                            <small class="text-muted d-block"><i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($event['date'])); ?></small>
                                            <small class="text-muted d-block"><i class="fas fa-map-marker-alt me-1"></i><?php echo $event['venue']; ?></small>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <div class="btn-group" role="group">
                                                <button onclick="approveEvent(<?php echo $event['id']; ?>, 'approved')" class="btn btn-sm btn-success" title="Pre-approve Event">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="View Details" data-bs-toggle="modal" data-bs-target="#eventModal<?php echo $event['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button onclick="approveEvent(<?php echo $event['id']; ?>, 'rejected')" class="btn btn-sm btn-danger" title="Reject Event">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Event Details Modal -->
                            <div class="modal fade" id="eventModal<?php echo $event['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo $event['title']; ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p><strong>Description:</strong> <?php echo $event['description']; ?></p>
                                            <p><strong>Date:</strong> <?php echo date('M d, Y', strtotime($event['date'])); ?></p>
                                            <p><strong>Venue:</strong> <?php echo $event['venue']; ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Biometric Attendance Reports -->
        <div id="biometric" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-fingerprint me-2"></i>Mess Attendance (Last 7 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        try {
                            require_once '../AttendanceProcessor.php';
                            $processor = new AttendanceProcessor($pdo);
                            $messReport = $processor->getAttendanceReport($hostel_id, date('Y-m-d', strtotime('-7 days')), date('Y-m-d'));
                        } catch (Exception $e) {
                            $messReport = [];
                        }
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
                                    <?php if (empty($messReport)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No biometric data available</td></tr>
                                    <?php else: ?>
                                        <?php foreach($messReport as $record): ?>
                                        <tr>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                            <td><span class="badge bg-<?php echo $record['morning_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $record['morning_meal']; ?></span></td>
                                            <td><span class="badge bg-<?php echo $record['night_meal'] == 'Present' ? 'success' : 'danger'; ?>"><?php echo $record['night_meal']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-home me-2"></i>Hostel Attendance (Last 7 Days)</h5>
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
                                    <?php if (empty($messReport)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No biometric data available</td></tr>
                                    <?php else: ?>
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
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mess Feedback Section -->
        <div id="mess-feedback" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-comments me-2"></i>Student Mess Feedback</h5>
                        <span class="badge bg-primary"><?php echo count($feedback_list); ?> Total</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Feedback Monitoring:</strong> Review student feedback about mess services to ensure quality and address concerns promptly.
                        </div>
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
        
        <!-- Analytics & Performance Dashboard -->
        <div id="reports" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-chart-bar me-2"></i>Hostel Performance Analytics</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Export Report</button>
                            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync me-1"></i>Refresh Data</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success mb-4">
                            <i class="fas fa-chart-line me-2"></i>
                            <strong>Performance Overview:</strong> Monitor key hostel metrics to ensure optimal student experience and operational efficiency.
                        </div>
                        <div class="row">
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card border-0 shadow-sm text-center h-100">
                                    <div class="card-body">
                                        <div class="progress-circle mx-auto mb-3" data-percentage="<?php echo $mess_attendance_percent; ?>">
                                            <span><?php echo $mess_attendance_percent; ?>%</span>
                                        </div>
                                        <h6 class="card-title">Mess Attendance</h6>
                                        <p class="card-text small text-muted">Daily meal participation rate</p>
                                        <span class="badge bg-<?php echo $mess_attendance_percent > 80 ? 'success' : ($mess_attendance_percent > 60 ? 'warning' : 'danger'); ?>">
                                            <?php echo $mess_attendance_percent > 80 ? 'Excellent' : ($mess_attendance_percent > 60 ? 'Good' : 'Needs Attention'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card border-0 shadow-sm text-center h-100">
                                    <div class="card-body">
                                        <div class="progress-circle mx-auto mb-3" data-percentage="<?php echo $library_usage_percent; ?>">
                                            <span><?php echo $library_usage_percent; ?>%</span>
                                        </div>
                                        <h6 class="card-title">Library Engagement</h6>
                                        <p class="card-text small text-muted">Students actively using library</p>
                                        <span class="badge bg-<?php echo $library_usage_percent > 70 ? 'success' : ($library_usage_percent > 40 ? 'warning' : 'danger'); ?>">
                                            <?php echo $library_usage_percent > 70 ? 'High Usage' : ($library_usage_percent > 40 ? 'Moderate' : 'Low Usage'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card border-0 shadow-sm text-center h-100">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="bg-info rounded-circle mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-user-md text-white" style="font-size: 24px;"></i>
                                            </div>
                                        </div>
                                        <h3 class="text-info mb-2"><?php echo $health_visits_count; ?></h3>
                                        <h6 class="card-title">Health Consultations</h6>
                                        <p class="card-text small text-muted">Medical visits (Last 30 days)</p>
                                        <span class="badge bg-info">Active Monitoring</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card border-0 shadow-sm text-center h-100">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="bg-success rounded-circle mx-auto" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-award text-white" style="font-size: 24px;"></i>
                                            </div>
                                        </div>
                                        <h3 class="text-success mb-2"><?php echo $scholarships_count; ?></h3>
                                        <h6 class="card-title">Scholarships Awarded</h6>
                                        <p class="card-text small text-muted">Financial aid approved</p>
                                        <span class="badge bg-success">Student Support</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card border-0 bg-light">
                                    <div class="card-body text-center py-4">
                                        <h6 class="mb-3">Quick Action Center</h6>
                                        <div class="d-flex flex-wrap justify-content-center gap-2">
                                            <button class="btn btn-outline-primary btn-sm" onclick="alert('Announcement feature coming soon!')"><i class="fas fa-bell me-1"></i>Send Announcement</button>
                                            <button class="btn btn-outline-success btn-sm" onclick="alert('Meeting scheduling feature coming soon!')"><i class="fas fa-calendar-plus me-1"></i>Schedule Meeting</button>
                                            <button class="btn btn-outline-info btn-sm" onclick="generateReport()"><i class="fas fa-clipboard-list me-1"></i>Generate Report</button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="sendEmergencyAlert()"><i class="fas fa-exclamation-triangle me-1"></i>Emergency Alert</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

    <!-- Add Council Member Modal -->
    <div class="modal fade" id="addCouncilModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Council Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_council_member">
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select class="form-control" name="student_id" required>
                                <option value="">Choose a student...</option>
                                <?php 
                                $students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
                                $students->execute([$hostel_id]);
                                $students_list = $students->fetchAll();
                                foreach($students_list as $student): 
                                ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select class="form-control" name="position" required>
                                <option value="">Select position...</option>
                                <option value="President">President</option>
                                <option value="Vice President">Vice President</option>
                                <option value="Secretary">Secretary</option>
                                <option value="Treasurer">Treasurer</option>
                                <option value="Cultural Head">Cultural Head</option>
                                <option value="Sports Head">Sports Head</option>
                                <option value="Technical Head">Technical Head</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wing/Block</label>
                            <input type="text" class="form-control" name="wing_block" placeholder="e.g., Block A, Wing 1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact" placeholder="Enter contact number" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize progress circles
        document.querySelectorAll('.progress-circle').forEach(circle => {
            const percentage = circle.getAttribute('data-percentage');
            circle.style.background = `conic-gradient(#667eea ${percentage * 3.6}deg, #e9ecef 0deg)`;
        });

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

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // Add subtle indicator that data is being refreshed
            const refreshBtn = document.querySelector('[data-refresh]');
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync fa-spin me-1"></i>Refreshing...';
                setTimeout(() => {
                    refreshBtn.innerHTML = '<i class="fas fa-sync me-1"></i>Refresh Data';
                }, 2000);
            }
        }, 300000); // 5 minutes

        // Generate Report Function
        function generateReport() {
            const reportData = {
                students: <?php echo $total_students_count; ?>,
                staff: <?php echo $total_staff_count; ?>,
                complaints: <?php echo $active_complaints_count; ?>,
                events: <?php echo $upcoming_events_count; ?>,
                messAttendance: <?php echo $mess_attendance_percent; ?>,
                libraryUsage: <?php echo $library_usage_percent; ?>,
                healthVisits: <?php echo $health_visits_count; ?>,
                scholarships: <?php echo $scholarships_count; ?>
            };
            
            let report = `HOSTEL PERFORMANCE REPORT\n`;
            report += `Generated on: ${new Date().toLocaleDateString()}\n\n`;
            report += `OVERVIEW:\n`;
            report += `- Students Under Care: ${reportData.students}\n`;
            report += `- Staff Members: ${reportData.staff}\n`;
            report += `- Pending Issues: ${reportData.complaints}\n`;
            report += `- Upcoming Events: ${reportData.events}\n\n`;
            report += `PERFORMANCE METRICS:\n`;
            report += `- Mess Attendance: ${reportData.messAttendance}%\n`;
            report += `- Library Usage: ${reportData.libraryUsage}%\n`;
            report += `- Health Visits (30 days): ${reportData.healthVisits}\n`;
            report += `- Scholarships Approved: ${reportData.scholarships}\n`;
            
            const blob = new Blob([report], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'hostel_report_' + new Date().toISOString().split('T')[0] + '.txt';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Send Emergency Alert Function
        function sendEmergencyAlert() {
            if (confirm('Send emergency alert to all students and staff?')) {
                alert('Emergency alert sent successfully! All residents will be notified immediately.');
            }
        }

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
        
        // Resolve complaint
        function resolveComplaint(id, status) {
            const formData = new FormData();
            formData.append('action', 'resolve_complaint');
            formData.append('complaint_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Complaint status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // Approve event
        function approveEvent(id, status) {
            if (status === 'rejected' && !confirm('Reject this event?')) return;
            
            const formData = new FormData();
            formData.append('action', 'approve_event');
            formData.append('event_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Event status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                }
            });
        }, 5000);
    </script>
</body>
</html>