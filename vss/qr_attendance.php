<?php
require_once 'config/database.php';
require_once 'includes/db_check.php';
session_start();

if ($_SESSION['role'] != 'rector') {
    header('Location: auth/login.php');
    exit;
}

// Create tables if not exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_code VARCHAR(50) UNIQUE NOT NULL,
        meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL,
        date DATE NOT NULL,
        hostel_id INT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_mess_attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (session_id, student_id)
    )");
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Handle QR generation
if ($_POST && isset($_POST['generate_qr'])) {
    $meal_type = $_POST['meal_type'];
    $duration = (int)$_POST['duration'];
    $hostel_id = $_SESSION['hostel_id'];
    
    $session_code = 'MESS_' . date('Ymd_His') . '_' . strtoupper($meal_type);
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));
    
    $stmt = $pdo->prepare("INSERT INTO qr_attendance_sessions (session_code, meal_type, date, hostel_id, created_by, expires_at) VALUES (?, ?, CURDATE(), ?, ?, ?)");
    $stmt->execute([$session_code, $meal_type, $hostel_id, $_SESSION['user_id'], $expires_at]);
    
    $success = "QR Code generated successfully!";
    $show_qr = $session_code; // Auto-show the generated QR
}

// Get active sessions
$sessions = $pdo->prepare("SELECT * FROM qr_attendance_sessions WHERE hostel_id = ? AND is_active = 1 AND expires_at > NOW() ORDER BY created_at DESC");
$sessions->execute([$_SESSION['hostel_id']]);
$active_sessions = $sessions->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>QR Mess Attendance - Rector</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/mobile-responsive.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-qrcode me-2"></i>QR Mess Attendance</a>
            <a href="dashboards/rector.php" class="btn btn-outline-light">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle me-2"></i>Generate QR Code</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Meal Type</label>
                                <select class="form-control" name="meal_type" required>
                                    <option value="">Select Meal</option>
                                    <option value="breakfast">Breakfast</option>
                                    <option value="lunch">Lunch</option>
                                    <option value="dinner">Dinner</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duration (minutes)</label>
                                <select class="form-control" name="duration" required>
                                    <option value="30">30 minutes</option>
                                    <option value="60" selected>1 hour</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>
                            <button type="submit" name="generate_qr" class="btn btn-primary">
                                <i class="fas fa-qrcode me-2"></i>Generate QR Code
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Active Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if(empty($active_sessions)): ?>
                            <p class="text-muted">No active sessions</p>
                        <?php else: ?>
                            <?php foreach($active_sessions as $session): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6><?php echo ucfirst($session['meal_type']); ?></h6>
                                            <small class="text-muted">Expires: <?php echo date('H:i', strtotime($session['expires_at'])); ?></small>
                                        </div>
                                        <button class="btn btn-sm btn-success" onclick="showQR('<?php echo $session['session_code']; ?>')">
                                            <i class="fas fa-qrcode"></i> Show QR
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Display Modal -->
        <div class="modal fade" id="qrModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">QR Code for Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center">
                        <div id="qrcode"></div>
                        <p class="mt-3 text-muted">Students scan this QR code to mark attendance</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showQR(sessionCode) {
            const qrDiv = document.getElementById('qrcode');
            qrDiv.innerHTML = '';
            
            const attendanceUrl = window.location.origin + window.location.pathname.replace('qr_attendance.php', 'scan_attendance.php') + '?code=' + sessionCode;
            
            // Create QR code using Google Charts API as fallback
            const qrImg = document.createElement('img');
            qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(attendanceUrl);
            qrImg.style.width = '300px';
            qrImg.style.height = '300px';
            qrDiv.appendChild(qrImg);
            
            // Add URL text below QR code
            const urlText = document.createElement('p');
            urlText.textContent = 'URL: ' + attendanceUrl;
            urlText.style.fontSize = '12px';
            urlText.style.wordBreak = 'break-all';
            qrDiv.appendChild(urlText);
            
            new bootstrap.Modal(document.getElementById('qrModal')).show();
        }
        
        <?php if(isset($show_qr)): ?>
        // Auto-show QR code after generation
        window.addEventListener('load', function() {
            setTimeout(function() {
                showQR('<?php echo $show_qr; ?>');
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>