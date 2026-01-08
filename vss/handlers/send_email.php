<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Download PHPMailer if not exists
$phpmailer_path = '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (!file_exists($phpmailer_path)) {
    // Create vendor directory structure
    $vendor_dir = '../vendor/phpmailer/phpmailer/src/';
    if (!is_dir($vendor_dir)) {
        mkdir($vendor_dir, 0777, true);
    }
    
    // Download PHPMailer files
    $files = [
        'PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php'
    ];
    
    foreach ($files as $filename => $url) {
        $content = file_get_contents($url);
        if ($content !== false) {
            file_put_contents($vendor_dir . $filename, $content);
        }
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $rector_id = $_POST['rector_id'] ?? '';
        $subject = $_POST['subject'] ?? '';
        $message = $_POST['message'] ?? '';
        
        // Log admin details
        error_log("ADMIN EMAIL DEBUG: Admin ID: " . $_SESSION['user_id']);
        error_log("ADMIN EMAIL DEBUG: Admin Username: " . $_SESSION['username']);
        error_log("ADMIN EMAIL DEBUG: Rector ID: " . $rector_id);
        error_log("ADMIN EMAIL DEBUG: Subject: " . $subject);
        error_log("ADMIN EMAIL DEBUG: SMTP Config - Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", Username: " . SMTP_USERNAME);
        
        if (empty($rector_id) || empty($subject) || empty($message)) {
            throw new Exception('All fields are required');
        }
        
        // Get rector details from staff table
        $stmt = $pdo->prepare("SELECT u.username, st.name as full_name FROM staff st JOIN users u ON st.user_id = u.id WHERE st.id = ? AND st.role = 'rector'");
        $stmt->execute([$rector_id]);
        $rector = $stmt->fetch();
        
        error_log("ADMIN EMAIL DEBUG: Rector found: " . ($rector ? 'Yes' : 'No'));
        if ($rector) {
            error_log("ADMIN EMAIL DEBUG: Rector Email: " . $rector['username']);
            error_log("ADMIN EMAIL DEBUG: Rector Name: " . $rector['full_name']);
        }
        
        if (!$rector) {
            throw new Exception('Rector not found');
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = sakshihudge2004@gmail.com;
        $mail->Password = tkylhqyezplsbalb;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 2; // Enable detailed debugging
        $mail->Debugoutput = 'html';
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($rector['username'], $rector['full_name'] ?: 'Rector');
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Handle file attachments
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $upload_dir = '../uploads/email_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === 0) {
                    $filename = time() . '_' . $_FILES['attachments']['name'][$i];
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $filepath)) {
                        $mail->addAttachment($filepath, $_FILES['attachments']['name'][$i]);
                    }
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>VSS Hostel Management System</h2>
                    <p style='margin: 5px 0 0 0;'>Official Communication</p>
                </div>
                <div style='padding: 20px;'>
                    <p>Dear " . ($rector['full_name'] ?: 'Rector') . ",</p>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='color: #666; font-size: 14px;'>
                        Best regards,<br>
                        <strong>VSS Hostel Management Team</strong><br>
                        <em>This is an automated email from the Hostel Management System.</em>
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->AltBody = strip_tags($message);
        
        // Send email
        error_log("ADMIN EMAIL DEBUG: Attempting to send email...");
        $mail->send();
        error_log("ADMIN EMAIL DEBUG: Email sent successfully!");
        
        // Log email in database
        $log_stmt = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_id, subject, message, sent_at, status) VALUES (?, ?, ?, ?, NOW(), 'sent')");
        $log_stmt->execute([$_SESSION['user_id'], $rector_id, $subject, $message]);
        
        // Clean up attachment files
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                if ($_FILES['attachments']['error'][$i] === 0) {
                    $filename = time() . '_' . $_FILES['attachments']['name'][$i];
                    $filepath = $upload_dir . $filename;
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Email sent successfully to ' . $rector['full_name']]);
        
    } catch (Exception $e) {
        // Log detailed error
        error_log("ADMIN EMAIL DEBUG: ERROR OCCURRED: " . $e->getMessage());
        error_log("ADMIN EMAIL DEBUG: Error File: " . $e->getFile() . " Line: " . $e->getLine());
        
        if (isset($pdo) && isset($rector_id)) {
            $log_stmt = $pdo->prepare("INSERT INTO email_logs (sender_id, recipient_id, subject, message, sent_at, status, error_message) VALUES (?, ?, ?, ?, NOW(), 'failed', ?)");
            $log_stmt->execute([$_SESSION['user_id'], $rector_id, $subject ?? '', $message ?? '', $e->getMessage()]);
        }
        
        echo json_encode(['success' => false, 'message' => 'Email failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>