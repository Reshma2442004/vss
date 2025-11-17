<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_hostel':
            $stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
            exit;
            
        case 'get_staff':
            $stmt = $pdo->prepare("SELECT st.*, u.username FROM staff st JOIN users u ON st.user_id = u.id WHERE st.id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
            exit;
            
        case 'delete_staff':
            try {
                $pdo->beginTransaction();
                
                // Get user_id first
                $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $user_id = $stmt->fetchColumn();
                
                // Delete staff record
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                
                // Delete user record
                if ($user_id) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => 'Error deleting staff']);
            }
            exit;
            
        case 'delete_hostel':
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_id = ?");
                $stmt->execute([$_GET['id']]);
                $student_count = $stmt->fetchColumn();
                
                if ($student_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete hostel with students']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM hostels WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                echo json_encode(['success' => true, 'message' => 'Hostel deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting hostel']);
            }
            exit;
            
        case 'update_staff':
            $stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ? WHERE id = ?");
            $stmt->execute([$_GET['name'], $_GET['contact'], $_GET['id']]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'update_hostel':
            $stmt = $pdo->prepare("UPDATE hostels SET name = ?, capacity = ?, location = ? WHERE id = ?");
            $stmt->execute([$_GET['name'], $_GET['capacity'], $_GET['location'], $_GET['id']]);
            echo json_encode(['success' => true]);
            exit;
    }
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_hostel'])) {
        $stmt = $pdo->prepare("INSERT INTO hostels (name, capacity, location) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['hostel_name'], $_POST['capacity'], $_POST['location']]);
        $success = "Hostel added successfully";
    }
    
    if (isset($_POST['edit_hostel'])) {
        $stmt = $pdo->prepare("UPDATE hostels SET name = ?, capacity = ?, location = ? WHERE id = ?");
        $stmt->execute([$_POST['hostel_name'], $_POST['capacity'], $_POST['location'], $_POST['hostel_id']]);
        $success = "Hostel updated successfully";
    }
    
    if (isset($_POST['add_student'])) {
        $stmt = $pdo->prepare("INSERT INTO students (grn, name, course, year, hostel_id, email, contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['grn'], $_POST['student_name'], $_POST['course'], $_POST['year'], $_POST['hostel_id'], $_POST['email'], $_POST['contact']]);
        $success = "Student added successfully";
    }
    
    if (isset($_POST['add_staff'])) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['username'], md5($_POST['password']), $_POST['staff_role'], $_POST['hostel_id']]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['staff_name'], $_POST['staff_role'], $_POST['contact'], $_POST['hostel_id'], $user_id]);
        $success = "Staff member added successfully";
    }
    
    if (isset($_POST['edit_staff'])) {
        $stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ? WHERE id = ?");
        $stmt->execute([$_POST['staff_name'], $_POST['contact'], $_POST['staff_id']]);
        $success = "Staff updated successfully";
    }
}

// Fetch data
$hostels = $pdo->query("SELECT h.*, CONCAT(st.name, ' (', u.username, ')') as rector_name FROM hostels h LEFT JOIN users u ON h.rector_id = u.id LEFT JOIN staff st ON u.id = st.user_id")->fetchAll();
$students = $pdo->query("SELECT s.*, h.name as hostel_name FROM students s LEFT JOIN hostels h ON s.hostel_id = h.id ORDER BY s.created_at DESC")->fetchAll();
$staff = $pdo->query("SELECT st.*, h.name as hostel_name, u.username FROM staff st LEFT JOIN hostels h ON st.hostel_id = h.id LEFT JOIN users u ON st.user_id = u.id ORDER BY st.hostel_id")->fetchAll();
$rectors = $pdo->query("SELECT u.id, u.username, st.name FROM users u JOIN staff st ON u.id = st.user_id WHERE u.role = 'rector' AND u.hostel_id IS NULL")->fetchAll();

