<?php
session_start();
require_once '../config/database.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$message = '';
$error = '';
$valid_token = false;
$reset_token = null;

// Verify token
if ($token && strlen($token) === 64) { // Ensure token is correct length
    try {
        // Check if token exists
        $stmt = $pdo->prepare("SELECT * FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $token_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($token_info) {
            // Check if token is valid (not used and not expired) using PHP time comparison
            if ($token_info['used'] == 0 && strtotime($token_info['expires_at']) > time()) {
                $reset_token = $token_info;
            } else {
                $reset_token = false;
            }
        } else {
            $reset_token = false;
        }
        
        if ($reset_token) {
            $valid_token = true;
            
            // Handle password reset
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                $new_password = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);
                
                // Validate passwords
                if (empty($new_password) || empty($confirm_password)) {
                    $error = "Please fill in all password fields.";
                } elseif ($new_password !== $confirm_password) {
                    $error = "Passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error = "Password must be at least 6 characters long.";
                } else {
                    try {
                        // Begin transaction
                        $pdo->beginTransaction();
                        
                        // Update password
                        $hashed_password = md5($new_password);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
                        $result = $stmt->execute([$hashed_password, $reset_token['email']]);
                        
                        if ($result && $stmt->rowCount() > 0) {
                            // Mark token as used
                            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE token = ?");
                            $stmt->execute([$token]);
                            
                            // Commit transaction
                            $pdo->commit();
                            
                            $message = "Password reset successfully! You can now <a href='login.php' style='color: #667eea; font-weight: 600;'>login with your new password</a>.";
                            $valid_token = false; // Hide form after successful reset
                        } else {
                            $pdo->rollBack();
                            $error = "Failed to update password. Please try again.";
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = "An error occurred while resetting your password. Please try again.";
                    }
                }
            }
        } else {
            // Provide more specific error message
            if (isset($token_info) && $token_info) {
                if ($token_info['used'] == 1) {
                    $error = "This password reset link has already been used. Please request a new one if you need to reset your password again.";
                } elseif (strtotime($token_info['expires_at']) <= time()) {
                    $minutes_ago = round((time() - strtotime($token_info['expires_at'])) / 60);
                    $error = "This password reset link expired " . ($minutes_ago > 0 ? $minutes_ago . " minutes ago" : "recently") . ". Please request a new one.";
                } else {
                    $error = "This password reset link is invalid. Please request a new one.";
                }
            } else {
                $error = "This password reset link is invalid or has expired. Please request a new one.";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred while validating the reset token. Please try again.";
    }
} else {
    $error = "Invalid reset token format. Please use the link from your email.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - VSS Hostel Management</title>
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
        .reset-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
        }
        .reset-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .reset-header {
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
        .reset-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }
        .reset-subtitle {
            color: #6b7280;
            font-size: 16px;
            font-weight: 400;
            line-height: 1.5;
        }
        .reset-body {
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
        .password-strength {
            font-size: 12px;
            margin-top: 4px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="logo">
                    <i class="fas fa-lock"></i>
                </div>
                <h1 class="reset-title">Reset Password</h1>
                <p class="reset-subtitle">Enter your new password below</p>
            </div>
            
            <div class="reset-body">
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
                
                <?php if(!$valid_token && !$message && $token): ?>
                    <div class="error-alert">
                        <i class="fas fa-info-circle"></i>
                        This reset link appears to be invalid or expired. <a href="forgot_password.php" style="color: #dc2626; font-weight: 600; text-decoration: underline;">Request a new password reset link</a>.
                        <?php if(isset($token_info) && $token_info): ?>
                            <br><small style="font-size: 11px; opacity: 0.8; margin-top: 8px; display: block;">
                                Debug: Token expires at <?php echo date('Y-m-d H:i:s', strtotime($token_info['expires_at'])); ?>, 
                                Current time: <?php echo date('Y-m-d H:i:s'); ?>, 
                                Used: <?php echo $token_info['used'] ? 'Yes' : 'No'; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($valid_token): ?>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="Enter new password" required minlength="6">
                        <div class="password-strength">Password must be at least 6 characters long</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Confirm new password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        Reset Password
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="back-link">
                <a href="login.php">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Real-time password validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        
        function validatePasswords() {
            const password = passwordInput?.value || '';
            const confirmPassword = confirmPasswordInput?.value || '';
            
            // Password strength validation
            if (passwordInput) {
                if (password.length < 6) {
                    passwordInput.setCustomValidity('Password must be at least 6 characters long');
                } else {
                    passwordInput.setCustomValidity('');
                }
            }
            
            // Password confirmation validation
            if (confirmPasswordInput) {
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            }
        }
        
        passwordInput?.addEventListener('input', validatePasswords);
        confirmPasswordInput?.addEventListener('input', validatePasswords);
        
        // Form submission validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const password = passwordInput?.value || '';
            const confirmPassword = confirmPasswordInput?.value || '';
            
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
                return false;
            }
        });
    </script>
</body>
</html>