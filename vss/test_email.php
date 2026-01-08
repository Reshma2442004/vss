<?php
require_once 'config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

$test_email = isset($_GET['email']) ? $_GET['email'] : 'test@example.com';

try {
    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;
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
    
    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    $mail->addAddress($test_email);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Test Email from VSS Hostel';
    $mail->Body = '<html><body><p>This is a test email.</p><p>If you receive this, email configuration is working correctly.</p><p>Best regards,<br>VSS Hostel</p></body></html>';
    $mail->AltBody = 'This is a test email. If you receive this, email configuration is working correctly.';
    
    if ($mail->send()) {
        echo '<h2>✅ Email sent successfully to ' . htmlspecialchars($test_email) . '</h2>';
        echo '<p>Check inbox and spam folder</p>';
    } else {
        echo '<h2>❌ Failed to send</h2>';
        echo '<p>Error: ' . $mail->ErrorInfo . '</p>';
    }
} catch (Exception $e) {
    echo '<h2>❌ Email failed</h2>';
    echo '<p>Error: ' . $mail->ErrorInfo . '</p>';
}
?>
