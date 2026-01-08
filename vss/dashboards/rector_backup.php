<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fix database structure first
try {
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS contact VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS course VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS year INT DEFAULT 1");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {
    // Columns might already exist
}

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
    
    if (isset($_POST['add_staff'])) {
        $student_id = $_POST['student_id'];
        $role = $_POST['staff_role'];
        
        // Get student details
        $student_query = $pdo->prepare("SELECT * FROM students WHERE id = ? AND hostel_id = ?");
        $student_query->execute([$student_id, $hostel_id]);
        $student = $student_query->fetch();
        
        if ($student) {
            // Create user account for staff
            $username = strtolower(str_replace(' ', '_', $student['name']));
            $password = 'staff' . rand(1000, 9999);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $email = $student['email'] ?? $username . '@hostel.com';
            $user_stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, hostel_id) VALUES (?, ?, ?, ?, ?)");
            $user_stmt->execute([$username, $hashed_password, $email, $role, $hostel_id]);
            $user_id = $pdo->lastInsertId();
            
            // Add to staff table
            $contact = $student['contact'] ?? 'N/A';
            $staff_stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id, student_id) VALUES (?, ?, ?, ?, ?, ?)");
            $staff_stmt->execute([$student['name'], $role, $contact, $hostel_id, $user_id, $student_id]);
            
            $success = "Staff member added successfully. Username: {$username}, Password: {$password}";
        } else {
            $error = "Student not found";
        }
    }
    
    if (isset($_POST['add_individual_student'])) {
        try {
            // Ensure password column exists
            $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
            
            $grn = generateUniqueGRN($pdo);
            $password = generateRandomPassword();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO students (grn, name, email, contact, course, year, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $grn,
                $_POST['student_name'],
                $_POST['student_email'],
                $_POST['student_contact'],
                $_POST['student_course'],
                $_POST['student_year'],
                $hostel_id,
                $hashed_password
            ]);
            
            $success = "Student added successfully. GRN: {$grn}, Password: {$password}";
        } catch (Exception $e) {
            $error = "Error adding student: " . $e->getMessage();
        }
    }
}

// Handle file upload
if (isset($_FILES['student_file']) && $_FILES['student_file']['error'] == 0) {
    $file = $_FILES['student_file'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($file_extension == 'csv') {
        $upload_dir = '../uploads/students/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_path = $upload_dir . time() . '_' . $file['name'];
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $students_added = processCSVFile($file_path, $hostel_id, $pdo);
            $success = "Successfully uploaded and processed {$students_added} students";
            unlink($file_path);
        } else {
            $error = "Failed to upload file";
        }
    } else {
        $error = "Please upload a CSV file";
    }
}

// Function to process CSV file
function processCSVFile($file_path, $hostel_id, $pdo) {
    $students_added = 0;
    
    // First ensure password column exists
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {
        // Column might already exist, continue
    }
    
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        $header = fgetcsv($handle); // Skip header row
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 4) { // Minimum required fields
                $grn = generateUniqueGRN($pdo);
                $password = generateRandomPassword();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert student with safe defaults
                $name = $data[0] ?? '';
                $email = $data[1] ?? '';
                $contact = $data[2] ?? '';
                $course = $data[3] ?? '';
                $year = $data[4] ?? 1;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO students (grn, name, email, contact, course, year, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$grn, $name, $email, $contact, $course, $year, $hostel_id, $hashed_password]);
                    $students_added++;
                } catch (Exception $e) {
                    // Log error but continue with other students
                    error_log("Error adding student: " . $e->getMessage());
                }
            }
        }
        fclose($handle);
    }
    
    return $students_added;
}

