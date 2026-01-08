const db = require('../config/database');
const csv = require('csv-parser');
const fs = require('fs');

// Student login with GRN and password
const studentLogin = async (req, res) => {
    try {
        const { grn, password } = req.body;
        
        const [students] = await db.execute(
            'SELECT * FROM students WHERE grn = ? AND password = ?',
            [grn, password]
        );
        
        if (students.length === 0) {
            return res.status(401).json({ error: 'Invalid GRN or password' });
        }

        const student = students[0];
        res.json({
            message: 'Login successful',
            student: {
                id: student.id,
                grn: student.grn,
                first_name: student.first_name,
                last_name: student.last_name,
                email: student.email,
                phone: student.phone,
                father_name: student.father_name,
                mother_name: student.mother_name,
                class: student.class,
                section: student.section,
                roll_number: student.roll_number,
                hostel_room: student.hostel_room
            }
        });
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

// Bulk upload students from CSV
const bulkUploadStudents = async (req, res) => {
    try {
        if (!req.file) {
            return res.status(400).json({ error: 'No file uploaded' });
        }

        const filePath = req.file.path;
        const students = [];
        let totalRecords = 0;
        let successfulImports = 0;

        fs.createReadStream(filePath)
            .pipe(csv())
            .on('data', (row) => {
                totalRecords++;
                const grn = row.grn || `GRN${Date.now()}${Math.floor(Math.random() * 1000)}`;
                const password = row.password || Math.random().toString(36).slice(-8);
                
                students.push({
                    grn,
                    password,
                    first_name: row.first_name,
                    last_name: row.last_name,
                    email: row.email,
                    phone: row.phone,
                    father_name: row.father_name,
                    mother_name: row.mother_name,
                    class: row.class,
                    section: row.section,
                    roll_number: row.roll_number,
                    hostel_room: row.hostel_room,
                    is_hostel_student: row.is_hostel_student === 'true'
                });
            })
            .on('end', async () => {
                for (const student of students) {
                    try {
                        await db.execute(
                            `INSERT INTO students (grn, password, first_name, last_name, email, phone, 
                             father_name, mother_name, class, section, roll_number, hostel_room, 
                             is_hostel_student, uploaded_by) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
                            [
                                student.grn, student.password, student.first_name, student.last_name,
                                student.email, student.phone, student.father_name, student.mother_name,
                                student.class, student.section, student.roll_number, student.hostel_room,
                                student.is_hostel_student, req.user.id
                            ]
                        );
                        successfulImports++;
                    } catch (error) {
                        console.error('Insert error:', error);
                    }
                }

                fs.unlinkSync(filePath);

                res.json({
                    message: 'Bulk upload completed',
                    totalRecords,
                    successfulImports,
                    students: students.map(s => ({ grn: s.grn, password: s.password, name: `${s.first_name} ${s.last_name}` }))
                });
            });
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

// Get all students
const getAllStudents = async (req, res) => {
    try {
        const [students] = await db.execute(
            'SELECT id, grn, first_name, last_name, class, section, is_hostel_student FROM students ORDER BY first_name'
        );
        
        res.json(students);
    } catch (error) {
        res.status(400).json({ error: error.message });
    }
};

module.exports = {
    studentLogin,
    bulkUploadStudents,
    getAllStudents
};