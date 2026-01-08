<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fix database structure immediately
try {
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT 'student@hostel.com'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS contact VARCHAR(20) DEFAULT '0000000000'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS course VARCHAR(100) DEFAULT 'General'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS year INT DEFAULT 1");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
    
    // Update NULL values
    $pdo->exec("UPDATE students SET email = 'student@hostel.com' WHERE email IS NULL OR email = ''");
    $pdo->exec("UPDATE students SET contact = '0000000000' WHERE contact IS NULL OR contact = ''");
    $pdo->exec("UPDATE students SET course = 'General' WHERE course IS NULL OR course = ''");
    $pdo->exec("UPDATE students SET year = 1 WHERE year IS NULL");
} catch (Exception $e) {
    // Continue if columns exist
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_staff'])) {
        $student_id = $_POST['student_id'];
        $role = $_POST['staff_role'];
        
        try {
            $student_query = $pdo->prepare("SELECT * FROM students WHERE id = ? AND hostel_id = ?");
            $student_query->execute([$student_id, $hostel_id]);
            $student = $student_query->fetch();
            
            if ($student) {
                $username = strtolower(str_replace(' ', '_', $student['name']));
                $password = 'staff' . rand(1000, 9999);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $user_stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, hostel_id) VALUES (?, ?, ?, ?, ?)");
                $user_stmt->execute([$username, $hashed_password, $student['email'], $role, $hostel_id]);
                $user_id = $pdo->lastInsertId();
                
                $staff_stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id, student_id) VALUES (?, ?, ?, ?, ?, ?)");
                $staff_stmt->execute([$student['name'], $role, $student['contact'], $hostel_id, $user_id, $student_id]);
                
                $success = "Staff member added successfully. Username: {$username}, Password: {$password}";
            }
        } catch (Exception $e) {
            $error = "Error adding staff: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['add_individual_student'])) {
        try {
            $grn = 'GRN' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $password = 'pass' . rand(1000, 9999);
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

// Handle CSV upload
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
            $students_added = 0;
            
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $header = fgetcsv($handle);
                
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 4) {
                        $grn = 'GRN' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                        $password = 'pass' . rand(1000, 9999);
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        try {
                            $stmt = $pdo->prepare("INSERT INTO students (grn, name, email, contact, course, year, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $grn,
                                $data[0] ?? 'Student',
                                $data[1] ?? 'student@hostel.com',
                                $data[2] ?? '0000000000',
                                $data[3] ?? 'General',
                                $data[4] ?? 1,
                                $hostel_id,
                                $hashed_password
                            ]);
                            $students_added++;
                        } catch (Exception $e) {
                            continue;
                        }
                    }
                }
                fclose($handle);
            }
            
            $success = "Successfully uploaded {$students_added} students";
            unlink($file_path);
        }
    }
}

