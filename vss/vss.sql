-- VSS Hostel Management System Database
CREATE DATABASE IF NOT EXISTS vss;
USE vss;

-- Users table (for authentication)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'rector', 'student_head', 'mess_head', 'library_head', 'health_staff', 'vvk_staff', 'placement_staff', 'ed_cell_staff', 'scholarship_staff', 'student') NOT NULL,
    hostel_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hostels table
CREATE TABLE hostels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL,
    location VARCHAR(200) NOT NULL,
    rector_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    grn VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    course VARCHAR(100) NOT NULL,
    year INT NOT NULL,
    hostel_id INT,
    room_id INT DEFAULT NULL,
    user_id INT,
    email VARCHAR(100),
    contact VARCHAR(15),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) NOT NULL,
    capacity INT NOT NULL,
    hostel_id INT NOT NULL,
    occupied INT DEFAULT 0,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Staff table
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    contact VARCHAR(15),
    hostel_id INT NOT NULL,
    user_id INT,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    UNIQUE KEY unique_attendance (student_id, date)
);

-- Mess attendance table
CREATE TABLE mess_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL,
    taken BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(id),
    UNIQUE KEY unique_mess_attendance (student_id, date, meal_type)
);

-- Inventory table
CREATE TABLE inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    low_stock_alert INT DEFAULT 10,
    hostel_id INT NOT NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Books table
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20),
    stock INT DEFAULT 0,
    hostel_id INT NOT NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Book issues table
CREATE TABLE book_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    book_id INT NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE DEFAULT NULL,
    fine DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);

-- Events table
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    venue VARCHAR(100),
    hostel_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Event registrations table
CREATE TABLE event_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    UNIQUE KEY unique_registration (student_id, event_id)
);

-- Health records table
CREATE TABLE health_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    medical_history TEXT,
    allergies TEXT,
    insurance_no VARCHAR(50),
    vaccination_status TEXT,
    blood_group VARCHAR(5),
    emergency_contact VARCHAR(15),
    FOREIGN KEY (student_id) REFERENCES students(id),
    UNIQUE KEY unique_health_record (student_id)
);

-- Health visits table
CREATE TABLE health_visits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    visit_date DATE NOT NULL,
    complaint TEXT,
    treatment TEXT,
    prescribed_medicine TEXT,
    follow_up_required BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Scholarships table
CREATE TABLE scholarships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    scholarship_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_date DATE NOT NULL,
    approved_date DATE DEFAULT NULL,
    documents TEXT,
    remarks TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Startup ideas table
CREATE TABLE startup_ideas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'in_development') DEFAULT 'submitted',
    mentor_id INT DEFAULT NULL,
    funding_requested DECIMAL(12,2) DEFAULT 0,
    funding_approved DECIMAL(12,2) DEFAULT 0,
    submitted_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Mentors table
CREATE TABLE mentors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    expertise VARCHAR(200),
    contact VARCHAR(15),
    email VARCHAR(100),
    active_projects INT DEFAULT 0
);

-- Placement records table
CREATE TABLE placement_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    company_name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    package_amount DECIMAL(10,2),
    placement_type ENUM('internship', 'full_time') NOT NULL,
    status ENUM('applied', 'interview', 'selected', 'rejected', 'joined') DEFAULT 'applied',
    application_date DATE NOT NULL,
    interview_date DATE DEFAULT NULL,
    joining_date DATE DEFAULT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Placement drives table
CREATE TABLE placement_drives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    package_offered DECIMAL(10,2),
    eligibility_criteria TEXT,
    drive_date DATE NOT NULL,
    registration_deadline DATE,
    hostel_id INT NOT NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Mess feedback table
CREATE TABLE mess_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(50),
    message TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    response_message TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Food wastage tracking table
CREATE TABLE food_wastage (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hostel_id INT NOT NULL,
    date DATE NOT NULL,
    meal_type ENUM('breakfast', 'lunch', 'dinner') NOT NULL,
    wastage_amount DECIMAL(5,2) NOT NULL,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Password reset tokens table
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
);

-- Library reminders table
CREATE TABLE library_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    book_issue_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_by INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (book_issue_id) REFERENCES book_issues(id),
    FOREIGN KEY (sent_by) REFERENCES users(id)
);

