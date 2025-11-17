-- Create food wastage table
CREATE TABLE food_wastage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostel_id INT NOT NULL,
    date DATE NOT NULL,
    meal_type ENUM('morning_meal', 'night_meal') NOT NULL,
    food_item VARCHAR(255) NOT NULL,
    quantity_wasted DECIMAL(10,2) NOT NULL,
    unit ENUM('kg', 'liters', 'plates', 'portions') NOT NULL,
    reason ENUM('overcooked', 'undercooked', 'excess_preparation', 'spoiled', 'student_leftover', 'other') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (hostel_id) REFERENCES hostels(id)
);