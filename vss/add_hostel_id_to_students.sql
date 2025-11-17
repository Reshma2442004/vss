-- Add hostel_id column to students table if it doesn't exist
ALTER TABLE students ADD COLUMN hostel_id INT NULL;
ALTER TABLE students ADD FOREIGN KEY (hostel_id) REFERENCES hostels(id);

-- Update existing students with hostel_id based on room allocation
UPDATE students s 
JOIN rooms r ON s.room_id = r.id 
SET s.hostel_id = r.hostel_id 
WHERE s.room_id IS NOT NULL;