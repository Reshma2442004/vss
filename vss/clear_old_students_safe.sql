-- Delete related records first, then students
DELETE FROM attendance WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM mess_attendance WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM leave_applications WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM mess_feedback WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM scholarships WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM avalon_uploads WHERE student_id IN (SELECT id FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL);
DELETE FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL;