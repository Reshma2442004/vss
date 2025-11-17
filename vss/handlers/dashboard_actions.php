<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'submit_feedback':
                $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                $student_query->execute([$_SESSION['user_id']]);
                $student = $student_query->fetch();
                
                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student record not found']);
                    break;
                }
                
                $feedback_category = $_POST['feedback_category'];
                $table_name = $feedback_category === 'mess' ? 'mess_feedback' : 'general_feedback';
                
                if ($feedback_category === 'mess') {
                    $stmt = $pdo->prepare("INSERT INTO mess_feedback (student_id, feedback_type, subject, category, message, rating, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $student['id'],
                        $_POST['feedback_type'],
                        $_POST['subject'],
                        $_POST['category'],
                        $_POST['message'],
                        $_POST['rating'],
                        $_POST['priority']
                    ]);
                } else {
                    // Create general_feedback table if not exists
                    $pdo->exec("CREATE TABLE IF NOT EXISTS general_feedback (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        student_id INT NOT NULL,
                        feedback_category ENUM('library', 'event', 'staff') NOT NULL,
                        feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        rating INT CHECK (rating >= 1 AND rating <= 5),
                        priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (student_id) REFERENCES students(id)
                    )");
                    
                    $stmt = $pdo->prepare("INSERT INTO general_feedback (student_id, feedback_category, feedback_type, subject, message, rating, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $student['id'],
                        $feedback_category,
                        $_POST['feedback_type'],
                        $_POST['subject'],
                        $_POST['message'],
                        $_POST['rating'],
                        $_POST['priority']
                    ]);
                }
                
                $feedback_id = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => ucfirst($feedback_category) . ' feedback submitted successfully!', 'feedback_id' => $feedback_id]);
                break;
                
            case 'delete_feedback':
                $feedback_id = $_POST['feedback_id'];
                
                // Check if user is staff or student
                if ($_SESSION['role'] === 'student') {
                    $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                    $student_query->execute([$_SESSION['user_id']]);
                    $student = $student_query->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM mess_feedback WHERE id = ? AND student_id = ?");
                    $stmt->execute([$feedback_id, $student['id']]);
                } else {
                    // Staff can delete any feedback
                    $stmt = $pdo->prepare("DELETE FROM mess_feedback WHERE id = ?");
                    $stmt->execute([$feedback_id]);
                }
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Feedback not found or access denied']);
                }
                break;
                
            case 'update_general_feedback_status':
                $feedback_id = $_POST['feedback_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE general_feedback SET status = ? WHERE id = ?");
                $stmt->execute([$status, $feedback_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Feedback status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Feedback not found']);
                }
                break;
                
            case 'delete_general_feedback':
                $feedback_id = $_POST['feedback_id'];
                
                // Check if user is staff (library_head, etc.) or student
                if ($_SESSION['role'] === 'student') {
                    $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                    $student_query->execute([$_SESSION['user_id']]);
                    $student = $student_query->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM general_feedback WHERE id = ? AND student_id = ?");
                    $stmt->execute([$feedback_id, $student['id']]);
                } else {
                    // Staff can delete any feedback
                    $stmt = $pdo->prepare("DELETE FROM general_feedback WHERE id = ?");
                    $stmt->execute([$feedback_id]);
                }
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Feedback not found or access denied']);
                }
                break;
            case 'update_feedback_status':
                $feedback_id = $_POST['feedback_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE mess_feedback SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $_SESSION['user_id'], $feedback_id]);
                
                echo json_encode(['success' => true, 'message' => 'Feedback status updated successfully']);
                break;
                
            case 'resolve_complaint':
                $complaint_id = $_POST['complaint_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE student_complaints SET status = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $_SESSION['user_id'], $complaint_id]);
                
                echo json_encode(['success' => true, 'message' => 'Complaint status updated successfully']);
                break;
                
            case 'approve_event':
                $event_id = $_POST['event_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE events SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $_SESSION['user_id'], $event_id]);
                
                echo json_encode(['success' => true, 'message' => 'Event status updated successfully']);
                break;
                
            case 'update_scholarship_status':
                $scholarship_id = $_POST['scholarship_id'];
                $status = $_POST['status'];
                
                $stmt = $pdo->prepare("UPDATE scholarships SET status = ?, approved_by = ?, approved_date = NOW() WHERE id = ?");
                $stmt->execute([$status, $_SESSION['user_id'], $scholarship_id]);
                
                echo json_encode(['success' => true, 'message' => 'Scholarship status updated successfully']);
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