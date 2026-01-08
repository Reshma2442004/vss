<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (isset($_POST['staff_id'])) {
    $staff_id = $_POST['staff_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, st.grn
            FROM staff s 
            JOIN students st ON s.student_id = st.id 
            WHERE s.id = ?
        ");
        $stmt->execute([$staff_id]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            $password = $staff['plain_password'];
            
            if (!$password) {
                $password = 'staff' . rand(1000, 9999);
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                
                $pdo->prepare("UPDATE staff SET plain_password = ? WHERE id = ?")->execute([$password, $staff_id]);
                if ($staff['user_id']) {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $staff['user_id']]);
                }
            }
            
            echo json_encode(['success' => true, 'grn' => $staff['grn'], 'password' => $password]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Staff not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Staff ID not provided']);
}
?>