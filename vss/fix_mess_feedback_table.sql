-- Drop and recreate mess_feedback table without hostel_id
DROP TABLE IF EXISTS mess_feedback;

CREATE TABLE mess_feedback (
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