<?php
session_start();
require_once '../config/database.php';

if ($_POST) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    
    // Check for both admin and admin@vsshostel.edu usernames
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR username = 'admin') AND password = ? AND role = 'super_admin'");
    $stmt->execute([$username, $password]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['hostel_id'] = $user['hostel_id'];
        
        header('Location: ../dashboards/super_admin.php');
        exit;
    } else {
        $error = "Invalid admin credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4facfe">
    <title>Admin Login - VSS Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/mobile-responsive.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(-45deg, #4facfe, #00f2fe, #667eea, #4facfe);
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
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.1) 0%, rgba(0, 242, 254, 0.1) 100%);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(79, 172, 254, 0.3);
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
            border-color: #4facfe;
            box-shadow: 0 0 0 4px rgba(79, 172, 254, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(79, 172, 254, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 172, 254, 0.4);
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
            color: #4facfe;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.2s ease;
        }
        
        .back-link a:hover {
            color: #0ea5e9;
        }
        
        .security-note {
            background: rgba(79, 172, 254, 0.1);
            border: 1px solid rgba(79, 172, 254, 0.2);
            color: #1e40af;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
        
        .default-creds {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #b45309;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h1 class="login-title">Admin Login</h1>
                <p class="login-subtitle">System administration and user management</p>
            </div>
            
            <div class="login-body">
                <div class="security-note">
                    <i class="fas fa-shield-alt me-2"></i>
                    Restricted access - Administrators only
                </div>
                

                
                <?php if(isset($error)): ?>
                    <div class="error-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="username">Admin Username</label>
                        <input type="text" id="username" name="username" class="form-input" 
                               placeholder="Enter admin username" required autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Admin Password</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter admin password" required autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In as Admin
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