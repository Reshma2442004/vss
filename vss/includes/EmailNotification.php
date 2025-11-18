<?php
require_once __DIR__ . '/../config/database.php';

class EmailNotification {
    private $pdo;
    private $from_email = 'noreply@vss-hostel.com';
    private $from_name = 'VSS Hostel Management';
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }
    
    private function sendEmail($to, $subject, $message, $headers = '') {
        if (empty($headers)) {
            $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
            $headers .= "Reply-To: {$this->from_email}\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }
        
        return mail($to, $subject, $message, $headers);
    }
    
    private function getEmailTemplate($title, $content, $action_url = '') {
        $action_button = $action_url ? "<a href='{$action_url}' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin:20px 0'>View Details</a>" : '';
        
        return "
        <html>
        <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333'>
            <div style='max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd'>
                <h2 style='color:#007bff;border-bottom:2px solid #007bff;padding-bottom:10px'>{$title}</h2>
                <div style='margin:20px 0'>{$content}</div>
                {$action_button}
                <hr style='margin:30px 0'>
                <p style='font-size:12px;color:#666'>This is an automated notification from VSS Hostel Management System.</p>
            </div>
        </body>
        </html>";
    }
    
    public function notifyNewComplaint($complaint_id) {
        $stmt = $this->pdo->prepare("
            SELECT sc.*, s.name as student_name, s.email as student_email, 
                   h.name as hostel_name, u.username as staff_username
            FROM student_complaints sc
            JOIN students s ON sc.student_id = s.id
            JOIN hostels h ON s.hostel_id = h.id
            LEFT JOIN users u ON u.hostel_id = h.id AND u.role = 'student_head'
            WHERE sc.id = ?
        ");
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch();
        
        if ($complaint) {
            // Notify student head
            $staff_stmt = $this->pdo->prepare("
                SELECT st.name, u.username, s.email 
                FROM staff st 
                JOIN users u ON st.user_id = u.id 
                LEFT JOIN students s ON u.username = s.grn
                WHERE u.role = 'student_head' AND st.hostel_id = ?
            ");
            $staff_stmt->execute([$complaint['hostel_id']]);
            $staff = $staff_stmt->fetch();
            
            if ($staff && !empty($staff['email'])) {
                $subject = "New Student Complaint - {$complaint['subject']}";
                $content = "
                    <p><strong>Student:</strong> {$complaint['student_name']}</p>
                    <p><strong>Category:</strong> {$complaint['category']}</p>
                    <p><strong>Subject:</strong> {$complaint['subject']}</p>
                    <p><strong>Priority:</strong> {$complaint['priority']}</p>
                    <p><strong>Description:</strong></p>
                    <p>{$complaint['description']}</p>
                ";
                
                $this->sendEmail($staff['email'], $subject, $this->getEmailTemplate($subject, $content));
            }
            
            // Send confirmation to student
            if (!empty($complaint['student_email'])) {
                $subject = "Complaint Submitted Successfully";
                $content = "
                    <p>Dear {$complaint['student_name']},</p>
                    <p>Your complaint has been successfully submitted and assigned ID: <strong>#{$complaint_id}</strong></p>
                    <p><strong>Subject:</strong> {$complaint['subject']}</p>
                    <p>We will review your complaint and get back to you soon.</p>
                ";
                
                $this->sendEmail($complaint['student_email'], $subject, $this->getEmailTemplate($subject, $content));
            }
        }
    }
    
    public function notifyMessFeedback($feedback_id) {
        $stmt = $this->pdo->prepare("
            SELECT mf.*, s.name as student_name, s.email as student_email, s.hostel_id
            FROM mess_feedback mf
            JOIN students s ON mf.student_id = s.id
            WHERE mf.id = ?
        ");
        $stmt->execute([$feedback_id]);
        $feedback = $stmt->fetch();
        
        if ($feedback) {
            // Notify mess head
            $staff_stmt = $this->pdo->prepare("
                SELECT st.name, s.email 
                FROM staff st 
                JOIN users u ON st.user_id = u.id 
                LEFT JOIN students s ON u.username = s.grn
                WHERE u.role = 'mess_head' AND st.hostel_id = ?
            ");
            $staff_stmt->execute([$feedback['hostel_id']]);
            $staff = $staff_stmt->fetch();
            
            if ($staff && !empty($staff['email'])) {
                $subject = "New Mess Feedback - {$feedback['subject']}";
                $content = "
                    <p><strong>Student:</strong> {$feedback['student_name']}</p>
                    <p><strong>Type:</strong> {$feedback['feedback_type']}</p>
                    <p><strong>Category:</strong> {$feedback['category']}</p>
                    <p><strong>Rating:</strong> {$feedback['rating']}/5</p>
                    <p><strong>Priority:</strong> {$feedback['priority']}</p>
                    <p><strong>Message:</strong></p>
                    <p>{$feedback['message']}</p>
                ";
                
                $this->sendEmail($staff['email'], $subject, $this->getEmailTemplate($subject, $content));
            }
        }
    }
    
    public function notifyLibraryReminder($reminder_id) {
        $stmt = $this->pdo->prepare("
            SELECT lr.*, s.name as student_name, s.email as student_email,
                   b.title as book_title, bi.issue_date, bi.fine
            FROM library_reminders lr
            JOIN students s ON lr.student_id = s.id
            JOIN book_issues bi ON lr.book_issue_id = bi.id
            JOIN books b ON bi.book_id = b.id
            WHERE lr.id = ?
        ");
        $stmt->execute([$reminder_id]);
        $reminder = $stmt->fetch();
        
        if ($reminder && !empty($reminder['student_email'])) {
            $subject = "Library Book Return Reminder";
            $content = "
                <p>Dear {$reminder['student_name']},</p>
                <p><strong>Book:</strong> {$reminder['book_title']}</p>
                <p><strong>Issue Date:</strong> {$reminder['issue_date']}</p>
                <p><strong>Current Fine:</strong> ₹{$reminder['fine']}</p>
                <p><strong>Message:</strong></p>
                <p>{$reminder['message']}</p>
            ";
            
            $this->sendEmail($reminder['student_email'], $subject, $this->getEmailTemplate($subject, $content));
        }
    }
    
    public function notifyScholarshipUpdate($scholarship_id) {
        $stmt = $this->pdo->prepare("
            SELECT sch.*, s.name as student_name, s.email as student_email
            FROM scholarships sch
            JOIN students s ON sch.student_id = s.id
            WHERE sch.id = ?
        ");
        $stmt->execute([$scholarship_id]);
        $scholarship = $stmt->fetch();
        
        if ($scholarship && !empty($scholarship['student_email'])) {
            $status_msg = $scholarship['status'] == 'approved' ? 'approved' : 
                         ($scholarship['status'] == 'rejected' ? 'rejected' : 'under review');
            
            $subject = "Scholarship Application Update - {$scholarship['scholarship_type']}";
            $content = "
                <p>Dear {$scholarship['student_name']},</p>
                <p>Your scholarship application has been <strong>{$status_msg}</strong>.</p>
                <p><strong>Scholarship Type:</strong> {$scholarship['scholarship_type']}</p>
                <p><strong>Amount:</strong> ₹{$scholarship['amount']}</p>
                <p><strong>Status:</strong> {$scholarship['status']}</p>
            ";
            
            if (!empty($scholarship['remarks'])) {
                $content .= "<p><strong>Remarks:</strong> {$scholarship['remarks']}</p>";
            }
            
            $this->sendEmail($scholarship['student_email'], $subject, $this->getEmailTemplate($subject, $content));
        }
    }
    
    public function notifyEventRegistration($registration_id) {
        $stmt = $this->pdo->prepare("
            SELECT er.*, s.name as student_name, s.email as student_email,
                   e.title as event_title, e.date as event_date, e.venue
            FROM event_registrations er
            JOIN students s ON er.student_id = s.id
            JOIN events e ON er.event_id = e.id
            WHERE er.id = ?
        ");
        $stmt->execute([$registration_id]);
        $registration = $stmt->fetch();
        
        if ($registration && !empty($registration['student_email'])) {
            $subject = "Event Registration Confirmation - {$registration['event_title']}";
            $content = "
                <p>Dear {$registration['student_name']},</p>
                <p>You have successfully registered for the event:</p>
                <p><strong>Event:</strong> {$registration['event_title']}</p>
                <p><strong>Date:</strong> {$registration['event_date']}</p>
                <p><strong>Venue:</strong> {$registration['venue']}</p>
                <p>We look forward to your participation!</p>
            ";
            
            $this->sendEmail($registration['student_email'], $subject, $this->getEmailTemplate($subject, $content));
        }
    }
    
    public function notifyPlacementUpdate($placement_id) {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, s.name as student_name, s.email as student_email
            FROM placement_records pr
            JOIN students s ON pr.student_id = s.id
            WHERE pr.id = ?
        ");
        $stmt->execute([$placement_id]);
        $placement = $stmt->fetch();
        
        if ($placement && !empty($placement['student_email'])) {
            $subject = "Placement Update - {$placement['company_name']}";
            $content = "
                <p>Dear {$placement['student_name']},</p>
                <p>Your placement application status has been updated:</p>
                <p><strong>Company:</strong> {$placement['company_name']}</p>
                <p><strong>Position:</strong> {$placement['position']}</p>
                <p><strong>Status:</strong> {$placement['status']}</p>
                <p><strong>Package:</strong> ₹{$placement['package_amount']}</p>
            ";
            
            $this->sendEmail($placement['student_email'], $subject, $this->getEmailTemplate($subject, $content));
        }
    }
    
    public function sendBulkNotification($user_role, $hostel_id, $subject, $message) {
        if ($user_role == 'student') {
            $stmt = $this->pdo->prepare("
                SELECT name, email FROM students 
                WHERE hostel_id = ? AND email IS NOT NULL AND email != ''
            ");
            $stmt->execute([$hostel_id]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT st.name, s.email 
                FROM staff st 
                JOIN users u ON st.user_id = u.id 
                LEFT JOIN students s ON u.username = s.grn
                WHERE u.role = ? AND st.hostel_id = ? AND s.email IS NOT NULL AND s.email != ''
            ");
            $stmt->execute([$user_role, $hostel_id]);
        }
        
        $users = $stmt->fetchAll();
        $sent_count = 0;
        
        foreach ($users as $user) {
            if ($this->sendEmail($user['email'], $subject, $this->getEmailTemplate($subject, $message))) {
                $sent_count++;
            }
        }
        
        return $sent_count;
    }
}
?>