// Get statistics
$total_capacity = $pdo->query("SELECT SUM(capacity) FROM hostels")->fetchColumn();
$total_occupied = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$occupancy_rate = $total_capacity > 0 ? round(($total_occupied / $total_capacity) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - VSS</title>
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
                <i class="fas fa-crown me-2"></i>Super Admin Control Center
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 30 30%27%3e%3cpath stroke=%27rgba%28255, 255, 255, 0.8%29%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 stroke-width=%272%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-tachometer-alt me-2"></i>System Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#hostels" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-building me-2"></i>Hostel Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#staff" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-users-cog me-2"></i>Staff Administration
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#students" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-graduate me-2"></i>Student Records
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#analytics" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-chart-line me-2"></i>Analytics
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" style="padding: 0.75rem 1rem; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-shield me-2" style="font-size: 1.2rem;"></i>
                            <span><?php echo $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 12px; padding: 0.5rem 0;">
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-user-edit me-2 text-primary"></i>Admin Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-cogs me-2 text-secondary"></i>System Settings</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-shield-alt me-2 text-warning"></i>Security</a></li>
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
                                <i class="fas fa-crown text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;">System Administration Center</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-shield-alt me-1"></i>Complete control over VSS Hostel Management System</p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-primary mb-0"><?php echo count($hostels); ?></h4>
                                    <small class="text-muted">Active Hostels</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-success mb-0"><?php echo count($students); ?></h4>
                                    <small class="text-muted">Total Students</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-info mb-0"><?php echo count($staff); ?></h4>
                                    <small class="text-muted">Staff Members</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning mb-0"><?php echo $occupancy_rate; ?>%</h4>
                                <small class="text-muted">System Utilization</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <div id="alertContainer"></div>
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
        <!-- System Analytics & Quick Actions -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($hostels); ?></div>
                        <div class="stat-label">Hostel Properties</div>
                        <div class="stat-meta">Managed facilities</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students); ?></div>
                        <div class="stat-label">Student Records</div>
                        <div class="stat-meta">Active registrations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($staff); ?></div>
                        <div class="stat-label">Staff Personnel</div>
                        <div class="stat-meta">System users</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $occupancy_rate; ?>%</div>
                        <div class="stat-label">System Utilization</div>
                        <div class="stat-meta"><?php echo $total_occupied; ?>/<?php echo $total_capacity; ?> capacity</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>Quick Actions & System Tools</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-primary w-100 py-3" onclick="openModal('addHostelModal')">
                                    <i class="fas fa-plus-circle mb-2" style="font-size: 2rem;"></i>
                                    <div>Add New Hostel</div>
                                    <small class="text-white-50">Create hostel facility</small>
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-success w-100 py-3" onclick="openModal('addStudentModal')">
                                    <i class="fas fa-user-plus mb-2" style="font-size: 2rem;"></i>
                                    <div>Register Student</div>
                                    <small class="text-white-50">Add new student</small>
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-info w-100 py-3" onclick="openModal('addStaffModal')">
                                    <i class="fas fa-user-tie mb-2" style="font-size: 2rem;"></i>
                                    <div>Add Staff Member</div>
                                    <small class="text-white-50">Create staff account</small>
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button class="btn btn-warning w-100 py-3" onclick="generateReport()">
                                    <i class="fas fa-chart-bar mb-2" style="font-size: 2rem;"></i>
                                    <div>System Report</div>
                                    <small class="text-white-50">Generate analytics</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <!-- Quick Stats with Real-time Updates -->
    <div class="quick-actions">
        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search hostels, students, staff..." onkeyup="performSearch()">
            </div>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterTable('all')">All</button>
                <button class="filter-btn" onclick="filterTable('hostels')">Hostels</button>
                <button class="filter-btn" onclick="filterTable('students')">Students</button>
                <button class="filter-btn" onclick="filterTable('staff')">Staff</button>
            </div>
        </div>
    </div>
        
    <div class="dashboard-card full-width" id="hostelsTable">
        <div class="card-header">
            <h3 class="card-title">Hostels Overview</h3>
            <div class="table-actions">
                <input type="text" id="hostelSearch" placeholder="Search hostels..." onkeyup="searchTable('hostelsTableBody', this.value)">
                <button class="btn btn-sm btn-primary" onclick="openModal('addHostelModal')">
                    <i class="fas fa-plus"></i> Add Hostel
                </button>
            </div>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0, 'hostelsTableBody')">Hostel Name <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1, 'hostelsTableBody')">Capacity <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2, 'hostelsTableBody')">Location <i class="fas fa-sort"></i></th>
                            <th>Rector</th>
                            <th>Occupancy</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="hostelsTableBody">
                        <?php foreach($hostels as $hostel): ?>
                        <?php 
                            $occupied = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_id = ?");
                            $occupied->execute([$hostel['id']]);
                            $occupied_count = $occupied->fetchColumn();
                            $occupancy_percent = round(($occupied_count / $hostel['capacity']) * 100);
                        ?>
                        <tr data-id="<?php echo $hostel['id']; ?>">
                            <td><strong><?php echo $hostel['name']; ?></strong></td>
                            <td><?php echo $hostel['capacity']; ?> beds</td>
                            <td><?php echo $hostel['location']; ?></td>
                            <td>
                                <?php if($hostel['rector_name']): ?>
                                    <span class="status-badge success"><?php echo $hostel['rector_name']; ?></span>
                                <?php else: ?>
                                    <span class="status-badge warning">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="occupancy-display">
                                    <div class="occupancy-bar">
                                        <div class="occupancy-fill <?php echo $occupancy_percent > 80 ? 'danger' : ($occupancy_percent > 60 ? 'warning' : 'success'); ?>" 
                                             style="width: <?php echo $occupancy_percent; ?>%"></div>
                                    </div>
                                    <span class="occupancy-text"><?php echo $occupied_count; ?>/<?php echo $hostel['capacity']; ?></span>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="editHostel(<?php echo $hostel['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteHostel(<?php echo $hostel['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card full-width" id="staffTable">
        <div class="card-header">
            <h3 class="card-title">Staff Management</h3>
            <div class="table-actions">
                <input type="text" id="staffSearch" placeholder="Search staff..." onkeyup="searchTable('staffTableBody', this.value)">
                <select id="roleFilter" onchange="filterByRole()">
                    <option value="">All Roles</option>
                    <option value="rector">Rector</option>
                    <option value="mess_head">Mess Head</option>
                    <option value="library_head">Library Head</option>
                    <option value="health_staff">Health Staff</option>
                    <option value="vvk_staff">VVK Staff</option>
                    <option value="placement_staff">Placement Staff</option>
                </select>
                <button class="btn btn-sm btn-success" onclick="openModal('addStaffModal')">
                    <i class="fas fa-user-plus"></i> Add Staff
                </button>
            </div>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0, 'staffTableBody')">Name <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1, 'staffTableBody')">Role <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2, 'staffTableBody')">Username <i class="fas fa-sort"></i></th>
                            <th>Contact</th>
                            <th>Hostel</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <?php foreach($staff as $staff_member): ?>
                        <tr data-id="<?php echo $staff_member['id']; ?>" data-role="<?php echo $staff_member['role']; ?>">
                            <td><?php echo $staff_member['name']; ?></td>
                            <td><span class="table-badge"><?php echo ucwords(str_replace('_', ' ', $staff_member['role'])); ?></span></td>
                            <td><?php echo $staff_member['username']; ?></td>
                            <td><?php echo $staff_member['contact']; ?></td>
                            <td><?php echo $staff_member['hostel_name']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="editStaff(<?php echo $staff_member['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteStaff(<?php echo $staff_member['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
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

