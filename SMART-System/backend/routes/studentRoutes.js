const express = require('express');
const multer = require('multer');
const { studentLogin, bulkUploadStudents, getAllStudents } = require('../controllers/studentController');
const { auth, authorize } = require('../middleware/auth');

const router = express.Router();

// Configure multer for file uploads
const upload = multer({
    dest: 'uploads/',
    fileFilter: (req, file, cb) => {
        if (file.mimetype === 'text/csv' || file.originalname.endsWith('.csv')) {
            cb(null, true);
        } else {
            cb(new Error('Only CSV files allowed'), false);
        }
    }
});

// Student login with GRN and password
router.post('/login', studentLogin);

// Bulk upload students (rector only)
router.post('/bulk-upload', auth, authorize(['rector']), upload.single('file'), bulkUploadStudents);

// Get all students (rector only)
router.get('/all', auth, authorize(['rector']), getAllStudents);

module.exports = router;