-- Complete mess feedback table setup
DROP TABLE IF EXISTS mess_feedback;

CREATE TABLE mess_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    feedback_type VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    FOREIGN KEY (student_id) REFERENCES students(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Insert sample feedback data for testing
INSERT INTO mess_feedback (student_id, feedback_type, subject, message, rating, status) VALUES
(1, 'complaint', 'Food Quality Issue', 'The food served today was not fresh and had a bad taste.', 2, 'pending'),
(2, 'suggestion', 'Menu Variety', 'Please add more vegetarian options to the daily menu.', 4, 'pending'),
(3, 'compliment', 'Excellent Service', 'The mess staff is very courteous and the food quality has improved.', 5, 'pending');