<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        try {
            $full_name = $_POST['full_name'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $location = $_POST['location'] ?? '';
            
            // Update users table with CSV data structure
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, contact = ?, location = ? WHERE id = ?");
            $stmt->execute([$full_name, $contact, $location, $user_id]);
            
            // Update staff table if rector exists there
            $staff_stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ? WHERE user_id = ?");
            $staff_stmt->execute([$full_name, $contact, $user_id]);
            
            // Update session if needed
            $_SESSION['rector_name'] = $full_name;
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
        }
    }
    
    if ($action === 'upload_photo') {
        try {
            if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== 0) {
                throw new Exception('No file uploaded or upload error');
            }
            
            $file = $_FILES['profile_photo'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF allowed');
            }
            
            if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception('File too large. Maximum 5MB allowed');
            }
            
            $upload_dir = '../uploads/profile_photos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'rector_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Update database with photo path
                $photo_path = 'uploads/profile_photos/' . $new_filename;
                $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                $stmt->execute([$photo_path, $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Photo uploaded successfully', 'photo_path' => $photo_path]);
            } else {
                throw new Exception('Failed to upload file');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    
    if ($action === 'update_settings') {
        try {
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
            $theme = $_POST['theme'] ?? 'light';
            
            // Update settings in database
            $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, sms_notifications = ?, theme = ? WHERE id = ?");
            $stmt->execute([$email_notifications, $sms_notifications, $theme, $user_id]);
            
            // Update session theme
            $_SESSION['theme'] = $theme;
            
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error updating settings: ' . $e->getMessage()]);
        }
    }
} else {
    // GET request - return rector profile data from CSV
    try {
        $stmt = $pdo->prepare("SELECT u.username as email, u.full_name, u.contact, u.location, u.profile_photo, u.email_notifications, u.sms_notifications, u.theme, h.name as hostel_name, h.location as hostel_location FROM users u LEFT JOIN hostels h ON u.hostel_id = h.id WHERE u.id = ?");
        $stmt->execute([$user_id]);
        $rector = $stmt->fetch();
        
        if ($rector) {
            // Generate name from email if not available
            if (!$rector['full_name']) {
                $email_parts = explode('@', $rector['email']);
                $rector['full_name'] = ucwords(str_replace('.', ' ', $email_parts[0]));
            }
            echo json_encode(['success' => true, 'data' => $rector]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Rector not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching profile: ' . $e->getMessage()]);
    }
}
?>