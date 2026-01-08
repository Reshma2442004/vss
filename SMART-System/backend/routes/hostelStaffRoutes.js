const express = require('express');
const { addHostelStaff, getHostelStaff, removeHostelStaff, getAvailableStudents } = require('../controllers/hostelStaffController');
const { auth, authorize } = require('../middleware/auth');

const router = express.Router();

// Add student as hostel staff (rector only)
router.post('/add', auth, authorize(['rector']), addHostelStaff);

// Get all hostel staff
router.get('/', auth, authorize(['rector']), getHostelStaff);

// Remove hostel staff
router.delete('/:staff_id', auth, authorize(['rector']), removeHostelStaff);

// Get available students for staff assignment
router.get('/available-students', auth, authorize(['rector']), getAvailableStudents);

module.exports = router;