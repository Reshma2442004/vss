<?php
require_once __DIR__ . '/EmailNotification.php';

class NotificationTriggers {
    private $emailNotification;
    
    public function __construct() {
        $this->emailNotification = new EmailNotification();
    }
    
    // Trigger when new complaint is submitted
    public static function onComplaintSubmitted($complaint_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyNewComplaint($complaint_id);
    }
    
    // Trigger when mess feedback is submitted
    public static function onMessFeedbackSubmitted($feedback_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyMessFeedback($feedback_id);
    }
    
    // Trigger when library reminder is created
    public static function onLibraryReminderCreated($reminder_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyLibraryReminder($reminder_id);
    }
    
    // Trigger when scholarship status is updated
    public static function onScholarshipStatusUpdated($scholarship_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyScholarshipUpdate($scholarship_id);
    }
    
    // Trigger when student registers for event
    public static function onEventRegistration($registration_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyEventRegistration($registration_id);
    }
    
    // Trigger when placement status is updated
    public static function onPlacementStatusUpdated($placement_id) {
        $triggers = new self();
        $triggers->emailNotification->notifyPlacementUpdate($placement_id);
    }
}
?>