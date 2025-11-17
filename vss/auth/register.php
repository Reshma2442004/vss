<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $hostel_id = $_POST['hostel_id'] ?? null;
    
    // Validation
    $errors = [];
    
    // Email validation
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    // Password validation
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Phone validation (if provided)
    if (isset($_POST['contact']) && !empty($_POST['contact'])) {
        if (!preg_match('/^[0-9]{10}$/', $_POST['contact'])) {
            $errors[] = "Contact number must be 10 digits";
        }
    }
    
    // Check if email exists
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->fetch()) {
        $errors[] = "Email already registered";
    }
    
    // Check GRN uniqueness for students
    if ($role == 'student' && !empty($_POST['grn'])) {
        $check_grn = $pdo->prepare("SELECT id FROM students WHERE grn = ?");
        $check_grn->execute([$_POST['grn']]);
        
        if ($check_grn->fetch()) {
            $errors[] = "GRN already exists";
        }
    }
    
    if (empty($errors)) {
        // Insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, md5($password), $role, $hostel_id]);
        $user_id = $pdo->lastInsertId();
        
        // If student, create student record
        if ($role == 'student') {
            $course = $_POST['course'] == 'other' ? $_POST['custom_course'] : $_POST['course'];
            $stmt = $pdo->prepare("INSERT INTO students (grn, name, course, year, hostel_id, user_id, email, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['grn'], $_POST['name'], $course, $_POST['year'], $hostel_id, $user_id, $username, $_POST['contact'] ?? null]);
        }
        
        // Handle all staff roles
        if ($role == 'staff') {
            $staff_role = $_POST['staff_role'];
            // Update user role to specific staff role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$staff_role, $user_id]);
            
            $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $staff_role, $_POST['contact'], $hostel_id, $user_id]);
        }
        
        // Handle rector and student_head
        if ($role == 'rector' || $role == 'student_head') {
            $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $role, $_POST['contact'], $hostel_id, $user_id]);
        }
        
        // Handle other staff roles directly selected
        $direct_staff_roles = ['mess_head', 'library_head', 'health_staff', 'vvk_staff', 'placement_staff', 'ed_cell_staff', 'scholarship_staff'];
        if (in_array($role, $direct_staff_roles)) {
            $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['name'], $role, $_POST['contact'] ?? null, $hostel_id, $user_id]);
        }
        
        // Super admin doesn't need additional records
        if ($role == 'super_admin') {
            // No additional records needed for super admin
        }
        
        $success = "Registration successful! You can now login.";
    } else {
        $error = implode('<br>', $errors);
    }
}

