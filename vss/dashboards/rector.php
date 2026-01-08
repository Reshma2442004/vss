<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header('Location: ../auth/login.php');
    exit;
}

// Get rector's hostel_id from database
$hostel_id_query = $pdo->prepare("SELECT u.hostel_id, st.hostel_id as staff_hostel_id, st.csv_hostel_name FROM users u LEFT JOIN staff st ON u.id = st.user_id WHERE u.id = ?");
$hostel_id_query->execute([$_SESSION['user_id']]);
$hostel_data = $hostel_id_query->fetch();
$hostel_id = $hostel_data['hostel_id'] ?: $hostel_data['staff_hostel_id'];
$csv_hostel_name = $hostel_data['csv_hostel_name'];

// For CSV-based rectors, use the rector's user_id as hostel_id
if (!$hostel_id && $csv_hostel_name) {
    $hostel_id = $_SESSION['user_id']; // Use user_id as hostel_id for CSV rectors
}

// Update session with correct hostel_id
$_SESSION['hostel_id'] = $hostel_id;
if ($csv_hostel_name) {
    $_SESSION['csv_hostel_name'] = $csv_hostel_name;
}

// CRITICAL: Database structure update - MUST run first
try {
    // Ensure students table exists with all required columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        grn VARCHAR(50) UNIQUE,
        name VARCHAR(255) NOT NULL,
        contact VARCHAR(20) DEFAULT NULL,
        course VARCHAR(100) DEFAULT NULL,
        year INT DEFAULT 1,
        room_no VARCHAR(20) DEFAULT NULL,
        hostel_id INT DEFAULT NULL,
        password VARCHAR(255) DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Ensure users table has required columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'rector', 'student', 'staff') NOT NULL,
        hostel_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Ensure staff table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        role VARCHAR(100) NOT NULL,
        contact VARCHAR(20) DEFAULT NULL,
        hostel_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add missing columns safely
    $table_columns = [
        'students' => [
            'email' => 'VARCHAR(255) DEFAULT NULL',
            'grn_no' => 'VARCHAR(50) DEFAULT NULL',
            'first_name' => 'VARCHAR(100) DEFAULT NULL',
            'middle_name' => 'VARCHAR(100) DEFAULT NULL',
            'last_name' => 'VARCHAR(100) DEFAULT NULL',
            'mothers_name' => 'VARCHAR(255) DEFAULT NULL',
            'student_mobile' => 'VARCHAR(20) DEFAULT NULL',
            'parents_mobile' => 'VARCHAR(20) DEFAULT NULL',
            'faculty' => 'VARCHAR(100) DEFAULT NULL',
            'samiti_year' => 'INT DEFAULT 1',
            'college_year' => 'INT DEFAULT 1',
            'course_duration' => 'INT DEFAULT 4',
            'hostel_allocation' => 'VARCHAR(100) DEFAULT NULL',
            'wing' => 'VARCHAR(50) DEFAULT NULL',
            'floor' => 'VARCHAR(10) DEFAULT NULL',
            'room_number' => 'VARCHAR(20) DEFAULT NULL',
            'emergency_contact' => 'VARCHAR(20) DEFAULT NULL'
        ],
        'users' => [
            'email' => 'VARCHAR(255) DEFAULT NULL',
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'contact' => 'VARCHAR(20) DEFAULT NULL',
            'location' => 'VARCHAR(255) DEFAULT NULL',
            'profile_photo' => 'VARCHAR(500) DEFAULT NULL',
            'email_notifications' => 'TINYINT(1) DEFAULT 1',
            'sms_notifications' => 'TINYINT(1) DEFAULT 0',
            'theme' => 'VARCHAR(20) DEFAULT "light"'
        ],
        'staff' => [
            'student_id' => 'INT DEFAULT NULL'
        ]
    ];
    
    foreach ($table_columns as $table => $columns) {
        foreach ($columns as $column => $definition) {
            try {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
            } catch (Exception $e) {
                // Column exists, continue
            }
        }
    }
} catch (Exception $e) {
    error_log("Critical database setup error: " . $e->getMessage());
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['allocate_room'])) {
        $stmt = $pdo->prepare("UPDATE students SET room_id = ? WHERE id = ?");
        $stmt->execute([$_POST['room_id'], $_POST['student_id']]);
        $success = "Room allocated successfully";
    }
    
    if (isset($_POST['mark_attendance'])) {
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?");
        $stmt->execute([$_POST['student_id'], date('Y-m-d'), $_POST['status'], $_POST['status']]);
        $success = "Attendance marked successfully";
    }
    
    if (isset($_POST['add_staff'])) {
        try {
            $student_id = $_POST['student_id'];
            $role = $_POST['staff_role'];
        
        // Get student details
        $student_query = $pdo->prepare("SELECT * FROM students WHERE id = ? AND hostel_id = ?");
        $student_query->execute([$student_id, $hostel_id]);
        $student = $student_query->fetch();
        
        if ($student) {
            // Create user account for staff with unique username
            $base_username = strtolower(str_replace(' ', '_', $student['name']));
            $username = $base_username;
            $counter = 1;
            
            // Check for existing username and make it unique
            while (true) {
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetchColumn() == 0) {
                    break; // Username is unique
                }
                $username = $base_username . '_' . $counter;
                $counter++;
            }
            
            $password = 'staff' . rand(1000, 9999);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Add plain_password column to staff table if not exists
            try {
                $pdo->exec("ALTER TABLE staff ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL");
            } catch (Exception $e) {
                // Column already exists
            }
            
            // Safely get email with fallback
            $email = 'N/A';
            if (isset($student['email']) && !empty($student['email'])) {
                $email = $student['email'];
            } else {
                $email = $username . '@hostel.com';
            }
            
            // Check if users table has email column, if not use basic insert
            try {
                $user_stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, hostel_id) VALUES (?, ?, ?, ?, ?)");
                $user_stmt->execute([$username, $hashed_password, $email, $role, $hostel_id]);
            } catch (Exception $e) {
                // Fallback to basic insert without email
                $user_stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, ?, ?)");
                $user_stmt->execute([$username, $hashed_password, $role, $hostel_id]);
            }
            $user_id = $pdo->lastInsertId();
            
            // Add to staff table - handle missing student_id column
            $contact = $student['contact'] ?? 'N/A';
            try {
                $staff_stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id, student_id, plain_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $staff_stmt->execute([$student['name'], $role, $contact, $hostel_id, $user_id, $student_id, $password]);
            } catch (Exception $e) {
                // Add student_id column if it doesn't exist
                try {
                    $pdo->exec("ALTER TABLE staff ADD COLUMN student_id INT DEFAULT NULL");
                    $pdo->exec("ALTER TABLE staff ADD COLUMN plain_password VARCHAR(255) DEFAULT NULL");
                    $staff_stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id, student_id, plain_password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $staff_stmt->execute([$student['name'], $role, $contact, $hostel_id, $user_id, $student_id, $password]);
                } catch (Exception $e2) {
                    // Fallback without student_id column
                    $staff_stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
                    $staff_stmt->execute([$student['name'], $role, $contact, $hostel_id, $user_id]);
                }
            }
            
            $_SESSION['staff_success'] = "Staff member added successfully.<br><strong>Student Login:</strong> GRN: {$student['grn']}, Student Password: [existing]<br><strong>Staff Login:</strong> GRN: {$student['grn']}, Staff Password: {$password}";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['staff_error'] = "Student not found";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        } catch (Exception $e) {
            $_SESSION['staff_error'] = "Error adding staff: " . $e->getMessage();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    
    if (isset($_POST['add_individual_student'])) {
        try {
            $grn = generateUniqueGRN($pdo);
            $password = generateRandomPassword();
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Try with email column first, fallback to basic columns
            try {
                $stmt = $pdo->prepare("INSERT INTO students (grn, name, email, contact, course, year, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $grn,
                    $_POST['student_name'],
                    $_POST['student_email'] ?? '',
                    $_POST['student_contact'],
                    $_POST['student_course'],
                    $_POST['student_year'],
                    $hostel_id,
                    $hashed_password
                ]);
            } catch (Exception $e) {
                // Fallback to basic columns
                $stmt = $pdo->prepare("INSERT INTO students (grn, name, contact, course, year, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $grn,
                    $_POST['student_name'],
                    $_POST['student_contact'],
                    $_POST['student_course'],
                    $_POST['student_year'],
                    $hostel_id,
                    $hashed_password
                ]);
            }
            
            $success = "Student added successfully. GRN: {$grn}, Password: {$password}";
        } catch (Exception $e) {
            $error = "Error adding student: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_duplicates'])) {
        try {
            $delete_stmt = $pdo->prepare("DELETE s1 FROM students s1 INNER JOIN students s2 WHERE s1.id > s2.id AND s1.grn = s2.grn AND s1.hostel_id = ? AND s2.hostel_id = ?");
            $delete_stmt->execute([$hostel_id, $hostel_id]);
            $removed_count = $delete_stmt->rowCount();
            
            $success = "Removed {$removed_count} duplicate students";
        } catch (Exception $e) {
            $error = "Error removing duplicates: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['clear_old_data']) || isset($_GET['clear_old_data'])) {
        try {
            // Get student IDs first
            $student_ids_query = $pdo->prepare("SELECT id FROM students WHERE hostel_id = ?");
            $student_ids_query->execute([$hostel_id]);
            $student_ids = $student_ids_query->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($student_ids)) {
                $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
                
                // Delete all related records first to avoid foreign key constraints
                $tables_to_clean = [
                    'event_registrations',
                    'attendance', 
                    'mess_attendance',
                    'leave_applications',
                    'mess_feedback',
                    'general_feedback',
                    'scholarships',
                    'avalon_uploads',
                    'complaints'
                ];
                
                foreach ($tables_to_clean as $table) {
                    try {
                        $pdo->prepare("DELETE FROM $table WHERE student_id IN ($placeholders)")->execute($student_ids);
                    } catch (Exception $e) {
                        // Table might not exist, continue
                    }
                }
                
                // Now delete ALL students for this hostel
                $delete_stmt = $pdo->prepare("DELETE FROM students WHERE hostel_id = ?");
                $delete_stmt->execute([$hostel_id]);
                $removed_count = $delete_stmt->rowCount();
                
                $success = "✅ Successfully removed {$removed_count} student records and all related data";
            } else {
                $success = "⚠️ No student records found to remove for hostel ID: {$hostel_id}";
            }
            
            // Redirect to prevent resubmission
            if (isset($_GET['clear_old_data'])) {
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
        } catch (Exception $e) {
            $error = "Error clearing data: " . $e->getMessage();
        }
    }
    
    // Handle AJAX update student request
    if (isset($_POST['action']) && $_POST['action'] === 'update_student') {
        header('Content-Type: application/json');
        try {
            $student_id = $_POST['student_id'];
            $name = $_POST['name'];
            $email = $_POST['email'];
            $contact = $_POST['contact'];
            $course = $_POST['course'];
            $year = $_POST['year'];
            $room_no = $_POST['room_no'];
            
            // Verify student belongs to this rector's hostel
            $verify_stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND hostel_id = ?");
            $verify_stmt->execute([$student_id, $hostel_id]);
            
            if (!$verify_stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Student not found or access denied']);
                exit;
            }
            
            // Try update with email column first, fallback without
            try {
                $update_stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, contact = ?, course = ?, year = ?, room_no = ? WHERE id = ?");
                $update_stmt->execute([$name, $email, $contact, $course, $year, $room_no, $student_id]);
            } catch (Exception $e) {
                // Fallback without email column
                $update_stmt = $pdo->prepare("UPDATE students SET name = ?, contact = ?, course = ?, year = ?, room_no = ? WHERE id = ?");
                $update_stmt->execute([$name, $contact, $course, $year, $room_no, $student_id]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
    
    // Handle AJAX remove staff request
    if (isset($_POST['action']) && $_POST['action'] === 'remove_staff') {
        header('Content-Type: application/json');
        try {
            $staff_id = $_POST['staff_id'];
            
            // Verify staff belongs to this rector's hostel
            $verify_stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ? AND hostel_id = ?");
            $verify_stmt->execute([$staff_id, $hostel_id]);
            $staff_data = $verify_stmt->fetch();
            
            if (!$staff_data) {
                echo json_encode(['success' => false, 'message' => 'Staff not found or access denied']);
                exit;
            }
            
            // Delete staff record
            $delete_staff = $pdo->prepare("DELETE FROM staff WHERE id = ?");
            $delete_staff->execute([$staff_id]);
            
            // Delete associated user account if exists
            if ($staff_data['user_id']) {
                $delete_user = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delete_user->execute([$staff_data['user_id']]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Staff removed successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle CSV file upload
if (isset($_POST['upload_csv']) && isset($_FILES['student_file'])) {
    $file = $_FILES['student_file'];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'File is too large (exceeds php.ini limit)',
            UPLOAD_ERR_FORM_SIZE => 'File is too large (exceeds form limit)',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $error = "❌ Upload Error: " . ($upload_errors[$file['error']] ?? 'Unknown error');
    } else {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension !== 'csv') {
            $error = "❌ Please upload a CSV file only. Current file type: .{$file_extension}";
        } else {
            // Validate file size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                $error = "❌ File is too large. Maximum size allowed is 5MB.";
            } else {
                $upload_dir = '../uploads/students/';
                
                // Create directory with proper permissions
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0777, true)) {
                        $error = "❌ Failed to create upload directory";
                    }
                }
                
                // Check if directory is writable
                if (!is_writable($upload_dir)) {
                    $error = "❌ Upload directory is not writable";
                }
                
                if (!isset($error)) {
                    $file_path = $upload_dir . time() . '_' . basename($file['name']);
                    
                    error_log("Attempting to move file from {$file['tmp_name']} to {$file_path}");
                    if (move_uploaded_file($file['tmp_name'], $file_path)) {
                        error_log("File moved successfully, processing CSV");
                        // Process the CSV file
                        $students_added = processCSVFile($file_path, $hostel_id, $pdo, $csv_hostel_name);
                        $hostel_name = $csv_hostel_name ?: 'hostel ' . $hostel_id;
                        error_log("CSV processing completed, students added: " . $students_added);
                        
                        error_log("Students added: " . $students_added);
                        if ($students_added > 0) {
                            $success_msg = "✅ <strong>CSV Upload Successful!</strong><br>";
                            $success_msg .= "Added {$students_added} students to {$hostel_name}";
                            
                            if (isset($_SESSION['csv_processing_summary'])) {
                                $summary = $_SESSION['csv_processing_summary'];
                                $success_msg .= "<br><small class='text-muted'>Processed {$summary['total_rows']} rows with {$summary['errors_count']} errors</small>";
                            }
                            
                            if (isset($_SESSION['csv_errors']) && !empty($_SESSION['csv_errors'])) {
                                $success_msg .= "<br><br><strong>⚠️ Some rows had issues:</strong>";
                                $success_msg .= "<div class='mt-2' style='max-height: 200px; overflow-y: auto;'><ul class='mb-0'>";
                                foreach ($_SESSION['csv_errors'] as $err) {
                                    $success_msg .= "<li><small>" . htmlspecialchars($err) . "</small></li>";
                                }
                                $success_msg .= "</ul></div>";
                                unset($_SESSION['csv_errors']);
                            }
                            
                            $_SESSION['upload_success'] = $success_msg;
                            error_log("Success message set, redirecting");
                            
                            // Clean up uploaded file
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                            
                            // Redirect to refresh the page and show new students
                            header('Location: ' . $_SERVER['PHP_SELF'] . '?uploaded=' . $students_added);
                            exit;
                        } else {
                            $error = "❌ No students were added. Check CSV format and data.";
                            if (isset($_SESSION['csv_errors']) && !empty($_SESSION['csv_errors'])) {
                                $error .= "<br>Errors: " . implode(', ', array_slice($_SESSION['csv_errors'], 0, 3));
                                unset($_SESSION['csv_errors']);
                            }
                        }
                        
                        // Clean up uploaded file
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    } else {
                        $error = "❌ Failed to move uploaded file. Check directory permissions.";
                    }
                }
            }
        }
    }
}

// Function to process CSV file
function processCSVFile($file_path, $hostel_id, $pdo, $csv_hostel_name = null) {
    $students_added = 0;
    $errors = [];
    
    if (!file_exists($file_path)) {
        $errors[] = "File not found";
        $_SESSION['csv_errors'] = $errors;
        return 0;
    }
    
    $handle = fopen($file_path, "r");
    if (!$handle) {
        $errors[] = "Cannot open CSV file";
        $_SESSION['csv_errors'] = $errors;
        return 0;
    }
    
    // Skip header row
    fgetcsv($handle);
    
    $row = 1;
    while (($data = fgetcsv($handle)) !== FALSE) {
        $row++;
        if (empty(array_filter($data))) continue;
        
        // Get basic data (flexible column count)
        $grn = !empty($data[0]) ? trim($data[0]) : generateUniqueGRN($pdo);
        $name = trim(($data[1] ?? '') . ' ' . ($data[2] ?? '') . ' ' . ($data[3] ?? ''));
        $name = trim($name) ?: 'Student ' . $row;
        $contact = trim($data[5] ?? '');
        $email = trim($data[7] ?? '') ?: strtolower(str_replace(' ', '.', $data[1] ?? 'student')) . '@student.com';
        $course = trim($data[8] ?? '') ?: 'General';
        $year = max(1, intval($data[11] ?? 1));
        $room = trim($data[16] ?? '');
        
        // Generate password
        $password = generateRandomPassword();
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO students (grn, name, email, contact, course, year, room_no, hostel_id, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$grn, $name, $email, $contact, $course, $year, $room, $hostel_id, $hashed_password])) {
                $students_added++;
            }
        } catch (Exception $e) {
            $errors[] = "Row $row: " . $e->getMessage();
        }
    }
    fclose($handle);
    
    if (!empty($errors)) {
        $_SESSION['csv_errors'] = array_slice($errors, 0, 5);
    }
    
    return $students_added;
}

