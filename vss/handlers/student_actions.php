<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'update_student_profile':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                $contact = trim($_POST['contact']);
                $course = trim($_POST['course']);
                $year = intval($_POST['year']);
                
                if (empty($name) || empty($email)) {
                    throw new Exception('Name and email are required');
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address');
                }
                
                // Get student ID
                $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                $student_query->execute([$_SESSION['user_id']]);
                $student = $student_query->fetch();
                
                if (!$student) {
                    throw new Exception('Student record not found');
                }
                
                // Update student profile
                $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, contact = ?, course = ?, year = ? WHERE id = ?");
                $stmt->execute([$name, $email, $contact, $course, $year, $student['id']]);
                
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}
?>