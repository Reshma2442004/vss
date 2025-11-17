<?php
session_start();
require_once '../config/database.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_POST) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();
    
    if ($user) {
        if (empty($user['role'])) {
            $error = "User role not assigned. Please contact administrator.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['hostel_id'] = $user['hostel_id'];
        
        switch($user['role']) {
            case 'super_admin':
                header('Location: ../dashboards/super_admin.php');
                break;
            case 'rector':
                header('Location: ../dashboards/rector.php');
                break;
            case 'student_head':
                header('Location: ../dashboards/student_head.php');
                break;
            case 'mess_head':
                header('Location: ../dashboards/mess_head.php');
                break;
            case 'library_head':
                header('Location: ../dashboards/library_head.php');
                break;
            case 'health_staff':
                header('Location: ../dashboards/health_staff.php');
                break;
            case 'vvk_staff':
                header('Location: ../dashboards/vvk_staff.php');
                break;
            case 'placement_staff':
                header('Location: ../dashboards/placement_staff.php');
                break;
            case 'ed_cell_staff':
                header('Location: ../dashboards/ed_cell_staff.php');
                break;
            case 'scholarship_staff':
                header('Location: ../dashboards/scholarship_staff.php');
                break;
            case 'student':
                header('Location: ../dashboards/student.php');
                break;
            default:
                $error = "Dashboard not found for role: " . $user['role'];
                break;
        }
            if (!isset($error)) {
                exit;
            }
        }
    } else {
        $error = "Invalid credentials";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>VSS Hostel Management - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 119, 198, 0.2) 0%, transparent 50%);
        }
        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.99) !important;
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.12),
                0 0 0 1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 2px solid rgba(0, 0, 0, 0.1);
        }
        .login-header {
            padding: 48px 32px 32px;
            text-align: center;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
        }
        .logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
        }
        .logo i {
            color: white;
            font-size: 28px;
        }
        .login-title {
            font-size: 28px;
            font-weight: 700;
            color: #000000 !important;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .login-subtitle {
            color: #333333 !important;
            font-size: 16px;
            font-weight: 500;
            line-height: 1.5;
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
            color: #000000 !important;
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgba(0, 0, 0, 0.3) !important;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 400;
            background: #ffffff !important;
            color: #000000 !important;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .form-input::placeholder {
            color: #9ca3af;
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
        .btn-login:active {
            transform: translateY(0);
        }
        .divider {
            position: relative;
            text-align: center;
            margin: 32px 0;
        }
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e5e7eb, transparent);
        }
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 16px;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
        }
        .register-section {
            text-align: center;
            padding: 24px 32px;
            background: rgba(248, 250, 252, 0.5);
            border-top: 1px solid rgba(229, 231, 235, 0.5);
        }
        .register-text {
            color: #333333 !important;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .register-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        .register-link:hover {
            color: #5a67d8;
        }
        .form-options {
            text-align: right;
            margin-bottom: 24px;
        }
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .forgot-link:hover {
            color: #5a67d8;
        }
        .forgot-link i {
            font-size: 12px;
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
        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 70%;
            right: 10%;
            animation-delay: 2s;
        }
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        @media (max-width: 480px) {
            body {
                padding: 16px;
            }
            .login-header {
                padding: 32px 24px 24px;
            }
            .login-body {
                padding: 0 24px 24px;
            }
            .register-section {
                padding: 20px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-home"></i>
                </div>
                <h1 class="login-title">Welcome Back</h1>
                <p class="login-subtitle">Sign in to VSS Hostel Management System</p>
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
                        <label class="form-label" for="username">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="username" name="username" class="form-input" 
                                   placeholder="Enter your email" required autocomplete="email">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" class="form-input" 
                                   placeholder="Enter your password" required autocomplete="current-password">
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <a href="forgot_password.php" class="forgot-link">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        Sign In
                    </button>
                </form>
            </div>
            
            <div class="register-section">
                <p class="register-text">Don't have an account?</p>
                <a href="register.php" class="register-link">Create account</a>
            </div>
        </div>
    </div>
</body>
</html>