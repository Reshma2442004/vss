<?php
session_start();
require_once '../config/database.php';

// PREVENT ALL AUTOMATIC PROCESSING
if (!isset($_POST['upload_rectors']) && !isset($_GET['action'])) {
    // Clear any processing flags on normal page load
    unset($_SESSION['processing_lock']);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_hostel':
            $stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
            exit;
            
        case 'get_staff':
            $stmt = $pdo->prepare("SELECT st.*, u.username FROM staff st JOIN users u ON st.user_id = u.id WHERE st.id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
            exit;
            
        case 'delete_staff':
            try {
                $pdo->beginTransaction();
                
                // Get user_id first
                $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $user_id = $stmt->fetchColumn();
                
                // Delete staff record
                $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                
                // Delete user record
                if ($user_id) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Staff deleted successfully']);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => 'Error deleting staff']);
            }
            exit;
            
        case 'delete_hostel':
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE hostel_id = ?");
                $stmt->execute([$_GET['id']]);
                $student_count = $stmt->fetchColumn();
                
                if ($student_count > 0) {
                    echo json_encode(['success' => false, 'message' => 'Cannot delete hostel with students']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM hostels WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                echo json_encode(['success' => true, 'message' => 'Hostel deleted successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error deleting hostel']);
            }
            exit;
            
        case 'update_staff':
            $stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ? WHERE id = ?");
            $stmt->execute([$_GET['name'], $_GET['contact'], $_GET['id']]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'update_rector':
            try {
                $pdo->beginTransaction();
                
                $hostel_id = $_GET['hostel_id'] ?: null;
                $csv_hostel_name = null;
                
                // Handle special hostel names
                if ($hostel_id === 'kalyanrav_jadhav') {
                    $csv_hostel_name = 'Kalyanrav Jadhav Boys Hostel';
                    $hostel_id = null;
                } elseif ($hostel_id === 'madhubhau_chaudhari') {
                    $csv_hostel_name = 'Madhubhau Chaudhari Girls Hostel';
                    $hostel_id = null;
                }
                
                // Update staff table (including all rector details)
                $stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ?, location = ?, hostel_id = ?, csv_hostel_name = ? WHERE id = ?");
                $stmt->execute([$_GET['name'], $_GET['contact'], $_GET['location'], $hostel_id, $csv_hostel_name, $_GET['id']]);
                
                // Update users table (email/username and hostel assignment)
                $stmt = $pdo->prepare("UPDATE users u JOIN staff s ON u.id = s.user_id SET u.username = ?, u.hostel_id = ? WHERE s.id = ?");
                $stmt->execute([$_GET['email'], $hostel_id, $_GET['id']]);
                
                // Update hostel rector assignment if hostel is assigned
                if ($hostel_id) {
                    $stmt = $pdo->prepare("SELECT user_id FROM staff WHERE id = ?");
                    $stmt->execute([$_GET['id']]);
                    $user_id = $stmt->fetchColumn();
                    
                    if ($user_id) {
                        $stmt = $pdo->prepare("UPDATE hostels SET rector_id = ? WHERE id = ?");
                        $stmt->execute([$user_id, $hostel_id]);
                    }
                }
                
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'update_hostel':
            $stmt = $pdo->prepare("UPDATE hostels SET name = ?, capacity = ?, location = ? WHERE id = ?");
            $stmt->execute([$_GET['name'], $_GET['capacity'], $_GET['location'], $_GET['id']]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_rector_credentials':
            try {
                $stmt = $pdo->query("
                    SELECT 
                        st.name as rector_name,
                        u.username as email,
                        COALESCE(st.plain_password, 'Password Reset Required') as password,
                        COALESCE(st.csv_hostel_name, h.name) as hostel_name
                    FROM staff st 
                    JOIN users u ON st.user_id = u.id 
                    LEFT JOIN hostels h ON st.hostel_id = h.id 
                    WHERE st.role = 'rector'
                    ORDER BY st.name
                ");
                $rectors = $stmt->fetchAll();
                
                $credentials = [];
                foreach ($rectors as $rector) {
                    $credentials[] = [
                        'name' => $rector['rector_name'],
                        'rector_id' => substr($rector['email'], 0, strpos($rector['email'], '@')) ?: 'R' . rand(100, 999),
                        'email' => $rector['email'],
                        'password' => $rector['password'],
                        'hostel' => $rector['hostel_name'] ?: 'Not Assigned'
                    ];
                }
                
                echo json_encode(['success' => true, 'credentials' => $credentials]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'clear_old_rectors':
            try {
                $pdo->beginTransaction();
                
                // Get count before deletion
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE role = 'rector'");
                $stmt->execute();
                $deleted_count = $stmt->fetchColumn();
                
                // Delete all rector records completely
                $pdo->exec("DELETE FROM staff WHERE role = 'rector'");
                $pdo->exec("DELETE FROM users WHERE role = 'rector'");
                
                // Clear rector assignments from hostels
                $pdo->exec("UPDATE hostels SET rector_id = NULL");
                
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => "Successfully deleted {$deleted_count} rector records"]);
            } catch (Exception $e) {
                $pdo->rollback();
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'check_rector_count':
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM staff WHERE role = 'rector'");
                $stmt->execute();
                $count = $stmt->fetchColumn();
                echo json_encode(['success' => true, 'count' => $count]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_rector_details':
            try {
                // Get rector info
                $stmt = $pdo->prepare("
                    SELECT st.*, u.username as email, 
                           CASE WHEN h.rector_id = u.id THEN 'Active' ELSE 'Inactive' END as status
                    FROM staff st 
                    JOIN users u ON st.user_id = u.id 
                    LEFT JOIN hostels h ON st.hostel_id = h.id 
                    WHERE st.id = ? AND st.role = 'rector'
                ");
                $stmt->execute([$_GET['id']]);
                $rector = $stmt->fetch();
                
                if (!$rector) {
                    echo json_encode(['success' => false, 'message' => 'Rector not found']);
                    exit;
                }
                
                // Get hostel info if assigned
                $hostel = null;
                $staff = [];
                if ($rector['hostel_id']) {
                    $stmt = $pdo->prepare("
                        SELECT h.*, COUNT(s.id) as student_count
                        FROM hostels h 
                        LEFT JOIN students s ON h.id = s.hostel_id 
                        WHERE h.id = ?
                        GROUP BY h.id
                    ");
                    $stmt->execute([$rector['hostel_id']]);
                    $hostel = $stmt->fetch();
                    
                    // Get other staff in same hostel
                    $stmt = $pdo->prepare("
                        SELECT st.*, u.username 
                        FROM staff st 
                        LEFT JOIN users u ON st.user_id = u.id 
                        WHERE st.hostel_id = ? AND st.id != ?
                        ORDER BY st.role, st.name
                    ");
                    $stmt->execute([$rector['hostel_id'], $_GET['id']]);
                    $staff = $stmt->fetchAll();
                }
                
                echo json_encode([
                    'success' => true, 
                    'rector' => $rector, 
                    'hostel' => $hostel, 
                    'staff' => $staff
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_admin_profile':
            try {
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$_POST['username'], $_SESSION['user_id']]);
                
                $_SESSION['username'] = $_POST['username'];
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error updating profile']);
            }
            exit;
            
        case 'change_password':
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $current_hash = $stmt->fetchColumn();
                
                if (!password_verify($_POST['current_password'], $current_hash)) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    exit;
                }
                
                $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $_SESSION['user_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error changing password']);
            }
            exit;
            
        case 'get_admin_profile':
            try {
                $stmt = $pdo->prepare("SELECT username, created_at FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $profile = $stmt->fetch();
                $profile['email'] = $profile['username'];
                echo json_encode(['success' => true, 'profile' => $profile]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching profile']);
            }
            exit;
            
        case 'get_database_info':
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $size_query = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema='vss'");
                $size = $size_query->fetchColumn();
                
                echo json_encode([
                    'success' => true, 
                    'table_count' => count($tables),
                    'db_size' => $size . ' MB'
                ]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error fetching database info']);
            }
            exit;
            
        case 'optimize_database':
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec("OPTIMIZE TABLE `$table`");
                }
                echo json_encode(['success' => true, 'message' => 'Database optimized successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error optimizing database']);
            }
            exit;
            
        case 'create_backup':
            try {
                $backup_dir = 'backups/';
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0777, true);
                }
                
                $filename = 'vss_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $filepath = $backup_dir . $filename;
                
                // Create backup content
                $backup_content = "-- VSS Hostel Management System Backup\n";
                $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
                $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
                
                // Get all tables
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tables as $table) {
                    // Get table structure
                    $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                    $backup_content .= "DROP TABLE IF EXISTS `$table`;\n";
                    $backup_content .= $create_table['Create Table'] . ";\n\n";
                    
                    // Get table data
                    $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        $backup_content .= "INSERT INTO `$table` VALUES\n";
                        $values = [];
                        foreach ($rows as $row) {
                            $escaped_values = array_map(function($value) use ($pdo) {
                                return $value === null ? 'NULL' : $pdo->quote($value);
                            }, array_values($row));
                            $values[] = '(' . implode(', ', $escaped_values) . ')';
                        }
                        $backup_content .= implode(",\n", $values) . ";\n\n";
                    }
                }
                
                $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
                
                // Write backup file
                if (file_put_contents($filepath, $backup_content)) {
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Backup created successfully',
                        'filename' => $filename,
                        'filepath' => $filepath,
                        'size' => round(filesize($filepath) / 1024 / 1024, 2) . ' MB'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to write backup file']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error creating backup: ' . $e->getMessage()]);
            }
            exit;
            
        case 'download_backup':
            try {
                $filename = $_GET['file'];
                $filepath = 'backups/' . $filename;
                
                if (is_dir($filepath)) {
                    // For folder backups, create a simple text file with backup info
                    $info_file = $filepath . '/backup_info.txt';
                    if (file_exists($info_file)) {
                        header('Content-Type: text/plain');
                        header('Content-Disposition: attachment; filename="' . $filename . '_info.txt"');
                        readfile($info_file);
                    } else {
                        echo 'Backup folder created successfully. Check backups/' . $filename . ' directory.';
                    }
                } elseif (file_exists($filepath)) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Content-Length: ' . filesize($filepath));
                    readfile($filepath);
                } else {
                    echo 'Backup created in folder: backups/' . $filename;
                }
            } catch (Exception $e) {
                echo 'Backup folder: backups/' . $_GET['file'];
            }
            exit;
            
        case 'create_complete_backup':
            try {
                $backup_dir = 'backups';
                if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);
                
                $timestamp = date('Y-m-d_H-i-s');
                $folder_name = 'complete_backup_' . $timestamp;
                $backup_path = $backup_dir . '/' . $folder_name;
                
                if (!mkdir($backup_path, 0777, true)) {
                    throw new Exception('Cannot create backup folder');
                }
                
                $backup_info = [];
                
                // Create detailed backup info
                $backup_info_content = "VSS HOSTEL MANAGEMENT SYSTEM - COMPLETE BACKUP\n";
                $backup_info_content .= "===============================================\n\n";
                $backup_info_content .= "Backup Type: Complete System Backup\n";
                $backup_info_content .= "Created Date: " . date('Y-m-d H:i:s') . "\n";
                $backup_info_content .= "System: VSS Hostel Management\n\n";
                $backup_info_content .= "SELECTED COMPONENTS:\n";
                
                file_put_contents($backup_path . '/backup_info.txt', $backup_info_content);
                
                // Database
                if (isset($_GET['include_database']) && $_GET['include_database'] === 'true') {
                    $sql = "-- VSS HOSTEL MANAGEMENT SYSTEM DATABASE BACKUP\n";
                    $sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
                    $sql .= "-- Database: vss\n\n";
                    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
                    
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($tables as $table) {
                        // Table structure
                        $sql .= "-- Table structure for `$table`\n";
                        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                        $sql .= $create['Create Table'] . ";\n\n";
                        
                        // Table data
                        $result = $pdo->query("SELECT * FROM `$table`");
                        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($rows)) {
                            $sql .= "-- Data for table `$table`\n";
                            foreach ($rows as $row) {
                                $values = array_map(function($value) use ($pdo) {
                                    return $value === null ? 'NULL' : $pdo->quote($value);
                                }, array_values($row));
                                $sql .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                            }
                            $sql .= "\n";
                        }
                    }
                    
                    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
                    file_put_contents($backup_path . '/database.sql', $sql);
                    $backup_info['database'] = 'All tables, users, students, attendance';
                    $backup_info_content .= "✓ Database: All tables, users, students, attendance\n";
                }
                
                // Files
                if (isset($_GET['include_files']) && $_GET['include_files'] === 'true') {
                    $count = 0;
                    
                    function copyFilesComplete($src, $dst, &$count) {
                        if (!is_dir($src)) return;
                        $dir = opendir($src);
                        @mkdir($dst, 0777, true);
                        while (($file = readdir($dir)) !== false) {
                            if ($file != '.' && $file != '..') {
                                if (is_dir($src . '/' . $file)) {
                                    copyFilesComplete($src . '/' . $file, $dst . '/' . $file, $count);
                                } else {
                                    copy($src . '/' . $file, $dst . '/' . $file);
                                    $count++;
                                }
                            }
                        }
                        closedir($dir);
                    }
                    
                    // Copy uploads (profile photos, documents, QR codes)
                    copyFilesComplete('uploads', $backup_path . '/uploads', $count);
                    
                    // Copy assets (images)
                    copyFilesComplete('assets', $backup_path . '/assets', $count);
                    
                    $backup_info['files'] = 'Profile photos, documents, QR codes, uploads';
                    $backup_info_content .= "✓ Files: Profile photos, documents, QR codes, uploads\n";
                }
                
                // Config
                if (isset($_GET['include_config']) && $_GET['include_config'] === 'true') {
                    $config_backup = $backup_path . '/config';
                    mkdir($config_backup, 0777, true);
                    
                    // Copy system settings
                    if (file_exists('manifest.json')) {
                        copy('manifest.json', $backup_path . '/manifest.json');
                    }
                    if (file_exists('config/database.php')) {
                        copy('config/database.php', $config_backup . '/database.php');
                    }
                    
                    $backup_info['config'] = 'System settings, manifest, database config';
                    $backup_info_content .= "✓ Configuration: System settings, manifest, database config\n";
                }
                
                // Update backup info file with selected components
                file_put_contents($backup_path . '/backup_info.txt', $backup_info_content);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Complete backup created successfully',
                    'filename' => $folder_name,
                    'size' => 'Folder created',
                    'details' => $backup_info
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
            
        case 'create_files_backup':
            try {
                $backup_dir = 'backups';
                if (!is_dir($backup_dir)) mkdir($backup_dir, 0777, true);
                
                $timestamp = date('Y-m-d_H-i-s');
                $folder_name = 'files_backup_' . $timestamp;
                $backup_path = $backup_dir . '/' . $folder_name;
                
                if (!mkdir($backup_path, 0777, true)) {
                    throw new Exception('Cannot create backup folder');
                }
                
                $files_added = 0;
                
                // Create detailed backup info
                $backup_info_content = "VSS HOSTEL MANAGEMENT SYSTEM - FILES BACKUP\n";
                $backup_info_content .= "==========================================\n\n";
                $backup_info_content .= "Backup Type: Files Only\n";
                $backup_info_content .= "Created Date: " . date('Y-m-d H:i:s') . "\n";
                $backup_info_content .= "System: VSS Hostel Management\n\n";
                $backup_info_content .= "INCLUDES:\n";
                $backup_info_content .= "- Profile photos\n";
                $backup_info_content .= "- Documents\n";
                $backup_info_content .= "- QR codes\n";
                $backup_info_content .= "- CSV files\n";
                $backup_info_content .= "- Images\n\n";
                
                file_put_contents($backup_path . '/backup_info.txt', $backup_info_content);
                $files_added++;
                
                function copyAllFiles($src, $dst, &$count) {
                    if (!is_dir($src)) return;
                    $dir = opendir($src);
                    @mkdir($dst, 0777, true);
                    while (($file = readdir($dir)) !== false) {
                        if ($file != '.' && $file != '..') {
                            if (is_dir($src . '/' . $file)) {
                                copyAllFiles($src . '/' . $file, $dst . '/' . $file, $count);
                            } else {
                                copy($src . '/' . $file, $dst . '/' . $file);
                                $count++;
                            }
                        }
                    }
                    closedir($dir);
                }
                
                // Copy uploads (profile photos, documents, QR codes, CSV files)
                copyAllFiles('uploads', $backup_path . '/uploads', $files_added);
                
                // Copy assets (images)
                copyAllFiles('assets', $backup_path . '/assets', $files_added);
                
                // Copy any CSV files from root
                $csv_files = glob('*.csv');
                if (!empty($csv_files)) {
                    mkdir($backup_path . '/csv_files', 0777, true);
                    foreach ($csv_files as $csv_file) {
                        copy($csv_file, $backup_path . '/csv_files/' . basename($csv_file));
                        $files_added++;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Files backup created successfully',
                    'filename' => $folder_name,
                    'size' => 'Folder created',
                    'files_count' => $files_added
                ]);
                
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
    }
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Helper functions for backup system
function createDatabaseBackup($pdo) {
    $sql = "-- VSS Database Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $result = $pdo->query("SELECT * FROM `$table`");
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        $sql .= $create['Create Table'] . ";\n\n";
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $sql .= "INSERT INTO `$table` VALUES (" . implode(',', array_map([$pdo, 'quote'], $row)) . ");\n";
        }
        $sql .= "\n";
    }
    return $sql;
}

function copyDirectory($source, $destination) {
    $files_copied = 0;
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            mkdir($target, 0777, true);
        } else {
            copy($item, $target);
            $files_copied++;
        }
    }
    
    return $files_copied;
}

