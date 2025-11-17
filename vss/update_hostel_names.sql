-- Update hostel names to new requirements
UPDATE hostels SET name = 'Aapte girlshostel' WHERE name LIKE '%Aapte%';
UPDATE hostels SET name = 'sumitra sadan girlshostel' WHERE name LIKE '%Sumitra%';
UPDATE hostels SET name = 'P.D.karkhanis boys hostel' WHERE name LIKE '%Karkhanis%';
UPDATE hostels SET name = 'Haribhaupathak Boys hostel' WHERE name LIKE '%Haribhaupathak%';
UPDATE hostels SET name = 'lajpat sankul Boys hostel' WHERE name LIKE '%Lajpat%';
UPDATE hostels SET name = 'Latika Jayvantrav Gaybote girls hostel' WHERE name LIKE '%Latika%' OR name LIKE '%Gaytonde%';

-- Display updated hostels
SELECT * FROM hostels ORDER BY id;