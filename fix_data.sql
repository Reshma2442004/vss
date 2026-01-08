-- Fix existing student data
UPDATE students SET year = 1 WHERE year = 0 OR year IS NULL;
ALTER TABLE students ADD COLUMN IF NOT EXISTS room_no VARCHAR(20) DEFAULT NULL;