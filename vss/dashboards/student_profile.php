<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: ../auth/student_login.php');
    exit;
}

$student_id = $_SESSION['student_id'];

// Fix database structure first
try {
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS email VARCHAR(255) DEFAULT 'student@hostel.com'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS contact VARCHAR(20) DEFAULT '0000000000'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS course VARCHAR(100) DEFAULT 'General'");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS year INT DEFAULT 1");
    $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) {}

// Get student details
$stmt = $pdo->prepare("SELECT s.*, COALESCE(h.name, 'Unknown Hostel') as hostel_name, COALESCE(h.location, 'Unknown') as location, COALESCE(r.room_number, 'Not Assigned') as room_number FROM students s 
                       LEFT JOIN hostels h ON s.hostel_id = h.id 
                       LEFT JOIN rooms r ON s.room_id = r.id 
                       WHERE s.id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found");
}

// Handle password change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Ensure password column exists
    try {
        $pdo->exec("ALTER TABLE students ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL");
    } catch (Exception $e) {}
    
    if (isset($student['password']) && password_verify($current_password, $student['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $student_id]);
            $success = "Password changed successfully";
        } else {
            $error = "New passwords do not match";
        }
    } else {
        $error = "Current password is incorrect";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#">
                <i class="fas fa-user-graduate me-2"></i>Student Profile
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

        <div class="row">
            <div class="col-md-8">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">GRN</label>
                                    <p class="form-control-plaintext"><?php echo $student['grn']; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Full Name</label>
                                    <p class="form-control-plaintext"><?php echo $student['name']; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Email</label>
                                    <p class="form-control-plaintext"><?php echo $student['email']; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Contact</label>
                                    <p class="form-control-plaintext"><?php echo $student['contact']; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Course</label>
                                    <p class="form-control-plaintext"><?php echo $student['course']; ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Year</label>
                                    <p class="form-control-plaintext"><?php echo $student['year']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-home me-2"></i>Hostel Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hostel</label>
                            <p class="form-control-plaintext"><?php echo $student['hostel_name']; ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <p class="form-control-plaintext"><?php echo $student['location']; ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Room</label>
                            <p class="form-control-plaintext">
                                <?php echo $student['room_number'] ? 'Room ' . $student['room_number'] : 'Not Assigned'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="modern-card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-key me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>
                            <button type="submit" name="change_password" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>