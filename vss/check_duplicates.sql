-- Check for duplicate students
SELECT grn_no, first_name, last_name, COUNT(*) as count, GROUP_CONCAT(id) as ids
FROM students 
GROUP BY grn_no, first_name, last_name 
HAVING COUNT(*) > 1
ORDER BY count DESC;