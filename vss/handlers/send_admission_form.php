<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rector') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Download PHPMailer if not exists
$phpmailer_path = '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (!file_exists($phpmailer_path)) {
    $vendor_dir = '../vendor/phpmailer/phpmailer/src/';
    if (!is_dir($vendor_dir)) {
        mkdir($vendor_dir, 0777, true);
    }
    
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

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_admission_form') {
    try {
        $email = $_POST['email'];
        $name = $_POST['name'];
        $additional_message = $_POST['additional_message'] ?? '';
        
        if (empty($email) || empty($name)) {
            throw new Exception('Email and name are required');
        }
        
        $mail = new PHPMailer(true);
        
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
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $name);
        
        // Get current domain
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
        $admission_link = $domain . dirname($_SERVER['REQUEST_URI'], 2) . '/admission_form.php';
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Hostel Admission Form - VSS';
        $mail->Body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                    <h2 style='margin: 0;'>VSS Hostel Management System</h2>
                    <p style='margin: 5px 0 0 0;'>Hostel Admission Form</p>
                </div>
                <div style='padding: 20px;'>
                    <p>Dear " . htmlspecialchars($name) . ",</p>
                    <p>We are pleased to share the hostel admission form with you. Please click the link below to fill out your application:</p>
                    " . (!empty($additional_message) ? "<div style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #2196f3;'><strong>Message from Rector:</strong><br>" . nl2br(htmlspecialchars($additional_message)) . "</div>" : "") . "
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='" . $admission_link . "' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;'>
                            📝 Fill Admission Form
                        </a>
                    </div>
                    <p><strong>Important Instructions:</strong></p>
                    <ul>
                        <li>Fill all required fields accurately</li>
                        <li>Provide valid contact information</li>
                        <li>Submit the form before the deadline</li>
                        <li>Keep a copy of your application details</li>
                    </ul>
                    <p>If you have any questions, please contact the hostel administration.</p>
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
        
        $mail->AltBody = "Dear $name,\n\nPlease visit the following link to fill out your hostel admission form:\n\n$admission_link\n\nBest regards,\nVSS Hostel Management Team";
        
        $mail->send();
        
        echo json_encode(['success' => true, 'message' => 'Admission form link sent successfully to ' . $name]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>