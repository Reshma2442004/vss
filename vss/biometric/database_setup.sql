-- Biometric Attendance System Database Setup

-- Biometric devices configuration
CREATE TABLE IF NOT EXISTS biometric_devices (
    device_id INT PRIMARY KEY AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    device_ip VARCHAR(15) NOT NULL,
    device_port INT DEFAULT 80,
    username VARCHAR(50) DEFAULT 'admin',
    password VARCHAR(100) NOT NULL,
    device_type ENUM('mess', 'hostel') NOT NULL,
    hostel_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Student biometric enrollment (fingerprint for login, face for attendance)
CREATE TABLE IF NOT EXISTS student_fingerprints (
    fingerprint_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    grn VARCHAR(20) NOT NULL,
    finger_index INT NOT NULL,
    template_data LONGTEXT,
    device_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (device_id) REFERENCES biometric_devices(device_id),
    UNIQUE KEY unique_student_finger (student_id, finger_index)
);

-- Student face recognition for attendance
CREATE TABLE IF NOT EXISTS student_faces (
    face_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    grn VARCHAR(20) NOT NULL,
    face_template LONGTEXT,
    photo_path VARCHAR(255),
    device_id INT,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (device_id) REFERENCES biometric_devices(device_id),
    UNIQUE KEY unique_student_face (student_id, device_id)
);

-- Attendance logs from biometric devices
CREATE TABLE IF NOT EXISTS attendance_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    grn VARCHAR(20) NOT NULL,
    event_time DATETIME NOT NULL,
    event_type ENUM('mess_morning', 'mess_night', 'hostel_checkin') NOT NULL,
    device_id INT NOT NULL,
    auth_method ENUM('face', 'fingerprint', 'password', 'card', 'manual') DEFAULT 'face',
    source ENUM('biometric', 'manual') DEFAULT 'biometric',
    raw_data TEXT,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (device_id) REFERENCES biometric_devices(device_id),
    UNIQUE KEY unique_attendance (student_id, event_time, event_type)
);

-- Daily attendance summary
CREATE TABLE IF NOT EXISTS attendance_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    morning_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
    night_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
    hostel ENUM('Present', 'Absent', 'Late') DEFAULT 'Absent',
    hostel_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    UNIQUE KEY unique_daily_summary (student_id, date)
);

-- Attendance time windows configuration
CREATE TABLE IF NOT EXISTS attendance_windows (
    window_id INT PRIMARY KEY AUTO_INCREMENT,
    hostel_id INT NOT NULL,
    window_type ENUM('mess_morning', 'mess_night', 'hostel_checkin') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    late_threshold_minutes INT DEFAULT 30,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Insert default time windows
INSERT INTO attendance_windows (hostel_id, window_type, start_time, end_time, late_threshold_minutes) VALUES
(1, 'mess_morning', '08:00:00', '10:00:00', 30),
(1, 'mess_night', '19:00:00', '21:00:00', 30),
(1, 'hostel_checkin', '20:00:00', '21:00:00', 30),
(2, 'mess_morning', '08:00:00', '10:00:00', 30),
(2, 'mess_night', '19:00:00', '21:00:00', 30),
(2, 'hostel_checkin', '20:00:00', '21:00:00', 30);

-- Insert sample biometric devices
INSERT INTO biometric_devices (device_name, device_ip, username, password, device_type, hostel_id) VALUES
('Hostel 1 - Mess Device', '192.168.1.100', 'admin', 'admin123', 'mess', 1),
('Hostel 1 - Entry Device', '192.168.1.101', 'admin', 'admin123', 'hostel', 1),
('Hostel 2 - Mess Device', '192.168.1.102', 'admin', 'admin123', 'mess', 2),
('Hostel 2 - Entry Device', '192.168.1.103', 'admin', 'admin123', 'hostel', 2);