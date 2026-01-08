<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_POST['action'] === 'change_password') {
    try {
        $user_id = $_SESSION['user_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        
        // Update password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        
        // Also update plain_password in staff table for credential tracking
        $stmt = $pdo->prepare("UPDATE staff SET plain_password = ? WHERE user_id = ?");
        $stmt->execute([$new_password, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error changing password']);
    }
}
?>