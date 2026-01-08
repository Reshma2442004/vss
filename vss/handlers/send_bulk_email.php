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

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'send_bulk_email') {
    try {
        $recipients = json_decode($_POST['recipients'], true);
        $subject = $_POST['subject'];
        $message = $_POST['message'];
        $email_type = $_POST['email_type'];
        $include_admission_form = isset($_POST['include_admission_form']) && $_POST['include_admission_form'] === '1';
        $attachment_links = isset($_POST['attachment_links']) ? $_POST['attachment_links'] : '';
        
        if (empty($recipients)) {
            throw new Exception('No recipients selected');
        }
        
        if (empty($subject) || empty($message)) {
            throw new Exception('Subject and message are required');
        }
        
        // Handle file attachments
        $attachments = [];
        if (isset($_FILES['attachments'])) {
            $upload_dir = '../uploads/email_attachments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . $_FILES['attachments']['name'][$key];
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $attachments[] = $file_path;
                    }
                }
            }
        }
        
        $sent_count = 0;
        $failed_count = 0;
        $failed_emails = [];
        
        // Send individual emails with attachments
        foreach ($recipients as $recipient) {
            if (empty($recipient['email']) || $recipient['email'] === 'No email') {
                $failed_count++;
                $failed_emails[] = $recipient['name'] . ' (no email address)';
                continue;
            }
            
            try {
                $mail = new PHPMailer(true);
                
                // Server settings (same as working single email)
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
                $mail->addAddress($recipient['email'], $recipient['name']);
                
                // Add attachments
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment);
                }
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                
                $email_body = "Dear " . htmlspecialchars($recipient['name']) . ",<br><br>" . nl2br(htmlspecialchars($message)) . "<br><br>";
                
                if ($include_admission_form) {
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $domain = $protocol . '://' . $_SERVER['HTTP_HOST'];
                    $admission_link = $domain . dirname($_SERVER['REQUEST_URI'], 2) . '/admission_form.php';
                    $email_body .= "<a href='" . $admission_link . "'>Click here for Admission Form</a><br><br>";
                }
                
                // Handle link attachments
                if (!empty($attachment_links)) {
                    $links = array_filter(array_map('trim', explode('\n', $attachment_links)));
                    if (!empty($links)) {
                        $email_body .= "<strong>Additional Links:</strong><br>";
                        foreach ($links as $link) {
                            // Add protocol if missing
                            if (!preg_match('/^https?:\/\//i', $link)) {
                                $link = 'https://' . $link;
                            }
                            $email_body .= "<a href='" . htmlspecialchars($link) . "'>" . htmlspecialchars($link) . "</a><br>";
                        }
                        $email_body .= "<br>";
                    }
                }
                
                $email_body .= "Best regards,<br>VSS Hostel Management";
                
                $mail->Body = $email_body;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $email_body));
                
                $mail->send();
                $sent_count++;
                
            } catch (Exception $e) {
                $failed_count++;
                $failed_emails[] = $recipient['name'] . ' (' . $recipient['email'] . '): ' . $e->getMessage();
            }
        }
        
        // Clean up attachment files
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                unlink($attachment);
            }
        }
        
        $response = [
            'success' => true,
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'message' => "Bulk email completed. Sent: $sent_count, Failed: $failed_count"
        ];
        
        if (!empty($failed_emails)) {
            $response['failed_details'] = $failed_emails;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send bulk email: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>