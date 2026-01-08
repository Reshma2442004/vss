const db = require('../config/database');

// Add student as hostel staff
const addHostelStaff = async (req, res) => {
    try {
        const { student_id, position } = req.body;
        
        // Check if student exists
        const [students] = await db.execute(
            'SELECT * FROM students WHERE id = ?',
            [student_id]
        );
        
        if (students.length === 0) {
            return res.status(404).json({ error: 'Student not found' });
        }

        // Check if already assigned as staff
        const [existing] = await db.execute(
            'SELECT * FROM hostel_staff WHERE student_id = ? AND is_active = TRUE',
            [student_id]
        );
        
        if (existing.length > 0) {
            return res.status(400).json({ error: 'Student already assigned as hostel staff' });
        }

        // Add as hostel staff
        const [result] = await db.execute(
            'INSERT INTO hostel_staff (student_id, position, assigned_by, assigned_date) VALUES (?, ?, ?, CURDATE())',
            [student_id, position, req.user.id]
        );

        res.json({
            message: 'Student assigned as hostel staff successfully',
            staff_id: result.insertId
        });
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

// Get all hostel staff
const getHostelStaff = async (req, res) => {
    try {
        const [staff] = await db.execute(
            `SELECT hs.*, s.grn, s.first_name, s.last_name, s.hostel_room
             FROM hostel_staff hs
             JOIN students s ON hs.student_id = s.id
             WHERE hs.is_active = TRUE
             ORDER BY hs.position, s.first_name`
        );
        
        res.json(staff);
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

// Remove hostel staff
const removeHostelStaff = async (req, res) => {
    try {
        const { staff_id } = req.params;
        
        await db.execute(
            'UPDATE hostel_staff SET is_active = FALSE WHERE id = ?',
            [staff_id]
        );

        res.json({ message: 'Hostel staff removed successfully' });
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

// Get available students for staff assignment
const getAvailableStudents = async (req, res) => {
    try {
        const [students] = await db.execute(
            `SELECT s.id, s.grn, s.first_name, s.last_name, s.class, s.hostel_room
             FROM students s
             LEFT JOIN hostel_staff hs ON s.id = hs.student_id AND hs.is_active = TRUE
             WHERE s.is_hostel_student = TRUE AND hs.id IS NULL
             ORDER BY s.first_name`
        );
        
        res.json(students);
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

module.exports = {
    addHostelStaff,
    getHostelStaff,
    removeHostelStaff,
    getAvailableStudents
};