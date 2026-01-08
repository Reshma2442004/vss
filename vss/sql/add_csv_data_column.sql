-- Add csv_data column to staff table to store all CSV fields
ALTER TABLE staff ADD COLUMN csv_data TEXT DEFAULT NULL;

-- Add comment to explain the column
ALTER TABLE staff MODIFY COLUMN csv_data TEXT COMMENT 'JSON storage for all CSV fields from rector import';