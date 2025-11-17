<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_POST) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $grn = $_POST['grn'];
    $name = $_POST['name'];
    $course = $_POST['course'];
    $year = $_POST['year'];
    $hostel_id = $_POST['hostel_id'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    
    $check_grn = $pdo->prepare("SELECT id FROM students WHERE grn = ?");
    $check_grn->execute([$grn]);
    
    if ($check->fetch()) {
        $error = "Username already exists";
    } elseif ($check_grn->fetch()) {
        $error = "GRN already exists";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, 'student', ?)");
        $stmt->execute([$username, $password, $hostel_id]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO students (grn, name, course, year, hostel_id, user_id, email, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$grn, $name, $course, $year, $hostel_id, $user_id, $email, $contact]);
        
        $success = "Student registered successfully! You can now login.";
    }
}

$hostels = $pdo->query("SELECT * FROM hostels")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-8">
                <div class="card dashboard-card">
                    <div class="card-header card-header-custom">
                        <h3 class="text-center mb-0"><i class="fas fa-user-plus me-2"></i>Student Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">GRN (Student ID)</label>
                                        <input type="text" class="form-control form-control-custom" name="grn" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control form-control-custom" name="name" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control form-control-custom" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control form-control-custom" name="password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Course</label>
                                        <select class="form-control form-control-custom" name="course" required>
                                            <option value="">Select Course</option>
                                            <option value="Computer Science Engineering">Computer Science Engineering</option>
                                            <option value="Information Technology">Information Technology</option>
                                            <option value="Electronics Engineering">Electronics Engineering</option>
                                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                                            <option value="Civil Engineering">Civil Engineering</option>
                                            <option value="Electrical Engineering">Electrical Engineering</option>
                                            <option value="Chemical Engineering">Chemical Engineering</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Year</label>
                                        <select class="form-control form-control-custom" name="year" required>
                                            <option value="">Select Year</option>
                                            <option value="1">1st Year</option>
                                            <option value="2">2nd Year</option>
                                            <option value="3">3rd Year</option>
                                            <option value="4">4th Year</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control form-control-custom" name="email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" class="form-control form-control-custom" name="contact" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Hostel Preference</label>
                                <select class="form-control form-control-custom" name="hostel_id" required>
                                    <option value="">Select Hostel</option>
                                    <?php foreach($hostels as $hostel): ?>
                                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?> - <?php echo $hostel['location']; ?> (Capacity: <?php echo $hostel['capacity']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-custom w-100">Register as Student</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="login.php">Already have an account? Login here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>