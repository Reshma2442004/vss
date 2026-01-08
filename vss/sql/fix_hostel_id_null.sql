-- Allow NULL values for hostel_id in staff table
ALTER TABLE staff MODIFY COLUMN hostel_id INT DEFAULT NULL;

-- Allow NULL values for hostel_id in users table  
ALTER TABLE users MODIFY COLUMN hostel_id INT DEFAULT NULL;