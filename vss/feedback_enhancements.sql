-- Enhanced Feedback System
USE vss;

-- Add additional columns to mess_feedback table
ALTER TABLE mess_feedback 
ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER rating,
ADD COLUMN category VARCHAR(50) AFTER subject,
ADD COLUMN response_message TEXT AFTER reviewed_by,
ADD COLUMN resolved_at TIMESTAMP NULL AFTER reviewed_at;

-- Create feedback responses table
CREATE TABLE IF NOT EXISTS feedback_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    feedback_id INT NOT NULL,
    responder_id INT NOT NULL,
    response_message TEXT NOT NULL,
    response_type ENUM('acknowledgment', 'action_taken', 'resolution') DEFAULT 'acknowledgment',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES mess_feedback(id),
    FOREIGN KEY (responder_id) REFERENCES users(id)
);

-- Create feedback categories table
CREATE TABLE IF NOT EXISTS feedback_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE
);

-- Insert default feedback categories
INSERT IGNORE INTO feedback_categories (category_name, description) VALUES 
('Food Quality', 'Issues related to taste, freshness, and preparation'),
('Food Quantity', 'Portion size and availability concerns'),
('Hygiene', 'Cleanliness of dining area and food preparation'),
('Service', 'Staff behavior and service quality'),
('Menu Variety', 'Suggestions for menu changes and variety'),
('Timing', 'Meal timing and schedule related feedback'),
('Infrastructure', 'Dining hall facilities and equipment'),
('Other', 'General feedback not covered in other categories');