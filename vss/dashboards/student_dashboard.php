<?php
require_once '../config/database.php';
require_once '../includes/db_check.php';
session_start();

if (!isset($_SESSION['student_id'])) {
    header('Location: ../auth/student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Get student details with hostel and rector information
$student_query = executeQuery($pdo, "
    SELECT s.*, 
           h.name as hostel_name, h.location as hostel_location,
           st.name as rector_name, st.contact as rector_contact,
           st.csv_hostel_name,
           u.username as rector_email
    FROM students s 
    LEFT JOIN hostels h ON s.hostel_id = h.id 
    LEFT JOIN staff st ON s.hostel_id = st.hostel_id AND st.role = 'rector'
    LEFT JOIN users u ON st.user_id = u.id
    WHERE s.id = ?
", [$student_id]);

$student = $student_query ? $student_query->fetch() : null;

if (!$student) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Student record not found.</div></div>");
}

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mess_feedback (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
        subject VARCHAR(255) NOT NULL,
        category VARCHAR(50),
        message TEXT NOT NULL,
        rating INT CHECK (rating >= 1 AND rating <= 5),
        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_at TIMESTAMP NULL,
        reviewed_by INT NULL,
        response_message TEXT,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )");
} catch (Exception $e) {
    // Table creation error - will be handled by AJAX
}

// Get data
$attendance_query = executeQuery($pdo, "SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 10", [$student['id']]);
$attendance_records = $attendance_query ? $attendance_query->fetchAll() : [];

$attendance_stats = executeQuery($pdo, "
    SELECT COUNT(*) as total_days, COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days
    FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$student['id']]);
$attendance_data = $attendance_stats ? $attendance_stats->fetch() : ['total_days' => 0, 'present_days' => 0];
$attendance_percentage = $attendance_data['total_days'] > 0 ? round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100) : 0;

$books_query = executeQuery($pdo, "SELECT COUNT(*) as count FROM book_issues WHERE student_id = ? AND return_date IS NULL", [$student['id']]);
$issued_count = $books_query ? $books_query->fetch()['count'] : 0;

$events_query = executeQuery($pdo, "SELECT * FROM events WHERE hostel_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 5", [$student['hostel_id'] ?? 1]);
$events_list = $events_query ? $events_query->fetchAll() : [];

// Get all feedback (mess and general) for the student
$mess_feedback_query = executeQuery($pdo, "SELECT *, 'mess' as feedback_category FROM mess_feedback WHERE student_id = ? ORDER BY created_at DESC", [$student['id']]);
$mess_feedback = $mess_feedback_query ? $mess_feedback_query->fetchAll() : [];

$general_feedback_query = executeQuery($pdo, "SELECT *, feedback_category FROM general_feedback WHERE student_id = ? ORDER BY created_at DESC", [$student['id']]);
$general_feedback = $general_feedback_query ? $general_feedback_query->fetchAll() : [];

