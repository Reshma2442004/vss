<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get user role for display
$role_display = [
    'super_admin' => 'Super Administrator',
    'rector' => 'Rector',
    'mess_head' => 'Mess Head',
    'library_head' => 'Library Head',
    'health_staff' => 'Health Staff',
    'vvk_staff' => 'VVK Staff',
    'placement_staff' => 'Placement Staff',
    'ed_cell_staff' => 'ED Cell Staff',
    'scholarship_staff' => 'Scholarship Staff',
    'student' => 'Student'
];

$current_role = $role_display[$_SESSION['role']] ?? $_SESSION['role'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="#667eea">
    <link rel="manifest" href="../manifest.json">
    <link rel="apple-touch-icon" href="../assets/icons/icon-192x192.png">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - VSS Hostel Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
    <link href="../assets/mobile-responsive.css" rel="stylesheet">
</head>
<body>
    <script src="../assets/mobile-interactions.js"></script>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title"><?php echo $dashboard_title ?? 'Dashboard'; ?></h1>
            <p class="dashboard-subtitle"><?php echo $dashboard_subtitle ?? $current_role . ' Portal'; ?></p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-sm text-secondary">Welcome, <?php echo $_SESSION['username']; ?></span>
            <a href="../auth/login.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="dashboard-main">