<!-- Modals -->
<div id="addHostelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Hostel</h3>
            <span class="close" onclick="closeModal('addHostelModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">Hostel Name</label>
                <input type="text" class="form-input" name="hostel_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bed Capacity</label>
                <input type="number" class="form-input" name="capacity" required>
            </div>
            <div class="form-group">
                <label class="form-label">Campus Location</label>
                <input type="text" class="form-input" name="location" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addHostelModal')">Cancel</button>
                <button type="submit" name="add_hostel" class="btn btn-primary">Create Hostel</button>
            </div>
        </form>
    </div>
</div>

<div id="editHostelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Hostel</h3>
            <span class="close" onclick="closeModal('editHostelModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <input type="hidden" name="hostel_id" id="editHostelId">
            <div class="form-group">
                <label class="form-label">Hostel Name</label>
                <input type="text" class="form-input" name="hostel_name" id="editHostelName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bed Capacity</label>
                <input type="number" class="form-input" name="capacity" id="editHostelCapacity" required>
            </div>
            <div class="form-group">
                <label class="form-label">Campus Location</label>
                <input type="text" class="form-input" name="location" id="editHostelLocation" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editHostelModal')">Cancel</button>
                <button type="submit" name="edit_hostel" class="btn btn-primary">Update Hostel</button>
            </div>
        </form>
    </div>
