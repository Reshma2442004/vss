<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard if already logged in
    switch($_SESSION['role']) {
        case 'super_admin':
            header('Location: dashboards/super_admin.php');
            break;
        case 'rector':
            header('Location: dashboards/rector.php');
            break;
        case 'mess_head':
            header('Location: dashboards/mess_head.php');
            break;
        case 'library_head':
            header('Location: dashboards/library_head.php');
            break;
        case 'health_staff':
            header('Location: dashboards/health_staff.php');
            break;
        case 'vvk_staff':
            header('Location: dashboards/vvk_staff.php');
            break;
        case 'placement_staff':
            header('Location: dashboards/placement_staff.php');
            break;
        case 'ed_cell_staff':
            header('Location: dashboards/ed_cell_staff.php');
            break;
        case 'scholarship_staff':
            header('Location: dashboards/scholarship_staff.php');
            break;
        case 'student':
            header('Location: dashboards/student.php');
            break;
        default:
            header('Location: auth/login.php');
    }
} else {
    header('Location: auth/login.php');
}
exit;
?>