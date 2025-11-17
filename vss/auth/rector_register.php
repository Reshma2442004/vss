<?php
session_start();
require_once '../config/database.php';

if ($_POST) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $name = $_POST['name'];
    $contact = $_POST['contact'];
    $hostel_id = $_POST['hostel_id'];
    
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    
    if ($check->fetch()) {
        $error = "Username already exists";
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, 'rector', ?)");
        $stmt->execute([$username, $password, $hostel_id]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, 'rector', ?, ?, ?)");
        $stmt->execute([$name, $contact, $hostel_id, $user_id]);
        
        $stmt = $pdo->prepare("UPDATE hostels SET rector_id = ? WHERE id = ?");
        $stmt->execute([$user_id, $hostel_id]);
        
        $success = "Rector registered successfully! You can now login.";
    }
}

$hostels = $pdo->query("SELECT * FROM hostels WHERE rector_id IS NULL")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Rector Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header card-header-custom">
                        <h3 class="text-center mb-0"><i class="fas fa-user-graduate me-2"></i>Rector Registration</h3>
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
                            <div class="mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" class="form-control form-control-custom" name="contact" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hostel Assignment</label>
                                <select class="form-control form-control-custom" name="hostel_id" required>
                                    <option value="">Select Hostel</option>
                                    <?php foreach($hostels as $hostel): ?>
                                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?> - <?php echo $hostel['location']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-custom w-100">Register as Rector</button>
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