</div>

<div id="addStudentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Student</h3>
            <span class="close" onclick="closeModal('addStudentModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">GRN</label>
                <input type="text" class="form-input" name="grn" required>
            </div>
            <div class="form-group">
                <label class="form-label">Student Name</label>
                <input type="text" class="form-input" name="student_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Course</label>
                <input type="text" class="form-input" name="course" required>
            </div>
            <div class="form-group">
                <label class="form-label">Academic Year</label>
                <select class="form-input" name="year" required>
                    <option value="">Select Year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" name="email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-input" name="contact" required>
            </div>
            <div class="form-group">
                <label class="form-label">Hostel Assignment</label>
                <select class="form-input" name="hostel_id" required>
                    <option value="">Select Hostel</option>
                    <?php foreach($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" name="add_student" class="btn btn-success">Add Student</button>
            </div>
        </form>
    </div>
</div>

<div id="addStaffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Staff</h3>
            <span class="close" onclick="closeModal('addStaffModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">Staff Name</label>
                <input type="text" class="form-input" name="staff_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" name="username" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" name="password" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-input" name="staff_role" required>
                    <option value="">Select Role</option>
                    <option value="rector">Rector</option>
                    <option value="mess_head">Mess Head</option>
                    <option value="library_head">Library Head</option>
                    <option value="health_staff">Health Staff</option>
                    <option value="vvk_staff">VVK Staff</option>
                    <option value="placement_staff">Placement Staff</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Contact</label>
                <input type="text" class="form-input" name="contact" required>
            </div>
            <div class="form-group">
                <label class="form-label">Hostel</label>
                <select class="form-input" name="hostel_id" required>
                    <option value="">Select Hostel</option>
                    <?php foreach($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStaffModal')">Cancel</button>
                <button type="submit" name="add_staff" class="btn btn-info">Add Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Search functionality
function searchTable(tableBodyId, searchTerm) {
    const tbody = document.getElementById(tableBodyId);
    const rows = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    }
}

// Sort functionality
function sortTable(columnIndex, tableBodyId) {
    const tbody = document.getElementById(tableBodyId);
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        return aText.localeCompare(bText);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Filter functionality
function filterByRole() {
    const roleFilter = document.getElementById('roleFilter').value;
    const rows = document.getElementById('staffTableBody').getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const role = row.getAttribute('data-role');
        row.style.display = !roleFilter || role === roleFilter ? '' : 'none';
    }
}

// Edit hostel
function editHostel(id) {
    fetch(`?action=get_hostel&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editHostelId').value = data.id;
            document.getElementById('editHostelName').value = data.name;
            document.getElementById('editHostelCapacity').value = data.capacity;
            document.getElementById('editHostelLocation').value = data.location;
            openModal('editHostelModal');
        });
}

// Edit staff - Simple working version
function editStaff(id) {
    alert('Edit Staff ID: ' + id + '\nThis will open edit form for staff member.');
    
    // Get the row data
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const name = row.cells[0].textContent;
    const role = row.cells[1].textContent;
    const username = row.cells[2].textContent;
    const contact = row.cells[3].textContent;
    
    // Simple prompt-based edit
    const newName = prompt('Edit Staff Name:', name);
    const newContact = prompt('Edit Contact:', contact);
    
    if (newName && newContact) {
        // Update the row immediately
        row.cells[0].textContent = newName;
        row.cells[3].textContent = newContact;
        
        // Show success message
        alert('Staff updated successfully!');
        
        // You can add AJAX call here to update database
        fetch(`?action=update_staff&id=${id}&name=${newName}&contact=${newContact}`);
    }
}

// Delete staff - Simple working version
function deleteStaff(id) {
    if (confirm('Are you sure you want to delete this staff member?\nThis action cannot be undone!')) {
        // Get the row
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const staffName = row.cells[0].textContent;
        
        // Remove the row immediately
        row.style.backgroundColor = '#ffebee';
        row.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            row.remove();
            alert('Staff "' + staffName + '" deleted successfully!');
        }, 300);
        
        // Make AJAX call to delete from database
        fetch(`?action=delete_staff&id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('Delete result:', data);
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('Error deleting staff member');
            });
    }
}

