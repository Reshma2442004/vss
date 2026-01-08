<?php
session_start();
require_once '../config/database.php';

if ($_POST) {
    $grn = $_POST['grn'];
    $password = $_POST['password'];
    
    // First check student credentials
    $stmt = $pdo->prepare("SELECT * FROM students WHERE grn = ?");
    $stmt->execute([$grn]);
    $student = $stmt->fetch();
    
    if ($student && password_verify($password, $student['password'])) {
        // Student login
        $_SESSION['user_id'] = $student['id'];
        $_SESSION['student_id'] = $student['id'];
        $_SESSION['username'] = $student['email'];
        $_SESSION['role'] = 'student';
        $_SESSION['hostel_id'] = $student['hostel_id'];
        $_SESSION['student_name'] = $student['name'];
        $_SESSION['grn'] = $student['grn'];
        
        header('Location: ../dashboards/student_dashboard.php');
        exit;
    }
    
    // If student login fails, check staff credentials with same GRN
    if ($student) {
        $staff_stmt = $pdo->prepare("SELECT s.*, u.password as staff_password, u.id as user_id FROM staff s JOIN users u ON s.user_id = u.id WHERE s.student_id = ?");
        $staff_stmt->execute([$student['id']]);
        $staff = $staff_stmt->fetch();
        
        if ($staff && password_verify($password, $staff['staff_password'])) {
            // Staff login with student GRN
            $_SESSION['user_id'] = $staff['user_id'];
            $_SESSION['username'] = $student['email'];
            $_SESSION['role'] = $staff['role'];
            $_SESSION['hostel_id'] = $staff['hostel_id'];
            $_SESSION['staff_name'] = $staff['name'];
            $_SESSION['grn'] = $student['grn'];
            
            // Redirect based on staff role
            if ($staff['role'] === 'rector') {
                header('Location: ../dashboards/rector.php');
            } elseif ($staff['role'] === 'mess_head') {
                header('Location: ../dashboards/mess_head.php');
            } elseif ($staff['role'] === 'vvk_staff') {
                header('Location: ../dashboards/vvk_staff.php');
            } else {
                header('Location: ../dashboards/' . $staff['role'] . '_dashboard.php');
            }
            exit;
        }
    }
    
    $error = "Invalid GRN or password";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#667eea">
    <title>Student Login - VSS Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/mobile-responsive.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #667eea);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 32px 64px rgba(0, 0, 0, 0.12);
            border: 2px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }
        
        .login-header {
            padding: 48px 32px 32px;
            text-align: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }
        
        .logo i {
            color: white;
            font-size: 32px;
        }
        
        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            color: #4a5568;
            font-size: 16px;
            font-weight: 500;
        }
        
        .login-body {
            padding: 0 32px 32px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 16px;
            background: white;
            color: #2d3748;
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
        }
        
        .error-alert {
            background: linear-gradient(135deg, #fef2f2 0%, #fde8e8 100%);
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link {
            text-align: center;
            padding: 24px 32px;
            background: rgba(248, 250, 252, 0.5);
            border-top: 1px solid rgba(229, 231, 235, 0.5);
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        
        .back-link a:hover {
            color: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h1 class="login-title">Student Login</h1>
                <p class="login-subtitle">Enter your GRN and password to access your dashboard</p>
            </div>
            
            <div class="login-body">
                <?php if(isset($error)): ?>
                    <div class="error-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="grn">GRN (Student ID)</label>
                        <input type="text" id="grn" name="grn" class="form-input" 
                               placeholder="Enter your GRN" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter your password" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>
            </div>
            
            <div class="back-link">
                <a href="../index.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="../assets/mobile-interactions.js"></script>
</body>
</html>