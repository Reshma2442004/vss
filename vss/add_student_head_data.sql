-- Add sample data for Student Head functionality
USE vss;

-- Insert sample complaints
INSERT INTO student_complaints (student_id, category, subject, description, status, priority) VALUES
(1, 'Mess', 'Poor Food Quality', 'The food quality has been consistently poor for the past week', 'pending', 'high'),
(2, 'Maintenance', 'Broken AC in Room 101', 'The air conditioning unit in room 101 is not working', 'pending', 'medium'),
(3, 'Internet', 'Slow WiFi Connection', 'Internet speed is very slow in the evening hours', 'resolved', 'low'),
(4, 'Cleanliness', 'Dirty Common Areas', 'Common areas are not being cleaned regularly', 'pending', 'medium'),
(5, 'Security', 'Gate Security Issues', 'Security guard is often absent during night hours', 'forwarded', 'high');

-- Insert sample staff reports
INSERT INTO staff_reports (staff_id, report_type, title, content, status) VALUES
(4, 'Daily Report', 'Mess Operations - Week 1', 'Weekly summary of mess operations including attendance and feedback', 'pending'),
(6, 'Monthly Report', 'Library Usage Statistics', 'Monthly report on book issues, returns, and library usage patterns', 'approved'),
(8, 'Incident Report', 'Health Emergency Response', 'Report on handling of medical emergency in Block A', 'pending'),
(4, 'Inventory Report', 'Mess Inventory Status', 'Current status of mess inventory and requirements', 'forwarded');

-- Insert sample student council members
INSERT INTO student_council (student_id, position, wing_block, contact, appointed_date) VALUES
(1, 'President', 'Block A', '9876543210', '2024-01-15'),
(2, 'Vice President', 'Block B', '9876543211', '2024-01-15'),
(3, 'Secretary', 'Block A', '9876543212', '2024-01-15'),
(4, 'Cultural Head', 'Block C', '9876543213', '2024-01-15'),
(5, 'Sports Head', 'Block B', '9876543214', '2024-01-15');

-- Insert sample digital suggestions
INSERT INTO digital_suggestions (student_id, category, suggestion, status) VALUES
(1, 'Mess', 'Introduce more variety in breakfast menu', 'new'),
(2, 'Recreation', 'Add more sports equipment in the recreation room', 'reviewed'),
(3, 'Study', 'Extend library hours during exam periods', 'implemented'),
(4, 'Technology', 'Install more charging points in common areas', 'new'),
(5, 'Environment', 'Start a recycling program in the hostel', 'reviewed');

-- Update some existing data to ensure proper relationships
UPDATE users SET hostel_id = 1 WHERE id IN (1, 2, 4, 6, 8);
UPDATE users SET hostel_id = 2 WHERE id IN (3, 5, 7, 9);

-- Insert sample students if not exists
INSERT IGNORE INTO students (grn, name, course, year, hostel_id, user_id, email, contact) VALUES
('GRN001', 'John Doe', 'Computer Science Engineering', 2, 1, NULL, 'john@example.com', '9876543210'),
('GRN002', 'Jane Smith', 'Information Technology', 3, 1, NULL, 'jane@example.com', '9876543211'),
('GRN003', 'Mike Johnson', 'Electronics Engineering', 1, 2, NULL, 'mike@example.com', '9876543212'),
('GRN004', 'Sarah Wilson', 'Mechanical Engineering', 4, 1, NULL, 'sarah@example.com', '9876543213'),
('GRN005', 'David Brown', 'Civil Engineering', 2, 2, NULL, 'david@example.com', '9876543214');

-- Insert sample hostels if not exists
INSERT IGNORE INTO hostels (id, name, capacity, location) VALUES
(1, 'Hostel Block A', 200, 'North Campus'),
(2, 'Hostel Block B', 180, 'South Campus');