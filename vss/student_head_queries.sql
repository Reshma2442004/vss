-- Essential queries for Student Head Dashboard
-- Run these in phpMyAdmin SQL tab

-- 1. Add student_head role to users table (if not already done)
ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'rector', 'student_head', 'mess_head', 'library_head', 'health_staff', 'vvk_staff', 'placement_staff', 'ed_cell_staff', 'scholarship_staff', 'student') NOT NULL;

-- 2. Create required tables for student head functionality
CREATE TABLE IF NOT EXISTS student_complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'resolved', 'forwarded') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS staff_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    staff_id INT NOT NULL,
    report_type VARCHAR(100) NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('pending', 'approved', 'forwarded') DEFAULT 'pending',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    FOREIGN KEY (staff_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS student_council (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    position VARCHAR(100) NOT NULL,
    wing_block VARCHAR(50) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    appointed_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS digital_suggestions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    suggestion TEXT NOT NULL,
    status ENUM('new', 'reviewed', 'implemented') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- 3. Insert student head users
INSERT IGNORE INTO users (username, password, role, hostel_id) VALUES 
('student_head1@vss.com', MD5('shead123'), 'student_head', 1),
('student_head2@vss.com', MD5('shead123'), 'student_head', 2);

-- 4. Insert staff records for student heads
INSERT IGNORE INTO staff (name, role, contact, hostel_id, user_id) VALUES 
('Student Head One', 'student_head', '9876543202', 1, (SELECT id FROM users WHERE username = 'student_head1@vss.com')),
('Student Head Two', 'student_head', '9876543203', 2, (SELECT id FROM users WHERE username = 'student_head2@vss.com'));

-- 5. Insert sample data
INSERT IGNORE INTO student_complaints (student_id, category, subject, description, status, priority) VALUES
(1, 'Mess', 'Poor Food Quality', 'The food quality has been consistently poor for the past week', 'pending', 'high'),
(2, 'Maintenance', 'Broken AC in Room 101', 'The air conditioning unit in room 101 is not working', 'pending', 'medium'),
(3, 'Internet', 'Slow WiFi Connection', 'Internet speed is very slow in the evening hours', 'resolved', 'low');

INSERT IGNORE INTO staff_reports (staff_id, report_type, title, content, status) VALUES
(4, 'Daily Report', 'Mess Operations - Week 1', 'Weekly summary of mess operations including attendance and feedback', 'pending'),
(6, 'Monthly Report', 'Library Usage Statistics', 'Monthly report on book issues, returns, and library usage patterns', 'approved');

INSERT IGNORE INTO student_council (student_id, position, wing_block, contact, appointed_date) VALUES
(1, 'President', 'Block A', '9876543210', '2024-01-15'),
(2, 'Vice President', 'Block B', '9876543211', '2024-01-15');

INSERT IGNORE INTO digital_suggestions (student_id, category, suggestion, status) VALUES
(1, 'Mess', 'Introduce more variety in breakfast menu', 'new'),
(2, 'Recreation', 'Add more sports equipment in the recreation room', 'reviewed');

-- 6. Ensure hostels exist
INSERT IGNORE INTO hostels (id, name, capacity, location) VALUES 
(1, 'Hostel Block A', 200, 'North Campus'),
(2, 'Hostel Block B', 180, 'South Campus');

-- 7. Update existing users to have proper hostel assignments
UPDATE users SET hostel_id = 1 WHERE role IN ('mess_head', 'library_head', 'health_staff') AND hostel_id IS NULL LIMIT 3;
UPDATE users SET hostel_id = 2 WHERE role IN ('mess_head', 'library_head', 'health_staff') AND hostel_id IS NULL LIMIT 3;