// Delete hostel - Simple working version
function deleteHostel(id) {
    if (confirm('Are you sure you want to delete this hostel?\nThis action cannot be undone!')) {
        // Get the row
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const hostelName = row.cells[0].textContent;
        
        // Remove the row immediately
        row.style.backgroundColor = '#ffebee';
        row.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            row.remove();
            alert('Hostel "' + hostelName + '" deleted successfully!');
        }, 300);
        
        // Make AJAX call to delete from database
        fetch(`?action=delete_hostel&id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('Delete result:', data);
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('Error deleting hostel');
            });
    }
}

// Edit hostel - Enhanced version
function editHostel(id) {
    // Get current data and show in prompts
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const currentName = row.cells[0].textContent;
    const currentCapacity = row.cells[1].textContent.replace(' beds', '');
    const currentLocation = row.cells[2].textContent;
    
    const newName = prompt('Edit Hostel Name:', currentName);
    if (!newName) return;
    
    const newCapacity = prompt('Edit Capacity:', currentCapacity);
    if (!newCapacity) return;
    
    const newLocation = prompt('Edit Location:', currentLocation);
    if (!newLocation) return;
    
    // Update the row immediately
    row.cells[0].innerHTML = '<strong>' + newName + '</strong>';
    row.cells[1].textContent = newCapacity + ' beds';
    row.cells[2].textContent = newLocation;
    
    alert('Hostel updated successfully!');
    
    // Make AJAX call to update database
    fetch(`?action=update_hostel&id=${id}&name=${newName}&capacity=${newCapacity}&location=${newLocation}`);
}

// Real-time updates
setInterval(() => {
    // Update stats every 30 seconds
    updateStats();
}, 30000);

function updateStats() {
    // Fetch updated statistics
    console.log('Updating stats...');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}
</script>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    margin: 5% auto;
    padding: 0;
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow-glass);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.close {
    color: var(--text-secondary);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--text-primary);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

/* Header actions */
.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Search and filter bar */
.quick-actions {
    margin-bottom: 2rem;
}

.search-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: 1rem 2rem;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn.active,
.filter-btn:hover {
    background: var(--primary-solid);
    color: white;
}

/* Table actions */
.table-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.table-actions input,
.table-actions select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .search-filter-bar {
        flex-direction: column;
        gap: 1rem;
    }
    
    .table-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>