-- Student complaints table
CREATE TABLE student_complaints (
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

-- Staff reports table
CREATE TABLE staff_reports (
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

-- Student council table
CREATE TABLE student_council (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    position VARCHAR(100) NOT NULL,
    wing_block VARCHAR(50) NOT NULL,
    contact VARCHAR(15) NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    appointed_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Digital suggestions table
CREATE TABLE digital_suggestions (
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

-- Insert sample data
INSERT INTO users (username, password, role, hostel_id) VALUES 
('admin', MD5('admin123'), 'super_admin', NULL),
('rector1', MD5('rector123'), 'rector', 1),
('rector2', MD5('rector123'), 'rector', 2),
('mess1', MD5('mess123'), 'mess_head', 1),
('mess2', MD5('mess123'), 'mess_head', 2),
('library1', MD5('lib123'), 'library_head', 1),
('library2', MD5('lib123'), 'library_head', 2),
('student_head1', MD5('shead123'), 'student_head', 1),
('student_head2', MD5('shead123'), 'student_head', 2),
('health1', MD5('health123'), 'health_staff', 1),
('health2', MD5('health123'), 'health_staff', 2),
('vvk1', MD5('vvk123'), 'vvk_staff', 1),
('vvk2', MD5('vvk123'), 'vvk_staff', 2),
('placement1', MD5('place123'), 'placement_staff', 1),
('placement2', MD5('place123'), 'placement_staff', 2),
('ed1', MD5('ed123'), 'ed_cell_staff', 1),
('ed2', MD5('ed123'), 'ed_cell_staff', 2),
('scholarship1', MD5('scholar123'), 'scholarship_staff', 1),
('scholarship2', MD5('scholar123'), 'scholarship_staff', 2),
('vvk1', MD5('vvk123'), 'vvk_staff', 1),
('vvk2', MD5('vvk123'), 'vvk_staff', 2),
('placement1', MD5('place123'), 'placement_staff', 1),
('placement2', MD5('place123'), 'placement_staff', 2),
('ed1', MD5('ed123'), 'ed_cell_staff', 1),
('ed2', MD5('ed123'), 'ed_cell_staff', 2),
('scholarship1', MD5('scholar123'), 'scholarship_staff', 1),
('scholarship2', MD5('scholar123'), 'scholarship_staff', 2),
('student1', MD5('student123'), 'student', 1),
('student2', MD5('student123'), 'student', 1),
('student3', MD5('student123'), 'student', 2);

INSERT INTO hostels (name, capacity, location, rector_id) VALUES 
('Aapte girlshostel', 150, 'South Campus Block B', 2),
('sumitra sadan girlshostel', 180, 'East Campus Block C', 3),
('P.D.karkhanis boys hostel', 120, 'West Campus Block D', NULL),
('Haribhaupathak Boys hostel', 160, 'North Campus Block F', NULL),
('lajpat sankul Boys hostel', 220, 'Central Campus Block E', NULL),
('Latika Jayvantrav Gaybote girls hostel', 200, 'North Campus Block A', NULL);

INSERT INTO students (grn, name, course, year, hostel_id, user_id, email, contact) VALUES 
('GRN001', 'John Doe', 'Computer Science Engineering', 2, 1, 17, 'john@email.com', '9876543201'),
('GRN002', 'Jane Smith', 'Information Technology', 3, 2, 18, 'jane@email.com', '9876543202'),
('GRN003', 'Mike Johnson', 'Electronics Engineering', 1, 1, 19, 'mike@email.com', '9876543203'),
('GRN004', 'Sarah Wilson', 'Mechanical Engineering', 4, 2, NULL, 'sarah@email.com', '9876543204'),
('GRN005', 'Tom Brown', 'Civil Engineering', 2, 1, NULL, 'tom@email.com', '9876543205'),
('GRN006', 'Lisa Davis', 'Computer Science Engineering', 3, 2, NULL, 'lisa@email.com', '9876543206'),
('GRN007', 'David Miller', 'Electrical Engineering', 1, 1, NULL, 'david@email.com', '9876543207'),
('GRN008', 'Emma Garcia', 'Chemical Engineering', 4, 2, NULL, 'emma@email.com', '9876543208');

INSERT INTO rooms (room_number, capacity, hostel_id) VALUES 
('101', 2, 1), ('102', 2, 1), ('103', 3, 1), ('104', 2, 1), ('105', 3, 1),
('201', 2, 1), ('202', 2, 1), ('203', 3, 1), ('204', 2, 1), ('205', 3, 1),
('101', 2, 2), ('102', 2, 2), ('103', 2, 2), ('104', 3, 2), ('105', 2, 2),
('201', 2, 2), ('202', 3, 2), ('203', 2, 2), ('204', 2, 2), ('205', 3, 2);

INSERT INTO staff (name, role, contact, hostel_id, user_id) VALUES 
('Rector One', 'rector', '9876543200', 1, 2),
('Rector Two', 'rector', '9876543201', 2, 3),
('Student Head One', 'student_head', '9876543202', 1, 8),
('Student Head Two', 'student_head', '9876543203', 2, 9),
('Rajesh Kumar', 'mess_head', '9876543210', 1, 4),
('Kavita Reddy', 'mess_head', '9876543217', 2, 5),
('Priya Sharma', 'library_head', '9876543211', 1, 6),
('Suresh Nair', 'library_head', '9876543218', 2, 7),
('Dr. Amit Patel', 'health_staff', '9876543212', 1, 10),
('Dr. Pooja Mehta', 'health_staff', '9876543219', 2, 11),
('Sunita Verma', 'vvk_staff', '9876543213', 1, 12),
('Ravi Gupta', 'vvk_staff', '9876543220', 2, 13),
('Ravi Singh', 'placement_staff', '9876543214', 1, 14),
('Meera Jain', 'placement_staff', '9876543221', 2, 15),
('Meera Gupta', 'ed_cell_staff', '9876543215', 1, 16),
('Amit Sharma', 'ed_cell_staff', '9876543222', 2, 17),
('Anita Joshi', 'scholarship_staff', '9876543216', 1, 18),
('Pooja Verma', 'scholarship_staff', '9876543223', 2, 19);

INSERT INTO books (title, author, isbn, stock, hostel_id) VALUES 
('Data Structures and Algorithms', 'Thomas Cormen', '978-0262033848', 5, 1),
('Operating System Concepts', 'Abraham Silberschatz', '978-1118063330', 3, 1),
('Computer Networks', 'Andrew Tanenbaum', '978-0132126953', 4, 1),
('Database System Concepts', 'Henry Korth', '978-0073523323', 2, 1),
('Software Engineering', 'Ian Sommerville', '978-0133943030', 6, 1),
('Digital Design', 'Morris Mano', '978-0134549897', 3, 2),
('Engineering Mathematics', 'B.S. Grewal', '978-8193245071', 8, 2),
('Physics for Engineers', 'Serway & Jewett', '978-1305405202', 4, 2);

INSERT INTO events (title, description, date, venue, hostel_id) VALUES 
('Tech Fest 2024', 'Annual technical festival with competitions and workshops', '2024-12-20', 'Main Auditorium', 1),
('Cultural Night', 'Evening of music, dance and drama performances', '2024-12-22', 'Open Air Theatre', 1),
('Sports Tournament', 'Inter-hostel cricket and football matches', '2024-12-25', 'Sports Ground', 1),
('Career Guidance Seminar', 'Industry experts sharing career insights', '2024-12-18', 'Conference Hall', 2),
('Health Awareness Camp', 'Free health checkup and awareness program', '2024-12-21', 'Medical Center', 2);

INSERT INTO scholarships (student_id, scholarship_type, amount, status, applied_date, documents, remarks) VALUES 
(1, 'Merit Scholarship', 50000.00, 'approved', '2024-01-15', 'Academic transcripts, Income certificate', 'Excellent academic performance'),
(2, 'Need-based Scholarship', 30000.00, 'pending', '2024-02-01', 'Income certificate, Bank statements', 'Under review'),
(3, 'Sports Scholarship', 25000.00, 'approved', '2024-01-20', 'Sports certificates, Medical certificate', 'Outstanding sports achievements'),
(4, 'Minority Scholarship', 40000.00, 'pending', '2024-02-10', 'Caste certificate, Income certificate', 'Documentation verification pending');

INSERT INTO attendance (student_id, date, status) VALUES 
(1, '2024-12-01', 'present'),
(1, '2024-12-02', 'present'),
(2, '2024-12-01', 'present'),
(2, '2024-12-02', 'absent'),
(3, '2024-12-01', 'present'),
(3, '2024-12-02', 'present');

INSERT INTO mess_attendance (student_id, date, meal_type, taken) VALUES 
(1, '2024-12-01', 'breakfast', TRUE),
(1, '2024-12-01', 'lunch', TRUE),
(1, '2024-12-01', 'dinner', FALSE),
(2, '2024-12-01', 'breakfast', TRUE),
(2, '2024-12-01', 'lunch', FALSE),
(2, '2024-12-01', 'dinner', TRUE);

INSERT INTO book_issues (student_id, book_id, issue_date, return_date, fine) VALUES 
(1, 1, '2024-11-15', NULL, 0.00),
(2, 2, '2024-11-20', '2024-12-01', 0.00),
(3, 3, '2024-11-10', NULL, 0.00);

-- Sample data for student head functionality
INSERT INTO student_complaints (student_id, category, subject, description, status, priority) VALUES
(1, 'Mess', 'Poor Food Quality', 'The food quality has been consistently poor for the past week', 'pending', 'high'),
(2, 'Maintenance', 'Broken AC in Room 101', 'The air conditioning unit in room 101 is not working', 'pending', 'medium'),
(3, 'Internet', 'Slow WiFi Connection', 'Internet speed is very slow in the evening hours', 'resolved', 'low'),
(4, 'Cleanliness', 'Dirty Common Areas', 'Common areas are not being cleaned regularly', 'pending', 'medium'),
(5, 'Security', 'Gate Security Issues', 'Security guard is often absent during night hours', 'forwarded', 'high');

INSERT INTO staff_reports (staff_id, report_type, title, content, status) VALUES
(4, 'Daily Report', 'Mess Operations - Week 1', 'Weekly summary of mess operations including attendance and feedback', 'pending'),
(6, 'Monthly Report', 'Library Usage Statistics', 'Monthly report on book issues, returns, and library usage patterns', 'approved'),
(8, 'Incident Report', 'Health Emergency Response', 'Report on handling of medical emergency in Block A', 'pending'),
(4, 'Inventory Report', 'Mess Inventory Status', 'Current status of mess inventory and requirements', 'forwarded');

INSERT INTO student_council (student_id, position, wing_block, contact, appointed_date) VALUES
(1, 'President', 'Block A', '9876543210', '2024-01-15'),
(2, 'Vice President', 'Block B', '9876543211', '2024-01-15'),
(3, 'Secretary', 'Block A', '9876543212', '2024-01-15'),
(4, 'Cultural Head', 'Block C', '9876543213', '2024-01-15'),
(5, 'Sports Head', 'Block B', '9876543214', '2024-01-15');

INSERT INTO digital_suggestions (student_id, category, suggestion, status) VALUES
(1, 'Mess', 'Introduce more variety in breakfast menu', 'new'),
(2, 'Recreation', 'Add more sports equipment in the recreation room', 'reviewed'),
(3, 'Study', 'Extend library hours during exam periods', 'implemented'),
(4, 'Technology', 'Install more charging points in common areas', 'new'),
(5, 'Environment', 'Start a recycling program in the hostel', 'reviewed');

INSERT INTO health_records (student_id, medical_history, allergies, blood_group, emergency_contact) VALUES
(1, 'No major medical history', 'None', 'O+', '9876543301'),
(2, 'Asthma', 'Dust, Pollen', 'A+', '9876543302'),
(3, 'No major medical history', 'Peanuts', 'B+', '9876543303');

INSERT INTO inventory (item_name, quantity, unit, low_stock_alert, hostel_id) VALUES
('Rice', 500, 'kg', 50, 1),
('Dal', 200, 'kg', 20, 1),
('Vegetables', 100, 'kg', 10, 1),
('Milk', 50, 'liters', 5, 1),
('Bread', 100, 'packets', 10, 2),
('Eggs', 200, 'pieces', 20, 2);nt, status, applied_date) VALUES 
(1, 'Merit Scholarship', 25000.00, 'approved', '2024-11-15'),
(2, 'Need-Based Scholarship', 40000.00, 'pending', '2024-11-20'),
(3, 'Sports Scholarship', 30000.00, 'under_review', '2024-11-18'),
(4, 'Minority Scholarship', 20000.00, 'approved', '2024-11-10'),
(5, 'Merit Scholarship', 25000.00, 'rejected', '2024-11-12');

INSERT INTO health_records (student_id, medical_history, allergies, insurance_no, vaccination_status, blood_group, emergency_contact) VALUES 
(1, 'No major medical history', 'None', 'INS001234567', 'COVID-19 vaccinated, Hepatitis B completed', 'O+', '9876543200'),
(2, 'Asthma since childhood', 'Dust, Pollen', 'INS001234568', 'All vaccinations up to date', 'A+', '9876543201'),
(3, 'Minor surgery in 2020', 'Penicillin', 'INS001234569', 'COVID-19 booster pending', 'B+', '9876543202'),
(4, 'No significant history', 'Shellfish', 'INS001234570', 'Complete vaccination record', 'AB+', '9876543203');

INSERT INTO placement_records (student_id, company_name, position, package_amount, placement_type, status, application_date) VALUES 
(1, 'TCS', 'Software Developer', 350000.00, 'full_time', 'selected', '2024-11-01'),
(2, 'Infosys', 'System Engineer', 400000.00, 'full_time', 'interview', '2024-11-05'),
(3, 'Google', 'Software Intern', 50000.00, 'internship', 'selected', '2024-10-15'),
(4, 'Microsoft', 'Data Analyst', 600000.00, 'full_time', 'applied', '2024-11-10');

INSERT INTO startup_ideas (student_id, title, description, category, status, funding_requested, submitted_date) VALUES 
(1, 'EcoFriendly Food Delivery', 'Sustainable food delivery using electric vehicles and biodegradable packaging', 'Sustainability', 'approved', 200000.00, '2024-11-01'),
(2, 'AI-Powered Study Assistant', 'Personalized learning platform using AI to help students', 'EdTech', 'in_development', 350000.00, '2024-11-05'),
(3, 'Smart Campus Navigation', 'Mobile app for indoor navigation in large campus buildings', 'Technology', 'under_review', 100000.00, '2024-11-10');

INSERT INTO mentors (name, expertise, contact, email, active_projects) VALUES 
('Dr. Rajesh Kumar', 'Technology & Software Development', '9876543220', 'rajesh.kumar@email.com', 3),
('Ms. Priya Sharma', 'Business Development & Marketing', '9876543221', 'priya.sharma@email.com', 2),
('Mr. Amit Patel', 'Finance & Investment', '9876543222', 'amit.patel@email.com', 1),
('Dr. Sunita Verma', 'Research & Innovation', '9876543223', 'sunita.verma@email.com', 4);

INSERT INTO inventory (item_name, quantity, unit, low_stock_alert, hostel_id) VALUES 
('Rice', 500, 'kg', 50, 1),
('Dal', 200, 'kg', 20, 1),
('Vegetables', 100, 'kg', 15, 1),
('Oil', 50, 'liters', 10, 1),
('Spices', 25, 'kg', 5, 1),
('Rice', 400, 'kg', 50, 2),
('Dal', 150, 'kg', 20, 2),
('Vegetables', 80, 'kg', 15, 2);

INSERT INTO attendance (student_id, date, status) VALUES 
(1, '2024-12-01', 'present'),
(1, '2024-12-02', 'present'),
(1, '2024-12-03', 'absent'),
(2, '2024-12-01', 'present'),
(2, '2024-12-02', 'present'),
(2, '2024-12-03', 'present'),
(3, '2024-12-01', 'present'),
(3, '2024-12-02', 'absent'),
(3, '2024-12-03', 'present');

INSERT INTO mess_attendance (student_id, date, meal_type, taken) VALUES 
(1, '2024-12-01', 'breakfast', TRUE),
(1, '2024-12-01', 'lunch', TRUE),
(1, '2024-12-01', 'dinner', FALSE),
(2, '2024-12-01', 'breakfast', TRUE),
(2, '2024-12-01', 'lunch', TRUE),
(2, '2024-12-01', 'dinner', TRUE),
(3, '2024-12-01', 'breakfast', FALSE),
(3, '2024-12-01', 'lunch', TRUE),
(3, '2024-12-01', 'dinner', TRUE);

INSERT INTO event_registrations (student_id, event_id, attended) VALUES 
(1, 1, FALSE),
(1, 2, FALSE),
(2, 1, FALSE),
(2, 4, FALSE),
(3, 1, FALSE),
(3, 3, FALSE);

-- Update hostel rector assignments
UPDATE hostels SET rector_id = 2 WHERE id = 1;
UPDATE hostels SET rector_id = 3 WHERE id = 2;

-- Update user hostel assignments
UPDATE users SET hostel_id = 1 WHERE id IN (2, 4, 6, 8, 10, 12, 14, 16, 17, 19);
UPDATE users SET hostel_id = 2 WHERE id IN (3, 5, 7, 9, 11, 13, 15, 18);

-- Update student room assignments
UPDATE students SET room_id = 1 WHERE id = 1;
UPDATE students SET room_id = 2 WHERE id = 3;
UPDATE students SET room_id = 5 WHERE id = 5;
UPDATE students SET room_id = 11 WHERE id = 2;
UPDATE students SET room_id = 12 WHERE id = 4;
UPDATE students SET room_id = 14 WHERE id = 6;



-- General feedback table for library, event, and staff feedback
CREATE TABLE IF NOT EXISTS general_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    feedback_category ENUM('library', 'event', 'staff') NOT NULL,
    feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    response_message TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id)
);