<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['grn'])) {
    $grn = $_POST['grn'];
    $hostel_id = $_SESSION['hostel_id'];
    $generate_new = isset($_POST['generate_new']) && $_POST['generate_new'] === '1';
    
    try {
        // Ensure plain_password column exists
        try {
            $pdo->exec("ALTER TABLE students ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL");
        } catch (Exception $e) {}
        
        $stmt = $pdo->prepare("SELECT id, plain_password FROM students WHERE grn = ? AND hostel_id = ?");
        $stmt->execute([$grn, $hostel_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Auto-generate password if not exists or if explicitly requested
            if ($generate_new || empty($student['plain_password'])) {
                // Generate unique password using GRN
                $temp_password = 'VSS' . substr($grn, -4) . rand(10, 99);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $update_stmt = $pdo->prepare("UPDATE students SET password = ?, plain_password = ? WHERE grn = ? AND hostel_id = ?");
                $update_stmt->execute([$hashed_password, $temp_password, $grn, $hostel_id]);
                
                echo json_encode(['success' => true, 'password' => $temp_password, 'is_new' => true]);
            } else {
                echo json_encode(['success' => true, 'password' => $student['plain_password'], 'is_new' => false]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'GRN not provided']);
}
?>