// Handle AJAX form submissions
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {

                
            case 'scholarship':
                // Create scholarships table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS scholarships (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    scholarship_name VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    reason TEXT NOT NULL,
                    academic_performance VARCHAR(50),
                    status ENUM('applied', 'approved', 'rejected') DEFAULT 'applied',
                    applied_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_date TIMESTAMP NULL
                )");
                
                $stmt = $pdo->prepare("INSERT INTO scholarships (student_id, scholarship_name, amount, reason, academic_performance) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $_POST['scholarship_name'], $_POST['amount'], $_POST['reason'], $_POST['academic_performance']]);
                echo json_encode(['success' => true, 'message' => 'Scholarship application submitted successfully!']);
                break;
                
            case 'health_appointment':
                // Create health_appointments table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS health_appointments (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    appointment_type VARCHAR(50) NOT NULL,
                    appointment_date DATE NOT NULL,
                    appointment_time TIME NOT NULL,
                    symptoms TEXT NOT NULL,
                    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                
                $stmt = $pdo->prepare("INSERT INTO health_appointments (student_id, appointment_type, appointment_date, appointment_time, symptoms) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $_POST['appointment_type'], $_POST['appointment_date'], $_POST['appointment_time'], $_POST['symptoms']]);
                echo json_encode(['success' => true, 'message' => 'Health appointment booked successfully!']);
                break;
                
            case 'leave_application':
                // Create leave_applications table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS leave_applications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    leave_type VARCHAR(50) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason TEXT NOT NULL,
                    emergency_contact VARCHAR(100) DEFAULT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_by INT NULL,
                    reviewed_at TIMESTAMP NULL
                )");
                
                // Add emergency_contact column if it doesn't exist
                try {
                    $pdo->exec("ALTER TABLE leave_applications ADD COLUMN emergency_contact VARCHAR(100) DEFAULT NULL");
                } catch (Exception $e) {
                    // Column already exists
                }
                
                $stmt = $pdo->prepare("INSERT INTO leave_applications (student_id, leave_type, start_date, end_date, reason, emergency_contact) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $_POST['leave_type'], $_POST['start_date'], $_POST['end_date'], $_POST['reason'], $_POST['emergency_contact']]);
                echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully! Your rector will review it.']);
                break;
                
            case 'feedback':
                $category = $_POST['category'];
                $photo_path = null;
                
                // Handle photo upload
                if (isset($_FILES['feedback_photo']) && $_FILES['feedback_photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/feedback_photos/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = strtolower(pathinfo($_FILES['feedback_photo']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array($file_extension, $allowed_extensions) && $_FILES['feedback_photo']['size'] <= 5 * 1024 * 1024) {
                        $filename = 'feedback_' . $student_id . '_' . time() . '.' . $file_extension;
                        $photo_path = $upload_dir . $filename;
                        
                        if (!move_uploaded_file($_FILES['feedback_photo']['tmp_name'], $photo_path)) {
                            $photo_path = null;
                        }
                    }
                }
                
                // Add photo_path column to tables if not exists
                try {
                    $pdo->exec("ALTER TABLE mess_feedback ADD COLUMN photo_path VARCHAR(500) DEFAULT NULL");
                } catch (Exception $e) {}
                
                if ($category === 'mess') {
                    $stmt = $pdo->prepare("INSERT INTO mess_feedback (student_id, feedback_type, subject, category, message, rating, priority, photo_path) VALUES (?, ?, ?, ?, ?, ?, 'medium', ?)");
                    $stmt->execute([$student_id, $_POST['feedback_type'], $_POST['subject'], $category, $_POST['message'], $_POST['rating'], $photo_path]);
                } elseif ($category === 'vvk') {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS vvk_feedback (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        student_id INT NOT NULL,
                        event_id INT NULL,
                        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        rating INT CHECK (rating >= 1 AND rating <= 5),
                        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
                        photo_path VARCHAR(500) DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    try {
                        $pdo->exec("ALTER TABLE vvk_feedback ADD COLUMN photo_path VARCHAR(500) DEFAULT NULL");
                    } catch (Exception $e) {}
                    
                    $stmt = $pdo->prepare("INSERT INTO vvk_feedback (student_id, event_id, feedback_type, subject, message, rating, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $_POST['event_id'] ?: null, $_POST['feedback_type'], $_POST['subject'], $_POST['message'], $_POST['rating'], $photo_path]);
                } else {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS general_feedback (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        student_id INT NOT NULL,
                        feedback_category VARCHAR(50) NOT NULL,
                        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        rating INT CHECK (rating >= 1 AND rating <= 5),
                        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
                        photo_path VARCHAR(500) DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    
                    try {
                        $pdo->exec("ALTER TABLE general_feedback ADD COLUMN photo_path VARCHAR(500) DEFAULT NULL");
                    } catch (Exception $e) {}
                    
                    $stmt = $pdo->prepare("INSERT INTO general_feedback (student_id, feedback_category, feedback_type, subject, message, rating, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_id, $category, $_POST['feedback_type'], $_POST['subject'], $_POST['message'], $_POST['rating'], $photo_path]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully!']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Combine all feedback
$my_feedback = array_merge($mess_feedback, $general_feedback);
// Sort by created_at descending
usort($my_feedback, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$my_feedback = array_slice($my_feedback, 0, 10); // Show last 10 feedback
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
    <style>
        .rating-stars { font-size: 30px; cursor: pointer; user-select: none; }
        .rating-stars span { transition: color 0.2s ease; }
        .rating-stars span.star-hover { color: #ffc107 !important; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-user-graduate me-2"></i>Student Portal
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#profile">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#attendance">
                            <i class="fas fa-calendar-check me-2"></i>Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo $student['name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" onclick="openChangePasswordModal()"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                                <i class="fas fa-user-graduate text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;">Welcome, <?php echo $student['name']; ?>!</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-id-card me-1"></i>GRN: <?php echo $student['grn_no'] ?: $student['grn']; ?> | <?php echo $student['course']; ?> | <?php echo $student['csv_hostel_name'] ?: $student['hostel_name'] ?: 'Hostel'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_percentage; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                        <div class="stat-meta">Last 30 days performance</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $issued_count; ?></div>
                        <div class="stat-label">Active Books</div>
                        <div class="stat-meta">Currently borrowed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($events_list); ?></div>
                        <div class="stat-label">Upcoming Events</div>
                        <div class="stat-meta">Hostel activities</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $student['college_year'] ?: $student['year']; ?></div>
                        <div class="stat-label">Academic Year</div>
                        <div class="stat-meta">Current semester</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Personal Information -->
                <div id="profile" class="modern-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i> Personal Information
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>GRN Number:</strong> <?php echo $student['grn_no'] ?: $student['grn']; ?></p>
                                <p><strong>First Name:</strong> <?php echo $student['first_name']; ?></p>
                                <p><strong>Middle Name:</strong> <?php echo $student['middle_name'] ?: 'N/A'; ?></p>
                                <p><strong>Last Name:</strong> <?php echo $student['last_name']; ?></p>
                                <p><strong>Mother's Name:</strong> <?php echo $student['mothers_name'] ?: 'N/A'; ?></p>
                                <p><strong>Email:</strong> <?php echo $student['email']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Student Mobile:</strong> <?php echo $student['student_mobile'] ?: 'N/A'; ?></p>
                                <p><strong>Parents Mobile:</strong> <?php echo $student['parents_mobile'] ?: 'N/A'; ?></p>
                                <p><strong>Course:</strong> <?php echo $student['course']; ?></p>
                                <p><strong>Faculty:</strong> <?php echo $student['faculty'] ?: 'N/A'; ?></p>
                                <p><strong>Samiti Year:</strong> <?php echo $student['samiti_year'] ?: 'N/A'; ?></p>
                                <p><strong>College Year:</strong> <?php echo $student['college_year'] ?: $student['year']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="modern-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-graduation-cap"></i> Academic Details
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Course Duration:</strong> <?php echo $student['course_duration'] ?: 'N/A'; ?> years</p>
                                <p><strong>Current Year:</strong> <?php echo $student['college_year'] ?: $student['year']; ?></p>
                                <p><strong>Faculty:</strong> <?php echo $student['faculty'] ?: 'N/A'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Samiti Year:</strong> <?php echo $student['samiti_year'] ?: 'N/A'; ?></p>
                                <p><strong>Course:</strong> <?php echo $student['course']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hostel Information -->
                <div class="modern-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-building"></i> Hostel Details
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Hostel Name:</strong> <?php echo $student['csv_hostel_name'] ?: $student['hostel_name'] ?: 'N/A'; ?></p>
                                <p><strong>Hostel Allocation:</strong> <?php echo $student['hostel_allocation'] ?: 'N/A'; ?></p>
                                <p><strong>Wing:</strong> <?php echo $student['wing'] ?: 'N/A'; ?></p>
                                <p><strong>Floor:</strong> <?php echo $student['floor'] ?: 'N/A'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Room Number:</strong> <?php echo $student['room_number'] ?: $student['room_no'] ?: 'Not Assigned'; ?></p>
                                <p><strong>Rector Name:</strong> <?php echo $student['rector_name'] ?: 'N/A'; ?></p>
                                <p><strong>Rector Contact:</strong> <?php echo $student['rector_contact'] ?: 'N/A'; ?></p>
                                <p><strong>Rector Email:</strong> <?php echo $student['rector_email'] ?: 'N/A'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Complaints -->
                <div class="modern-card mb-4">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-exclamation-triangle"></i> My Complaints
                        </h3>
                    </div>
                    <div class="card-content">
                        <?php 
                        $my_complaints_query = executeQuery($pdo, "SELECT * FROM complaints WHERE student_id = ? ORDER BY created_at DESC LIMIT 5", [$student['id']]);
                        $my_complaints = $my_complaints_query ? $my_complaints_query->fetchAll() : [];
                        ?>
                        <div class="modern-table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Subject</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($my_complaints)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No complaints submitted</td></tr>
                                    <?php else: ?>
                                        <?php foreach($my_complaints as $complaint): ?>
                                        <tr>
                                            <td><?php echo $complaint['subject']; ?></td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($complaint['type']); ?></span></td>
                                            <td>
                                                <?php 
                                                $status_colors = ['pending' => 'warning', 'in_progress' => 'info', 'resolved' => 'success'];
                                                $status_color = $status_colors[$complaint['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($complaint['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div id="attendance" class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check"></i> Recent Attendance
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="modern-table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($attendance_records)): ?>
                                        <tr><td colspan="2" class="text-center text-muted">No attendance records found</td></tr>
                                    <?php else: ?>
                                        <?php foreach($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $record['status'] == 'present' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($record['status']); ?>
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

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h3>
                    </div>
                    <div class="card-content">
                        <button class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                            <i class="fas fa-comment me-2"></i>Submit Feedback
                        </button>
                        <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#scholarshipModal">
                            <i class="fas fa-award me-2"></i>Apply for Scholarship
                        </button>
                        <button class="btn btn-info w-100 mb-2" data-bs-toggle="modal" data-bs-target="#healthModal">
                            <i class="fas fa-user-md me-2"></i>Book Health Appointment
                        </button>
                        <button class="btn btn-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#leaveModal">
                            <i class="fas fa-calendar-times me-2"></i>Apply for Leave
                        </button>
                        <a href="../scan_attendance.php" class="btn btn-warning w-100 mb-2">
                            <i class="fas fa-qrcode me-2"></i>Scan QR for Mess
                        </a>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i> Upcoming Events
                        </h3>
                    </div>
                    <div class="card-content">
                        <?php if (empty($events_list)): ?>
                            <p class="text-muted">No upcoming events</p>
                        <?php else: ?>
                            <?php foreach($events_list as $event): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <h6 class="mb-1"><?php echo $event['title']; ?></h6>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($event['date'])); ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    


    <!-- Apply for Scholarship Modal -->
    <div class="modal fade" id="scholarshipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-award me-2"></i>Apply for Scholarship</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="scholarshipForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Scholarship Name</label>
                            <input type="text" class="form-control" id="scholarshipName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount Requested</label>
                            <input type="number" class="form-control" id="scholarshipAmount" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Application</label>
                            <textarea class="form-control" id="scholarshipReason" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Academic Performance (CGPA/Percentage)</label>
                            <input type="text" class="form-control" id="academicPerformance" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Apply for Scholarship</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Book Health Appointment Modal -->
    <div class="modal fade" id="healthModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-md me-2"></i>Book Health Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="healthForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Appointment Type</label>
                            <select class="form-control" id="appointmentType" required>
                                <option value="">Select type...</option>
                                <option value="general">General Checkup</option>
                                <option value="emergency">Emergency</option>
                                <option value="consultation">Consultation</option>
                                <option value="follow-up">Follow-up</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preferred Date</label>
                            <input type="date" class="form-control" id="appointmentDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preferred Time</label>
                            <select class="form-control" id="appointmentTime" required>
                                <option value="">Select time...</option>
                                <option value="09:00">9:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="14:00">2:00 PM</option>
                                <option value="15:00">3:00 PM</option>
                                <option value="16:00">4:00 PM</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Symptoms/Reason</label>
                            <textarea class="form-control" id="symptoms" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Book Appointment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Submit Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-comment me-2"></i>Submit Feedback</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="feedbackForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Feedback Category</label>
                            <select class="form-control" id="feedbackCategory" required onchange="toggleCategoryFields()">
                                <option value="">Select category...</option>
                                <option value="mess">Mess</option>
                                <option value="library">Library</option>
                                <option value="vvk">VVK</option>
                                <option value="events">Events</option>
                                <option value="computer_lab">Computer Lab</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3" id="eventSelectionDiv" style="display: none;">
                            <label class="form-label">Select Event (Optional)</label>
                            <select class="form-control" id="eventSelection">
                                <option value="">General VVK Feedback</option>
                                <?php foreach($events_list as $event): ?>
                                    <option value="<?php echo $event['id']; ?>"><?php echo htmlspecialchars($event['title']); ?> - <?php echo date('M d, Y', strtotime($event['date'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="customSubjectDiv" style="display: none;">
                            <label class="form-label">Custom Subject</label>
                            <input type="text" class="form-control" id="customSubject" placeholder="Enter your feedback subject">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Feedback Type</label>
                            <select class="form-control" id="feedbackType" required>
                                <option value="suggestion">Suggestion</option>
                                <option value="compliment">Compliment</option>
                                <option value="complaint">Complaint</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="feedbackMessage" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating (1-5)</label>
                            <select class="form-control" id="feedbackRating" required>
                                <option value="5">5 - Excellent</option>
                                <option value="4">4 - Good</option>
                                <option value="3" selected>3 - Average</option>
                                <option value="2">2 - Poor</option>
                                <option value="1">1 - Very Poor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Photo (Optional)</label>
                            <input type="file" class="form-control" id="feedbackPhoto" accept="image/*">
                            <small class="text-muted">Upload a photo as proof or evidence (JPG, PNG, max 5MB)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Apply for Leave Modal -->
    <div class="modal fade" id="leaveModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-times me-2"></i>Apply for Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="leaveForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Leave Type</label>
                            <select class="form-control" id="leaveType" required>
                                <option value="">Select type...</option>
                                <option value="sick">Sick Leave</option>
                                <option value="personal">Personal Leave</option>
                                <option value="emergency">Emergency Leave</option>
                                <option value="vacation">Vacation</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="leaveStartDate" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="leaveEndDate" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Leave</label>
                            <textarea class="form-control" id="leaveReason" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Emergency Contact (if applicable)</label>
                            <input type="text" class="form-control" id="emergencyContact">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Apply for Leave</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Choose a strong password for your account security
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" minlength="6" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openChangePasswordModal() {
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }
        


        // Apply for Scholarship Form
        document.getElementById('scholarshipForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm('scholarship', {
                scholarship_name: document.getElementById('scholarshipName').value,
                amount: document.getElementById('scholarshipAmount').value,
                reason: document.getElementById('scholarshipReason').value,
                academic_performance: document.getElementById('academicPerformance').value
            }, 'scholarshipModal');
        });

        // Book Health Appointment Form
        document.getElementById('healthForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm('health_appointment', {
                appointment_type: document.getElementById('appointmentType').value,
                appointment_date: document.getElementById('appointmentDate').value,
                appointment_time: document.getElementById('appointmentTime').value,
                symptoms: document.getElementById('symptoms').value
            }, 'healthModal');
        });

        // Submit Feedback Form
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const category = document.getElementById('feedbackCategory').value;
            const subject = category === 'other' ? document.getElementById('customSubject').value : category + ' feedback';
            
            const formData = new FormData();
            formData.append('action', 'feedback');
            formData.append('category', category);
            formData.append('subject', subject);
            formData.append('feedback_type', document.getElementById('feedbackType').value);
            formData.append('message', document.getElementById('feedbackMessage').value);
            formData.append('rating', document.getElementById('feedbackRating').value);
            
            // Add event_id for VVK feedback
            if (category === 'vvk') {
                formData.append('event_id', document.getElementById('eventSelection').value);
            }
            
            // Add photo if uploaded
            const photoFile = document.getElementById('feedbackPhoto').files[0];
            if (photoFile) {
                formData.append('feedback_photo', photoFile);
            }
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ ' + result.message);
                    bootstrap.Modal.getInstance(document.getElementById('feedbackModal')).hide();
                    document.getElementById('feedbackForm').reset();
                } else {
                    alert('❌ Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('❌ Error submitting feedback');
            });
        });
        
        // Toggle category-specific fields
        function toggleCategoryFields() {
            const category = document.getElementById('feedbackCategory').value;
            const customDiv = document.getElementById('customSubjectDiv');
            const customInput = document.getElementById('customSubject');
            const eventDiv = document.getElementById('eventSelectionDiv');
            
            // Handle custom subject for 'other' category
            if (category === 'other') {
                customDiv.style.display = 'block';
                customInput.required = true;
            } else {
                customDiv.style.display = 'none';
                customInput.required = false;
            }
            
            // Handle event selection for VVK category
            if (category === 'vvk') {
                eventDiv.style.display = 'block';
            } else {
                eventDiv.style.display = 'none';
            }
        }

        // Apply for Leave Form
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm('leave_application', {
                leave_type: document.getElementById('leaveType').value,
                start_date: document.getElementById('leaveStartDate').value,
                end_date: document.getElementById('leaveEndDate').value,
                reason: document.getElementById('leaveReason').value,
                emergency_contact: document.getElementById('emergencyContact').value
            }, 'leaveModal');
        });

        // Generic form submission function
        function submitForm(action, data, modalId) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('✅ ' + result.message);
                    bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    document.getElementById(modalId.replace('Modal', 'Form')).reset();
                } else {
                    alert('❌ Error: ' + result.message);
                }
            })
            .catch(error => {
                alert('❌ Error submitting form');
            });
        }

        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            
            fetch('../handlers/student_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Password changed successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                    this.reset();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error changing password');
            });
        });
    </script>
</body>
</html>