<?php
require_once 'config/database.php';
require_once 'includes/db_check.php';
session_start();

if ($_SESSION['role'] != 'student') {
    header('Location: auth/login.php');
    exit;
}

$message = '';
$error = '';

// Get student info
$student_query = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
$student_query->execute([$_SESSION['user_id']]);
$student = $student_query->fetch();

// Handle QR scan
if (isset($_GET['code'])) {
    $session_code = $_GET['code'];
    
    // Check if session exists and is valid
    $session_query = $pdo->prepare("SELECT * FROM qr_attendance_sessions WHERE session_code = ? AND is_active = 1 AND expires_at > NOW()");
    $session_query->execute([$session_code]);
    $session = $session_query->fetch();
    
    if (!$session) {
        $error = "Invalid or expired QR code!";
    } else {
        // Check if student already marked attendance
        $check_query = $pdo->prepare("SELECT * FROM qr_mess_attendance WHERE session_id = ? AND student_id = ?");
        $check_query->execute([$session['id'], $student['id']]);
        
        if ($check_query->fetch()) {
            $error = "You have already marked attendance for this meal!";
        } else {
            // Mark attendance
            $mark_query = $pdo->prepare("INSERT INTO qr_mess_attendance (session_id, student_id) VALUES (?, ?)");
            if ($mark_query->execute([$session['id'], $student['id']])) {
                $message = "Attendance marked successfully for " . ucfirst($session['meal_type']) . "!";
            } else {
                $error = "Failed to mark attendance. Please try again.";
            }
        }
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
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Scan QR - Mess Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/mobile-responsive.css" rel="stylesheet">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-qrcode me-2"></i>Mess Attendance Scanner</a>
            <a href="dashboards/student.php" class="btn btn-outline-light">Back to Dashboard</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if($message): ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <h5><?php echo $message; ?></h5>
                        <p class="mb-0">Welcome, <?php echo $student['name']; ?>!</p>
                    </div>
                <?php endif; ?>

                <?php if($error): ?>
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                        <h5><?php echo $error; ?></h5>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header text-center">
                        <h5><i class="fas fa-camera me-2"></i>Scan QR Code for Mess Attendance</h5>
                    </div>
                    <div class="card-body">
                        <div id="qr-reader" style="width: 100%;"></div>
                        <div class="text-center mt-3">
                            <button id="start-scan" class="btn btn-success me-2">
                                <i class="fas fa-play me-2"></i>Start Scanner
                            </button>
                            <button id="stop-scan" class="btn btn-danger" style="display: none;">
                                <i class="fas fa-stop me-2"></i>Stop Scanner
                            </button>
                        </div>
                        <div class="mt-3 text-center text-muted">
                            <small>Position the QR code within the camera frame to scan</small>
                        </div>
                    </div>
                </div>

                <!-- Manual Code Entry -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6><i class="fas fa-keyboard me-2"></i>Or Enter Code Manually</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="input-group">
                                <input type="text" class="form-control" name="code" placeholder="Enter session code" required>
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-check me-2"></i>Submit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let html5QrcodeScanner;
        let isScanning = false;

        function onScanSuccess(decodedText, decodedResult) {
            // Extract session code from URL
            const url = new URL(decodedText);
            const code = url.searchParams.get('code');
            
            if (code) {
                // Stop scanning and redirect
                html5QrcodeScanner.clear();
                window.location.href = '?code=' + code;
            }
        }

        function onScanFailure(error) {
            // Handle scan failure silently
        }

        document.getElementById('start-scan').addEventListener('click', function() {
            if (!isScanning) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader",
                    { 
                        fps: 10, 
                        qrbox: {width: 250, height: 250},
                        aspectRatio: 1.0
                    },
                    false
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                
                this.style.display = 'none';
                document.getElementById('stop-scan').style.display = 'inline-block';
                isScanning = true;
            }
        });

        document.getElementById('stop-scan').addEventListener('click', function() {
            if (isScanning && html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                this.style.display = 'none';
                document.getElementById('start-scan').style.display = 'inline-block';
                isScanning = false;
            }
        });
    </script>
</body>
</html>