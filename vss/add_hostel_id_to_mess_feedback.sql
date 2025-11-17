-- Add hostel_id column to mess_feedback table
ALTER TABLE mess_feedback ADD COLUMN hostel_id INT DEFAULT 1;
ALTER TABLE mess_feedback ADD FOREIGN KEY (hostel_id) REFERENCES hostels(id);