// Fetch hostels for dropdown
$hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - VSS Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        .register-container {
            max-width: 600px;
            margin: 0 auto;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.99) !important;
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(0, 0, 0, 0.1);
        }
        .register-header {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        .register-body {
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <div class="logo" style="width: 64px; height: 64px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);">
                    <i class="fas fa-user-plus" style="color: white; font-size: 28px;"></i>
                </div>
                <h1 style="font-size: 28px; font-weight: 700; color: #000000 !important; margin-bottom: 8px;">Create Account</h1>
                <p style="color: #333333 !important; font-size: 16px;">Join VSS Hostel Management System</p>
            </div>
            <div class="register-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                <form method="POST" id="registerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-input" name="username" placeholder="Enter your email" required>
                                <small style="color: #666666 !important; font-size: 0.75rem;">This will be your login username</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-input" name="password" id="password" placeholder="Create password" minlength="6" required>
                                <small style="color: #666666 !important; font-size: 0.75rem;">Minimum 6 characters</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-input" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
                                <div id="password-match" style="color: #dc2626 !important; font-size: 0.75rem; display:none;">Passwords do not match</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-input" name="name" placeholder="Enter full name" pattern="[A-Za-z\s]+" required>
                                <small style="color: #666666 !important; font-size: 0.75rem;">Letters and spaces only</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" id="role" required onchange="toggleFields()">
                                    <option value="">Select Role</option>
                                    <option value="super_admin">Super Admin</option>
                                    <option value="rector">Rector</option>
                                    <option value="student_head">Student Head</option>
                                    <option value="staff">Staff</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Hostel</label>
                                <select class="form-select" name="hostel_id" required>
                                    <option value="">Select Hostel</option>
                                    <?php foreach($hostels as $hostel): ?>
                                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                            

                            
                            <div id="studentFields" style="display:none;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">GRN (Student ID)</label>
                                            <input type="text" class="form-control" name="grn">
                                            <small class="text-muted">Unique student registration number</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Course</label>
                                            <select class="form-control" name="course" id="course_select" onchange="toggleCustomCourse()">
                                                <option value="">Select Course</option>
                                                <option value="Computer Science Engineering">Computer Science Engineering</option>
                                                <option value="Information Technology">Information Technology</option>
                                                <option value="Electronics Engineering">Electronics Engineering</option>
                                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                                <option value="Civil Engineering">Civil Engineering</option>
                                                <option value="Electrical Engineering">Electrical Engineering</option>
                                                <option value="Chemical Engineering">Chemical Engineering</option>
                                                <option value="other">Other (Enter Custom Course)</option>
                                            </select>
                                            <input type="text" class="form-control mt-2" name="custom_course" id="custom_course" placeholder="Enter your course name" style="display:none;">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Year</label>
                                            <select class="form-control" name="year">
                                                <option value="">Select Year</option>
                                                <option value="1">1st Year</option>
                                                <option value="2">2nd Year</option>
                                                <option value="3">3rd Year</option>
                                                <option value="4">4th Year</option>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            
                            <div id="staffFields" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">Staff Role</label>
                                    <select class="form-control" name="staff_role" id="staff_role">
                                        <option value="">Select Staff Role</option>
                                        <option value="mess_head">Mess Head</option>
                                        <option value="library_head">Library Head</option>
                                        <option value="health_staff">Health Staff</option>
                                        <option value="vvk_staff">VVK Staff</option>
                                        <option value="placement_staff">Placement Staff</option>
                                        <option value="ed_cell_staff">ED Cell Staff</option>
                                        <option value="scholarship_staff">Scholarship Staff</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="tel" class="form-control" name="contact" pattern="[0-9]{10}" maxlength="10">
                                    <small class="text-muted">10 digit mobile number</small>
                                </div>
                            </div>
                    
                    <button type="submit" class="btn btn-primary w-100" style="margin-top: 1rem;">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                </form>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                    <p style="color: #333333 !important; margin-bottom: 0.5rem;">Already have an account?</p>
                    <a href="login.php" style="color: #667eea !important; text-decoration: none; font-weight: 600;">
                        Sign in here
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const matchDiv = document.getElementById('password-match');
            
            if (password !== confirmPassword && confirmPassword !== '') {
                matchDiv.style.display = 'block';
                this.setCustomValidity('Passwords do not match');
            } else {
                matchDiv.style.display = 'none';
                this.setCustomValidity('');
            }
        });
        
        // Form validation before submit
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters!');
                return false;
            }
        });
        
        function toggleCustomCourse() {
            const courseSelect = document.getElementById('course_select');
            const customCourse = document.getElementById('custom_course');
            
            if (courseSelect.value === 'other') {
                customCourse.style.display = 'block';
                customCourse.required = true;
                courseSelect.required = false;
            } else {
                customCourse.style.display = 'none';
                customCourse.required = false;
                courseSelect.required = true;
            }
        }
        
        function toggleFields() {
            const role = document.getElementById('role').value;
            const studentFields = document.getElementById('studentFields');
            const staffFields = document.getElementById('staffFields');
            
            if (role === 'student') {
                studentFields.style.display = 'block';
                staffFields.style.display = 'none';
                document.querySelector('input[name="grn"]').required = true;
                document.querySelector('select[name="course"]').required = true;
                document.querySelector('select[name="year"]').required = true;
            } else if (role === 'staff') {
                studentFields.style.display = 'none';
                staffFields.style.display = 'block';
                document.querySelector('select[name="staff_role"]').required = true;
                document.querySelector('input[name="contact"]').required = true;
            } else if (role === 'rector' || role === 'student_head') {
                studentFields.style.display = 'none';
                staffFields.style.display = 'block';
                document.querySelector('select[name="staff_role"]').required = false;
                document.querySelector('input[name="contact"]').required = true;
            } else if (role === 'super_admin') {
                studentFields.style.display = 'none';
                staffFields.style.display = 'none';
            } else {
                studentFields.style.display = 'none';
                staffFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>