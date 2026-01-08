-- Delete old student data that doesn't have proper CSV structure
DELETE FROM students WHERE first_name IS NULL OR first_name = '' OR room_number IS NULL;