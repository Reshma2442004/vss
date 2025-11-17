<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SESSION['role'] != 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_POST && isset($_POST['reminder_id'])) {
    $reminder_id = $_POST['reminder_id'];
    
    // Get student ID from session
    $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $student_query->execute([$_SESSION['user_id']]);
    $student = $student_query->fetch();
    
    if ($student) {
        // Mark reminder as read (only if it belongs to this student)
        $stmt = $pdo->prepare("UPDATE library_reminders SET is_read = TRUE WHERE id = ? AND student_id = ?");
        $result = $stmt->execute([$reminder_id, $student['id']]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update reminder']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>