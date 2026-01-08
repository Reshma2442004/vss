-- QR Code Mess Attendance System Tables

CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_code VARCHAR(50) UNIQUE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL,
    date DATE NOT NULL,
    hostel_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

CREATE TABLE IF NOT EXISTS qr_mess_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES qr_attendance_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id),
    UNIQUE KEY unique_attendance (session_id, student_id)
);