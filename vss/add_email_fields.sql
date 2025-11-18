-- Add email fields to tables that don't have them
ALTER TABLE staff ADD COLUMN email VARCHAR(100) AFTER contact;
ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER role;

-- Update existing staff emails (sample data)
UPDATE staff SET email = 'rector1@vss-hostel.com' WHERE role = 'rector' AND hostel_id = 1;
UPDATE staff SET email = 'rector2@vss-hostel.com' WHERE role = 'rector' AND hostel_id = 2;
UPDATE staff SET email = 'mess1@vss-hostel.com' WHERE role = 'mess_head' AND hostel_id = 1;
UPDATE staff SET email = 'mess2@vss-hostel.com' WHERE role = 'mess_head' AND hostel_id = 2;
UPDATE staff SET email = 'library1@vss-hostel.com' WHERE role = 'library_head' AND hostel_id = 1;
UPDATE staff SET email = 'library2@vss-hostel.com' WHERE role = 'library_head' AND hostel_id = 2;
UPDATE staff SET email = 'health1@vss-hostel.com' WHERE role = 'health_staff' AND hostel_id = 1;
UPDATE staff SET email = 'health2@vss-hostel.com' WHERE role = 'health_staff' AND hostel_id = 2;
UPDATE staff SET email = 'placement1@vss-hostel.com' WHERE role = 'placement_staff' AND hostel_id = 1;
UPDATE staff SET email = 'placement2@vss-hostel.com' WHERE role = 'placement_staff' AND hostel_id = 2;
UPDATE staff SET email = 'scholarship1@vss-hostel.com' WHERE role = 'scholarship_staff' AND hostel_id = 1;
UPDATE staff SET email = 'scholarship2@vss-hostel.com' WHERE role = 'scholarship_staff' AND hostel_id = 2;

-- Create email notifications log table
CREATE TABLE IF NOT EXISTS email_notifications_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    recipient_email VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient (recipient_email),
    INDEX idx_type (notification_type),
    INDEX idx_sent_at (sent_at)
);