function createZipFromFolder($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $zip->addFile($file, str_replace($source, '', $file));
            }
        }
        
        $zip->close();
    }
}

function deleteDirectory($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir."/".$object)) {
                    deleteDirectory($dir."/".$object);
                } else {
                    unlink($dir."/".$object);
                }
            }
        }
        rmdir($dir);
    }
}

// Handle form submissions - ONLY process when explicitly requested
if ($_POST && isset($_POST['upload_rectors']) && isset($_FILES['rector_csv']) && !isset($_SESSION['processing_lock'])) {
    $_SESSION['processing_lock'] = true;
    if ($_FILES['rector_csv']['error'] == 0 && $_FILES['rector_csv']['size'] > 0) {
            $file = $_FILES['rector_csv']['tmp_name'];
            $handle = fopen($file, 'r');
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            $credentials = [];
            
            // Skip header row
            fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                // Take all fields as they are from CSV
                $csv_data = array_map('trim', $data);
                
                if (count($csv_data) >= 1 && !empty($csv_data[0])) {
                    // CSV structure: Rector_Id, Full-Name, email, contact, hostel, password, Location
                    $rector_id = $csv_data[0] ?? '';
                    $rector_name = $csv_data[1] ?? '';
                    $email = $csv_data[2] ?? '';
                    $contact = $csv_data[3] ?? '';
                    $hostel_name = $csv_data[4] ?? '';
                    $csv_password = $csv_data[5] ?? '';
                    $location = $csv_data[6] ?? '';
                    
                    // Use password from CSV if provided, otherwise generate
                    $password = !empty($csv_password) ? $csv_password : generateRandomPassword(8);
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $email = strtolower(str_replace(' ', '.', $rector_name)) . '@vss.edu';
                    }
                    
                    // Use rector ID from CSV or generate if empty
                    if (empty($rector_id)) {
                        $rector_id = 'R' . str_pad($success_count + 1, 3, '0', STR_PAD_LEFT);
                    }
                    
                    // Find or create hostel
                    $hostel_id = null;
                    if ($hostel_name) {
                        $stmt = $pdo->prepare("SELECT id FROM hostels WHERE name LIKE ?");
                        $stmt->execute(['%' . $hostel_name . '%']);
                        $hostel = $stmt->fetch();
                        if ($hostel) {
                            $hostel_id = $hostel['id'];
                        }
                    }
                    
                    try {
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                            // Generate unique email if duplicate
                            $email = strtolower(str_replace(' ', '.', $rector_name)) . '.' . rand(100, 999) . '@vss.edu';
                        }
                        
                        $pdo->beginTransaction();
                        
                        // Create user account with email as username
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, 'rector', ?)");
                        $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $hostel_id]);
                        $user_id = $pdo->lastInsertId();
                        
                        // Create staff record with CSV data
                        $all_csv_data = json_encode($csv_data);
                        $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id, csv_data, location, rector_id, csv_hostel_name) VALUES (?, 'rector', ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$rector_name, $contact, $hostel_id, $user_id, $all_csv_data, $location, $rector_id, $hostel_name]);
                        
                        // Update hostel rector assignment if hostel found
                        if ($hostel_id) {
                            $stmt = $pdo->prepare("UPDATE hostels SET rector_id = ? WHERE id = ?");
                            $stmt->execute([$user_id, $hostel_id]);
                        }
                        
                        $pdo->commit();
                        
                        // Store credentials for display and in database
                        $credentials[] = [
                            'name' => $rector_name,
                            'rector_id' => $rector_id,
                            'email' => $email,
                            'password' => $password,
                            'hostel' => $hostel_name ?: 'Not Assigned'
                        ];
                        
                        // Store plain password in staff table for credential retrieval
                        $stmt = $pdo->prepare("UPDATE staff SET plain_password = ? WHERE user_id = ?");
                        $stmt->execute([$password, $user_id]);
                        
                        $success_count++;
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $errors[] = "Error registering $rector_name: " . $e->getMessage();
                        $error_count++;
                    }
                }
            }
            fclose($handle);
            
            // Don't store credentials in session - only show on manual request
            
            $success = "CSV Import completed: $success_count rectors registered successfully";
            if ($error_count > 0) {
                $success .= ", $error_count errors occurred";
                if (!empty($errors)) {
                    $success .= "<br><strong>Error Details:</strong><ul>";
                    foreach (array_slice($errors, 0, 5) as $error_msg) {
                        $success .= "<li>" . htmlspecialchars($error_msg) . "</li>";
                    }
                    $success .= "</ul>";
                }
            }
            // Store success message and clear lock
            $_SESSION['upload_success'] = $success;
            unset($_SESSION['processing_lock']);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
    } else {
        $error = "Please select a valid CSV file";
        unset($_SESSION['processing_lock']);
    }
    
    if (isset($_POST['add_hostel'])) {
        $stmt = $pdo->prepare("INSERT INTO hostels (name, capacity, location) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['hostel_name'], $_POST['capacity'], $_POST['location']]);
        $success = "Hostel added successfully";
    }
    
    if (isset($_POST['edit_hostel'])) {
        $stmt = $pdo->prepare("UPDATE hostels SET name = ?, capacity = ?, location = ? WHERE id = ?");
        $stmt->execute([$_POST['hostel_name'], $_POST['capacity'], $_POST['location'], $_POST['hostel_id']]);
        $success = "Hostel updated successfully";
    }
    
    if (isset($_POST['add_student'])) {
        $stmt = $pdo->prepare("INSERT INTO students (grn, name, course, year, hostel_id, email, contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['grn'], $_POST['student_name'], $_POST['course'], $_POST['year'], $_POST['hostel_id'], $_POST['email'], $_POST['contact']]);
        $success = "Student added successfully";
    }
    
    if (isset($_POST['add_staff'])) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, hostel_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['username'], md5($_POST['password']), $_POST['staff_role'], $_POST['hostel_id']]);
        $user_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['staff_name'], $_POST['staff_role'], $_POST['contact'], $_POST['hostel_id'], $user_id]);
        $success = "Staff member added successfully";
    }
    
    if (isset($_POST['edit_staff'])) {
        $stmt = $pdo->prepare("UPDATE staff SET name = ?, contact = ? WHERE id = ?");
        $stmt->execute([$_POST['staff_name'], $_POST['contact'], $_POST['staff_id']]);
        $success = "Staff updated successfully";
    }
    
    if (isset($_POST['update_admin_profile'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$_POST['username'], $_SESSION['user_id']]);
            $_SESSION['username'] = $_POST['username'];
            $success = "Profile updated successfully";
        } catch (Exception $e) {
            $error = "Error updating profile";
        }
    }
    
    if (isset($_POST['change_password'])) {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_hash = $stmt->fetchColumn();
            
            if (!password_verify($_POST['current_password'], $current_hash)) {
                $error = "Current password is incorrect";
            } else {
                $new_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hash, $_SESSION['user_id']]);
                $success = "Password changed successfully";
            }
        } catch (Exception $e) {
            $error = "Error changing password";
        }
    }
}

