CREATE DATABASE smart_system;
USE smart_system;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('rector', 'teacher', 'parent', 'student', 'staff') NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    grn VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(50) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    father_name VARCHAR(100),
    mother_name VARCHAR(100),
    address TEXT,
    date_of_birth DATE,
    class VARCHAR(50),
    section VARCHAR(10),
    roll_number VARCHAR(20),
    admission_date DATE,
    blood_group VARCHAR(5),
    emergency_contact VARCHAR(20),
    hostel_room VARCHAR(20),
    is_hostel_student BOOLEAN DEFAULT FALSE,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE hostel_staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    position ENUM('warden', 'assistant_warden', 'security', 'cleaner', 'maintenance') NOT NULL,
    assigned_by INT NOT NULL,
    assigned_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

CREATE TABLE student_uploads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    total_records INT NOT NULL,
    successful_imports INT NOT NULL,
    failed_imports INT NOT NULL,
    uploaded_by INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);