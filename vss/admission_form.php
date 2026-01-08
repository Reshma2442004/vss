<?php
session_start();
require_once 'config/database.php';

if ($_POST) {
    try {
        $stmt = $pdo->prepare("INSERT INTO admission_applications (full_name, email, phone, course, year, address, parent_name, parent_phone, status, applied_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            $_POST['full_name'],
            $_POST['email'], 
            $_POST['phone'],
            $_POST['course'],
            $_POST['year'],
            $_POST['address'],
            $_POST['parent_name'],
            $_POST['parent_phone']
        ]);
        $success = "Application submitted successfully! We will contact you soon.";
    } catch (Exception $e) {
        $error = "Error submitting application. Please try again.";
    }
}

// Create table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admission_applications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        full_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        course VARCHAR(100) NOT NULL,
        year INT NOT NULL,
        address TEXT NOT NULL,
        parent_name VARCHAR(255) NOT NULL,
        parent_phone VARCHAR(20) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Admission Form - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg" style="border-radius: 15px; border: none;">
                    <div class="card-header text-center py-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px 15px 0 0;">
                        <h2 class="text-white mb-0"><i class="fas fa-university me-2"></i>Hostel Admission Form</h2>
                        <p class="text-white-50 mb-0">VSS Hostel Management System</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                        <?php endif; ?>
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-1"></i>Full Name</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-envelope me-1"></i>Email</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-phone me-1"></i>Phone</label>
                                    <input type="tel" class="form-control" name="phone" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-graduation-cap me-1"></i>Course</label>
                                    <select class="form-control" name="course" required>
                                        <option value="">Select Course</option>
                                        <option value="Computer Science">Computer Science</option>
                                        <option value="Information Technology">Information Technology</option>
                                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                                        <option value="Civil Engineering">Civil Engineering</option>
                                        <option value="Electrical Engineering">Electrical Engineering</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-calendar me-1"></i>Year</label>
                                    <select class="form-control" name="year" required>
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-map-marker-alt me-1"></i>Address</label>
                                <textarea class="form-control" name="address" rows="3" required></textarea>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-user-friends me-1"></i>Parent/Guardian Name</label>
                                    <input type="text" class="form-control" name="parent_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"><i class="fas fa-phone me-1"></i>Parent/Guardian Phone</label>
                                    <input type="tel" class="form-control" name="parent_phone" required>
                                </div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>