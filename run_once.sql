-- Run this SQL in phpMyAdmin to add room_no column
ALTER TABLE students ADD COLUMN IF NOT EXISTS room_no VARCHAR(20) DEFAULT NULL;