// Fetch data safely
try {
    $hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
    $hostel->execute([$hostel_id]);
    $hostel_info = $hostel->fetch();
    
    $students = $pdo->prepare("SELECT s.*, COALESCE(r.room_number, 'Not Assigned') as room_number FROM students s LEFT JOIN rooms r ON s.room_id = r.id WHERE s.hostel_id = ?");
    $students->execute([$hostel_id]);
    $students_list = $students->fetchAll();
    
    $staff = $pdo->prepare("SELECT * FROM staff WHERE hostel_id = ?");
    $staff->execute([$hostel_id]);
    $staff_list = $staff->fetchAll();
} catch (Exception $e) {
    $students_list = [];
    $staff_list = [];
    $hostel_info = ['name' => 'Unknown Hostel', 'location' => 'Unknown', 'capacity' => 0];
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
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#">
                <i class="fas fa-university me-2"></i>Rector Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-white" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h2><?php echo $hostel_info['name']; ?></h2>
                        <p class="text-muted"><?php echo $hostel_info['location']; ?></p>
                        <div class="row">
                            <div class="col-md-4">
                                <h4 class="text-primary"><?php echo count($students_list); ?></h4>
                                <small>Students</small>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-success"><?php echo $hostel_info['capacity']; ?></h4>
                                <small>Capacity</small>
                            </div>
                            <div class="col-md-4">
                                <h4 class="text-warning"><?php echo count($staff_list); ?></h4>
                                <small>Staff</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Upload -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload me-2"></i>Bulk Upload Students</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <input type="file" class="form-control" name="student_file" accept=".csv" required>
                                    <small class="text-muted">CSV Format: Name, Email, Contact, Course, Year</small>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-upload me-2"></i>Upload Students
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Management -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5><i class="fas fa-users me-2"></i>Students Management</h5>
                        <div>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                                <i class="fas fa-user-plus me-1"></i>Add Staff
                            </button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                <i class="fas fa-user-plus me-1"></i>Add Student
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>GRN</th>
                                        <th>Student Details</th>
                                        <th>Academic Info</th>
                                        <th>Room Status</th>
                                        <th>Contact</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students_list as $student): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo $student['grn']; ?></span></td>
                                        <td>
                                            <div>
                                                <strong><?php echo $student['name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $student['email']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo $student['course']; ?></strong><br>
                                                <small class="text-muted">Year <?php echo $student['year']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $student['room_number'] != 'Not Assigned' ? 'success' : 'warning'; ?>">
                                                <?php echo $student['room_number']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $student['contact']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="viewCredentials('<?php echo $student['grn']; ?>', '<?php echo $student['name']; ?>')">
                                                <i class="fas fa-key"></i>
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
        </div>

        <!-- Staff Directory -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-users-cog me-2"></i>Staff Directory</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($staff_list as $staff_member): ?>
                                    <tr>
                                        <td><?php echo $staff_member['name']; ?></td>
                                        <td><span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $staff_member['role'])); ?></span></td>
                                        <td><?php echo $staff_member['contact']; ?></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                            <input type="text" class="form-control" id="staffSearch" placeholder="Type student name..." autocomplete="off">
                            <div id="searchResults" class="mt-2" style="max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <input type="hidden" name="student_id" id="selectedStudentId">
                        <div class="mb-3">
                            <label class="form-label">Staff Role</label>
                            <select class="form-control" name="staff_role" required>
                                <option value="">Select role...</option>
                                <option value="mess_head">Mess Head</option>
                                <option value="library_head">Library Head</option>
                                <option value="health_staff">Health Staff</option>
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
                    <h5 class="modal-title">Add Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
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

    <!-- View Credentials Modal -->
    <div class="modal fade" id="credentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" class="form-control" id="credName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GRN (Username)</label>
                        <input type="text" class="form-control" id="credGRN" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" class="form-control" value="password123" readonly>
                        <small class="text-muted">Default password - student can change after login</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-search for staff
        const students = <?php echo json_encode($students_list); ?>;
        
        document.getElementById('staffSearch').addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const results = document.getElementById('searchResults');
            
            if (query.length < 2) {
                results.innerHTML = '';
                return;
            }
            
            const filtered = students.filter(s => 
                s.name.toLowerCase().includes(query) || 
                s.grn.toLowerCase().includes(query)
            );
            
            results.innerHTML = filtered.map(s => 
                `<div class="p-2 border-bottom" style="cursor: pointer;" onclick="selectStudent(${s.id}, '${s.name}', '${s.grn}')">
                    <strong>${s.name}</strong> (${s.grn})<br>
                    <small class="text-muted">${s.course} - Year ${s.year}</small>
                </div>`
            ).join('');
        });
        
        function selectStudent(id, name, grn) {
            document.getElementById('selectedStudentId').value = id;
            document.getElementById('staffSearch').value = `${name} (${grn})`;
            document.getElementById('searchResults').innerHTML = '';
        }
        
        function viewCredentials(grn, name) {
            document.getElementById('credName').value = name;
            document.getElementById('credGRN').value = grn;
            new bootstrap.Modal(document.getElementById('credentialsModal')).show();
        }
    </script>
</body>
</html>