// Function to generate unique GRN
function generateUniqueGRN($pdo) {
    do {
        $grn = 'GRN' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE grn = ?");
        $stmt->execute([$grn]);
    } while ($stmt->fetchColumn() > 0);
    
    return $grn;
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Database columns are now handled in the main setup section above

// Fetch rector details from both users and staff tables with safe column access
try {
    $rector_query = $pdo->prepare("
        SELECT u.username as email, u.full_name, u.contact, u.location, u.profile_photo, u.email_notifications, u.sms_notifications, u.theme, 
               h.name as hostel_name, h.location as hostel_location,
               st.name as staff_name, st.contact as staff_contact, st.location as staff_location, st.csv_hostel_name
        FROM users u 
        LEFT JOIN hostels h ON u.hostel_id = h.id 
        LEFT JOIN staff st ON u.id = st.user_id 
        WHERE u.id = ?
    ");
    $rector_query->execute([$_SESSION['user_id']]);
    $rector_info = $rector_query->fetch();
} catch (Exception $e) {
    // Fallback query with basic columns only
    $rector_query = $pdo->prepare("
        SELECT u.username as email, 
               h.name as hostel_name, h.location as hostel_location,
               st.name as staff_name, st.contact as staff_contact, st.location as staff_location, st.csv_hostel_name
        FROM users u 
        LEFT JOIN hostels h ON u.hostel_id = h.id 
        LEFT JOIN staff st ON u.id = st.user_id 
        WHERE u.id = ?
    ");
    $rector_query->execute([$_SESSION['user_id']]);
    $rector_info = $rector_query->fetch();
    
    // Set default values for missing columns
    $rector_info['full_name'] = null;
    $rector_info['contact'] = null;
    $rector_info['location'] = null;
    $rector_info['profile_photo'] = null;
    $rector_info['email_notifications'] = 1;
    $rector_info['sms_notifications'] = 0;
    $rector_info['theme'] = 'light';
}

// Use staff data if available (from CSV import)
if ($rector_info) {
    if (!$rector_info['full_name'] && $rector_info['staff_name']) {
        $rector_info['full_name'] = $rector_info['staff_name'];
    }
    if (!$rector_info['contact'] && $rector_info['staff_contact']) {
        $rector_info['contact'] = $rector_info['staff_contact'];
    }
    if (!$rector_info['location'] && $rector_info['staff_location']) {
        $rector_info['location'] = $rector_info['staff_location'];
    }
    if (!$rector_info['hostel_name'] && $rector_info['csv_hostel_name']) {
        $rector_info['hostel_name'] = $rector_info['csv_hostel_name'];
    }
}

// If no full_name found, try to get it from username (email)
if (!isset($rector_info['full_name']) || !$rector_info['full_name']) {
    $email_parts = explode('@', $_SESSION['username']);
    $rector_info['full_name'] = ucwords(str_replace('.', ' ', $email_parts[0]));
}

// Fetch data for rector's hostel
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

// Debug hostel assignment
if (!$hostel_info && !$csv_hostel_name) {
    // Check if rector has hostel_id in users table or staff table
    $debug_query = $pdo->prepare("SELECT u.hostel_id as user_hostel, st.hostel_id as staff_hostel, st.csv_hostel_name FROM users u LEFT JOIN staff st ON u.id = st.user_id WHERE u.id = ?");
    $debug_query->execute([$_SESSION['user_id']]);
    $debug_data = $debug_query->fetch();
    
    if ($debug_data && ($debug_data['user_hostel'] || $debug_data['staff_hostel'] || $debug_data['csv_hostel_name'])) {
        // Rector has hostel assignment but hostel record not found, use fallback
        $hostel_info = [
            'id' => $debug_data['user_hostel'] ?: $debug_data['staff_hostel'] ?: 'csv_' . md5($debug_data['csv_hostel_name']),
            'name' => $debug_data['csv_hostel_name'] ?: 'Assigned Hostel',
            'capacity' => 100,
            'location' => 'Campus'
        ];
        $hostel_id = $hostel_info['id'];
        $csv_hostel_name = $debug_data['csv_hostel_name'];
    } else {
        die("<div style='padding: 20px; text-align: center;'><h3>Hostel Assignment Issue</h3><p>No hostel is currently assigned to rector: <strong>" . $_SESSION['username'] . "</strong></p><p>Please contact the admin to assign a hostel to your account.</p><p><a href='../auth/logout.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Logout</a></p></div>");
    }
}

// If no hostel_info but has csv_hostel_name, create a virtual hostel info
if (!$hostel_info && $csv_hostel_name) {
    $hostel_info = [
        'id' => 'csv_' . md5($csv_hostel_name),
        'name' => $csv_hostel_name,
        'capacity' => 100, // Default capacity
        'location' => 'Campus'
    ];
    $hostel_id = $hostel_info['id'];
}

// Query students based on CSV hostel name or regular hostel_id with safe column handling
try {
    if ($csv_hostel_name) {
        // For CSV hostels, find students by hostel_id OR hostel allocation names
        $students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ? OR hostel_allocation LIKE ? OR hostel_allocation = ?");
        $students->execute([$hostel_id, '%' . $csv_hostel_name . '%', $csv_hostel_name]);
    } else {
        $students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
        $students->execute([$hostel_id]);
    }
    $students_list = $students->fetchAll();
} catch (Exception $e) {
    // Fallback query with basic columns only
    if ($csv_hostel_name) {
        $students = $pdo->prepare("SELECT id, grn, name, contact, course, year, room_no, hostel_id, password FROM students WHERE hostel_id = ?");
        $students->execute([$hostel_id]);
    } else {
        $students = $pdo->prepare("SELECT id, grn, name, contact, course, year, room_no, hostel_id, password FROM students WHERE hostel_id = ?");
        $students->execute([$hostel_id]);
    }
    $students_list = $students->fetchAll();
}



// Ensure all students have required fields with defaults
foreach ($students_list as &$student) {
    $student['email'] = isset($student['email']) && !empty($student['email']) ? $student['email'] : 'No email';
    $student['contact'] = isset($student['contact']) && !empty($student['contact']) ? $student['contact'] : 'No contact';
    $student['course'] = isset($student['course']) && !empty($student['course']) ? $student['course'] : 'General';
    $student['year'] = max(1, intval($student['year'] ?? 1));
    $student['room_no'] = isset($student['room_no']) && !empty($student['room_no']) ? $student['room_no'] : 'Not Assigned';
    $student['grn'] = isset($student['grn']) && !empty($student['grn']) ? $student['grn'] : 'GRN' . str_pad($student['id'], 6, '0', STR_PAD_LEFT);
    $student['student_mobile'] = isset($student['student_mobile']) ? $student['student_mobile'] : $student['contact'];
    $student['parents_mobile'] = isset($student['parents_mobile']) ? $student['parents_mobile'] : '';
    $student['faculty'] = isset($student['faculty']) ? $student['faculty'] : '';
    $student['name'] = isset($student['name']) && !empty($student['name']) ? $student['name'] : 'Unknown Student';
}

// Get unique room count from students' room numbers
$unique_rooms = array_unique(array_filter(array_column($students_list, 'room_no'), function($room) {
    return $room && $room !== 'Not Assigned' && $room !== '' && $room !== '-';
}));
$total_rooms_count = count($unique_rooms);

$rooms = $pdo->prepare("SELECT * FROM rooms WHERE hostel_id = ?");
$rooms->execute([$hostel_id]);
$rooms_list = $rooms->fetchAll();

$staff = $pdo->prepare("SELECT * FROM staff WHERE hostel_id = ?");
$staff->execute([$hostel_id]);
$staff_list = $staff->fetchAll();

// Get attendance statistics
$attendance_stats = $pdo->prepare("SELECT 
    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
    FROM attendance a 
    JOIN students s ON a.student_id = s.id 
    WHERE s.hostel_id = ? AND a.date = CURDATE()");
$attendance_stats->execute([$hostel_id]);
$attendance_data = $attendance_stats->fetch();

// Get mess feedback for this hostel only
try {
    if ($csv_hostel_name) {
        // For CSV hostels, match by hostel_id OR hostel allocation
        $feedback_query = $pdo->prepare("
            SELECT mf.*, s.name as student_name, s.grn, s.hostel_id,
                   COALESCE(mf.category, 'Other') as category,
                   COALESCE(mf.priority, 'medium') as priority
            FROM mess_feedback mf 
            JOIN students s ON mf.student_id = s.id 
            WHERE s.hostel_id = ? OR s.hostel_allocation LIKE ? OR s.hostel_allocation = ?
            ORDER BY 
                CASE mf.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                    ELSE 5 
                END,
                mf.created_at DESC
        ");
        $feedback_query->execute([$hostel_id, '%' . $csv_hostel_name . '%', $csv_hostel_name]);
    } else {
        $feedback_query = $pdo->prepare("
            SELECT mf.*, s.name as student_name, s.grn, s.hostel_id,
                   COALESCE(mf.category, 'Other') as category,
                   COALESCE(mf.priority, 'medium') as priority
            FROM mess_feedback mf 
            JOIN students s ON mf.student_id = s.id 
            WHERE s.hostel_id = ?
            ORDER BY 
                CASE mf.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                    ELSE 5 
                END,
                mf.created_at DESC
        ");
        $feedback_query->execute([$hostel_id]);
    }
    $feedback_list = $feedback_query->fetchAll() ?: [];
} catch (Exception $e) {
    $feedback_list = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rector Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-university me-2"></i>Rector Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 30 30%27%3e%3cpath stroke=%27rgba%28255, 255, 255, 0.8%29%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 stroke-width=%272%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#students" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-graduate me-2"></i>Students
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#rooms" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-door-open me-2"></i>Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#staff" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-users-cog me-2"></i>Staff
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#reports" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-chart-line me-2"></i>Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#leave-applications" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-calendar-times me-2"></i>Leave Applications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#complaints" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-exclamation-triangle me-2"></i>Complaints
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#compliments" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-thumbs-up me-2"></i>Compliments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#suggestions" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-lightbulb me-2"></i>Suggestions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-comments me-2"></i>Mess Feedback
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="../qr_attendance.php" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-qrcode me-2"></i>QR Attendance
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" style="padding: 0.75rem 1rem; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <?php if(isset($rector_info['profile_photo']) && $rector_info['profile_photo']): ?>
                                <img src="../<?php echo $rector_info['profile_photo']; ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle me-2" style="font-size: 1.2rem;"></i>
                            <?php endif; ?>
                            <span><?php echo isset($rector_info['full_name']) && $rector_info['full_name'] ? $rector_info['full_name'] : $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 12px; padding: 0.5rem 0;">
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openProfileModal()" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-user-edit me-2 text-primary"></i>Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openChangePasswordModal()" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-key me-2 text-warning"></i>Change Password</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openSettingsModal()" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-cog me-2 text-secondary"></i>Settings</a></li>
                            <li><hr class="dropdown-divider mx-2" style="margin: 0.5rem 0;"></li>
                            <li><a class="dropdown-item py-2 px-3 text-danger" href="../auth/logout.php" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">

    
    <?php if(isset($_SESSION['upload_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    

    
    <?php if(isset($_SESSION['csv_processing_summary'])): ?>
        <?php $summary = $_SESSION['csv_processing_summary']; unset($_SESSION['csv_processing_summary']); ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Processing Summary:</strong>
            <ul class="mb-0 mt-2">
                <li>Total rows processed: <?php echo $summary['total_rows']; ?></li>
                <li>Students successfully added: <?php echo $summary['students_added']; ?></li>
                <li>Errors encountered: <?php echo $summary['errors_count']; ?></li>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['staff_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['staff_success']; unset($_SESSION['staff_success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['staff_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['staff_error']; unset($_SESSION['staff_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Debug Information (only show if there are issues) -->
    <?php if(isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-bug me-2"></i>
            <strong>Debug Information:</strong>
            <div class="row">
                <div class="col-md-6">
                    <h6>System Info:</h6>
                    <ul class="mb-0">
                        <li>PHP Version: <?php echo PHP_VERSION; ?></li>
                        <li>Upload Max Filesize: <?php echo ini_get('upload_max_filesize'); ?></li>
                        <li>Post Max Size: <?php echo ini_get('post_max_size'); ?></li>
                        <li>Max Execution Time: <?php echo ini_get('max_execution_time'); ?>s</li>
                        <li>Memory Limit: <?php echo ini_get('memory_limit'); ?></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>Directory Info:</h6>
                    <ul class="mb-0">
                        <li>Uploads Directory: <?php echo realpath('../uploads/students/') ?: 'Not found'; ?></li>
                        <li>Directory Writable: <?php echo is_writable('../uploads/students/') ? 'Yes' : 'No'; ?></li>
                        <li>Current Hostel ID: <?php echo $hostel_id; ?></li>
                        <li>CSV Hostel Name: <?php echo $csv_hostel_name ?: 'None'; ?></li>
                        <li>Session User ID: <?php echo $_SESSION['user_id']; ?></li>
                    </ul>
                </div>
            </div>
            
            <?php 
            // Check database table structure
            try {
                $columns_check = $pdo->query("DESCRIBE students");
                $existing_columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
                echo "<h6 class='mt-3'>Database Columns (".count($existing_columns)."):</h6>";
                echo "<small class='font-monospace'>" . implode(', ', $existing_columns) . "</small>";
            } catch (Exception $e) {
                echo "<p class='text-danger mt-3'>Database Error: " . $e->getMessage() . "</p>";
            }
            ?>
            
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    

    
        <!-- Rector Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if(isset($rector_info['profile_photo']) && $rector_info['profile_photo']): ?>
                                    <img src="../<?php echo $rector_info['profile_photo']; ?>" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                        <i class="fas fa-user text-white" style="font-size: 2rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-10">
                                <h4 class="mb-1"><?php echo isset($rector_info['full_name']) && $rector_info['full_name'] ? $rector_info['full_name'] : 'Rector'; ?></h4>
                                <p class="text-muted mb-2"><?php echo $_SESSION['username']; ?></p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <strong><i class="fas fa-university me-2 text-primary"></i>Hostel:</strong> <?php echo isset($rector_info['hostel_name']) ? $rector_info['hostel_name'] : 'Not Assigned'; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong><i class="fas fa-map-marker-alt me-2 text-success"></i>Location:</strong> <?php echo isset($rector_info['location']) && $rector_info['location'] ? $rector_info['location'] : (isset($rector_info['hostel_location']) && $rector_info['hostel_location'] ? $rector_info['hostel_location'] : 'Not Set'); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong><i class="fas fa-phone me-2 text-info"></i>Contact:</strong> <?php echo isset($rector_info['contact']) && $rector_info['contact'] ? $rector_info['contact'] : 'Not Set'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions & Analytics -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students_list); ?></div>
                        <div class="stat-label">Active Students</div>
                        <div class="stat-meta">Out of <?php echo $hostel_info['capacity']; ?> capacity</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $total_rooms_count; ?></div>
                        <div class="stat-label">Total Rooms</div>
                        <div class="stat-meta">Available rooms</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($staff_list); ?></div>
                        <div class="stat-label">Staff Members</div>
                        <div class="stat-meta">Active personnel</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_data['present_count'] ?? 0; ?></div>
                        <div class="stat-label">Present Today</div>
                        <div class="stat-meta">Daily attendance</div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Management Tools -->
        <div id="rooms" class="row mb-4">
            <div class="col-md-12 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i>Attendance Management</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-user-graduate me-1"></i>Select Student</label>
                                        <select class="form-input" name="student_id" required>
                                            <option value="">Choose a student...</option>
                                            <?php foreach($students_list as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label"><i class="fas fa-clipboard-check me-1"></i>Attendance Status</label>
                                        <select class="form-input" name="status" required>
                                            <option value="">Select status...</option>
                                            <option value="present"><i class="fas fa-check"></i> Present</option>
                                            <option value="absent"><i class="fas fa-times"></i> Absent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="mark_attendance" class="btn btn-success w-100">
                                        <i class="fas fa-check-circle me-2"></i>Mark Attendance
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Students Management -->
        <div id="students" class="row mb-4">
            <div class="col-12 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-upload me-2"></i>Bulk Upload Students</h5>
                    </div>
                    <div class="card-body">

                        
                        <form method="POST" enctype="multipart/form-data" class="modern-form" id="csvUploadForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Upload Student Data (CSV)</label>
                                    <input type="file" class="form-control" name="student_file" id="csvFile" accept=".csv" required>
                                    <div class="mt-2">
                                        <small class="text-muted">Selected file: <span id="fileName">None</span></small><br>
                                        <small class="text-muted">File size: <span id="fileSize">0 KB</span></small>
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="upload_csv" class="btn btn-success w-100" id="uploadBtn">
                                        <i class="fas fa-upload me-2"></i>Upload Students
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Progress bar -->
                            <div class="row mt-3" id="uploadProgress" style="display: none;">
                                <div class="col-12">
                                    <div class="progress">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Processing CSV file...</small>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Sample CSV format -->
                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-info btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#csvSample">
                                <i class="fas fa-eye me-1"></i>View Sample CSV Format
                            </button>
                            <a href="../assets/sample_students.csv" class="btn btn-outline-success btn-sm" download>
                                <i class="fas fa-download me-1"></i>Download Sample CSV
                            </a>
                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="toggleDebugInfo()">
                                <i class="fas fa-bug me-1"></i>Debug Info
                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="testCSVUpload()">
                                <i class="fas fa-vial me-1"></i>Test Upload
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.open('../handlers/test_upload.php', '_blank')">
                                <i class="fas fa-server me-1"></i>Server Test
                            </button>
                        </div>
                        
                        <div class="collapse mt-2" id="csvSample">
                            <div class="card card-body bg-light">
                                <h6 class="mb-2">Sample CSV Format:</h6>
                                <small class="font-monospace">
                                    GRN no.,First Name,Middle Name,Last Name,Mother's Name,Student Mobile,Parents Mobile,Email,Course,Faculty,Samiti Year,College Year,Duration,Hostel Allocation,Wing,Floor,Room no.<br>
                                    GRN001,John,M,Doe,Jane Doe,9876543210,9876543211,john.doe@email.com,Computer Science,Engineering,1,1,4,Hostel A,North Wing,1,101<br>
                                    GRN002,Jane,K,Smith,Mary Smith,9876543212,9876543213,jane.smith@email.com,Information Technology,Engineering,1,1,4,Hostel A,South Wing,1,102
                                </small>
                                <div class="mt-2">
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <strong>📋 Important Notes:</strong><br>
                                            • All 17 columns must be present in the exact order shown<br>
                                            • First row must contain column headers (will be skipped)<br>
                                            • <strong>First Name</strong> is mandatory - rows without it will be skipped<br>
                                            • GRN no. can be empty (will be auto-generated)<br>
                                            • Email can be empty (will be auto-generated)<br>
                                            • Hostel Allocation will be set to your assigned hostel<br>
                                            • Save your file as CSV (UTF-8) format for best compatibility
                                        </small>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <small class="text-primary">
                                                <strong>🔧 Troubleshooting:</strong> If uploads fail, click "Server Test" to diagnose issues or "Debug Info" for detailed system information.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users me-2"></i>Students Management</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-danger" onclick="clearOldData()"><i class="fas fa-trash me-1"></i>Clear Old Data</button>
                            <button class="btn btn-sm btn-outline-warning" onclick="removeDuplicates()"><i class="fas fa-copy me-1"></i>Remove Duplicates</button>
                            <button class="btn btn-sm btn-outline-primary" onclick="exportStudents()"><i class="fas fa-download me-1"></i>Export</button>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i class="fas fa-user-plus me-1"></i>Add Student</button>
                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#sendAdmissionModal"><i class="fas fa-envelope me-1"></i>Send Email</button>
                            <button class="btn btn-sm btn-info" onclick="toggleBulkMode()"><i class="fas fa-envelope-bulk me-1"></i>Send to Multiple</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="studentSearch" placeholder="🔍 Search by GRN, Name, Course, Room Number, or Contact...">
                            <small id="searchCount" class="text-muted d-block mt-1"></small>
                        </div>
                        <div id="bulkControls" class="mb-3" style="display: none;">
                            <div class="alert alert-info py-2">
                                <i class="fas fa-info-circle me-2"></i>Select students and click "Send Email" to compose message.
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="selectAll()"><i class="fas fa-check-square me-1"></i>Select All</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="deselectAll()"><i class="fas fa-square me-1"></i>Deselect All</button>
                                <button class="btn btn-sm btn-success" onclick="sendToSelected()" id="sendBtn" disabled><i class="fas fa-envelope me-1"></i>Send Email (<span id="count">0</span>)</button>
                                <button class="btn btn-sm btn-outline-danger" onclick="toggleBulkMode()"><i class="fas fa-times me-1"></i>Cancel</button>
                            </div>
                        </div>
                        <script>
                        document.getElementById('studentSearch').addEventListener('keyup', function() {
                            const searchTerm = this.value.toLowerCase().trim();
                            const table = document.getElementById('studentsTable');
                            const rows = table.querySelectorAll('tbody tr');
                            let visibleCount = 0;
                            
                            rows.forEach(row => {
                                const cells = row.querySelectorAll('td:not(.bulk-select-cell)');
                                const grn = cells[0]?.textContent.toLowerCase().trim() || '';
                                const name = cells[1]?.textContent.toLowerCase().trim() || '';
                                const courseCell = cells[2]?.querySelector('.fw-semibold');
                                const course = courseCell ? courseCell.textContent.toLowerCase().trim() : '';
                                const room = cells[3]?.textContent.toLowerCase().trim() || '';
                                const phoneElement = cells[4]?.querySelector('.fas.fa-phone');
                                const phone = phoneElement ? phoneElement.parentElement.textContent.replace(/[^0-9]/g, '') : '';
                                
                                if (grn.includes(searchTerm) || name.includes(searchTerm) || 
                                    course.includes(searchTerm) || room.includes(searchTerm) || 
                                    phone.includes(searchTerm)) {
                                    row.style.display = '';
                                    visibleCount++;
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                            
                            const countElement = document.getElementById('searchCount');
                            if (searchTerm) {
                                countElement.textContent = `Showing ${visibleCount} of ${rows.length} students`;
                            } else {
                                countElement.textContent = `Total: ${rows.length} students`;
                            }
                        });
                        </script>
                        

                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table modern-table" id="studentsTable">
                                <thead>
                                    <tr>
                                        <th id="chkHeader" style="display: none;"><input type="checkbox" id="selectAllChk" onchange="toggleAll()"></th>
                                        <th><i class="fas fa-id-card me-1"></i>GRN</th>
                                        <th><i class="fas fa-user me-1"></i>Student Details</th>
                                        <th><i class="fas fa-graduation-cap me-1"></i>Academic Info</th>
                                        <th><i class="fas fa-home me-1"></i>Room No</th>
                                        <th><i class="fas fa-phone me-1"></i>Contact</th>
                                        <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($students_list as $student): ?>
                                    <tr>
                                        <td class="chkCell" style="display: none;"><input type="checkbox" class="studentChk" data-id="<?php echo $student['id']; ?>" data-name="<?php echo addslashes($student['name']); ?>" data-email="<?php echo addslashes($student['email']); ?>" onchange="updateCount()"></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $student['grn']; ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle p-2 me-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-user text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo $student['name']; ?></div>
                                                    <small class="text-muted">ID: <?php echo $student['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?php echo $student['course']; ?></div>
                                                <small class="text-muted"><?php echo $student['faculty'] ? $student['faculty'] : 'Year ' . $student['year']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-primary fw-semibold"><?php echo $student['room_no'] ?: '-'; ?></span>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="fas fa-phone me-1 text-primary"></i><?php echo $student['student_mobile'] ?: $student['contact']; ?><br>
                                                <small class="text-muted"><i class="fas fa-envelope me-1"></i><?php echo $student['email']; ?></small>
                                                <?php if($student['parents_mobile']): ?><br><small class="text-muted">Parent: <?php echo $student['parents_mobile']; ?></small><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" title="View Credentials" onclick="viewStudentCredentials('<?php echo str_replace("'", "\\'", $student['grn']); ?>', '<?php echo str_replace("'", "\\'", $student['name']); ?>', '<?php echo str_replace("'", "\\'", $student['email']); ?>')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" title="Edit Details" onclick="editStudent(<?php echo $student['id']; ?>, '<?php echo str_replace("'", "\\'", $student['name']); ?>', '<?php echo str_replace("'", "\\'", $student['email']); ?>', '<?php echo str_replace("'", "\\'", $student['contact']); ?>', '<?php echo str_replace("'", "\\'", $student['course']); ?>', <?php echo $student['year']; ?>, '<?php echo str_replace("'", "\\'", $student['room_no']); ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Send Email" onclick="openEmailModal('<?php echo str_replace("'", "\\'", $student['name']); ?>', '<?php echo str_replace("'", "\\'", $student['email']); ?>')">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Staff Management -->
        <div id="staff" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-users-cog me-2"></i>Hostel Staff Directory</h5>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="fas fa-user-plus me-1"></i>Add Staff</button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user me-1"></i>Staff Member</th>
                                        <th><i class="fas fa-id-badge me-1"></i>Role</th>
                                        <th><i class="fas fa-phone me-1"></i>Contact</th>
                                        <th><i class="fas fa-calendar me-1"></i>Status</th>
                                        <th><i class="fas fa-cogs me-1"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($staff_list as $staff_member): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $role_icons = [
                                                    'mess_head' => 'fas fa-utensils',
                                                    'library_head' => 'fas fa-book',
                                                    'health_staff' => 'fas fa-user-md',
                                                    'vvk_staff' => 'fas fa-lightbulb',
                                                    'placement_staff' => 'fas fa-briefcase',
                                                    'ed_cell_staff' => 'fas fa-chalkboard-teacher',
                                                    'scholarship_staff' => 'fas fa-award',
                                                    'student_head' => 'fas fa-users',
                                                    'rector' => 'fas fa-university'
                                                ];
                                                $icon = $role_icons[$staff_member['role']] ?? 'fas fa-user-tie';
                                                ?>
                                                <div class="bg-primary rounded-circle p-2 me-3" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                                    <i class="<?php echo $icon; ?> text-white"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo $staff_member['name']; ?></div>
                                                    <small class="text-muted">Staff ID: <?php echo $staff_member['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo ucwords(str_replace('_', ' ', $staff_member['role'])); ?></span>
                                        </td>
                                        <td>
                                            <i class="fas fa-phone me-1 text-primary"></i><?php echo $staff_member['contact']; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-warning" title="Login Credentials" onclick="viewStaffCredentials(<?php echo $staff_member['id']; ?>, '<?php echo str_replace("'", "\\'", $staff_member['name']); ?>')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info" title="Send Message" onclick="openMessageModal('staff', <?php echo $staff_member['id']; ?>, '<?php echo str_replace("'", "\\'", $staff_member['name']); ?>')">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" title="Remove Staff" onclick="removeStaff(<?php echo $staff_member['id']; ?>, '<?php echo str_replace("'", "\\'", $staff_member['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Attendance Reports -->
        <div id="attendance-reports" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i>Recent Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $recent_attendance = $pdo->prepare("
                            SELECT s.name, s.grn, a.date, a.status
                            FROM attendance a
                            JOIN students s ON a.student_id = s.id
                            WHERE s.hostel_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                            ORDER BY a.date DESC, s.name
                            LIMIT 20
                        ");
                        $recent_attendance->execute([$hostel_id]);
                        $attendance_records = $recent_attendance->fetchAll();
                        ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($attendance_records)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No attendance records</td></tr>
                                    <?php else: ?>
                                        <?php foreach($attendance_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                            <td><span class="badge bg-<?php echo $record['status'] == 'present' ? 'success' : 'danger'; ?>"><?php echo ucfirst($record['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-utensils me-2"></i>Mess Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $mess_attendance = $pdo->prepare("
                            SELECT s.name, ma.date, ma.meal_type, ma.taken
                            FROM mess_attendance ma
                            JOIN students s ON ma.student_id = s.id
                            WHERE s.hostel_id = ? AND ma.date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                            ORDER BY ma.date DESC, s.name
                            LIMIT 15
                        ");
                        $mess_attendance->execute([$hostel_id]);
                        $mess_records = $mess_attendance->fetchAll();
                        ?>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Meal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($mess_records)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No mess attendance records</td></tr>
                                    <?php else: ?>
                                        <?php foreach($mess_records as $record): ?>
                                        <tr>
                                            <td><?php echo $record['name']; ?></td>
                                            <td><?php echo date('M d', strtotime($record['date'])); ?></td>
                                            <td><?php echo ucfirst($record['meal_type']); ?></td>
                                            <td><span class="badge bg-<?php echo $record['taken'] ? 'success' : 'danger'; ?>"><?php echo $record['taken'] ? 'Taken' : 'Missed'; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scholarship Management -->
        <div id="scholarships" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-award me-2"></i>Scholarship Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        $scholarships_query = $pdo->prepare("
                            SELECT sc.*, s.name as student_name, s.grn 
                            FROM scholarships sc 
                            JOIN students s ON sc.student_id = s.id 
                            WHERE s.hostel_id = ? 
                            ORDER BY sc.applied_date DESC
                        ");
                        $scholarships_query->execute([$hostel_id]);
                        $scholarships_list = $scholarships_query->fetchAll();
                        ?>
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Scholarship</th>
                                        <th>Amount</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($scholarships_list)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No scholarship applications</td></tr>
                                    <?php else: ?>
                                        <?php foreach($scholarships_list as $scholarship): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $scholarship['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $scholarship['grn']; ?></small>
                                            </td>
                                            <td><?php echo $scholarship['scholarship_name']; ?></td>
                                            <td>₹<?php echo number_format($scholarship['amount']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($scholarship['applied_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $scholarship['status'] == 'approved' ? 'success' : ($scholarship['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <?php echo ucfirst($scholarship['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if($scholarship['status'] == 'applied'): ?>
                                                <button class="btn btn-sm btn-success me-1" onclick="updateScholarshipStatus(<?php echo $scholarship['id']; ?>, 'approved')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="updateScholarshipStatus(<?php echo $scholarship['id']; ?>, 'rejected')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Leave Applications Section -->
        <div id="leave-applications" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-times me-2"></i>Leave Applications</h5>
                        <?php 
                        try {
                            $leave_query = $pdo->prepare("
                                SELECT la.*, s.name as student_name, s.grn, u.username as reviewer_name
                                FROM leave_applications la 
                                JOIN students s ON la.student_id = s.id 
                                LEFT JOIN users u ON la.reviewed_by = u.id
                                WHERE s.hostel_id = ? 
                                ORDER BY la.applied_at DESC
                            ");
                            $leave_query->execute([$hostel_id]);
                            $leave_applications = $leave_query->fetchAll();
                        } catch (Exception $e) {
                            $leave_applications = [];
                        }
                        ?>
                        <span class="badge bg-primary"><?php echo count($leave_applications); ?> Applications</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Leave Type</th>
                                        <th>Duration</th>
                                        <th>Reason</th>
                                        <th>Applied Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($leave_applications)): ?>
                                        <tr><td colspan="7" class="text-center text-muted">No leave applications</td></tr>
                                    <?php else: ?>
                                        <?php foreach($leave_applications as $leave): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $leave['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $leave['grn']; ?></small>
                                            </td>
                                            <td><span class="badge bg-info"><?php echo ucfirst($leave['leave_type']); ?></span></td>
                                            <td>
                                                <?php echo date('M d', strtotime($leave['start_date'])); ?> - 
                                                <?php echo date('M d, Y', strtotime($leave['end_date'])); ?>
                                            </td>
                                            <td><?php echo substr($leave['reason'], 0, 50) . (strlen($leave['reason']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($leave['applied_at'])); ?></td>
                                            <td>
                                                <?php if($leave['status'] == 'pending'): ?>
                                                    <span class="badge bg-warning">🟡 Pending</span>
                                                <?php elseif($leave['status'] == 'approved'): ?>
                                                    <span class="badge bg-success">🟢 Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">🔴 Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewLeaveDetails(<?php echo $leave['id']; ?>, '<?php echo addslashes($leave['student_name']); ?>', '<?php echo $leave['leave_type']; ?>', '<?php echo $leave['start_date']; ?>', '<?php echo $leave['end_date']; ?>', '<?php echo addslashes($leave['reason']); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if($leave['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveLeave(<?php echo $leave['id']; ?>)" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="rejectLeave(<?php echo $leave['id']; ?>)" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Avalon Uploads Section -->
        <div id="avalon-uploads" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-upload me-2"></i>Avalon Uploads</h5>
                        <?php 
                        $avalon_query = $pdo->prepare("
                            SELECT au.*, s.name as student_name, s.grn 
                            FROM avalon_uploads au 
                            JOIN students s ON au.student_id = s.id 
                            WHERE s.hostel_id = ? 
                            ORDER BY au.uploaded_at DESC
                        ");
                        $avalon_query->execute([$hostel_id]);
                        $avalon_uploads = $avalon_query->fetchAll();
                        ?>
                        <span class="badge bg-primary"><?php echo count($avalon_uploads); ?> Files</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Title</th>
                                        <th>File Name</th>
                                        <th>Size</th>
                                        <th>Uploaded Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($avalon_uploads)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No avalon uploads</td></tr>
                                    <?php else: ?>
                                        <?php foreach($avalon_uploads as $avalon): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $avalon['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $avalon['grn']; ?></small>
                                            </td>
                                            <td><?php echo $avalon['title']; ?></td>
                                            <td><?php echo $avalon['file_name']; ?></td>
                                            <td><?php echo round($avalon['file_size'] / 1024, 2); ?> KB</td>
                                            <td><?php echo date('M d, Y H:i', strtotime($avalon['uploaded_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-primary me-1" onclick="downloadFile('<?php echo $avalon['file_path']; ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-info" onclick="viewAvalonDetails(<?php echo $avalon['id']; ?>, '<?php echo addslashes($avalon['description']); ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Feedback Sections -->
        <?php 
        // Create general_feedback table if not exists
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS general_feedback (
                id INT PRIMARY KEY AUTO_INCREMENT,
                student_id INT NOT NULL,
                feedback_category VARCHAR(50) NOT NULL,
                feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                rating INT CHECK (rating >= 1 AND rating <= 5),
                priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
                status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {
            // Table creation error
        }
        
        // Create email_logs table for tracking bulk emails
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sender_id INT NOT NULL,
                recipient_count INT NOT NULL,
                subject VARCHAR(255) NOT NULL,
                sent_count INT DEFAULT 0,
                failed_count INT DEFAULT 0,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
            )");
        } catch (Exception $e) {
            // Table creation error
        }
        
        // Get all feedback types
        $feedback_types = ['complaint', 'compliment', 'suggestion'];
        foreach ($feedback_types as $type):
            // Get feedback for this type from both mess and general feedback
            $mess_feedback_query = $pdo->prepare("
                SELECT mf.*, s.name as student_name, s.grn, 'mess' as source
                FROM mess_feedback mf 
                JOIN students s ON mf.student_id = s.id 
                WHERE s.hostel_id = ? AND mf.feedback_type = ?
                ORDER BY mf.created_at DESC
            ");
            $mess_feedback_query->execute([$hostel_id, $type]);
            $mess_feedback = $mess_feedback_query->fetchAll();
            
            try {
                $general_feedback_query = $pdo->prepare("
                    SELECT gf.*, s.name as student_name, s.grn, 'general' as source
                    FROM general_feedback gf 
                    JOIN students s ON gf.student_id = s.id 
                    WHERE s.hostel_id = ? AND gf.feedback_type = ?
                    ORDER BY gf.created_at DESC
                ");
                $general_feedback_query->execute([$hostel_id, $type]);
                $general_feedback = $general_feedback_query->fetchAll();
            } catch (Exception $e) {
                $general_feedback = [];
            }
            
            $all_feedback = array_merge($mess_feedback, $general_feedback);
        ?>
        <div id="<?php echo $type; ?>s" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-<?php echo $type === 'complaint' ? 'exclamation-triangle' : ($type === 'compliment' ? 'thumbs-up' : 'lightbulb'); ?> me-2"></i><?php echo ucfirst($type); ?>s</h5>
                        <span class="badge bg-<?php echo $type === 'complaint' ? 'danger' : ($type === 'compliment' ? 'success' : 'info'); ?>"><?php echo count($all_feedback); ?> Total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($all_feedback)): ?>
                                        <tr><td colspan="7" class="text-center text-muted">No <?php echo $type; ?>s received</td></tr>
                                    <?php else: ?>
                                        <?php foreach($all_feedback as $feedback): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo $feedback['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $feedback['grn']; ?></small>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo ucfirst($feedback['category'] ?? $feedback['feedback_category'] ?? 'General'); ?></span></td>
                                            <td><?php echo $feedback['subject']; ?></td>
                                            <td>
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= ($feedback['rating'] ?? 0) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_colors = ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success'];
                                                $status_color = $status_colors[$feedback['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($feedback['status']); ?></span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>, '<?php echo addslashes($feedback['message']); ?>', '<?php echo addslashes($feedback['photo_path'] ?? ''); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if($feedback['status'] !== 'resolved'): ?>
                                                    <button class="btn btn-sm btn-info" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed', '<?php echo $feedback['source']; ?>')" title="Mark Reviewed">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved', '<?php echo $feedback['source']; ?>')" title="Mark Resolved">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Mess Feedback Section -->
        <div id="feedback" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-comments me-2"></i>Mess Feedback from Students</h5>
                        <span class="badge bg-primary"><?php echo count($feedback_list); ?> Total</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Student</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Priority</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($feedback_list)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">No feedback received yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($feedback_list as $feedback): ?>
                                        <tr class="<?php echo $feedback['priority'] == 'urgent' ? 'table-danger' : ($feedback['priority'] == 'high' ? 'table-warning' : ''); ?>">
                                            <td><span class="badge bg-primary">#<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
                                            <td>
                                                <strong><?php echo $feedback['student_name']; ?></strong><br>
                                                <small class="text-muted"><?php echo $feedback['grn']; ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                $type_colors = ['complaint' => 'danger', 'suggestion' => 'warning', 'compliment' => 'success'];
                                                $color = $type_colors[$feedback['feedback_type'] ?? 'complaint'] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($feedback['feedback_type'] ?? 'Complaint'); ?></span>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo $feedback['category']; ?></span></td>
                                            <td><?php echo $feedback['subject'] ?? 'Mess Feedback'; ?></td>
                                            <td>
                                                <?php 
                                                $priority_colors = ['urgent' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];
                                                $priority_color = $priority_colors[$feedback['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $priority_color; ?>"><?php echo ucfirst($feedback['priority']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= ($feedback['rating'] ?? 0) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-2"><?php echo $feedback['rating'] ?? 0; ?>/5</span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_colors = ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success'];
                                                $status_color = $status_colors[$feedback['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($feedback['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-primary" onclick="viewFeedback(<?php echo $feedback['id']; ?>, '<?php echo addslashes($feedback['message'] ?? 'No message'); ?>', '<?php echo addslashes($feedback['photo_path'] ?? ''); ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-info" onclick="openMessageModal('student', <?php echo $feedback['student_id']; ?>, '<?php echo addslashes($feedback['student_name']); ?>')" title="Send Message">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <?php if($feedback['status'] == 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed')" title="Mark Reviewed">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved')" title="Mark Resolved">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Feedback Modal -->
    <div class="modal fade" id="viewFeedbackModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="feedbackMessage"></p>
                    <div id="feedbackPhotoDiv" style="display: none;">
                        <hr>
                        <h6>Attached Photo:</h6>
                        <img id="feedbackPhotoImg" src="" class="img-fluid" style="max-height: 300px; cursor: pointer;" onclick="openPhotoModal(this.src)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Viewer Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feedback Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalPhotoImg" src="" class="img-fluid" style="max-width: 100%; max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Leave Details Modal -->
    <div class="modal fade" id="viewLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Leave Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Student:</strong> <span id="modalStudentName"></span></p>
                            <p><strong>Leave Type:</strong> <span id="modalLeaveType"></span></p>
                            <p><strong>Start Date:</strong> <span id="modalStartDate"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>End Date:</strong> <span id="modalEndDate"></span></p>
                            <p><strong>Duration:</strong> <span id="modalDuration"></span></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p><strong>Reason:</strong></p>
                        <div class="border p-3 bg-light rounded" id="modalReason"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal fade" id="viewComplaintModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complaint Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="complaintDescription"></p>
                    <div id="complaintPhoto" style="display: none;">
                        <hr>
                        <h6>Attached Photo:</h6>
                        <img id="complaintPhotoImg" src="" class="img-fluid" style="max-height: 300px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Avalon Details Modal -->
    <div class="modal fade" id="viewAvalonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Avalon Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="avalonDescription"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Staff Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Search Student</label>
                            <input type="text" class="form-control" id="staffStudentSearch" placeholder="Type student name or GRN to search...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select class="form-control" name="student_id" id="staffStudentSelect" required>
                                <option value="">Choose a student to make staff...</option>
                                <?php foreach($students_list as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" data-name="<?php echo strtolower($student['name']); ?>" data-grn="<?php echo $student['grn']; ?>">
                                        <?php echo $student['name']; ?> (<?php echo $student['grn']; ?>) - <?php echo $student['course'] ?? 'N/A'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Staff Role</label>
                            <select class="form-control" name="staff_role" required>
                                <option value="">Select role...</option>
                                <option value="mess_head">Mess Head</option>
                                <option value="library_head">Library Head</option>
                                <option value="health_staff">Health Staff</option>
                                <option value="vvk_staff">VVK Staff</option>
                                <option value="placement_staff">Placement Staff</option>
                                <option value="ed_cell_staff">Ed Cell Staff</option>
                                <option value="scholarship_staff">Scholarship Staff</option>
                                <option value="student_head">Student Head</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_staff" class="btn btn-success">Add Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Individual Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" name="student_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (Optional)</label>
                            <input type="email" class="form-control" name="student_email" placeholder="student@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="student_contact" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" name="student_course" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-control" name="student_year" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_individual_student" class="btn btn-primary">Add Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Student Credentials Modal -->
    <div class="modal fade" id="viewCredentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Student Login Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Share these credentials with the student for login
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" class="form-control" id="credentialStudentName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GRN (Username)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialGRN" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('credentialGRN')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="credentialPassword" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('credentialPassword')">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="generateNewPassword()">
                                <i class="fas fa-sync"></i> New
                            </button>
                        </div>
                        <small class="text-muted">Student can change this after first login</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="sendCredentialsEmail()">
                        <i class="fas fa-envelope me-1"></i>Email Credentials
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Staff Credentials Modal -->
    <div class="modal fade" id="viewStaffCredentialsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Staff Login Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-key me-2"></i>Staff can use these credentials to login from student login page
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Staff Name</label>
                        <input type="text" class="form-control" id="staffCredentialName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">GRN (Username)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="staffCredentialGRN" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('staffCredentialGRN')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Staff Password</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="staffCredentialPassword" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyToClipboard('staffCredentialPassword')">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted">Use this password for staff dashboard access</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Message Modal -->
    <div class="modal fade" id="sendMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="messageForm">
                        <input type="hidden" id="recipientType">
                        <input type="hidden" id="recipientId">
                        <div class="mb-3">
                            <label class="form-label">To:</label>
                            <input type="text" class="form-control" id="recipientName" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="messageSubject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message:</label>
                            <textarea class="form-control" id="messageContent" rows="5" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="sendMessage()">Send Message</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Update your login password for security
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmPassword" minlength="6" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Rector Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="profileForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="mb-3">
                                    <div id="profilePhotoPreview" class="mb-3">
                                        <?php if(isset($rector_info['profile_photo']) && $rector_info['profile_photo']): ?>
                                            <img src="../<?php echo $rector_info['profile_photo']; ?>" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 120px; height: 120px;">
                                                <i class="fas fa-user text-muted" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <input type="file" class="form-control" id="profilePhoto" accept="image/*" style="display: none;">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('profilePhoto').click()">
                                        <i class="fas fa-camera me-1"></i>Change Photo
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullName" value="<?php echo isset($rector_info['full_name']) ? $rector_info['full_name'] : ''; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo $_SESSION['username']; ?>" readonly>
                                    <small class="text-muted">Email cannot be changed</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" id="contact" value="<?php echo isset($rector_info['contact']) ? $rector_info['contact'] : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" value="<?php echo isset($rector_info['location']) ? $rector_info['location'] : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Assigned Hostel</label>
                                    <input type="text" class="form-control" value="<?php echo isset($rector_info['hostel_name']) ? $rector_info['hostel_name'] : 'Not Assigned'; ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Email Modal -->
    <div class="modal fade" id="bulkModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope-bulk me-2"></i>Send Email to Selected Students</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bulkForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle me-2"></i>Sending to <strong id="recipientCount">0</strong> students
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Recipients:</label>
                            <div id="recipientList" class="border rounded p-2 bg-light" style="max-height: 100px; overflow-y: auto;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="bulkSubject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message:</label>
                            <textarea class="form-control" id="bulkMessage" rows="6" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachments (optional):</label>
                            <input type="file" class="form-control" id="bulkAttachments" multiple>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Email to Student Modal -->
    <div class="modal fade" id="sendAdmissionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Send Email to Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="admissionFormSender" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">From:</label>
                                    <input type="email" class="form-control" id="rectorEmail" value="<?php echo $_SESSION['username']; ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">To Email:</label>
                                    <input type="email" class="form-control" id="studentEmail" required placeholder="Enter recipient email">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject:</label>
                            <input type="text" class="form-control" id="emailSubject" required placeholder="Enter email subject">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message:</label>
                            <textarea class="form-control" id="emailMessage" rows="6" required placeholder="Type your message here..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Attachments:</label>
                            <div class="btn-group w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('emailAttachments').click()">
                                    <i class="fas fa-paperclip me-1"></i>Files
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('emailAttachments').setAttribute('accept', 'image/*'); document.getElementById('emailAttachments').click()">
                                    <i class="fas fa-image me-1"></i>Photos
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleLinksInput('emailAttachmentLinks')">
                                    <i class="fas fa-link me-1"></i>Links
                                </button>
                            </div>
                            <input type="file" class="form-control mt-2" id="emailAttachments" multiple style="display: none;" onchange="showSelectedFiles(this, 'emailFilesList')">
                            <div id="emailFilesList" class="mt-2"></div>
                            <textarea class="form-control mt-2" id="emailAttachmentLinks" rows="3" placeholder="Enter links, one per line" style="display: none;"></textarea>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Student Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editStudentForm">
                    <input type="hidden" id="editStudentId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student Name</label>
                            <input type="text" class="form-control" id="editStudentName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editStudentEmail">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" id="editStudentContact">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" id="editStudentCourse">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Year</label>
                            <select class="form-control" id="editStudentYear">
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="editStudentRoom">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Student</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cog me-2"></i>Settings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="settingsForm">
                    <div class="modal-body">
                        <h6 class="mb-3"><i class="fas fa-bell me-2"></i>Notifications</h6>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="emailNotifications" <?php echo (isset($rector_info['email_notifications']) ? $rector_info['email_notifications'] : 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emailNotifications">
                                    Email Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive notifications via email</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="smsNotifications" <?php echo (isset($rector_info['sms_notifications']) ? $rector_info['sms_notifications'] : 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="smsNotifications">
                                    SMS Notifications
                                </label>
                            </div>
                            <small class="text-muted">Receive notifications via SMS</small>
                        </div>
                        <hr>
                        <h6 class="mb-3"><i class="fas fa-palette me-2"></i>Appearance</h6>
                        <div class="mb-3">
                            <label class="form-label">Theme</label>
                            <select class="form-control" id="theme">
                                <option value="light" <?php echo (isset($rector_info['theme']) ? $rector_info['theme'] : 'light') === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo (isset($rector_info['theme']) ? $rector_info['theme'] : 'light') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="auto" <?php echo (isset($rector_info['theme']) ? $rector_info['theme'] : 'light') === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let currentStudentGRN = '';
        let currentStudentName = '';
        let currentStudentEmail = '';
        
        // View Student Credentials
        function viewStudentCredentials(grn, name, email) {
            currentStudentGRN = grn;
            currentStudentName = name;
            currentStudentEmail = email;
            
            document.getElementById('credentialStudentName').value = name;
            document.getElementById('credentialGRN').value = grn;
            document.getElementById('credentialPassword').value = 'Loading...';
            
            fetch('../handlers/get_student_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'grn=' + encodeURIComponent(grn)
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('credentialPassword').value = data.success ? data.password : 'Error';
            })
            .catch(() => {
                document.getElementById('credentialPassword').value = 'Error';
            });
            
            new bootstrap.Modal(document.getElementById('viewCredentialsModal')).show();
        }
        
        // Edit Student
        function editStudent(id, name, email, contact, course, year, room) {
            document.getElementById('editStudentId').value = id;
            document.getElementById('editStudentName').value = name;
            document.getElementById('editStudentEmail').value = email || '';
            document.getElementById('editStudentContact').value = contact || '';
            document.getElementById('editStudentCourse').value = course || '';
            document.getElementById('editStudentYear').value = year || 1;
            document.getElementById('editStudentRoom').value = room || '';
            new bootstrap.Modal(document.getElementById('editStudentModal')).show();
        }
        
        // Open Email Modal
        function openEmailModal(name, email) {
            document.getElementById('studentEmail').value = email;
            document.getElementById('emailSubject').value = 'Message from VSS Hostel';
            document.getElementById('emailMessage').value = '';
            new bootstrap.Modal(document.getElementById('sendAdmissionModal')).show();
        }
        
        // Generate New Password
        function generateNewPassword() {
            if (!confirm('Generate new password?')) return;
            document.getElementById('credentialPassword').value = 'Generating...';
            fetch('../handlers/get_student_password.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'grn=' + encodeURIComponent(currentStudentGRN) + '&generate_new=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('credentialPassword').value = data.password;
                    alert('New password: ' + data.password);
                }
            });
        }
        
        // Send Credentials Email
        function sendCredentialsEmail() {
            const grn = document.getElementById('credentialGRN').value;
            const password = document.getElementById('credentialPassword').value;
            const formData = new FormData();
            formData.append('action', 'send_custom_email');
            formData.append('student_email', currentStudentEmail);
            formData.append('student_name', currentStudentName);
            formData.append('subject', 'Your Login Credentials');
            formData.append('message', 'Username: ' + grn + '\nPassword: ' + password);
            formData.append('include_admission_form', '0');
            formData.append('attachment_links', '');
            
            fetch('../handlers/send_custom_email.php', {method: 'POST', body: formData})
            .then(response => response.json())
            .then(data => alert(data.success ? 'Email sent!' : 'Error: ' + data.message));
        }
        
        // Copy to Clipboard
        function copyToClipboard(id) {
            document.getElementById(id).select();
            document.execCommand('copy');
            alert('Copied!');
        }
        
        // Toggle Links Input
        function toggleLinksInput(id) {
            const el = document.getElementById(id);
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        }
        
        // Show Selected Files
        function showSelectedFiles(input, listId) {
            const list = document.getElementById(listId);
            list.innerHTML = '';
            if (input.files.length > 0) {
                list.innerHTML = '<small>' + input.files.length + ' file(s) selected</small>';
            }
        }
        
        // View Staff Credentials
        function viewStaffCredentials(staffId, name) {
            document.getElementById('staffCredentialName').value = name;
            document.getElementById('staffCredentialGRN').value = 'Loading...';
            document.getElementById('staffCredentialPassword').value = 'Loading...';
            
            fetch('../handlers/get_staff_credentials.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'staff_id=' + encodeURIComponent(staffId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('staffCredentialGRN').value = data.grn;
                    document.getElementById('staffCredentialPassword').value = data.password;
                } else {
                    document.getElementById('staffCredentialGRN').value = 'Not available';
                    document.getElementById('staffCredentialPassword').value = 'Not available';
                }
            })
            .catch(() => {
                document.getElementById('staffCredentialGRN').value = 'Error';
                document.getElementById('staffCredentialPassword').value = 'Error';
            });
            
            new bootstrap.Modal(document.getElementById('viewStaffCredentialsModal')).show();
        }
        
        // Open Message Modal for Staff
        function openMessageModal(type, id, name) {
            document.getElementById('recipientType').value = type;
            document.getElementById('recipientId').value = id;
            document.getElementById('recipientName').value = name;
            document.getElementById('messageSubject').value = '';
            document.getElementById('messageContent').value = '';
            new bootstrap.Modal(document.getElementById('sendMessageModal')).show();
        }
        
        // Remove Staff
        function removeStaff(staffId, name) {
            if (!confirm('Remove ' + name + ' from staff? This will delete their account.')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_staff');
            formData.append('staff_id', staffId);
            
            fetch(window.location.href, {method: 'POST', body: formData})
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Staff removed successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(() => alert('Error removing staff'));
        }
        
        // Export Students
        function exportStudents() {
            window.location.href = '../handlers/export_students.php';
        }
        
        // Handle Edit Student Form
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('editStudentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                
                const formData = new FormData();
                formData.append('action', 'update_student');
                formData.append('student_id', document.getElementById('editStudentId').value);
                formData.append('name', document.getElementById('editStudentName').value);
                formData.append('email', document.getElementById('editStudentEmail').value);
                formData.append('contact', document.getElementById('editStudentContact').value);
                formData.append('course', document.getElementById('editStudentCourse').value);
                formData.append('year', document.getElementById('editStudentYear').value);
                formData.append('room_no', document.getElementById('editStudentRoom').value);
                
                fetch(window.location.href, {method: 'POST', body: formData})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Student updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Update Student';
                    }
                })
                .catch(() => {
                    alert('Error updating student');
                    btn.disabled = false;
                    btn.innerHTML = 'Update Student';
                });
            });
            
            // Handle Send Email Form
            document.getElementById('admissionFormSender').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                
                const formData = new FormData();
                formData.append('action', 'send_custom_email');
                formData.append('student_email', document.getElementById('studentEmail').value);
                formData.append('student_name', 'Student');
                formData.append('subject', document.getElementById('emailSubject').value);
                formData.append('message', document.getElementById('emailMessage').value);
                formData.append('include_admission_form', '0');
                formData.append('attachment_links', document.getElementById('emailAttachmentLinks').value || '');
                
                const files = document.getElementById('emailAttachments').files;
                for (let i = 0; i < files.length; i++) {
                    formData.append('attachments[]', files[i]);
                }
                
                fetch('../handlers/send_custom_email.php', {method: 'POST', body: formData})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Email sent successfully!');
                        bootstrap.Modal.getInstance(document.getElementById('sendAdmissionModal')).hide();
                        this.reset();
                    } else {
                        alert('Error: ' + data.message);
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
                })
                .catch(() => {
                    alert('Error sending email');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
                });
            });
            
            // Handle Send Message Form (for staff)
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    alert('Message functionality coming soon!');
                });
            }
        });
    </script>
    <script src="rector_functions.js"></script>
    <script src="rector_complete.js"></script>
    <script>
        function openChangePasswordModal() {
            new bootstrap.Modal(document.getElementById('changePasswordModal')).show();
        }
        
        function openProfileModal() {
            new bootstrap.Modal(document.getElementById('profileModal')).show();
        }
        
        function openSettingsModal() {
            new bootstrap.Modal(document.getElementById('settingsModal')).show();
        }
        
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_password', currentPassword);
            formData.append('new_password', newPassword);
            
            fetch('../handlers/rector_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Password changed successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                    this.reset();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error changing password');
            });
        });
        
        // Profile form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_profile');
            formData.append('full_name', document.getElementById('fullName').value);
            formData.append('contact', document.getElementById('contact').value);
            formData.append('location', document.getElementById('location').value);
            
            fetch('../handlers/rector_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Profile updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error updating profile');
            });
        });
        
        // Profile photo upload
        document.getElementById('profilePhoto').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePhotoPreview').innerHTML = 
                        '<img src="' + e.target.result + '" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">';
                };
                reader.readAsDataURL(file);
                
                // Upload image
                const formData = new FormData();
                formData.append('action', 'upload_photo');
                formData.append('profile_photo', file);
                
                fetch('../handlers/rector_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Photo uploaded successfully!');
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Error uploading photo');
                });
            }
        });
        
        // Settings form submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_settings');
            formData.append('email_notifications', document.getElementById('emailNotifications').checked ? '1' : '0');
            formData.append('sms_notifications', document.getElementById('smsNotifications').checked ? '1' : '0');
            formData.append('theme', document.getElementById('theme').value);
            
            fetch('../handlers/rector_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Settings updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('settingsModal')).hide();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error updating settings');
            });
        });
        
        // View feedback function
        function viewFeedback(id, message, photoPath) {
            document.getElementById('feedbackMessage').textContent = message;
            
            const photoDiv = document.getElementById('feedbackPhotoDiv');
            const photoImg = document.getElementById('feedbackPhotoImg');
            
            if (photoPath && photoPath.trim() !== '') {
                photoImg.src = photoPath;
                photoDiv.style.display = 'block';
            } else {
                photoDiv.style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
        }
        
        // Open photo in modal
        function openPhotoModal(src) {
            document.getElementById('modalPhotoImg').src = src;
            new bootstrap.Modal(document.getElementById('photoModal')).show();
        }
        
        // Update feedback status
        function updateFeedbackStatus(id, status, source) {
            const formData = new FormData();
            formData.append('action', source === 'mess' ? 'update_feedback_status' : 'update_general_feedback_status');
            formData.append('feedback_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Feedback status updated!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error updating feedback status');
            });
        }
        
        // Update scholarship status
        function updateScholarshipStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_scholarship_status');
            formData.append('scholarship_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Scholarship status updated!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
        
        // View leave application details
        function viewLeaveDetails(id, studentName, leaveType, startDate, endDate, reason) {
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalLeaveType').textContent = leaveType.charAt(0).toUpperCase() + leaveType.slice(1);
            document.getElementById('modalStartDate').textContent = new Date(startDate).toLocaleDateString();
            document.getElementById('modalEndDate').textContent = new Date(endDate).toLocaleDateString();
            document.getElementById('modalReason').textContent = reason;
            
            // Calculate duration
            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            document.getElementById('modalDuration').textContent = diffDays + ' day(s)';
            
            new bootstrap.Modal(document.getElementById('viewLeaveModal')).show();
        }
        
        // Approve leave application
        function approveLeave(id) {
            if (confirm('APPROVE this leave application?')) {
                updateLeaveStatus(id, 'approved');
            }
        }
        
        // Reject leave application
        function rejectLeave(id) {
            if (confirm('REJECT this leave application?')) {
                updateLeaveStatus(id, 'rejected');
            }
        }
        
        // Update leave application status
        function updateLeaveStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_leave_status');
            formData.append('leave_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Leave application ' + status + ' successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            });
        }
        
        // View complaint details
        function viewComplaint(id, description, photoPath) {
            document.getElementById('complaintDescription').textContent = description || 'No description provided';
            
            const photoDiv = document.getElementById('complaintPhoto');
            const photoImg = document.getElementById('complaintPhotoImg');
            
            if (photoPath && photoPath.trim() !== '') {
                photoImg.src = photoPath;
                photoDiv.style.display = 'block';
            } else {
                photoDiv.style.display = 'none';
            }
            
            new bootstrap.Modal(document.getElementById('viewComplaintModal')).show();
        }
        
        // Update complaint status
        function updateComplaintStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_complaint_status');
            formData.append('complaint_id', id);
            formData.append('status', status);
            
            fetch('../handlers/dashboard_actions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Complaint status updated!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error updating complaint status');
            });
        }
        
        // View avalon details
        function viewAvalonDetails(id, description) {
            document.getElementById('avalonDescription').textContent = description || 'No description provided';
            new bootstrap.Modal(document.getElementById('viewAvalonModal')).show();
        }
        
        // Download file
        function downloadFile(filePath) {
            window.open(filePath, '_blank');
        }
        
        // Open message modal
        window.openMessageModal = function(type, id, name) {
            document.getElementById('recipientType').value = type;
            document.getElementById('recipientId').value = id;
            document.getElementById('recipientName').value = name;
            document.getElementById('messageSubject').value = '';
            document.getElementById('messageContent').value = '';
            new bootstrap.Modal(document.getElementById('sendMessageModal')).show();
        }
        
        // View student credentials
        // Functions moved to rector_functions.js
        
        // View staff credentials
        window.viewStaffCredentials = function(staffId, name) {
            document.getElementById('staffCredentialName').value = name;
            
            // Fetch staff credentials from server
            fetch('../handlers/get_staff_credentials.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'staff_id=' + encodeURIComponent(staffId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('staffCredentialGRN').value = data.grn;
                    document.getElementById('staffCredentialPassword').value = data.password;
                } else {
                    document.getElementById('staffCredentialGRN').value = 'Not available';
                    document.getElementById('staffCredentialPassword').value = 'Not available';
                }
            })
            .catch(error => {
                document.getElementById('staffCredentialGRN').value = 'Error loading';
                document.getElementById('staffCredentialPassword').value = 'Error loading';
            });
            
            new bootstrap.Modal(document.getElementById('viewStaffCredentialsModal')).show();
        }
        
        // copyToClipboard function moved to rector_functions.js
        
        // Export students
        window.exportStudents = function() {
            window.location.href = '../handlers/export_students.php';
        }
        
        // Clear old data
        function clearOldData() {
            if (confirm('Are you sure you want to remove ALL student data? This will delete all students from your hostel. This action cannot be undone.')) {
                window.location.href = window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'clear_old_data=1';
            }
        }
        
        // Remove duplicates
        function removeDuplicates() {
            if (confirm('Are you sure you want to remove duplicate students? This will keep only the first occurrence of each GRN. This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="remove_duplicates" value="1">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Send message
        function sendMessage() {
            const formData = new FormData();
            formData.append('action', 'send_email');
            formData.append('recipient_type', document.getElementById('recipientType').value);
            formData.append('recipient_id', document.getElementById('recipientId').value);
            formData.append('subject', document.getElementById('messageSubject').value);
            formData.append('message', document.getElementById('messageContent').value);
            
            fetch('../handlers/send_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Message sent successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('sendMessageModal')).hide();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
            });
        }
        

        
        // Staff student search functionality
        document.getElementById('staffStudentSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const select = document.getElementById('staffStudentSelect');
            const options = select.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const name = option.getAttribute('data-name') || '';
                const grn = option.getAttribute('data-grn') || '';
                
                if (name.includes(searchTerm) || grn.toLowerCase().includes(searchTerm)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        });
        
        // File upload validation and preview with detailed feedback
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (file) {
                fileName.textContent = file.name;
                const sizeKB = (file.size / 1024).toFixed(2);
                const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                fileSize.textContent = sizeKB + ' KB' + (sizeMB > 1 ? ' (' + sizeMB + ' MB)' : '');
                
                // Validate file type
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    alert('❌ Invalid file type\n\nPlease select a CSV file only.\nCurrent file: ' + file.name + '\nRequired extension: .csv');
                    this.value = '';
                    fileName.textContent = 'None';
                    fileSize.textContent = '0 KB';
                    uploadBtn.disabled = true;
                    return;
                }
                
                // Validate file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    alert('❌ File size too large\n\nMaximum allowed size: 5MB\nYour file size: ' + sizeMB + ' MB\n\nPlease reduce the file size or split into smaller files.');
                    this.value = '';
                    fileName.textContent = 'None';
                    fileSize.textContent = '0 KB';
                    uploadBtn.disabled = true;
                    return;
                }
                
                // Check if file is empty
                if (file.size === 0) {
                    alert('❌ Empty file\n\nThe selected file appears to be empty.\nPlease select a valid CSV file with student data.');
                    this.value = '';
                    fileName.textContent = 'None';
                    fileSize.textContent = '0 KB';
                    uploadBtn.disabled = true;
                    return;
                }
                
                // File is valid
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload ' + file.name;
                
                // Try to read and validate CSV structure
                if (window.FileReader) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const text = e.target.result;
                        const lines = text.split('\n');
                        if (lines.length > 0) {
                            const headers = lines[0].split(',');
                            if (headers.length < 17) {
                                console.warn('CSV may have insufficient columns. Expected: 17, Found: ' + headers.length);
                            }
                        }
                    };
                    reader.readAsText(file.slice(0, 1024)); // Read first 1KB to check structure
                }
            } else {
                fileName.textContent = 'None';
                fileSize.textContent = '0 KB';
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-upload me-2"></i>Upload Students';
            }
        });
        
        // CSV upload form submission with progress and validation
        document.getElementById('csvUploadForm').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];
            
            // Final validation before submission
            if (!file) {
                e.preventDefault();
                alert('❌ Please select a CSV file first');
                return false;
            }
            
            if (!file.name.toLowerCase().endsWith('.csv')) {
                e.preventDefault();
                alert('❌ Please select a CSV file (.csv extension required)');
                return false;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                e.preventDefault();
                alert('❌ File size too large. Maximum allowed size is 5MB');
                return false;
            }
            
            // Show progress
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadBtn = document.getElementById('uploadBtn');
            const progressBar = uploadProgress.querySelector('.progress-bar');
            
            uploadProgress.style.display = 'block';
            uploadBtn.disabled = true;
            uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing CSV...';
            
            // Simulate progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
            }, 200);
            
            // Complete progress after 2 seconds
            setTimeout(() => {
                clearInterval(progressInterval);
                progressBar.style.width = '100%';
            }, 2000);
            
            // Add timestamp to prevent caching issues
            const timestamp = new Date().getTime();
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'upload_timestamp';
            hiddenInput.value = timestamp;
            this.appendChild(hiddenInput);
        });
        
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Add active class to navigation items on scroll
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('[id]');
            const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
        
        // Toggle debug info
        function toggleDebugInfo() {
            const debugDiv = document.getElementById('debugInfoDiv');
            if (debugDiv) {
                debugDiv.style.display = debugDiv.style.display === 'none' ? 'block' : 'none';
            } else {
                showDebugInfo();
            }
        }
        
        function showDebugInfo() {
            const debugInfo = `
                <div id="debugInfoDiv" class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-bug me-2"></i>
                    <strong>Debug Information:</strong>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>System Info:</h6>
                            <ul class="mb-0">
                                <li>PHP Version: <?php echo PHP_VERSION; ?></li>
                                <li>Upload Max Filesize: <?php echo ini_get('upload_max_filesize'); ?></li>
                                <li>Post Max Size: <?php echo ini_get('post_max_size'); ?></li>
                                <li>Max Execution Time: <?php echo ini_get('max_execution_time'); ?>s</li>
                                <li>Memory Limit: <?php echo ini_get('memory_limit'); ?></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Directory Info:</h6>
                            <ul class="mb-0">
                                <li>Uploads Directory: <?php echo realpath('../uploads/students/') ?: 'Not found'; ?></li>
                                <li>Directory Writable: <?php echo is_writable('../uploads/students/') ? 'Yes' : 'No'; ?></li>
                                <li>Current Hostel ID: <?php echo $hostel_id; ?></li>
                                <li>CSV Hostel Name: <?php echo $csv_hostel_name ?: 'None'; ?></li>
                                <li>Session User ID: <?php echo $_SESSION['user_id']; ?></li>
                            </ul>
                        </div>
                    </div>
                    <button type="button" class="btn-close" onclick="document.getElementById('debugInfoDiv').remove()"></button>
                </div>
            `;
            document.querySelector('.container-fluid').insertAdjacentHTML('afterbegin', debugInfo);
        }
        
        // Test CSV upload functionality
        function testCSVUpload() {
            const tests = [
                'PHP file upload settings',
                'Directory permissions', 
                'Database connection',
                'Required table columns',
                'Session variables'
            ];
            
            let results = 'CSV Upload Test Results:\n\n';
            
            // Test 1: Check if file input exists
            const fileInput = document.getElementById('csvFile');
            results += '1. File Input Element: ' + (fileInput ? '✅ Found' : '❌ Missing') + '\n';
            
            // Test 2: Check form attributes
            const form = document.getElementById('csvUploadForm');
            if (form) {
                results += '2. Form Method: ' + (form.method.toLowerCase() === 'post' ? '✅ POST' : '❌ Not POST') + '\n';
                results += '3. Form Enctype: ' + (form.enctype === 'multipart/form-data' ? '✅ Correct' : '❌ Missing multipart') + '\n';
            } else {
                results += '2-3. Form Element: ❌ Missing\n';
            }
            
            // Test 3: Check current page parameters
            const urlParams = new URLSearchParams(window.location.search);
            results += '4. Debug Mode: ' + (urlParams.get('debug') === '1' ? '✅ Enabled' : '⚠️ Disabled (add ?debug=1 to URL)') + '\n';
            
            // Test 4: Check browser support
            results += '5. File API Support: ' + (window.File && window.FileReader ? '✅ Supported' : '❌ Not supported') + '\n';
            
            // Test 5: Check sample file
            results += '6. Sample CSV Available: ' + (document.querySelector('a[href*="sample_students.csv"]') ? '✅ Yes' : '❌ No') + '\n';
            
            results += '\nRecommendations:\n';
            results += '• Enable debug mode by adding ?debug=1 to the URL\n';
            results += '• Download and use the sample CSV format\n';
            results += '• Ensure your CSV has exactly 17 columns\n';
            results += '• Check file size is under 5MB\n';
            results += '• Verify file extension is .csv\n';
            
            alert(results);
        }
        
        // Handle email sender
        document.getElementById('admissionFormSender').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            submitBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'send_custom_email');
            formData.append('student_email', document.getElementById('studentEmail').value);
            formData.append('student_name', 'Recipient');
            formData.append('subject', document.getElementById('emailSubject').value);
            formData.append('message', document.getElementById('emailMessage').value);

            formData.append('attachment_links', document.getElementById('emailAttachmentLinks').value);
            
            // Add file attachments
            const files = document.getElementById('emailAttachments').files;
            for (let i = 0; i < files.length; i++) {
                formData.append('attachments[]', files[i]);
            }
            
            fetch('../handlers/send_custom_email.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Email sent successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('sendAdmissionModal')).hide();
                    this.reset();
                    document.getElementById('rectorEmail').value = '<?php echo $_SESSION['username']; ?>';
                    document.getElementById('emailAttachmentLinks').value = '';
                } else {
                    alert('❌ Error: ' + data.message);
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                alert('❌ Error sending email');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Edit student function
        // editStudent function moved to rector_functions.js
        
        // Handle edit student form submission
        document.getElementById('editStudentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'update_student');
            formData.append('student_id', document.getElementById('editStudentId').value);
            formData.append('name', document.getElementById('editStudentName').value);
            formData.append('email', document.getElementById('editStudentEmail').value);
            formData.append('contact', document.getElementById('editStudentContact').value);
            formData.append('course', document.getElementById('editStudentCourse').value);
            formData.append('year', document.getElementById('editStudentYear').value);
            formData.append('room_no', document.getElementById('editStudentRoom').value);
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitBtn.disabled = true;
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Student updated successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('editStudentModal')).hide();
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            })
            .catch(error => {
                alert('❌ Error updating student');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Remove staff function
        window.removeStaff = function(staffId, staffName) {
            if (confirm('Are you sure you want to remove ' + staffName + ' from the hostel staff?\n\nThis will also delete their user account and cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'remove_staff');
                formData.append('staff_id', staffId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Staff removed successfully!');
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('❌ Error removing staff');
                });
            }
        }
        
        // Student search functionality
        function filterStudents() {
            const searchTerm = document.getElementById('studentSearch').value.toLowerCase().trim();
            const table = document.getElementById('studentsTable');
            const rows = table.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update count
            const countElement = document.getElementById('searchCount');
            if (countElement) {
                if (searchTerm) {
                    countElement.textContent = `Showing ${visibleCount} of ${rows.length} students`;
                } else {
                    countElement.textContent = `Total: ${rows.length} students`;
                }
            }
        }
        
        // Open email modal with pre-filled student info - Fixed
        // openEmailModal function moved to rector_functions.js
        
        // toggleLinksInput function moved to rector_functions.js
        
        // showSelectedFiles function moved to rector_functions.js
        
        // All bulk email functions are in rector_functions.js
        
        // Initialize upload button state
        const uploadBtn = document.getElementById('uploadBtn');
        const csvFile = document.getElementById('csvFile');
        
        if (uploadBtn && csvFile) {
            uploadBtn.disabled = !csvFile.files.length;
            }
            
            // Auto-hide alerts after 10 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-dismissible');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 10000);
        });
    </script>
    
    <script>
        // Bulk email functions
        let bulkMode = false;
        function toggleBulkMode() {
            bulkMode = !bulkMode;
            document.getElementById('bulkControls').style.display = bulkMode ? 'block' : 'none';
            document.getElementById('chkHeader').style.display = bulkMode ? 'table-cell' : 'none';
            document.querySelectorAll('.chkCell').forEach(el => el.style.display = bulkMode ? 'table-cell' : 'none');
            if (!bulkMode) deselectAll();
        }
        
        function selectAll() {
            document.querySelectorAll('.studentChk').forEach(chk => {
                if (chk.closest('tr').style.display !== 'none') chk.checked = true;
            });
            document.getElementById('selectAllChk').checked = true;
            updateCount();
        }
        
        function deselectAll() {
            document.querySelectorAll('.studentChk').forEach(chk => chk.checked = false);
            document.getElementById('selectAllChk').checked = false;
            updateCount();
        }
        
        function toggleAll() {
            const checked = document.getElementById('selectAllChk').checked;
            document.querySelectorAll('.studentChk').forEach(chk => {
                if (chk.closest('tr').style.display !== 'none') chk.checked = checked;
            });
            updateCount();
        }
        
        function updateCount() {
            const count = document.querySelectorAll('.studentChk:checked').length;
            document.getElementById('count').textContent = count;
            document.getElementById('sendBtn').disabled = count === 0;
        }
        
        function sendToSelected() {
            const selected = [];
            document.querySelectorAll('.studentChk:checked').forEach(chk => {
                const email = chk.dataset.email;
                if (email && email !== 'No email' && email.includes('@')) {
                    selected.push({id: chk.dataset.id, name: chk.dataset.name, email: email});
                }
            });
            
            if (selected.length === 0) {
                alert('No students with valid email addresses selected');
                return;
            }
            
            document.getElementById('recipientCount').textContent = selected.length;
            document.getElementById('recipientList').innerHTML = selected.map(s => `<small class="d-block">${s.name} (${s.email})</small>`).join('');
            window.selectedStudents = selected;
            new bootstrap.Modal(document.getElementById('bulkModal')).show();
        }
        
        document.getElementById('bulkForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
            
            const subject = document.getElementById('bulkSubject').value;
            const message = document.getElementById('bulkMessage').value;
            const files = document.getElementById('bulkAttachments').files;
            
            let sent = 0, failed = 0;
            
            for (const student of window.selectedStudents) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_custom_email');
                    formData.append('student_email', student.email);
                    formData.append('student_name', student.name);
                    formData.append('subject', subject);
                    formData.append('message', message);
                    formData.append('include_admission_form', '0');
                    formData.append('attachment_links', '');
                    
                    for (let i = 0; i < files.length; i++) {
                        formData.append('attachments[]', files[i]);
                    }
                    
                    const response = await fetch('../handlers/send_custom_email.php', {method: 'POST', body: formData});
                    const data = await response.json();
                    
                    if (data.success) sent++;
                    else failed++;
                } catch (error) {
                    failed++;
                }
            }
            
            alert(`✅ Email sent!\nSent: ${sent}\nFailed: ${failed}`);
            bootstrap.Modal.getInstance(document.getElementById('bulkModal')).hide();
            toggleBulkMode();
            document.getElementById('bulkForm').reset();
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Email';
        });
    </script>
</body>
</html>
