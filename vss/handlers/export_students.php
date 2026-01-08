<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

try {
    // Get hostel info
    $hostel_stmt = $pdo->prepare("SELECT name FROM hostels WHERE id = ?");
    $hostel_stmt->execute([$hostel_id]);
    $hostel = $hostel_stmt->fetch();
    
    // Get students
    $stmt = $pdo->prepare("SELECT grn, name, email, contact, course, year FROM students WHERE hostel_id = ? ORDER BY name");
    $stmt->execute([$hostel_id]);
    $students = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['GRN', 'Name', 'Email', 'Contact', 'Course', 'Year']);
    
    // Write data
    foreach($students as $student) {
        fputcsv($output, [
            $student['grn'],
            $student['name'],
            $student['email'],
            $student['contact'],
            $student['course'],
            $student['year']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    die("Error exporting students: " . $e->getMessage());
}
?>