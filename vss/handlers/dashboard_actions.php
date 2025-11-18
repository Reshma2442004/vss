<?php
session_start();
require_once '../config/database.php';
require_once '../includes/NotificationTriggers.php';

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
                
                // Send email notification
                if ($feedback_category === 'mess') {
                    NotificationTriggers::onMessFeedbackSubmitted($feedback_id);
                }
                
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
                
                // Send email notification
                NotificationTriggers::onScholarshipStatusUpdated($scholarship_id);
                
                echo json_encode(['success' => true, 'message' => 'Scholarship status updated successfully']);
                break;
                
            case 'submit_leave':
                $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                $student_query->execute([$_SESSION['user_id']]);
                $student = $student_query->fetch();
                
                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student record not found']);
                    break;
                }
                
                // Create table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS leave_applications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    leave_type ENUM('sick', 'emergency', 'personal', 'home', 'other') NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at TIMESTAMP NULL,
                    reviewed_by INT NULL,
                    rector_comments TEXT NULL,
                    FOREIGN KEY (student_id) REFERENCES students(id)
                )");
                
                $stmt = $pdo->prepare("INSERT INTO leave_applications (student_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $student['id'],
                    $_POST['leave_type'],
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['reason']
                ]);
                
                $leave_id = $pdo->lastInsertId();
                
                // Send email notification to rector
                $emailNotification = new EmailNotification();
                $emailNotification->sendBulkNotification('rector', $_SESSION['hostel_id'] ?? 1, 
                    'New Leave Application', 
                    'A new leave application has been submitted and requires your review.');
                
                echo json_encode(['success' => true, 'message' => 'Leave application submitted successfully']);
                break;
                
            case 'upload_avalon':
                $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                $student_query->execute([$_SESSION['user_id']]);
                $student = $student_query->fetch();
                
                if (!$student) {
                    echo json_encode(['success' => false, 'message' => 'Student record not found']);
                    break;
                }
                
                // Create table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS avalon_uploads (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    file_name VARCHAR(255) NOT NULL,
                    file_path VARCHAR(500) NOT NULL,
                    file_size INT NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (student_id) REFERENCES students(id)
                )");
                
                if (isset($_FILES['avalon_file']) && $_FILES['avalon_file']['error'] === 0) {
                    $upload_dir = '../uploads/avalon/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_name = $_FILES['avalon_file']['name'];
                    $file_size = $_FILES['avalon_file']['size'];
                    $file_tmp = $_FILES['avalon_file']['tmp_name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    if (!in_array($file_ext, $allowed_ext)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                        break;
                    }
                    
                    if ($file_size > 10 * 1024 * 1024) { // 10MB limit
                        echo json_encode(['success' => false, 'message' => 'File size too large (max 10MB)']);
                        break;
                    }
                    
                    $new_file_name = $student['id'] . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $stmt = $pdo->prepare("INSERT INTO avalon_uploads (student_id, title, description, file_name, file_path, file_size) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $student['id'],
                            $_POST['title'],
                            $_POST['description'] ?? '',
                            $file_name,
                            $file_path,
                            $file_size
                        ]);
                        
                        echo json_encode(['success' => true, 'message' => 'Avalon uploaded successfully']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                }
                break;
                
            case 'update_leave_status':
                $leave_id = $_POST['leave_id'];
                $status = $_POST['status'];
                
                // Create table if not exists
                $pdo->exec("CREATE TABLE IF NOT EXISTS leave_applications (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    student_id INT NOT NULL,
                    leave_type ENUM('sick', 'emergency', 'personal', 'home', 'other') NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    reason TEXT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    reviewed_at TIMESTAMP NULL,
                    reviewed_by INT NULL,
                    rector_comments TEXT NULL,
                    FOREIGN KEY (student_id) REFERENCES students(id)
                )");
                
                $stmt = $pdo->prepare("UPDATE leave_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $_SESSION['user_id'], $leave_id]);
                
                if ($stmt->rowCount() > 0) {
                    // Send email notification to student
                    $student_stmt = $pdo->prepare("SELECT s.name, s.email FROM students s JOIN leave_applications la ON s.id = la.student_id WHERE la.id = ?");
                    $student_stmt->execute([$leave_id]);
                    $student_data = $student_stmt->fetch();
                    
                    if ($student_data && !empty($student_data['email'])) {
                        $emailNotification = new EmailNotification();
                        $subject = "Leave Application {$status}";
                        $message = "Dear {$student_data['name']}, your leave application has been {$status}.";
                        $emailNotification->sendEmail($student_data['email'], $subject, $message);
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Leave application status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Leave application not found']);
                }
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