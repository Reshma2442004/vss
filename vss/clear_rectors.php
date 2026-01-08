<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get all rector user IDs
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN staff s ON u.id = s.user_id WHERE s.role = 'rector'");
    $stmt->execute();
    $rector_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $deleted_count = count($rector_ids);
    
    // Delete staff records
    $pdo->exec("DELETE FROM staff WHERE role = 'rector'");
    
    // Delete user records
    if (!empty($rector_ids)) {
        $placeholders = str_repeat('?,', count($rector_ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->execute($rector_ids);
    }
    
    // Clear rector assignments from hostels
    $pdo->exec("UPDATE hostels SET rector_id = NULL");
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Deleted {$deleted_count} rector records successfully"]);
} catch (Exception $e) {
    $pdo->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>