// Function to generate unique GRN
function generateUniqueGRN($pdo) {
    do {
        $grn = 'GRN' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE grn = ?");
        $stmt->execute([$grn]);
    } while ($stmt->fetchColumn() > 0);
    
    return $grn;
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
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
        SELECT mf.*, s.name as student_name, s.grn, s.hostel_id,
               COALESCE(mf.category, 'Other') as category,
               COALESCE(mf.priority, 'medium') as priority
        FROM mess_feedback mf 
        JOIN students s ON mf.student_id = s.id 
        WHERE s.hostel_id = ?
        ORDER BY 
            CASE mf.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
                ELSE 5 
            END,
            mf.created_at DESC
    ");
    $feedback_query->execute([$hostel_id]);
    $feedback_list = $feedback_query->fetchAll() ?: [];
} catch (Exception $e) {
    $feedback_list = [];
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
                        <a class="nav-link text-white fw-semibold" href="#leave-applications" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-calendar-times me-2"></i>Leave Applications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-comments me-2"></i>Mess Feedback
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="../qr_attendance.php" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-qrcode me-2"></i>QR Attendance
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
            <div class="col-12 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload me-2"></i>Bulk Upload Students</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="modern-form">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Upload Student Data (CSV/Excel)</label>
                                    <input type="file" class="form-control" name="student_file" accept=".csv" required>
                                    <small class="text-muted">CSV Format: Name, Email, Contact, Course, Year, Additional Info</small>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-upload me-2"></i>Upload Students
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users me-2"></i>Students Management</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="exportStudents()"><i class="fas fa-download me-1"></i>Export</button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fas fa-user-plus me-1"></i>Add Student</button>
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
                                                    <small class="text-muted"><?php echo isset($student['email']) ? $student['email'] : 'No email'; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?php echo isset($student['course']) ? $student['course'] : 'N/A'; ?></div>
                                                <small class="text-muted">Year <?php echo isset($student['year']) ? $student['year'] : 'N/A'; ?></small>
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
                                                <i class="fas fa-phone me-1"></i><?php echo isset($student['contact']) ? $student['contact'] : 'N/A'; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" title="View Credentials" onclick="viewStudentCredentials('<?php echo $student['grn']; ?>', '<?php echo $student['name']; ?>')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Edit Details">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Send Message" onclick="openMessageModal('student', <?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
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
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="fas fa-user-plus me-1"></i>Add Staff</button>
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
                                                <button class="btn btn-sm btn-outline-info" title="Send Message" onclick="openMessageModal('staff', <?php echo $staff_member['id']; ?>, '<?php echo addslashes($staff_member['name']); ?>')">
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
        
        <!-- Recent Attendance Reports -->
        <div id="attendance-reports" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i>Recent Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $recent_attendance = $pdo->prepare("
                            SELECT s.name, s.grn, a.date, a.status
                            FROM attendance a
                            JOIN students s ON a.student_id = s.id
                            WHERE s.hostel_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                            ORDER BY a.date DESC, s.name
                            LIMIT 20
                        ");
                        $recent_attendance->execute([$hostel_id]);
                        $attendance_records = $recent_attendance->fetchAll();
                        ?>
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
                                    <?php if(empty($attendance_records)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No attendance records</td></tr>
                                    <?php else: ?>
                                        <?php foreach($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                            <td><span class="badge bg-<?php echo $record['status'] == 'present' ? 'success' : 'danger'; ?>"><?php echo ucfirst($record['status']); ?></span></td>
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
                        <h5><i class="fas fa-utensils me-2"></i>Mess Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $mess_attendance = $pdo->prepare("
                            SELECT s.name, ma.date, ma.meal_type, ma.taken
                            FROM mess_attendance ma
                            JOIN students s ON ma.student_id = s.id
                            WHERE s.hostel_id = ? AND ma.date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                            ORDER BY ma.date DESC, s.name
                            LIMIT 15
                        ");
                        $mess_attendance->execute([$hostel_id]);
                        $mess_records = $mess_attendance->fetchAll();
                        ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Meal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($mess_records)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No mess attendance records</td></tr>
                                    <?php else: ?>
                                        <?php foreach($mess_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                            <td><?php echo ucfirst($record['meal_type']); ?></td>
                                            <td><span class="badge bg-<?php echo $record['taken'] ? 'success' : 'danger'; ?>"><?php echo $record['taken'] ? 'Taken' : 'Missed'; ?></span></td>
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
        
        <!-- Leave Applications Section -->
        <div id="leave-applications" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-times me-2"></i>Leave Applications</h5>
                        <?php 
                        try {
                            $leave_query = $pdo->prepare("
                                SELECT la.*, s.name as student_name, s.grn, u.username as reviewer_name
                                FROM leave_applications la 
                                JOIN students s ON la.student_id = s.id 
                                LEFT JOIN users u ON la.reviewed_by = u.id
                                WHERE s.hostel_id = ? 
                                ORDER BY la.applied_at DESC
                            ");
                            $leave_query->execute([$hostel_id]);
                            $leave_applications = $leave_query->fetchAll();
                        } catch (Exception $e) {
                            $leave_applications = [];
                        }
                        ?>
                        <span class="badge bg-primary"><?php echo count($leave_applications); ?> Applications</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($leave_applications)): ?>
                                        <tr><td colspan="7" class="text-center text-muted">No leave applications</td></tr>
                                    <?php else: ?>
                                        <?php foreach($leave_applications as $leave): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $leave['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $leave['grn']; ?></small>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo ucfirst($leave['leave_type']); ?></span></td>
                                            <td>
                                                <?php echo date('M d', strtotime($leave['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                            </td>
                                            <td><?php echo substr($leave['reason'], 0, 50) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['applied_at'])); ?></td>
                                            <td>
                                                <?php if($leave['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">🟡 Pending</span>
                                                <?php elseif($leave['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">🟢 Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">🔴 Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewLeaveDetails(<?php echo $leave['id']; ?>, '<?php echo addslashes($leave['student_name']); ?>', '<?php echo $leave['leave_type']; ?>', '<?php echo $leave['start_date']; ?>', '<?php echo $leave['end_date']; ?>', '<?php echo addslashes($leave['reason']); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if($leave['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveLeave(<?php echo $leave['id']; ?>)" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectLeave(<?php echo $leave['id']; ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
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

        <!-- Avalon Uploads Section -->
        <div id="avalon-uploads" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-upload me-2"></i>Avalon Uploads</h5>
                        <?php 
                        $avalon_query = $pdo->prepare("
                            SELECT au.*, s.name as student_name, s.grn 
                            FROM avalon_uploads au 
                            JOIN students s ON au.student_id = s.id 
                            WHERE s.hostel_id = ? 
                            ORDER BY au.uploaded_at DESC
                        ");
                        $avalon_query->execute([$hostel_id]);
                        $avalon_uploads = $avalon_query->fetchAll();
                        ?>
                        <span class="badge bg-primary"><?php echo count($avalon_uploads); ?> Files</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Title</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Uploaded Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($avalon_uploads)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No avalon uploads</td></tr>
                                    <?php else: ?>
                                        <?php foreach($avalon_uploads as $avalon): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $avalon['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $avalon['grn']; ?></small>
                                            </td>
                                            <td><?php echo $avalon['title']; ?></td>
                                            <td><?php echo $avalon['file_name']; ?></td>
                                            <td><?php echo round($avalon['file_size'] / 1024, 2); ?> KB</td>
                                            <td><?php echo date('M d, Y H:i', strtotime($avalon['uploaded_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="downloadFile('<?php echo $avalon['file_path']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="viewAvalonDetails(<?php echo $avalon['id']; ?>, '<?php echo addslashes($avalon['description']); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedback_list)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">No feedback received yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($feedback_list as $feedback): ?>
                                        <tr class="<?php echo $feedback['priority'] == 'urgent' ? 'table-danger' : ($feedback['priority'] == 'high' ? 'table-warning' : ''); ?>">
                                            <td><span class="badge bg-primary">#<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
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
                                            <td><span class="badge bg-secondary"><?php echo $feedback['category']; ?></span></td>
                                            <td><?php echo $feedback['subject']; ?></td>
                                            <td>
                                                <?php 
                                                $priority_colors = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];
                                                $priority_color = $priority_colors[$feedback['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $priority_color; ?>"><?php echo ucfirst($feedback['priority']); ?></span>
                                            </td>
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
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>, '<?php echo addslashes($feedback['message']); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-info" onclick="openMessageModal('student', <?php echo $feedback['student_id']; ?>, '<?php echo addslashes($feedback['student_name']); ?>')" title="Send Message">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <?php if($feedback['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed')" title="Mark Reviewed">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved')" title="Mark Resolved">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- View Leave Details Modal -->
    <div class="modal fade" id="viewLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Student:</strong> <span id="modalStudentName"></span></p>
                            <p><strong>Leave Type:</strong> <span id="modalLeaveType"></span></p>
                            <p><strong>Start Date:</strong> <span id="modalStartDate"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>End Date:</strong> <span id="modalEndDate"></span></p>
                            <p><strong>Duration:</strong> <span id="modalDuration"></span></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p><strong>Reason:</strong></p>
                        <div class="border p-3 bg-light rounded" id="modalReason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Avalon Details Modal -->
    <div class="modal fade" id="viewAvalonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Avalon Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="avalonDescription"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Search Student</label>
                            <input type="text" class="form-control" id="staffStudentSearch" placeholder="Type student name or GRN to search...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select class="form-control" name="student_id" id="staffStudentSelect" required>
                                <option value="">Choose a student to make staff...</option>
                                <?php foreach($students_list as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" data-name="<?php echo strtolower($student['name']); ?>" data-grn="<?php echo $student['grn']; ?>">
                                        <?php echo $student['name']; ?> (<?php echo $student['grn']; ?>) - <?php echo $student['course'] ?? 'N/A'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff Role</label>
                            <select class="form-control" name="staff_role" required>
                                <option value="">Select role...</option>
                                <option value="mess_head">Mess Head</option>
                                <option value="library_head">Library Head</option>
                                <option value="health_staff">Health Staff</option>
                                <option value="vvk_staff">VVK Staff</option>
                                <option value="placement_staff">Placement Staff</option>
                                <option value="ed_cell_staff">Ed Cell Staff</option>
                                <option value="scholarship_staff">Scholarship Staff</option>
                                <option value="student_head">Student Head</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_staff" class="btn btn-success">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Individual Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" name="student_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="student_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="student_contact" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" name="student_course" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-control" name="student_year" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_individual_student" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Credentials Modal -->
    <div class="modal fade" id="viewCredentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Login Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Share these credentials with the student for login
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" class="form-control" id="credentialStudentName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GRN (Username)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialGRN" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('credentialGRN')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialPassword" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('credentialPassword')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">Student can change this after first login</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="messageForm">
                        <input type="hidden" id="recipientType">
                        <input type="hidden" id="recipientId">
                        <div class="mb-3">
                            <label class="form-label">To:</label>
                            <input type="text" class="form-control" id="recipientName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="messageSubject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message:</label>
                            <textarea class="form-control" id="messageContent" rows="5" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendMessage()">Send Message</button>
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
        
        // View leave application details
        function viewLeaveDetails(id, studentName, leaveType, startDate, endDate, reason) {
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalLeaveType').textContent = leaveType.charAt(0).toUpperCase() + leaveType.slice(1);
            document.getElementById('modalStartDate').textContent = new Date(startDate).toLocaleDateString();
            document.getElementById('modalEndDate').textContent = new Date(endDate).toLocaleDateString();
            document.getElementById('modalReason').textContent = reason;
            
            // Calculate duration
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('modalDuration').textContent = diffDays + ' day(s)';
            
            new bootstrap.Modal(document.getElementById('viewLeaveModal')).show();
        }
        
        // Approve leave application
        function approveLeave(id) {
            if (confirm('APPROVE this leave application?')) {
                updateLeaveStatus(id, 'approved');
            }
        }
        
        // Reject leave application
        function rejectLeave(id) {
            if (confirm('REJECT this leave application?')) {
                updateLeaveStatus(id, 'rejected');
            }
        }
        
        // Update leave application status
        function updateLeaveStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_leave_status');
            formData.append('leave_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Leave application ' + status + ' successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            });
        }
        
        // View avalon details
        function viewAvalonDetails(id, description) {
            document.getElementById('avalonDescription').textContent = description || 'No description provided';
            new bootstrap.Modal(document.getElementById('viewAvalonModal')).show();
        }
        
        // Download file
        function downloadFile(filePath) {
            window.open(filePath, '_blank');
        }
        
        // Open message modal
        function openMessageModal(type, id, name) {
            document.getElementById('recipientType').value = type;
            document.getElementById('recipientId').value = id;
            document.getElementById('recipientName').value = name;
            document.getElementById('messageSubject').value = '';
            document.getElementById('messageContent').value = '';
            new bootstrap.Modal(document.getElementById('sendMessageModal')).show();
        }
        
        // View student credentials
        function viewStudentCredentials(grn, name) {
            document.getElementById('credentialStudentName').value = name;
            document.getElementById('credentialGRN').value = grn;
            
            // Fetch password from server
            fetch('../handlers/get_student_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'grn=' + encodeURIComponent(grn)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('credentialPassword').value = data.password;
                } else {
                    document.getElementById('credentialPassword').value = 'Password not available';
                }
            })
            .catch(error => {
                document.getElementById('credentialPassword').value = 'Error loading password';
            });
            
            new bootstrap.Modal(document.getElementById('viewCredentialsModal')).show();
        }
        
        // Copy to clipboard
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }
        
        // Export students
        function exportStudents() {
            window.location.href = '../handlers/export_students.php';
        }
        
        // Send message
        function sendMessage() {
            const formData = new FormData();
            formData.append('action', 'send_email');
            formData.append('recipient_type', document.getElementById('recipientType').value);
            formData.append('recipient_id', document.getElementById('recipientId').value);
            formData.append('subject', document.getElementById('messageSubject').value);
            formData.append('message', document.getElementById('messageContent').value);
            
            fetch('../handlers/send_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Message sent successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('sendMessageModal')).hide();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            });
        }
        
        // Staff student search functionality
        document.getElementById('staffStudentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const select = document.getElementById('staffStudentSelect');
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const name = option.getAttribute('data-name') || '';
                const grn = option.getAttribute('data-grn') || '';
                
                if (name.includes(searchTerm) || grn.toLowerCase().includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
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
    </script>
</body>
</html>