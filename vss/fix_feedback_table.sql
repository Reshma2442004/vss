-- Fix mess_feedback table structure
USE vss;

-- Drop existing table if it exists to recreate with proper structure
DROP TABLE IF EXISTS mess_feedback;

-- Create mess_feedback table with all required columns
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