// Database structure update for staff table - Add missing columns
try {
    // Check if columns exist and add them if missing
    $columns_to_add = [
        'rector_id' => 'VARCHAR(50) DEFAULT NULL',
        'location' => 'VARCHAR(255) DEFAULT NULL',
        'csv_data' => 'TEXT DEFAULT NULL',
        'plain_password' => 'VARCHAR(255) DEFAULT NULL',
        'csv_hostel_name' => 'VARCHAR(255) DEFAULT NULL'
    ];
    
    foreach ($columns_to_add as $column => $definition) {
        try {
            $pdo->exec("ALTER TABLE staff ADD COLUMN $column $definition");
        } catch (Exception $e) {
            // Column already exists, continue
        }
    }
} catch (Exception $e) {
    // Ignore structure update errors
}

// Fetch data
$hostels = $pdo->query("SELECT h.*, CONCAT(st.name, ' (', u.username, ')') as rector_name FROM hostels h LEFT JOIN users u ON h.rector_id = u.id LEFT JOIN staff st ON u.id = st.user_id")->fetchAll();
$students = $pdo->query("SELECT s.*, h.name as hostel_name FROM students s LEFT JOIN hostels h ON s.hostel_id = h.id ORDER BY s.created_at DESC")->fetchAll();
$staff = $pdo->query("SELECT st.*, h.name as hostel_name, u.username FROM staff st LEFT JOIN hostels h ON st.hostel_id = h.id LEFT JOIN users u ON st.user_id = u.id ORDER BY st.hostel_id")->fetchAll();
$rectors = $pdo->query("SELECT u.id, u.username, st.name FROM users u JOIN staff st ON u.id = st.user_id WHERE u.role = 'rector' AND u.hostel_id IS NULL")->fetchAll();

