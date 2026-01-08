<?php
require_once '../config/database.php';
require_once '../includes/db_check.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SESSION['role'] != 'mess_head') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this mess head");
}

$page_title = 'Mess Management';
$dashboard_title = $hostel_info['name'] . ' - Mess Management';
$dashboard_subtitle = $hostel_info['location'] . ' | Food Service & Dining Operations';

// Handle form submissions
if ($_POST) {
    try {
        if (isset($_POST['mark_single_attendance'])) {
            $student_id = $_POST['selected_student_id'];
            $attendance_date = $_POST['attendance_date'];
            $attendance_time = $_POST['attendance_time'];
            $meal_type = $_POST['meal_type'];
            $attendance_status = $_POST['student_attendance'];
            
            if ($student_id && $attendance_status) {
                $taken = $attendance_status == 'present' ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO mess_attendance (student_id, date, meal_type, taken, time) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE taken = ?, time = ?");
                $stmt->execute([$student_id, $attendance_date, $meal_type, $taken, $attendance_time, $taken, $attendance_time]);
                
                $student_name = '';
                foreach($students_list as $student) {
                    if($student['id'] == $student_id) {
                        $student_name = $student['name'];
                        break;
                    }
                }
                
                $status = $taken ? 'present' : 'absent';
                $success = "Attendance marked for {$student_name} as {$status} for {$meal_type} on {$attendance_date} at {$attendance_time}";
            } else {
                $error = "Please select a student and attendance status";
            }
        }
        
        if (isset($_POST['update_inventory'])) {
            $item_id = $_POST['item_id'];
            $quantity = $_POST['quantity'];
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ? AND hostel_id = ?");
            $stmt->execute([$quantity, $item_id, $hostel_id]);
            $success = "Inventory updated successfully";
        }
        
        if (isset($_POST['add_inventory'])) {
            $item_name = $_POST['item_name'];
            $quantity = $_POST['quantity'];
            $unit = $_POST['unit'];
            $low_stock_alert = $_POST['low_stock_alert'];
            $stmt = $pdo->prepare("INSERT INTO inventory (item_name, quantity, unit, low_stock_alert, hostel_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $quantity, $unit, $low_stock_alert, $hostel_id]);
            $success = "New inventory item added successfully";
        }
        
        if (isset($_POST['add_wastage'])) {
            $wastage_date = $_POST['wastage_date'];
            $meal_type = $_POST['wastage_meal_type'];
            $food_item = $_POST['food_item'];
            $quantity_wasted = $_POST['quantity_wasted'];
            $unit = $_POST['wastage_unit'];
            $reason = $_POST['reason'];
            
            $stmt = $pdo->prepare("INSERT INTO food_wastage (hostel_id, date, meal_type, food_item, quantity_wasted, unit, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$hostel_id, $wastage_date, $meal_type, $food_item, $quantity_wasted, $unit, $reason]);
            $_SESSION['success'] = "Food wastage record added successfully";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (isset($_POST['edit_wastage'])) {
            $wastage_id = $_POST['wastage_id'];
            $food_item = $_POST['edit_food_item'];
            $quantity_wasted = $_POST['edit_quantity_wasted'];
            $unit = $_POST['edit_unit'];
            $reason = $_POST['edit_reason'];
            
            $stmt = $pdo->prepare("UPDATE food_wastage SET food_item = ?, quantity_wasted = ?, unit = ?, reason = ? WHERE id = ? AND hostel_id = ?");
            $stmt->execute([$food_item, $quantity_wasted, $unit, $reason, $wastage_id, $hostel_id]);
            $_SESSION['success'] = "Wastage record updated successfully";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        
        if (isset($_POST['delete_wastage'])) {
            $wastage_id = $_POST['wastage_id'];
            $stmt = $pdo->prepare("DELETE FROM food_wastage WHERE id = ? AND hostel_id = ?");
            $stmt->execute([$wastage_id, $hostel_id]);
            $_SESSION['success'] = "Wastage record deleted successfully";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Validate hostel assignment
if (!$hostel_id) {
    die("No hostel assigned to this mess head");
}

// Fetch students in this hostel
$students_query = executeQuery($pdo, "SELECT * FROM students WHERE hostel_id = ?", [$hostel_id]);
$students_list = $students_query ? $students_query->fetchAll() : [];

// Fetch today's meal attendance with all students
$today_meals_query = executeQuery($pdo, "
    SELECT ma.student_id, ma.meal_type, ma.taken
    FROM mess_attendance ma 
    JOIN students s ON ma.student_id = s.id 
    WHERE s.hostel_id = ? AND ma.date = CURDATE()
", [$hostel_id]);
$meal_attendance = $today_meals_query ? $today_meals_query->fetchAll() : [];

// Get all students with today's attendance status - force fresh data
$students_attendance_query = executeQuery($pdo, "
    SELECT s.id, s.name, s.grn,
           CASE WHEN COUNT(CASE WHEN ma.taken = 1 THEN 1 END) > 0 THEN 'Present' ELSE 'Absent' END as status,
           GROUP_CONCAT(CASE WHEN ma.taken = 1 THEN ma.meal_type END) as meals_present,
           GROUP_CONCAT(CASE WHEN ma.taken = 0 THEN ma.meal_type END) as meals_absent
    FROM students s
    LEFT JOIN mess_attendance ma ON s.id = ma.student_id AND ma.date = CURDATE()
    WHERE s.hostel_id = ?
    GROUP BY s.id, s.name, s.grn
    ORDER BY s.name
", [$hostel_id]);
$students_attendance = $students_attendance_query ? $students_attendance_query->fetchAll() : [];

// Handle daily report generation
if (isset($_GET['generate_report'])) {
    $report_date = $_GET['report_date'] ?? date('Y-m-d');
    
    try {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mess_attendance_report_' . $report_date . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Write header
        fputcsv($output, ['Date', 'Student ID', 'GRN', 'Student Name', 'Meal Type', 'Attendance Status', 'Time']);
        
        // Add time column if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE mess_attendance ADD COLUMN time TIME DEFAULT '00:00:00'");
        } catch (Exception $e) {
            // Column already exists or other error, continue
        }
        
        // Get all attendance records for the date
        $stmt = $pdo->prepare("
            SELECT ma.date, s.id as student_id, s.grn, s.name, ma.meal_type, ma.taken, 
                   COALESCE(ma.time, '00:00:00') as time
            FROM mess_attendance ma
            JOIN students s ON ma.student_id = s.id
            WHERE ma.date = ? AND s.hostel_id = ?
            ORDER BY ma.id DESC
        ");
        $stmt->execute([$report_date, $hostel_id]);
        $all_records = $stmt->fetchAll();
        
        // Write data
        foreach($all_records as $record) {
            $status = $record['taken'] == 1 ? 'Present' : 'Absent';
            $time = $record['time'] ?: '00:00:00';
            $formatted_date = date('d-m-Y', strtotime($record['date']));
            
            fputcsv($output, [
                $formatted_date,
                $record['student_id'],
                $record['grn'],
                $record['name'],
                $record['meal_type'],
                $status,
                $time
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log("CSV Generation Error: " . $e->getMessage());
        die("Error generating report: " . $e->getMessage());
    }
}

// Handle food wastage report generation
if (isset($_GET['generate_wastage_report'])) {
    $report_date = $_GET['wastage_report_date'] ?? date('Y-m-d');
    
    try {
        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="food_wastage_report_' . $report_date . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        // Create file pointer
        $output = fopen('php://output', 'w');
        
        // Write header
        fputcsv($output, ['Date', 'Time', 'Meal Type', 'Food Item', 'Quantity Wasted', 'Unit', 'Reason']);
        
        // Get all wastage records for the date
        $stmt = $pdo->prepare("
            SELECT date, created_at, meal_type, food_item, quantity_wasted, unit, reason
            FROM food_wastage
            WHERE date = ? AND hostel_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$report_date, $hostel_id]);
        $wastage_records = $stmt->fetchAll();
        
        // Write data
        foreach($wastage_records as $record) {
            $formatted_date = date('d-m-Y', strtotime($record['date']));
            $formatted_time = date('h:i A', strtotime($record['created_at']));
            $meal_type = ucfirst(str_replace('_', ' ', $record['meal_type']));
            $reason = ucfirst(str_replace('_', ' ', $record['reason']));
            
            fputcsv($output, [
                $formatted_date,
                $formatted_time,
                $meal_type,
                $record['food_item'],
                $record['quantity_wasted'],
                $record['unit'],
                $reason
            ]);
        }
        
        fclose($output);
        exit;
    } catch (Exception $e) {
        error_log("Wastage CSV Generation Error: " . $e->getMessage());
        die("Error generating wastage report: " . $e->getMessage());
    }
}

// Get inventory data
$inventory_query = executeQuery($pdo, "SELECT * FROM inventory WHERE hostel_id = ?", [$hostel_id]);
$inventory_list = $inventory_query ? $inventory_query->fetchAll() : [];

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

// Calculate meal statistics - force fresh data
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$meal_stats = executeQuery($pdo, "
    SELECT meal_type, COUNT(*) as count 
    FROM mess_attendance ma 
    JOIN students s ON ma.student_id = s.id 
    WHERE s.hostel_id = ? AND ma.date = CURDATE() AND ma.taken = 1
    GROUP BY meal_type
", [$hostel_id]);
$meal_statistics = $meal_stats ? $meal_stats->fetchAll(PDO::FETCH_KEY_PAIR) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VSS</title>
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
                <i class="fas fa-utensils me-2"></i>Mess Management
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#attendance">
                            <i class="fas fa-clipboard-check me-2"></i>Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#inventory">
                            <i class="fas fa-boxes me-2"></i>Inventory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#wastage">
                            <i class="fas fa-trash-alt me-2"></i>Food Wastage
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#reports">
                            <i class="fas fa-file-alt me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback">
                            <i class="fas fa-comments me-2"></i>Feedback
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
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
                                <i class="fas fa-utensils text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;"><?php echo $dashboard_title; ?></h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-map-marker-alt me-1"></i><?php echo $dashboard_subtitle; ?></p>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students_list); ?></div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-meta">Registered for meals</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($meal_attendance); ?></div>
                        <div class="stat-label">Today's Meals</div>
                        <div class="stat-meta">Meals served today</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($inventory_list); ?></div>
                        <div class="stat-label">Inventory Items</div>
                        <div class="stat-meta">Items in stock</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $meal_statistics['morning_meal'] ?? 0; ?></div>
                        <div class="stat-label">Morning Meal</div>
                        <div class="stat-meta">Present today</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Meal Attendance Management -->
        <div id="attendance" class="row mb-4">
            <div class="col-12 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-utensils me-2"></i>Mark Meal Attendance</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form" id="attendanceForm">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-input" name="attendance_date" id="attendance_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Meal Type</label>
                                    <select class="form-input" name="meal_type" id="meal_type" required>
                                        <option value="">Select meal</option>
                                        <option value="morning_meal">Morning Meal</option>
                                        <option value="night_meal">Night Meal</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Time</label>
                                    <input type="text" class="form-input" name="attendance_time" id="attendance_time" readonly style="background-color: #e9ecef;">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Search Student</label>
                                <input type="text" class="form-input" id="studentSearch" placeholder="Type student name or GRN to search...">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Students List</label>
                                <div class="student-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px;">
                                    <?php foreach($students_list as $student): ?>
                                    <div class="student-item d-flex justify-content-between align-items-center p-2 mb-2" style="background: #f8f9fa; border-radius: 6px; cursor: pointer;" 
                                         data-student-name="<?php echo strtolower($student['name']); ?>" 
                                         data-student-grn="<?php echo $student['grn']; ?>"
                                         data-student-id="<?php echo $student['id']; ?>"
                                         onclick="selectStudent(this)">
                                        <div class="d-flex align-items-center">
                                            <div>
                                                <strong><?php echo $student['name']; ?></strong>
                                                <small class="text-muted d-block">GRN: <?php echo $student['grn']; ?></small>
                                            </div>
                                            <div class="ms-3">
                                                <span class="student-status-badge badge bg-secondary" style="display: none;">Not Marked</span>
                                            </div>
                                        </div>
                                        <div class="attendance-options" style="display: none;">
                                            <button type="button" class="btn btn-success btn-sm me-2" onclick="markAttendance(<?php echo $student['id']; ?>, 'present', this)">
                                                <i class="fas fa-check"></i> Present
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="markAttendance(<?php echo $student['id']; ?>, 'absent', this)">
                                                <i class="fas fa-times"></i> Absent
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <input type="hidden" name="selected_student_id" id="selectedStudentId">
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Management -->
        <div id="inventory" class="row mb-4">
            <div class="col-md-8 mb-3">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-boxes me-2"></i>Inventory Management</h5>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
                            <i class="fas fa-plus me-1"></i>Add Item
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Current Stock</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($inventory_list as $item): ?>
                                    <tr>
                                        <td><?php echo $item['item_name']; ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo $item['unit']; ?></td>
                                        <td>
                                            <?php if($item['quantity'] <= $item['low_stock_alert']): ?>
                                                <span class="badge bg-danger">Low Stock</span>
                                            <?php elseif($item['quantity'] <= $item['low_stock_alert'] * 2): ?>
                                                <span class="badge bg-warning">Medium</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Good</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="updateInventory(<?php echo $item['id']; ?>, '<?php echo $item['item_name']; ?>', <?php echo $item['quantity']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Stock Overview</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $low_stock = 0;
                        $good_stock = 0;
                        foreach($inventory_list as $item) {
                            if($item['quantity'] <= $item['low_stock_alert']) $low_stock++;
                            else $good_stock++;
                        }
                        ?>
                        <div class="text-center mb-3">
                            <div class="progress-circle mx-auto mb-3" data-percentage="<?php echo count($inventory_list) > 0 ? round(($good_stock / count($inventory_list)) * 100) : 0; ?>">
                                <span><?php echo count($inventory_list) > 0 ? round(($good_stock / count($inventory_list)) * 100) : 0; ?>%</span>
                            </div>
                            <h6>Stock Health</h6>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-success"><?php echo $good_stock; ?></h4>
                                <small class="text-muted">Good Stock</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-danger"><?php echo $low_stock; ?></h4>
                                <small class="text-muted">Low Stock</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Food Wastage Management -->
        <div id="wastage" class="row mb-4">
            <div class="col-md-8 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-trash-alt me-2"></i>Daily Food Wastage Tracking</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" class="form-input" name="wastage_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Meal Type</label>
                                    <select class="form-input" name="wastage_meal_type" required>
                                        <option value="">Select meal</option>
                                        <option value="morning_meal">Morning Meal</option>
                                        <option value="night_meal">Night Meal</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Food Item</label>
                                    <select class="form-input" name="food_item" required>
                                        <option value="">Select food item</option>
                                        <option value="Rice">Rice</option>
                                        <option value="Dal">Dal</option>
                                        <option value="Vegetables">Vegetables</option>
                                        <option value="Roti/Chapati">Roti/Chapati</option>
                                        <option value="Curry">Curry</option>
                                        <option value="Sambar">Sambar</option>
                                        <option value="Rasam">Rasam</option>
                                        <option value="Curd">Curd</option>
                                        <option value="Pickle">Pickle</option>
                                        <option value="Salad">Salad</option>
                                        <option value="Soup">Soup</option>
                                        <option value="Bread">Bread</option>
                                        <option value="Milk">Milk</option>
                                        <option value="Tea">Tea</option>
                                        <option value="Coffee">Coffee</option>
                                        <option value="Snacks">Snacks</option>
                                        <option value="Fruits">Fruits</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Quantity Wasted</label>
                                    <input type="number" step="0.1" class="form-input" name="quantity_wasted" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Unit</label>
                                    <select class="form-input" name="wastage_unit" required>
                                        <option value="kg">Kilograms</option>
                                        <option value="liters">Liters</option>
                                        <option value="plates">Plates</option>
                                        <option value="portions">Portions</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason for Wastage</label>
                                <select class="form-input" name="reason" required>
                                    <option value="">Select reason</option>
                                    <option value="overcooked">Overcooked</option>
                                    <option value="undercooked">Undercooked</option>
                                    <option value="excess_preparation">Excess Preparation</option>
                                    <option value="spoiled">Spoiled/Expired</option>
                                    <option value="student_leftover">Student Leftover</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <button type="submit" name="add_wastage" class="btn btn-warning">
                                <i class="fas fa-plus me-2"></i>Record Wastage
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <h6><i class="fas fa-list me-2"></i>Today's Wastage Records</h6>
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Meal</th>
                                        <th>Food Item</th>
                                        <th>Quantity</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $wastage_query = executeQuery($pdo, "SELECT * FROM food_wastage WHERE hostel_id = ? AND date = CURDATE() ORDER BY created_at DESC", [$hostel_id]);
                                    $wastage_records = $wastage_query ? $wastage_query->fetchAll() : [];
                                    
                                    if (empty($wastage_records)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No wastage records for today</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($wastage_records as $record): ?>
                                        <tr>
                                            <td><?php echo date('h:i A', strtotime($record['created_at'])); ?></td>
                                            <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $record['meal_type'])); ?></span></td>
                                            <td><?php echo $record['food_item']; ?></td>
                                            <td><?php echo $record['quantity_wasted'] . ' ' . $record['unit']; ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $record['reason'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="editWastage(<?php echo $record['id']; ?>, '<?php echo $record['food_item']; ?>', <?php echo $record['quantity_wasted']; ?>, '<?php echo $record['unit']; ?>', '<?php echo $record['reason']; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteWastage(<?php echo $record['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
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
            
            <div class="col-md-4 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Wastage Summary</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $today_wastage = executeQuery($pdo, "SELECT COUNT(*) as total_records, SUM(quantity_wasted) as total_quantity FROM food_wastage WHERE hostel_id = ? AND date = CURDATE()", [$hostel_id]);
                        $wastage_stats = $today_wastage ? $today_wastage->fetch() : ['total_records' => 0, 'total_quantity' => 0];
                        
                        $weekly_wastage = executeQuery($pdo, "SELECT COUNT(*) as weekly_records FROM food_wastage WHERE hostel_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)", [$hostel_id]);
                        $weekly_stats = $weekly_wastage ? $weekly_wastage->fetch() : ['weekly_records' => 0];
                        ?>
                        
                        <div class="text-center mb-3">
                            <h3 class="text-warning"><?php echo $wastage_stats['total_records']; ?></h3>
                            <p class="mb-0">Records Today</p>
                        </div>
                        
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <h5 class="text-danger"><?php echo number_format($wastage_stats['total_quantity'] ?? 0, 1); ?></h5>
                                <small class="text-muted">Total Quantity</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-info"><?php echo $weekly_stats['weekly_records']; ?></h5>
                                <small class="text-muted">This Week</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tips to Reduce Wastage</h6>
                            <ul class="mb-0 small">
                                <li>Plan portions based on attendance</li>
                                <li>Monitor cooking temperatures</li>
                                <li>Check expiry dates regularly</li>
                                <li>Encourage students to take only what they can eat</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        
        <!-- Daily Reports Section -->
        <div id="reports" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-file-alt me-2"></i>Attendance Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="modern-form">
                            <div class="form-group">
                                <label class="form-label">Select Date for Report</label>
                                <input type="date" class="form-input" name="report_date" required>
                            </div>
                            <button type="submit" name="generate_report" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Generate Attendance Report (CSV)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-trash-alt me-2"></i>Food Wastage Reports</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="modern-form">
                            <div class="form-group">
                                <label class="form-label">Select Date for Wastage Report</label>
                                <input type="date" class="form-input" name="wastage_report_date" required>
                            </div>
                            <button type="submit" name="generate_wastage_report" class="btn btn-warning">
                                <i class="fas fa-download me-2"></i>Generate Wastage Report (CSV)
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Student Feedback Section -->
        <div id="feedback" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-comments me-2"></i>Student Feedback</h5>
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
                                            <td colspan="7" class="text-center text-muted">No feedback received yet</td>
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
                                                    <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>, '<?php echo addslashes($feedback['message']); ?>', '<?php echo addslashes($feedback['photo_path'] ?? ''); ?>')" title="View Details">
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
                    <div id="feedbackPhotoDiv" style="display: none;">
                        <hr>
                        <h6>Attached Photo:</h6>
                        <img id="feedbackPhotoImg" src="" class="img-fluid" style="max-height: 300px; cursor: pointer;" onclick="openPhotoModal(this.src)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Viewer Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhotoImg" src="" class="img-fluid" style="max-width: 100%; max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Inventory Modal -->
    <div class="modal fade" id="addInventoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_inventory" value="1">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-control" name="unit" required>
                                <option value="kg">Kilograms</option>
                                <option value="liters">Liters</option>
                                <option value="pieces">Pieces</option>
                                <option value="packets">Packets</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Low Stock Alert</label>
                            <input type="number" class="form-control" name="low_stock_alert" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Inventory Modal -->
    <div class="modal fade" id="updateInventoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_inventory" value="1">
                        <input type="hidden" name="item_id" id="update_item_id">
                        <div class="mb-3">
                            <label class="form-label">Item Name</label>
                            <input type="text" class="form-control" id="update_item_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="update_quantity" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Wastage Modal -->
    <div class="modal fade" id="editWastageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Wastage Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_wastage" value="1">
                        <input type="hidden" name="wastage_id" id="edit_wastage_id">
                        <div class="mb-3">
                            <label class="form-label">Food Item</label>
                            <select class="form-control" name="edit_food_item" id="edit_food_item" required>
                                <option value="Rice">Rice</option>
                                <option value="Dal">Dal</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Roti/Chapati">Roti/Chapati</option>
                                <option value="Curry">Curry</option>
                                <option value="Sambar">Sambar</option>
                                <option value="Rasam">Rasam</option>
                                <option value="Curd">Curd</option>
                                <option value="Pickle">Pickle</option>
                                <option value="Salad">Salad</option>
                                <option value="Soup">Soup</option>
                                <option value="Bread">Bread</option>
                                <option value="Milk">Milk</option>
                                <option value="Tea">Tea</option>
                                <option value="Coffee">Coffee</option>
                                <option value="Snacks">Snacks</option>
                                <option value="Fruits">Fruits</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity Wasted</label>
                            <input type="number" step="0.1" class="form-control" name="edit_quantity_wasted" id="edit_quantity_wasted" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit</label>
                            <select class="form-control" name="edit_unit" id="edit_unit" required>
                                <option value="kg">Kilograms</option>
                                <option value="liters">Liters</option>
                                <option value="plates">Plates</option>
                                <option value="portions">Portions</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <select class="form-control" name="edit_reason" id="edit_reason" required>
                                <option value="overcooked">Overcooked</option>
                                <option value="undercooked">Undercooked</option>
                                <option value="excess_preparation">Excess Preparation</option>
                                <option value="spoiled">Spoiled/Expired</option>
                                <option value="student_leftover">Student Leftover</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if(isset($success)): ?>
    <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if(isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php include '../includes/message_component.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize progress circles
        document.querySelectorAll('.progress-circle').forEach(circle => {
            const percentage = circle.getAttribute('data-percentage');
            circle.style.background = `conic-gradient(#667eea ${percentage * 3.6}deg, #e9ecef 0deg)`;
        });

        // Update inventory function
        function updateInventory(id, name, currentQuantity) {
            document.getElementById('update_item_id').value = id;
            document.getElementById('update_item_name').value = name;
            document.getElementById('update_quantity').value = currentQuantity;
            new bootstrap.Modal(document.getElementById('updateInventoryModal')).show();
        }

        // Student search functionality with highlighting and reordering
        document.getElementById('studentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const container = document.querySelector('.student-list-container');
            const studentItems = Array.from(container.querySelectorAll('.student-item'));
            
            // Clear previous selections
            studentItems.forEach(item => {
                item.style.background = '#f8f9fa';
                item.style.border = 'none';
                const attendanceOptions = item.querySelector('.attendance-options');
                attendanceOptions.style.display = 'none';
            });
            
            document.getElementById('selectedStudentId').value = '';
            
            if (searchTerm.length > 0) {
                const matchingItems = [];
                const nonMatchingItems = [];
                
                studentItems.forEach(item => {
                    const studentName = item.getAttribute('data-student-name');
                    const studentGrn = item.getAttribute('data-student-grn');
                    
                    if (studentName.includes(searchTerm) || studentGrn.includes(searchTerm)) {
                        item.style.display = 'flex';
                        item.style.background = '#fff3cd';
                        item.style.border = '2px solid #ffc107';
                        item.style.boxShadow = '0 2px 8px rgba(255, 193, 7, 0.3)';
                        matchingItems.push(item);
                    } else {
                        item.style.display = 'flex';
                        item.style.background = '#f8f9fa';
                        item.style.border = 'none';
                        item.style.boxShadow = 'none';
                        nonMatchingItems.push(item);
                    }
                });
                
                // Reorder: matching items first, then non-matching
                container.innerHTML = '';
                matchingItems.forEach(item => container.appendChild(item));
                nonMatchingItems.forEach(item => container.appendChild(item));
                
                // Re-attach event listeners after DOM manipulation
                reattachEvents();
                
            } else {
                studentItems.forEach(item => {
                    item.style.display = 'flex';
                    item.style.background = '#f8f9fa';
                    item.style.border = 'none';
                    item.style.boxShadow = 'none';
                });
            }
        });
        
        // Store original order for reset
        const originalOrder = Array.from(document.querySelectorAll('.student-item'));
        
        // Select student function
        function selectStudent(element) {
            // Clear previous selections
            document.querySelectorAll('.student-item').forEach(item => {
                if (item !== element) {
                    item.style.background = '#f8f9fa';
                    item.style.border = 'none';
                    const attendanceOptions = item.querySelector('.attendance-options');
                    attendanceOptions.style.display = 'none';
                }
            });
            
            // Highlight selected student
            element.style.background = '#d1ecf1';
            element.style.border = '2px solid #17a2b8';
            
            // Show attendance options for selected student
            const attendanceOptions = element.querySelector('.attendance-options');
            attendanceOptions.style.display = 'block';
            
            // Set student ID
            const studentId = element.getAttribute('data-student-id');
            document.getElementById('selectedStudentId').value = studentId;
        }
        
        // Mark attendance function
        function markAttendance(studentId, status, buttonElement) {
            // Get current exact time when marking attendance
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 should be 12
            const exactTime = hours.toString().padStart(2, '0') + ':' + minutes + ':' + seconds + ' ' + ampm;
            
            // Check if required fields are filled
            const date = document.getElementById('attendance_date').value;
            const mealType = document.getElementById('meal_type').value;
            
            if (!date || !mealType) {
                alert('Please fill in Date and Meal Type first!');
                return;
            }
            
            // Update visual status immediately
            const studentItem = buttonElement.closest('.student-item');
            const statusBadge = studentItem.querySelector('.student-status-badge');
            
            statusBadge.style.display = 'inline-block';
            if (status === 'present') {
                statusBadge.className = 'student-status-badge badge bg-success';
                statusBadge.textContent = 'Present';
                studentItem.style.background = '#d4edda';
                studentItem.style.border = '2px solid #28a745';
            } else {
                statusBadge.className = 'student-status-badge badge bg-danger';
                statusBadge.textContent = 'Absent';
                studentItem.style.background = '#f8d7da';
                studentItem.style.border = '2px solid #dc3545';
            }
            
            // Hide attendance options after marking
            const attendanceOptions = studentItem.querySelector('.attendance-options');
            attendanceOptions.style.display = 'none';
            
            // Submit form via AJAX to avoid page reload
            const formData = new FormData();
            formData.append('mark_single_attendance', '1');
            formData.append('selected_student_id', studentId);
            formData.append('attendance_date', date);
            formData.append('attendance_time', exactTime);
            formData.append('meal_type', mealType);
            formData.append('student_attendance', status);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    // Show success message
                    const studentName = studentItem.querySelector('strong').textContent;
                    showAlert('success', `Attendance marked for ${studentName} as ${status}`);
                } else {
                    showAlert('error', 'Error marking attendance');
                }
            }).catch(error => {
                showAlert('error', 'Error marking attendance');
            });
        }
        
        // Show alert function
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }
        
        // Re-attach click events after DOM manipulation
        function reattachEvents() {
            document.querySelectorAll('.student-item').forEach(item => {
                item.onclick = function() { selectStudent(this); };
            });
        }
        
        // Initialize click events on page load
        reattachEvents();
        
        // Set current time and update it every second
        function updateCurrentTime() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 should be 12
            const currentTime = hours.toString().padStart(2, '0') + ':' + minutes + ' ' + ampm;
            document.getElementById('attendance_time').value = currentTime;
        }
        
        // Update time immediately and then every second
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);


        // Edit wastage function
        function editWastage(id, foodItem, quantity, unit, reason) {
            document.getElementById('edit_wastage_id').value = id;
            document.getElementById('edit_food_item').value = foodItem;
            document.getElementById('edit_quantity_wasted').value = quantity;
            document.getElementById('edit_unit').value = unit;
            document.getElementById('edit_reason').value = reason;
            new bootstrap.Modal(document.getElementById('editWastageModal')).show();
        }
        
        // Delete wastage function
        function deleteWastage(id) {
            if (confirm('Are you sure you want to delete this wastage record?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="delete_wastage" value="1">
                    <input type="hidden" name="wastage_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // View feedback function
        function viewFeedback(id, message, photoPath) {
            document.getElementById('feedbackMessage').textContent = message;
            
            const photoDiv = document.getElementById('feedbackPhotoDiv');
            const photoImg = document.getElementById('feedbackPhotoImg');
            
            if (photoPath && photoPath.trim() !== '') {
                photoImg.src = photoPath;
                photoDiv.style.display = 'block';
            } else {
                photoDiv.style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
        }
        
        // Open photo in modal
        function openPhotoModal(src) {
            document.getElementById('modalPhotoImg').src = src;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
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
        
        // Auto-dismiss alerts
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