<?php
session_start();
require_once '../config/database.php';

// Create password reset tokens table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        used BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table already exists or other error
}

$message = '';
$error = '';

if ($_POST && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Delete any existing tokens for this email
        $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
        $stmt->execute([$email]);
        
        // Generate secure reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now
        
        // Store token in database with timezone consideration
        $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (email, token, expires_at, used) VALUES (?, ?, ?, FALSE)");
        $stmt->execute([$email, $token, $expires]);
        

        
        // Create reset link
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $reset_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
        
        // For demo purposes, show the reset link
        $message = "Password reset link generated successfully!<br><br><a href='$reset_link' class='btn btn-primary' target='_blank' style='display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 8px 0;'>Reset Password</a><br><small style='color: #6b7280;'>This link will expire in 1 hour. In production, this would be sent to your email.</small>";
    } else {
        // Always show success message for security (prevent email enumeration)
        $message = "If an account with that email exists, a password reset link has been sent.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - VSS Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .forgot-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
        }
        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .forgot-header {
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
        .forgot-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .forgot-subtitle {
            color: #6b7280;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
        }
        .forgot-body {
            padding: 0 32px 32px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 400;
            background: #ffffff;
            transition: all 0.2s ease;
            outline: none;
        }
        .form-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        .btn-reset {
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
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
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
        .success-alert {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            color: #166534;
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
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
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="forgot-header">
                <div class="logo">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="forgot-title">Forgot Password?</h1>
                <p class="forgot-subtitle">Enter your email address and we'll send you a link to reset your password</p>
            </div>
            
            <div class="forgot-body">
                <?php if($message): ?>
                    <div class="success-alert">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="error-alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your registered email" required>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        Send Reset Link
                    </button>
                </form>
            </div>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>