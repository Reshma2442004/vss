-- Add time column to mess_attendance table
ALTER TABLE mess_attendance ADD COLUMN time TIME NULL;

-- Add created_at column if it doesn't exist
ALTER TABLE mess_attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- Update existing records
UPDATE mess_attendance SET time = '00:00:00' WHERE time IS NULL;