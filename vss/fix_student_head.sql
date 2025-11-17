-- Fix student head hostel assignments
USE vss;

-- Update student head users to have proper hostel assignments
UPDATE users SET hostel_id = 1 WHERE username = 'student_head1';
UPDATE users SET hostel_id = 2 WHERE username = 'student_head2';

-- Ensure hostels exist
INSERT IGNORE INTO hostels (id, name, capacity, location) VALUES 
(1, 'Hostel Block A', 200, 'North Campus'),
(2, 'Hostel Block B', 180, 'South Campus');

-- Check current student head users
SELECT id, username, role, hostel_id FROM users WHERE role = 'student_head';