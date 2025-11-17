-- Complete database setup for VSS system
USE vss;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'student_head', 'mess_head', 'library_head', 'health_staff', 'rector', 'super_admin') NOT NULL,
    hostel_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Hostels table
CREATE TABLE IF NOT EXISTS hostels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    capacity INT NOT NULL DEFAULT 100
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hostel_id INT NOT NULL,
    room_number VARCHAR(10) NOT NULL,
    capacity INT NOT NULL DEFAULT 2,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Students table
CREATE TABLE IF NOT EXISTS students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    grn VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    contact VARCHAR(15),
    course VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    hostel_id INT,
    room_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id)
);

-- Staff table
CREATE TABLE IF NOT EXISTS staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(50) NOT NULL,
    contact VARCHAR(15),
    hostel_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Books table
CREATE TABLE IF NOT EXISTS books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100) NOT NULL,
    isbn VARCHAR(20),
    available_copies INT DEFAULT 1,
    total_copies INT DEFAULT 1
);

-- Book issues table
CREATE TABLE IF NOT EXISTS book_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    book_id INT NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (book_id) REFERENCES books(id)
);

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    venue VARCHAR(100),
    hostel_id INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);

-- Scholarships table
CREATE TABLE IF NOT EXISTS scholarships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    scholarship_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2),
    status ENUM('applied', 'approved', 'rejected') DEFAULT 'applied',
    applied_date DATE NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Health records table
CREATE TABLE IF NOT EXISTS health_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    visit_date DATE NOT NULL,
    symptoms TEXT,
    diagnosis TEXT,
    treatment TEXT,
    doctor_name VARCHAR(100),
    FOREIGN KEY (student_id) REFERENCES students(id)
);

-- Mess feedback table
CREATE TABLE IF NOT EXISTS mess_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    feedback_type ENUM('complaint', 'suggestion', 'compliment') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Student complaints table
CREATE TABLE IF NOT EXISTS student_complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    status ENUM('pending', 'resolved', 'forwarded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Insert sample data if tables are empty
INSERT IGNORE INTO hostels (id, name, location, capacity) VALUES 
(1, 'Hostel A', 'North Campus', 100),
(2, 'Hostel B', 'South Campus', 120);

INSERT IGNORE INTO rooms (hostel_id, room_number, capacity) VALUES 
(1, '101', 2), (1, '102', 2), (1, '103', 2),
(2, '201', 2), (2, '202', 2), (2, '203', 2);

INSERT IGNORE INTO books (title, author, isbn, available_copies, total_copies) VALUES 
('Introduction to Programming', 'John Smith', '978-1234567890', 5, 5),
('Data Structures', 'Jane Doe', '978-0987654321', 3, 3),
('Database Systems', 'Bob Johnson', '978-1122334455', 4, 4);