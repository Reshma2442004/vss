-- Remove all duplicates keeping only the lowest ID for each GRN
DELETE s1 FROM students s1
INNER JOIN students s2 
WHERE s1.id > s2.id 
AND s1.grn_no = s2.grn_no;