-- Update hostel name and add new hostel for register.php
UPDATE hostels SET name = 'Latika Jaywantrao Gaytonde Hostel' WHERE name = 'Latika Jayvantrav Gaybote girls hostel';

-- Add new hostel
INSERT INTO hostels (name, capacity, location, rector_id) VALUES 
('New Girls Hostel A wing', 180, 'Campus Block G', NULL);