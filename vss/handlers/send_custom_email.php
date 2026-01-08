<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_custom_email') {
    try {
        $student_email = $_POST['student_email'];
        $student_name = $_POST['student_name'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $include_admission_form = $_POST['include_admission_form'] === '1';
        $attachment_links = isset($_POST['attachment_links']) ? $_POST['attachment_links'] : '';
        
        if (empty($student_email) || empty($subject) || empty($message)) {
            throw new Exception('Required fields missing');
        }
        
        if (!filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        $mail = new PHPMailer(true);
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Log email attempt
        error_log('Sending email to: ' . $student_email);
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($student_email, $student_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->CharSet = 'UTF-8';
        
        // Clean and prepare message
        $clean_name = preg_replace('/[^\x20-\x7E]/', '', $student_name);
        $clean_message = preg_replace('/[^\x20-\x7E\r\n]/', '', $message);
        
        $email_body = "<html><body>";
        $email_body .= "<p>Dear " . htmlspecialchars($clean_name) . ",</p>";
        $email_body .= "<p>" . nl2br(htmlspecialchars($clean_message)) . "</p>";
        
        // Handle file attachments
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $upload_dir = '../uploads/email_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $temp_file = $_FILES['attachments']['tmp_name'][$key];
                    $file_size = $_FILES['attachments']['size'][$key];
                    
                    // Validate file size (10MB limit)
                    if ($file_size > 10 * 1024 * 1024) {
                        throw new Exception('File too large: ' . $filename);
                    }
                    
                    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $file_path = $upload_dir . $safe_filename;
                    
                    if (move_uploaded_file($temp_file, $file_path)) {
                        $mail->addAttachment($file_path, $filename);
                    }
                }
            }
        }
        
        if ($include_admission_form) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
            $admission_link = $domain . dirname($_SERVER['REQUEST_URI'], 2) . '/admission_form.php';
            $email_body .= "<p><a href='" . $admission_link . "'>Click here for Admission Form</a></p>";
        }
        
        // Handle link attachments
        if (!empty($attachment_links)) {
            $links = array_filter(array_map('trim', explode("\n", $attachment_links)));
            if (!empty($links)) {
                $email_body .= "<p><strong>Additional Links:</strong></p><ul>";
                foreach ($links as $link) {
                    if (!preg_match('/^https?:\/\//i', $link)) {
                        $link = 'https://' . $link;
                    }
                    $email_body .= "<li><a href='" . htmlspecialchars($link) . "'>" . htmlspecialchars($link) . "</a></li>";
                }
                $email_body .= "</ul>";
            }
        }
        
        $email_body .= "<p>Best regards,<br>VSS Hostel Management</p>";
        $email_body .= "</body></html>";
        
        $mail->Body = $email_body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<p>', '</p>'], ["\n", "", "\n"], $email_body));
        
        if (!$mail->send()) {
            throw new Exception('Mailer Error: ' . $mail->ErrorInfo);
        }
        
        error_log('Email sent successfully to: ' . $student_email);
        
        // Clean up uploaded files
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $file_path = '../uploads/email_attachments/' . $safe_filename;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
        
    } catch (Exception $e) {
        // Clean up uploaded files on error
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['name'] as $key => $filename) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $safe_filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
                    $file_path = '../uploads/email_attachments/' . $safe_filename;
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
            }
        }
        echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>