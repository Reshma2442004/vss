-- Biometric Integration Tables for HMS
USE vss;

-- Add finger_id to students table
ALTER TABLE students ADD COLUMN finger_id INT UNIQUE AFTER contact;

-- Biometric devices table
CREATE TABLE IF NOT EXISTS biometric_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_name VARCHAR(100) NOT NULL,
    device_ip VARCHAR(15) NOT NULL,
    device_type ENUM('mess', 'hostel') NOT NULL,
    hostel_id INT,
    username VARCHAR(50) DEFAULT 'admin',
    password VARCHAR(100) DEFAULT 'admin123',
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Attendance logs from biometric devices
CREATE TABLE IF NOT EXISTS attendance_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    finger_id INT NOT NULL,
    event_time DATETIME NOT NULL,
    event_type ENUM('mess_morning', 'mess_night', 'hostel_checkin') NOT NULL,
    source ENUM('biometric', 'manual') DEFAULT 'biometric',
    device_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (device_id) REFERENCES biometric_devices(id)
);

-- Daily attendance summary
CREATE TABLE IF NOT EXISTS attendance_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    morning_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
    night_meal ENUM('Present', 'Absent') DEFAULT 'Absent',
    hostel ENUM('Present', 'Absent', 'Late') DEFAULT 'Absent',
    UNIQUE KEY unique_summary (student_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Insert sample devices
INSERT IGNORE INTO biometric_devices (device_name, device_ip, device_type, hostel_id) VALUES 
('Mess Entrance', '192.168.1.100', 'mess', 1),
('Hostel A Entrance', '192.168.1.101', 'hostel', 1);