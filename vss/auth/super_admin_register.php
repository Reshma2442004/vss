<?php
session_start();
require_once '../config/database.php';

if ($_POST) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $name = $_POST['name'];
    
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->fetch()) {
        $error = "Username already exists";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'super_admin')");
        $stmt->execute([$username, $password]);
        $success = "Super Admin registered successfully! You can now login.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Super Admin Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header card-header-custom">
                        <h3 class="text-center mb-0"><i class="fas fa-crown me-2"></i>Super Admin Registration</h3>
                    </div>
                    <div class="card-body">
                        <?php if(isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if(isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control form-control-custom" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control form-control-custom" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control form-control-custom" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-custom w-100">Register as Super Admin</button>
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