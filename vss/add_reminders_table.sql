-- Add library reminders table to existing VSS database
USE vss;

CREATE TABLE IF NOT EXISTS library_reminders (
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