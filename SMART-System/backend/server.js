const express = require('express');
const cors = require('cors');
require('dotenv').config();

const studentRoutes = require('./routes/studentRoutes');
const hostelStaffRoutes = require('./routes/hostelStaffRoutes');

const app = express();
const PORT = process.env.PORT || 5000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Routes
app.use('/api/students', studentRoutes);
app.use('/api/hostel-staff', hostelStaffRoutes);

// Health check
app.get('/api/health', (req, res) => {
    res.json({ message: 'SMART System API is running' });
});

app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
});