// Get admin profile data
$admin_profile = $pdo->prepare("SELECT username, created_at FROM users WHERE id = ?");
$admin_profile->execute([$_SESSION['user_id']]);
$admin_data = $admin_profile->fetch();
$admin_data['email'] = $admin_data['username']; // Use username as email

// Get statistics
$total_capacity = $pdo->query("SELECT SUM(capacity) FROM hostels")->fetchColumn();
$total_occupied = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$occupancy_rate = $total_capacity > 0 ? round(($total_occupied / $total_capacity) * 100) : 0;

// Get system statistics for settings
$system_stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_rectors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'rector'")->fetchColumn(),
    'total_students' => $total_occupied,
    'total_hostels' => count($hostels),
    'database_size' => '~2.5MB' // Placeholder
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); z-index: 1030;">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-crown me-2"></i>Super Admin Control Center
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="padding: 0.75rem 1rem; border-radius: 8px; transition: all 0.3s ease;">
                            <i class="fas fa-user-shield me-2" style="font-size: 1.2rem;"></i>
                            <span><?php echo $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 12px; padding: 0.5rem 0;">
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openModal('adminProfileModal')" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;"><i class="fas fa-user-edit me-2 text-primary"></i>Admin Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openModal('systemSettingsModal')" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;"><i class="fas fa-cogs me-2 text-secondary"></i>System Settings</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" onclick="openModal('securityModal')" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;"><i class="fas fa-shield-alt me-2 text-warning"></i>Security</a></li>
                            <li><hr class="dropdown-divider mx-2" style="margin: 0.5rem 0;"></li>
                            <li><a class="dropdown-item py-2 px-3 text-danger" href="../auth/logout.php" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4" style="margin-top: 80px;">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-body text-center py-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-primary rounded-circle p-1 me-3" style="width: 140px; height: 140px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                <img src="../admin.png" alt="Admin" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;">System Administration Center</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-shield-alt me-1"></i>Complete control over VSS Hostel Management System</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    
        <!-- System Analytics & Quick Actions -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($hostels); ?></div>
                        <div class="stat-label">Hostel Properties</div>
                        <div class="stat-meta">Managed facilities</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students); ?></div>
                        <div class="stat-label">Student Records</div>
                        <div class="stat-meta">Active registrations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($staff); ?></div>
                        <div class="stat-label">Staff Personnel</div>
                        <div class="stat-meta">System users</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $occupancy_rate; ?>%</div>
                        <div class="stat-label">System Utilization</div>
                        <div class="stat-meta"><?php echo $total_occupied; ?>/<?php echo $total_capacity; ?> capacity</div>
                    </div>
                </div>
            </div>
        </div>

    <div id="alertContainer"></div>
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['upload_success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?></div>
    <?php endif; ?>
    <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    

    



    

        

    
    <div class="dashboard-card full-width" id="rectorsTable">
        <div class="card-header">
            <h3 class="card-title">Rector Management</h3>
            <div class="table-actions">
                <input type="text" id="rectorSearch" placeholder="Search rectors..." onkeyup="searchTable('rectorsTableBody', this.value)">
                <button class="btn btn-sm btn-success" onclick="openModal('uploadRectorsModal')">
                    <i class="fas fa-file-csv"></i> Import CSV
                </button>
                <button class="btn btn-sm btn-info" onclick="viewRectorCredentials()">
                    <i class="fas fa-key"></i> View Credentials
                </button>
                <button class="btn btn-sm btn-danger" onclick="clearOldRectors()">
                    <i class="fas fa-trash"></i> Clear Old Records
                </button>
                <button class="btn btn-sm btn-secondary" onclick="checkRectorCount()">
                    <i class="fas fa-info"></i> Check Count
                </button>
            </div>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th onclick="sortTable(0, 'rectorsTableBody')">Rector ID <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(1, 'rectorsTableBody')">Full Name <i class="fas fa-sort"></i></th>
                            <th onclick="sortTable(2, 'rectorsTableBody')">Email <i class="fas fa-sort"></i></th>
                            <th>Contact</th>
                            <th>Location</th>
                            <th>Hostel Assigned</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="rectorsTableBody">
                        <?php 
                        $rectors_query = $pdo->query("
                            SELECT 
                                st.id,
                                u.username as rector_id,
                                st.name as full_name,
                                u.username as email,
                                st.contact,
                                h.name as hostel_name,
                                COALESCE(st.location, h.location) as location,
                                st.rector_id,
                                st.csv_hostel_name,
                                CASE WHEN h.rector_id = u.id THEN 'Active' ELSE 'Inactive' END as status
                            FROM staff st 
                            JOIN users u ON st.user_id = u.id 
                            LEFT JOIN hostels h ON st.hostel_id = h.id 
                            WHERE st.role = 'rector'
                            ORDER BY st.id
                        ");
                        $rectors = $rectors_query->fetchAll();
                        
                        foreach($rectors as $rector): ?>
                        <tr data-id="<?php echo $rector['id']; ?>">
                            <td><strong><?php echo htmlspecialchars($rector['rector_id'] ?: substr($rector['email'], 0, strpos($rector['email'], '@')) ?: 'R' . str_pad($rector['id'], 3, '0', STR_PAD_LEFT)); ?></strong></td>
                            <td><?php echo htmlspecialchars($rector['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($rector['email']); ?></td>
                            <td><?php echo htmlspecialchars($rector['contact'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($rector['location'] ?: 'N/A'); ?></td>
                            <td>
                                <?php if($rector['csv_hostel_name']): ?>
                                    <span class="status-badge success"><?php echo htmlspecialchars($rector['csv_hostel_name']); ?></span>
                                <?php elseif($rector['hostel_name']): ?>
                                    <span class="status-badge success"><?php echo htmlspecialchars($rector['hostel_name']); ?></span>
                                <?php else: ?>
                                    <span class="status-badge warning">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($rector['status'] == 'Active'): ?>
                                    <span class="status-badge success">Active</span>
                                <?php else: ?>
                                    <span class="status-badge danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" onclick="viewRectorDetails(<?php echo $rector['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <button class="btn btn-sm btn-outline" onclick="editRector(<?php echo $rector['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-success" onclick="sendEmailToRector(<?php echo $rector['id']; ?>, '<?php echo addslashes($rector['full_name']); ?>', '<?php echo addslashes($rector['email']); ?>')">
                                    <i class="fas fa-envelope"></i> Send Email
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteRector(<?php echo $rector['id']; ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    

</div>

<!-- Modals -->
<div id="addHostelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Hostel</h3>
            <span class="close" onclick="closeModal('addHostelModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">Hostel Name</label>
                <input type="text" class="form-input" name="hostel_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bed Capacity</label>
                <input type="number" class="form-input" name="capacity" required>
            </div>
            <div class="form-group">
                <label class="form-label">Campus Location</label>
                <input type="text" class="form-input" name="location" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addHostelModal')">Cancel</button>
                <button type="submit" name="add_hostel" class="btn btn-primary">Create Hostel</button>
            </div>
        </form>
    </div>
</div>

<div id="editHostelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Hostel</h3>
            <span class="close" onclick="closeModal('editHostelModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <input type="hidden" name="hostel_id" id="editHostelId">
            <div class="form-group">
                <label class="form-label">Hostel Name</label>
                <input type="text" class="form-input" name="hostel_name" id="editHostelName" required>
            </div>
            <div class="form-group">
                <label class="form-label">Bed Capacity</label>
                <input type="number" class="form-input" name="capacity" id="editHostelCapacity" required>
            </div>
            <div class="form-group">
                <label class="form-label">Campus Location</label>
                <input type="text" class="form-input" name="location" id="editHostelLocation" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editHostelModal')">Cancel</button>
                <button type="submit" name="edit_hostel" class="btn btn-primary">Update Hostel</button>
            </div>
        </form>
    </div>
</div>

<div id="addStudentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Student</h3>
            <span class="close" onclick="closeModal('addStudentModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">GRN</label>
                <input type="text" class="form-input" name="grn" required>
            </div>
            <div class="form-group">
                <label class="form-label">Student Name</label>
                <input type="text" class="form-input" name="student_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Course</label>
                <input type="text" class="form-input" name="course" required>
            </div>
            <div class="form-group">
                <label class="form-label">Academic Year</label>
                <select class="form-input" name="year" required>
                    <option value="">Select Year</option>
                    <option value="1">1st Year</option>
                    <option value="2">2nd Year</option>
                    <option value="3">3rd Year</option>
                    <option value="4">4th Year</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" name="email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-input" name="contact" required>
            </div>
            <div class="form-group">
                <label class="form-label">Hostel Assignment</label>
                <select class="form-input" name="hostel_id" required>
                    <option value="">Select Hostel</option>
                    <?php foreach($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStudentModal')">Cancel</button>
                <button type="submit" name="add_student" class="btn btn-success">Add Student</button>
            </div>
        </form>
    </div>
</div>

<div id="addStaffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Staff</h3>
            <span class="close" onclick="closeModal('addStaffModal')">&times;</span>
        </div>
        <form method="POST" class="modern-form">
            <div class="form-group">
                <label class="form-label">Staff Name</label>
                <input type="text" class="form-input" name="staff_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" class="form-input" name="username" required>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" class="form-input" name="password" required>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-input" name="staff_role" required>
                    <option value="">Select Role</option>
                    <option value="rector">Rector</option>
                    <option value="mess_head">Mess Head</option>
                    <option value="library_head">Library Head</option>
                    <option value="health_staff">Health Staff</option>
                    <option value="vvk_staff">VVK Staff</option>
                    <option value="placement_staff">Placement Staff</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Contact</label>
                <input type="text" class="form-input" name="contact" required>
            </div>
            <div class="form-group">
                <label class="form-label">Hostel</label>
                <select class="form-input" name="hostel_id" required>
                    <option value="">Select Hostel</option>
                    <?php foreach($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStaffModal')">Cancel</button>
                <button type="submit" name="add_staff" class="btn btn-info">Add Staff</button>
            </div>
        </form>
    </div>
</div>

<div id="uploadRectorsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Import Rectors from CSV</h3>
            <span class="close" onclick="closeModal('uploadRectorsModal')">&times;</span>
        </div>
        <div class="modal-body">

            <form method="POST" enctype="multipart/form-data" class="modern-form">
                <div class="form-group">
                    <label class="form-label">Select CSV File</label>
                    <input type="file" class="form-input" name="rector_csv" accept=".csv" required>
                    <small class="text-muted">All CSV fields will be preserved as-is from the file</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('uploadRectorsModal')">Cancel</button>
                    <button type="submit" name="upload_rectors" class="btn btn-warning">
                        <i class="fas fa-upload me-2"></i>Upload & Generate Credentials
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rector Credentials Modal -->
<div id="credentialsModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-key me-2"></i>Rector Login Credentials</h3>
            <span class="close" onclick="closeModal('credentialsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="alert alert-success mb-3">
                <i class="fas fa-check-circle me-2"></i>
                <strong>Share these credentials with the rectors for login</strong><br>
                <small>Rectors can change their password after first login</small>
            </div>
            <div id="credentialsList"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-primary" onclick="printCredentials()">
                    <i class="fas fa-print me-2"></i>Print Credentials
                </button>
                <button type="button" class="btn btn-success" onclick="copyAllCredentials()">
                    <i class="fas fa-copy me-2"></i>Copy All
                </button>
                <button type="button" class="btn btn-outline" onclick="closeModal('credentialsModal')">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Rector Modal -->
<div id="editRectorModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Rector Details</h3>
            <span class="close" onclick="closeModal('editRectorModal')">&times;</span>
        </div>
        <form id="editRectorForm" class="modern-form">
            <input type="hidden" id="editRectorId" name="rector_id">
            <div class="form-group">
                <label class="form-label">Rector Name</label>
                <input type="text" class="form-input" id="editRectorName" name="rector_name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-input" id="editRectorEmail" name="rector_email" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact</label>
                <input type="text" class="form-input" id="editRectorContact" name="rector_contact" required>
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" class="form-input" id="editRectorLocation" name="rector_location">
            </div>
            <div class="form-group">
                <label class="form-label">Hostel Assignment</label>
                <select class="form-input" id="editRectorHostel" name="hostel_id">
                    <option value="">Select Hostel</option>
                    <?php foreach($hostels as $hostel): ?>
                        <option value="<?php echo $hostel['id']; ?>"><?php echo $hostel['name']; ?></option>
                    <?php endforeach; ?>
                    <option value="kalyanrav_jadhav">Kalyanrav Jadhav Boys Hostel</option>
                    <option value="madhubhau_chaudhari">Madhubhau Chaudhari Girls Hostel</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editRectorModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Rector</button>
            </div>
        </form>
    </div>
</div>

<!-- Rector Details Modal -->
<div id="rectorDetailsModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-tie me-2"></i>Rector & Hostel Details</h3>
            <span class="close" onclick="closeModal('rectorDetailsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div id="rectorDetailsContent"></div>
        </div>
    </div>
</div>

<!-- Admin Profile Modal -->
<div id="adminProfileModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit me-2"></i>Admin Profile</h3>
            <span class="close" onclick="closeModal('adminProfileModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <div class="bg-primary rounded-circle p-1 mx-auto" style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <img src="../admin.png" alt="Admin" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
            </div>
            <form id="profileForm" class="modern-form">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" id="profileUsername" name="username" value="<?php echo htmlspecialchars($admin_data['username']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email (Username)</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($admin_data['username']); ?>" readonly>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-input" value="Super Administrator" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Account Created</label>
                        <input type="text" class="form-input" value="<?php echo date('M d, Y', strtotime($admin_data['created_at'])); ?>" readonly>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('adminProfileModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- System Settings Modal -->
<div id="systemSettingsModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-cogs me-2"></i>System Settings</h3>
            <span class="close" onclick="closeModal('systemSettingsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="settings-tabs">
                <div class="tab-buttons mb-3">
                    <button class="tab-btn active" onclick="showTab('general')">General</button>
                    <button class="tab-btn" onclick="showTab('database')">Database</button>
                    <button class="tab-btn" onclick="showTab('backup')">Backup</button>
                </div>
                
                <div id="general" class="tab-content active">
                    <h5>System Information</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6>Total Users</h6>
                                <p class="stat-number"><?php echo $system_stats['total_users']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6>Active Rectors</h6>
                                <p class="stat-number"><?php echo $system_stats['total_rectors']; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6>Total Students</h6>
                                <p class="stat-number"><?php echo $system_stats['total_students']; ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-card">
                                <h6>Hostel Properties</h6>
                                <p class="stat-number"><?php echo $system_stats['total_hostels']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="database" class="tab-content">
                    <h5>Database Management</h5>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Database operations should be performed with caution
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="info-card">
                                <h6>Database Name</h6>
                                <p>vss</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <h6>Total Tables</h6>
                                <p id="tableCount">Loading...</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-card">
                                <h6>Database Size</h6>
                                <p id="dbSize">Loading...</p>
                            </div>
                        </div>
                    </div>
                    <div class="database-actions">
                        <button class="btn btn-primary me-2" onclick="getDatabaseInfo()">
                            <i class="fas fa-sync me-2"></i>Refresh Info
                        </button>
                        <button class="btn btn-warning me-2" onclick="optimizeDatabase()">
                            <i class="fas fa-tools me-2"></i>Optimize Database
                        </button>
                        <button class="btn btn-danger" onclick="confirmDatabaseReset()">
                            <i class="fas fa-exclamation-triangle me-2"></i>Reset Database
                        </button>
                    </div>
                </div>
                
                <div id="backup" class="tab-content">
                    <h5>System Backup & Restore</h5>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Regular backups are recommended for data safety
                    </div>
                    
                    <div class="backup-section mb-4">
                        <h6>Individual Backup Options</h6>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="backup-card">
                                    <div class="backup-icon">
                                        <i class="fas fa-database"></i>
                                    </div>
                                    <h6>📊 Database Backup</h6>
                                    <p>Complete database with all tables, users, students, attendance records</p>
                                    <small class="text-muted">Downloads as .sql file</small>
                                    <div class="mt-2">
                                        <button class="btn btn-primary btn-sm" onclick="createDatabaseBackup()">
                                            <i class="fas fa-database me-1"></i>Create Database Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="backup-card">
                                    <div class="backup-icon">
                                        <i class="fas fa-folder"></i>
                                    </div>
                                    <h6>📁 Files Backup</h6>
                                    <p>Creates ZIP file with all uploaded files</p>
                                    <p>Includes profile photos, documents, QR codes</p>
                                    <small class="text-muted">Downloads as .zip file</small>
                                    <small class="text-muted d-block">Shows total file count</small>
                                    <div class="mt-2">
                                        <button class="btn btn-success btn-sm" onclick="createFilesBackup()">
                                            <i class="fas fa-folder me-1"></i>Create Files Backup
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="complete-backup-section mb-4">
                        <h6>Complete System Backup</h6>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Complete Backup includes:</strong><br>
                            🗄️ Full database with all records<br>
                            📁 All uploaded files and documents<br>
                            ⚙️ System configuration files<br>
                            📊 System information and metadata
                        </div>
                        
                        <div class="complete-backup-options mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeDatabase" checked>
                                <label class="form-check-label" for="includeDatabase">
                                    <strong>Include Database</strong> - All tables, users, students, attendance
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeFiles" checked>
                                <label class="form-check-label" for="includeFiles">
                                    <strong>Include Files</strong> - Profile photos, documents, QR codes, uploads
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="includeConfig" checked>
                                <label class="form-check-label" for="includeConfig">
                                    <strong>Include Configuration</strong> - System settings, manifest, database config
                                </label>
                            </div>
                        </div>
                        
                        <button class="btn btn-warning btn-lg" onclick="createCompleteBackup()">
                            <i class="fas fa-archive me-2"></i>Create Complete System Backup
                        </button>
                    </div>
                    
                    <div class="restore-section">
                        <h6>Restore from Backup</h6>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Restoring will overwrite current data
                        </div>
                        <div class="mb-3">
                            <input type="file" class="form-control" id="backupFile" accept=".sql,.zip">
                        </div>
                        <button class="btn btn-warning" onclick="restoreBackup()">
                            <i class="fas fa-upload me-2"></i>Restore from File
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Send Email Modal -->
<div id="sendEmailModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-envelope me-2"></i>Send Email to Rector</h3>
            <span class="close" onclick="closeModal('sendEmailModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="emailForm" enctype="multipart/form-data" class="modern-form">
                <input type="hidden" id="rectorId" name="rector_id">
                <div class="form-group mb-3">
                    <label class="form-label">To:</label>
                    <input type="text" class="form-input" id="rectorInfo" readonly>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Subject:</label>
                    <input type="text" class="form-input" name="subject" required placeholder="Enter email subject">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Message:</label>
                    <textarea class="form-input" name="message" rows="6" required placeholder="Enter your message here..."></textarea>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Attachments (Optional):</label>
                    <input type="file" class="form-input" name="attachments[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt">
                    <small class="text-muted">You can attach multiple files (PDF, DOC, Images, TXT)</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeModal('sendEmailModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane me-2"></i>Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Security Modal -->
<div id="securityModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-shield-alt me-2"></i>Security Settings</h3>
            <span class="close" onclick="closeModal('securityModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="security-section mb-4">
                <h5>Change Password</h5>
                <form id="passwordForm" class="modern-form">
                    <div class="form-group mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-input" name="current_password" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-input" name="new_password" minlength="6" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-input" name="confirm_password" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </form>
            </div>
            
            <div class="security-section">
                <h5>Security Information</h5>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Your account is secure with encrypted password storage
                </div>
                <div class="security-tips">
                    <h6>Security Tips:</h6>
                    <ul>
                        <li>Use a strong password with at least 8 characters</li>
                        <li>Include uppercase, lowercase, numbers, and symbols</li>
                        <li>Don't share your admin credentials</li>
                        <li>Log out when not using the system</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Search functionality
function searchTable(tableBodyId, searchTerm) {
    const tbody = document.getElementById(tableBodyId);
    const rows = tbody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm.toLowerCase()) ? '' : 'none';
    }
}

// Sort functionality
function sortTable(columnIndex, tableBodyId) {
    const tbody = document.getElementById(tableBodyId);
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    
    rows.sort((a, b) => {
        const aText = a.cells[columnIndex].textContent.trim();
        const bText = b.cells[columnIndex].textContent.trim();
        return aText.localeCompare(bText);
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Filter functionality
function filterByRole() {
    const roleFilter = document.getElementById('roleFilter').value;
    const rows = document.getElementById('staffTableBody').getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const role = row.getAttribute('data-role');
        row.style.display = !roleFilter || role === roleFilter ? '' : 'none';
    }
}

// Edit hostel
function editHostel(id) {
    fetch(`?action=get_hostel&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editHostelId').value = data.id;
            document.getElementById('editHostelName').value = data.name;
            document.getElementById('editHostelCapacity').value = data.capacity;
            document.getElementById('editHostelLocation').value = data.location;
            openModal('editHostelModal');
        });
}

// Edit staff - Simple working version
function editStaff(id) {
    alert('Edit Staff ID: ' + id + '\nThis will open edit form for staff member.');
    
    // Get the row data
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const name = row.cells[0].textContent;
    const role = row.cells[1].textContent;
    const username = row.cells[2].textContent;
    const contact = row.cells[3].textContent;
    
    // Simple prompt-based edit
    const newName = prompt('Edit Staff Name:', name);
    const newContact = prompt('Edit Contact:', contact);
    
    if (newName && newContact) {
        // Update the row immediately
        row.cells[0].textContent = newName;
        row.cells[3].textContent = newContact;
        
        // Show success message
        alert('Staff updated successfully!');
        
        // You can add AJAX call here to update database
        fetch(`?action=update_staff&id=${id}&name=${newName}&contact=${newContact}`);
    }
}

// Delete staff - Simple working version
function deleteStaff(id) {
    if (confirm('Are you sure you want to delete this staff member?\nThis action cannot be undone!')) {
        // Get the row
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const staffName = row.cells[0].textContent;
        
        // Remove the row immediately
        row.style.backgroundColor = '#ffebee';
        row.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            row.remove();
            alert('Staff "' + staffName + '" deleted successfully!');
        }, 300);
        
        // Make AJAX call to delete from database
        fetch(`?action=delete_staff&id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('Delete result:', data);
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('Error deleting staff member');
            });
    }
}

// Delete hostel - Simple working version
function deleteHostel(id) {
    if (confirm('Are you sure you want to delete this hostel?\nThis action cannot be undone!')) {
        // Get the row
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const hostelName = row.cells[0].textContent;
        
        // Remove the row immediately
        row.style.backgroundColor = '#ffebee';
        row.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            row.remove();
            alert('Hostel "' + hostelName + '" deleted successfully!');
        }, 300);
        
        // Make AJAX call to delete from database
        fetch(`?action=delete_hostel&id=${id}`)
            .then(response => response.json())
            .then(data => {
                console.log('Delete result:', data);
            })
            .catch(error => {
                console.error('Delete error:', error);
                alert('Error deleting hostel');
            });
    }
}

// Edit hostel - Enhanced version
function editHostel(id) {
    // Get current data and show in prompts
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const currentName = row.cells[0].textContent;
    const currentCapacity = row.cells[1].textContent.replace(' beds', '');
    const currentLocation = row.cells[2].textContent;
    
    const newName = prompt('Edit Hostel Name:', currentName);
    if (!newName) return;
    
    const newCapacity = prompt('Edit Capacity:', currentCapacity);
    if (!newCapacity) return;
    
    const newLocation = prompt('Edit Location:', currentLocation);
    if (!newLocation) return;
    
    // Update the row immediately
    row.cells[0].innerHTML = '<strong>' + newName + '</strong>';
    row.cells[1].textContent = newCapacity + ' beds';
    row.cells[2].textContent = newLocation;
    
    alert('Hostel updated successfully!');
    
    // Make AJAX call to update database
    fetch(`?action=update_hostel&id=${id}&name=${newName}&capacity=${newCapacity}&location=${newLocation}`);
}

// Rector management functions
function editRector(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    const name = row.cells[1].textContent;
    const email = row.cells[2].textContent;
    const contact = row.cells[3].textContent;
    const location = row.cells[4].textContent;
    
    // Populate form fields
    document.getElementById('editRectorId').value = id;
    document.getElementById('editRectorName').value = name;
    document.getElementById('editRectorEmail').value = email;
    document.getElementById('editRectorContact').value = contact;
    document.getElementById('editRectorLocation').value = location === 'N/A' ? '' : location;
    
    // Open modal
    openModal('editRectorModal');
}

function deleteRector(id) {
    if (confirm('Are you sure you want to remove this rector?\nThis will unassign them from their hostel.')) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        const rectorName = row.cells[1].textContent;
        
        row.style.backgroundColor = '#ffebee';
        row.style.transition = 'all 0.3s ease';
        
        setTimeout(() => {
            row.remove();
            alert('Rector "' + rectorName + '" removed successfully!');
        }, 300);
        
        fetch(`?action=delete_staff&id=${id}`);
    }
}

function viewRectorCredentials() {
    fetch('?action=get_rector_credentials')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.credentials && data.credentials.length > 0) {
                showCredentials(data.credentials);
            } else {
                alert('No rector credentials found. Please import rectors first.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error fetching credentials. Please try again.');
        });
}

function clearOldRectors() {
    if (confirm('⚠️ WARNING: This will delete ALL rector records from the system!\n\nThis action will:\n- Remove all rector accounts\n- Clear hostel assignments\n- Cannot be undone\n\nAre you absolutely sure?')) {
        if (confirm('Final confirmation: Delete ALL rector records?')) {
            // Show loading state
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            button.disabled = true;
            
            fetch('?action=clear_old_rectors')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert('✅ ' + data.message);
                        location.reload();
                    } else {
                        alert('❌ Error: ' + data.message);
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Network error. Please try again.');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
        }
    }
}

// Credentials management functions
function showCredentials(credentials) {
    const credentialsList = document.getElementById('credentialsList');
    let html = '';
    
    credentials.forEach((cred, index) => {
        html += `
            <div class="credential-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>${cred.name}</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="copyCredential(${index})">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Rector ID (Username):</strong><br>
                            <code class="credential-field" id="rectorId_${index}">${cred.email}</code>
                        </div>
                        <div class="col-md-6">
                            <strong>Password:</strong><br>
                            <code class="credential-field" id="password_${index}">${cred.password}</code>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <strong>Hostel Assigned:</strong> ${cred.hostel}<br>
                            <small class="text-muted">Rector can change password after first login</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    credentialsList.innerHTML = html;
    window.credentialsData = credentials;
    openModal('credentialsModal');
}

function copyCredential(index) {
    const cred = window.credentialsData[index];
    const text = `Rector Login Credentials\n\nName: ${cred.name}\nUsername: ${cred.email}\nPassword: ${cred.password}\nHostel: ${cred.hostel}\n\nPlease change your password after first login.`;
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Credentials copied to clipboard!');
    });
}

function copyAllCredentials() {
    let allText = 'RECTOR LOGIN CREDENTIALS\n\n';
    
    window.credentialsData.forEach((cred, index) => {
        allText += `${index + 1}. ${cred.name}\n`;
        allText += `   Username: ${cred.email}\n`;
        allText += `   Password: ${cred.password}\n`;
        allText += `   Hostel: ${cred.hostel}\n\n`;
    });
    
    allText += 'Note: Rectors can change their password after first login.';
    
    navigator.clipboard.writeText(allText).then(() => {
        alert('All credentials copied to clipboard!');
    });
}

function printCredentials() {
    const printWindow = window.open('', '_blank');
    let printContent = `
        <html>
        <head>
            <title>Rector Login Credentials</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .credential-card { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 5px; }
                .header { background: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; border-radius: 5px 5px 0 0; }
                code { background: #f1f3f4; padding: 2px 4px; border-radius: 3px; }
            </style>
        </head>
        <body>
            <h1>VSS Rector Login Credentials</h1>
            <p><strong>Generated on:</strong> ${new Date().toLocaleString()}</p>
    `;
    
    window.credentialsData.forEach((cred, index) => {
        printContent += `
            <div class="credential-card">
                <div class="header">
                    <h3>${cred.name}</h3>
                </div>
                <p><strong>Username:</strong> <code>${cred.email}</code></p>
                <p><strong>Password:</strong> <code>${cred.password}</code></p>
                <p><strong>Hostel:</strong> ${cred.hostel}</p>
                <p><small>Please change your password after first login.</small></p>
            </div>
        `;
    });
    
    printContent += '</body></html>';
    
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.print();
}

// Real-time updates
setInterval(() => {
    updateStats();
}, 30000);

function updateStats() {
    console.log('Updating stats...');
}

// Check rector count function
function checkRectorCount() {
    fetch('?action=check_rector_count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Current rector count: ' + data.count);
            } else {
                alert('Error checking count: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

// View rector details function
function viewRectorDetails(rectorId) {
    fetch(`?action=get_rector_details&id=${rectorId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showRectorDetails(data.rector, data.hostel, data.staff);
            } else {
                alert('Error loading rector details');
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

function showRectorDetails(rector, hostel, staff) {
    let html = `
        <div class="rector-info mb-4">
            <h4>${rector.name}</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Rector ID:</strong> ${rector.rector_id}</p>
                    <p><strong>Email:</strong> ${rector.email}</p>
                    <p><strong>Contact:</strong> ${rector.contact}</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Location:</strong> ${rector.location || 'N/A'}</p>
                    <p><strong>Status:</strong> <span class="status-badge ${rector.status === 'Active' ? 'success' : 'danger'}">${rector.status}</span></p>
                </div>
            </div>
        </div>
    `;
    
    if (hostel) {
        html += `
            <div class="hostel-info mb-4">
                <h5><i class="fas fa-building me-2"></i>Assigned Hostel</h5>
                <div class="card">
                    <div class="card-body">
                        <h6>${hostel.name}</h6>
                        <p><strong>Location:</strong> ${rector.location || hostel.location || 'N/A'}</p>
                        <p><strong>Capacity:</strong> ${hostel.student_count}/${hostel.capacity} Students</p>
                    </div>
                </div>
            </div>
        `;
        
        if (staff && staff.length > 0) {
            html += `
                <div class="staff-info">
                    <h5><i class="fas fa-users me-2"></i>Hostel Staff</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Contact</th>
                                    <th>Username</th>
                                </tr>
                            </thead>
                            <tbody>
            `;
            
            staff.forEach(member => {
                html += `
                    <tr>
                        <td>${member.name}</td>
                        <td><span class="badge bg-primary">${member.role.replace('_', ' ').toUpperCase()}</span></td>
                        <td>${member.contact || 'N/A'}</td>
                        <td>${member.username}</td>
                    </tr>
                `;
            });
            
            html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        } else {
            html += `
                <div class="staff-info">
                    <h5><i class="fas fa-users me-2"></i>Hostel Staff</h5>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No other staff members assigned to this hostel
                    </div>
                </div>
            `;
        }
    } else {
        html += `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>No hostel assigned to this rector
            </div>
        `;
    }
    
    document.getElementById('rectorDetailsContent').innerHTML = html;
    openModal('rectorDetailsModal');
}

// Edit rector form submission
document.getElementById('editRectorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const rectorId = formData.get('rector_id');
    
    fetch(`?action=update_rector&id=${rectorId}&name=${formData.get('rector_name')}&email=${formData.get('rector_email')}&contact=${formData.get('rector_contact')}&location=${formData.get('rector_location')}&hostel_id=${formData.get('hostel_id')}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Rector updated successfully!');
                closeModal('editRectorModal');
                location.reload();
            } else {
                alert('Error updating rector: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error updating rector');
        });
});

// Admin profile and settings functions
document.getElementById('profileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('?action=update_admin_profile', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Profile updated successfully!');
            closeModal('adminProfileModal');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error updating profile');
    });
});

document.getElementById('passwordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    if (formData.get('new_password') !== formData.get('confirm_password')) {
        alert('New passwords do not match!');
        return;
    }
    
    fetch('?action=change_password', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password changed successfully!');
            this.reset();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error changing password');
    });
});

function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab and activate button
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Database management functions
function getDatabaseInfo() {
    fetch('?action=get_database_info')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tableCount').textContent = data.table_count;
                document.getElementById('dbSize').textContent = data.db_size;
            } else {
                alert('Error fetching database info');
            }
        })
        .catch(error => {
            alert('Error: ' + error.message);
        });
}

function optimizeDatabase() {
    if (confirm('Optimize database? This will improve performance but may take a few minutes.')) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Optimizing...';
        button.disabled = true;
        
        fetch('?action=optimize_database')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    getDatabaseInfo(); // Refresh info
                } else {
                    alert('❌ ' + data.message);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .catch(error => {
                alert('❌ Error optimizing database');
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

function confirmDatabaseReset() {
    if (confirm('⚠️ WARNING: This will delete ALL data!\n\nThis action will:\n- Remove all students, staff, rectors\n- Clear all hostel data\n- Reset system to initial state\n\nThis CANNOT be undone!\n\nAre you absolutely sure?')) {
        if (confirm('Final confirmation: DELETE ALL DATA?')) {
            alert('Database reset functionality would be implemented here.\nThis is a dangerous operation that requires additional security measures.');
        }
    }
}

// Backup functions

function createDatabaseBackup() {
    if (confirm('Create database-only backup?\n\nThis will include all tables, users, students, attendance records, etc.')) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Database Backup...';
        button.disabled = true;
        
        fetch('?action=create_backup')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Database backup created!\n\nFile: ${data.filename}\nSize: ${data.size}`);
                    window.open(`?action=download_backup&file=${data.filename}`, '_blank');
                } else {
                    alert('❌ ' + data.message);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .catch(error => {
                alert('❌ Error creating database backup');
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

function createFilesBackup() {
    if (confirm('Create files backup?\n\nThis will include:\n• Profile photos\n• Student documents\n• QR code images\n• Event photos\n• All uploaded files')) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Files Backup...';
        button.disabled = true;
        
        fetch('?action=create_files_backup')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Files backup created!\n\nFile: ${data.filename}\nSize: ${data.size}\nFiles: ${data.files_count} files backed up`);
                    window.open(`?action=download_backup&file=${data.filename}`, '_blank');
                } else {
                    alert('❌ ' + data.message);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .catch(error => {
                alert('❌ Error creating files backup');
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

function createCompleteBackup() {
    const includeDatabase = document.getElementById('includeDatabase').checked;
    const includeFiles = document.getElementById('includeFiles').checked;
    const includeConfig = document.getElementById('includeConfig').checked;
    
    if (!includeDatabase && !includeFiles && !includeConfig) {
        alert('Please select at least one backup option.');
        return;
    }
    
    let backupItems = [];
    if (includeDatabase) backupItems.push('Database');
    if (includeFiles) backupItems.push('Files');
    if (includeConfig) backupItems.push('Configuration');
    
    if (confirm(`Create complete system backup?\n\nThis will include:\n• ${backupItems.join('\n• ')}\n\nThis may take several minutes depending on data size.`)) {
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Complete Backup...';
        button.disabled = true;
        
        fetch(`?action=create_complete_backup&include_database=${includeDatabase}&include_files=${includeFiles}&include_config=${includeConfig}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = `✅ Complete backup created successfully!\n\nFile: ${data.filename}\nSize: ${data.size}`;
                    if (data.details) {
                        message += '\n\nBackup Contents:';
                        Object.entries(data.details).forEach(([key, value]) => {
                            message += `\n• ${key.charAt(0).toUpperCase() + key.slice(1)}: ${value}`;
                        });
                    }
                    alert(message);
                    window.open(`?action=download_backup&file=${data.filename}`, '_blank');
                } else {
                    alert('❌ ' + data.message);
                }
                button.innerHTML = originalText;
                button.disabled = false;
            })
            .catch(error => {
                alert('❌ Error creating complete backup');
                button.innerHTML = originalText;
                button.disabled = false;
            });
    }
}

function restoreBackup() {
    const fileInput = document.getElementById('backupFile');
    if (!fileInput.files[0]) {
        alert('Please select a backup file first.');
        return;
    }
    
    const file = fileInput.files[0];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    let backupType = 'Unknown';
    if (fileExtension === 'sql') {
        backupType = 'Database';
    } else if (fileExtension === 'zip') {
        backupType = 'Files/Complete System';
    }
    
    if (confirm(`⚠️ WARNING: This will overwrite current data!\n\nBackup Type: ${backupType}\nFile: ${file.name}\nSize: ${(file.size / 1024 / 1024).toFixed(2)} MB\n\nThis action cannot be undone!\n\nAre you sure you want to restore from backup?`)) {
        alert('Restore functionality would be implemented here.\nThis requires careful handling of file upload and execution with proper security measures.');
    }
}

// Load database info when settings modal opens
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => tab.classList.remove('active'));
    
    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab and activate button
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
    
    // Load database info when database tab is shown
    if (tabName === 'database') {
        getDatabaseInfo();
    }
}

// Email functions
function sendEmailToRector(rectorId, rectorName, rectorEmail) {
    document.getElementById('rectorId').value = rectorId;
    document.getElementById('rectorInfo').value = rectorEmail;
    document.getElementById('emailForm').reset();
    document.getElementById('rectorId').value = rectorId; // Reset clears this, so set again
    document.getElementById('rectorInfo').value = rectorEmail;
    openModal('sendEmailModal');
}

// Email form submission
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    submitBtn.disabled = true;
    
    const formData = new FormData(this);
    
    fetch('../handlers/send_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            closeModal('sendEmailModal');
            this.reset();
        } else {
            alert('❌ ' + data.message);
        }
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    })
    .catch(error => {
        alert('❌ Error sending email. Please try again.');
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let i = 0; i < modals.length; i++) {
        if (event.target === modals[i]) {
            modals[i].style.display = 'none';
        }
    }
}
</script>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    margin: 5% auto;
    padding: 0;
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 500px;
    box-shadow: var(--shadow-glass);
}

.modal-header {
    padding: 1.5rem 2rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    color: var(--text-primary);
}

.close {
    color: var(--text-secondary);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover {
    color: var(--text-primary);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 2rem;
}

/* Header actions */
.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Search and filter bar */
.quick-actions {
    margin-bottom: 2rem;
}

.search-filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: 1rem 2rem;
}

.search-box {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 3rem;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
}

.filter-btn {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
    border-radius: var(--radius);
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-btn.active,
.filter-btn:hover {
    background: var(--primary-solid);
    color: white;
}

/* Table actions */
.table-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.table-actions input,
.table-actions select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.1);
    color: var(--text-primary);
}

/* Credential cards */
.credential-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    overflow: hidden;
}

.credential-card .card-header {
    background: rgba(255,255,255,0.1);
    padding: 1rem;
    border-bottom: 1px solid var(--glass-border);
}

.credential-card .card-body {
    padding: 1rem;
}

.credential-field {
    background: rgba(255,255,255,0.1);
    padding: 0.5rem;
    border-radius: 4px;
    display: inline-block;
    font-family: 'Courier New', monospace;
    font-weight: bold;
    color: var(--primary-solid);
}

/* Settings tabs */
.settings-tabs {
    width: 100%;
}

.tab-buttons {
    display: flex;
    border-bottom: 1px solid var(--glass-border);
    margin-bottom: 1rem;
}

.tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: transparent;
    color: var(--text-secondary);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}

.tab-btn.active {
    color: var(--primary-solid);
    border-bottom-color: var(--primary-solid);
}

.tab-btn:hover {
    color: var(--text-primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.info-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    padding: 1rem;
    text-align: center;
}

.info-card h6 {
    color: var(--text-secondary);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.info-card .stat-number {
    font-size: 1.5rem;
    font-weight: bold;
    color: var(--primary-solid);
    margin: 0;
}

.security-section {
    border-bottom: 1px solid var(--glass-border);
    padding-bottom: 1rem;
}

.security-section:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.security-tips ul {
    margin: 0;
    padding-left: 1.5rem;
}

.security-tips li {
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

/* Backup card styles */
.backup-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    padding: 1.5rem;
    text-align: center;
    height: 100%;
    transition: all 0.3s ease;
}

.backup-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-glass);
}

.backup-icon {
    font-size: 2rem;
    color: var(--primary-solid);
    margin-bottom: 1rem;
}

.backup-card h6 {
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.backup-card p {
    color: var(--text-secondary);
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.complete-backup-section {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: 2rem;
    margin-bottom: 2rem;
}

.complete-backup-options {
    background: rgba(255,255,255,0.05);
    border-radius: var(--radius);
    padding: 1rem;
}

.form-check {
    margin-bottom: 0.75rem;
}

.form-check-label {
    color: var(--text-primary);
    margin-left: 0.5rem;
}

.form-check-input:checked {
    background-color: var(--primary-solid);
    border-color: var(--primary-solid);
}

@media (max-width: 768px) {
    .header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .search-filter-bar {
        flex-direction: column;
        gap: 1rem;
    }
    
    .table-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .credential-card .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .credential-card .row {
        flex-direction: column;
    }
    
    .backup-card {
        margin-bottom: 1rem;
    }
    
    .complete-backup-section {
        padding: 1rem;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>