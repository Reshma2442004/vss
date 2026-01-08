-- Add password column to students table if it doesn't exist
ALTER TABLE students 
ADD COLUMN IF NOT EXISTS password VARCHAR(255) DEFAULT NULL;

-- Update existing students with default passwords
UPDATE students 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE password IS NULL;