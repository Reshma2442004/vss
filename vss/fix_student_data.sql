-- Fix existing student data to ensure proper display
UPDATE students SET year = 1 WHERE year = 0 OR year IS NULL;
UPDATE students SET room_no = 'Not Assigned' WHERE room_no IS NULL OR room_no = '';
UPDATE students SET email = CONCAT(name, '@student.com') WHERE email IS NULL OR email = '';
UPDATE students SET contact = '0000000000' WHERE contact IS NULL OR contact = '';
UPDATE students SET course = 'General' WHERE course IS NULL OR course = '';