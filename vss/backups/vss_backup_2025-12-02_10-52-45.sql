-- VSS Hostel Management System Backup
-- Generated on: 2025-12-02 10:52:45

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`student_id`,`date`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `avalon_uploads`;
CREATE TABLE `avalon_uploads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `avalon_uploads` VALUES
('1', '9', 'Dec 2025', '', 'imp.pdf', '../uploads/avalon/9_1764238348.pdf', '271967', '2025-11-27 15:42:28', NULL, NULL);

DROP TABLE IF EXISTS `book_issues`;
CREATE TABLE `book_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `fine` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `book_id` (`book_id`),
  CONSTRAINT `book_issues_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `book_issues_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `books`;
CREATE TABLE `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `hostel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `books_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `books` VALUES
('1', 'Data Structures and Algorithms', 'Thomas Cormen', '978-0262033848', '5', '1'),
('2', 'Operating System Concepts', 'Abraham Silberschatz', '978-1118063330', '3', '1'),
('3', 'Computer Networks', 'Andrew Tanenbaum', '978-0132126953', '4', '1'),
('4', 'Database System Concepts', 'Henry Korth', '978-0073523323', '2', '1'),
('5', 'Software Engineering', 'Ian Sommerville', '978-0133943030', '6', '1'),
('6', 'Digital Design', 'Morris Mano', '978-0134549897', '3', '2'),
('7', 'Engineering Mathematics', 'B.S. Grewal', '978-8193245071', '8', '2'),
('8', 'Physics for Engineers', 'Serway & Jewett', '978-1305405202', '4', '2');

DROP TABLE IF EXISTS `digital_suggestions`;
CREATE TABLE `digital_suggestions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `suggestion` text NOT NULL,
  `status` enum('new','reviewed','implemented') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `digital_suggestions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `digital_suggestions_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `event_registrations`;
CREATE TABLE `event_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attended` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_registration` (`student_id`,`event_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `event_registrations` VALUES
('1', '632', '6', '2025-11-28 17:22:45', '0');

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `date` date NOT NULL,
  `venue` varchar(100) DEFAULT NULL,
  `hostel_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `events` VALUES
('1', 'Tech Fest 2024', 'Annual technical festival with competitions and workshops', '2024-12-20', 'Main Auditorium', '1', '2025-11-18 15:50:33'),
('2', 'Cultural Night', 'Evening of music, dance and drama performances', '2024-12-22', 'Open Air Theatre', '1', '2025-11-18 15:50:33'),
('3', 'Sports Tournament', 'Inter-hostel cricket and football matches', '2024-12-25', 'Sports Ground', '1', '2025-11-18 15:50:33'),
('4', 'Career Guidance Seminar', 'Industry experts sharing career insights', '2024-12-18', 'Conference Hall', '2', '2025-11-18 15:50:33'),
('5', 'Health Awareness Camp', 'Free health checkup and awareness program', '2024-12-21', 'Medical Center', '2', '2025-11-18 15:50:33'),
('6', 'Sport Inaugration ceremony', 'Lets come for sports activities', '2025-12-14', 'New Girls Hostel', '7', '2025-11-28 17:22:17');

DROP TABLE IF EXISTS `food_wastage`;
CREATE TABLE `food_wastage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hostel_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `meal_type` enum('morning_meal','night_meal') NOT NULL,
  `food_item` varchar(255) NOT NULL,
  `quantity_wasted` decimal(10,2) NOT NULL,
  `unit` enum('kg','liters','plates','portions') NOT NULL,
  `reason` enum('overcooked','undercooked','excess_preparation','spoiled','student_leftover','other') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `food_wastage_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `food_wastage` VALUES
('1', '7', '2025-11-18', 'night_meal', 'Salad', '3.20', 'kg', 'student_leftover', '2025-11-18 20:59:15');

DROP TABLE IF EXISTS `health_records`;
CREATE TABLE `health_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `insurance_no` varchar(50) DEFAULT NULL,
  `vaccination_status` text DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `emergency_contact` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_health_record` (`student_id`),
  CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `health_visits`;
CREATE TABLE `health_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `visit_date` date NOT NULL,
  `complaint` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `prescribed_medicine` text DEFAULT NULL,
  `follow_up_required` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `health_visits_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `hostels`;
CREATE TABLE `hostels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(200) NOT NULL,
  `rector_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `hostels` VALUES
('1', 'Aapte girlshostel', '150', 'South Campus Block B', '310', '2025-11-18 15:50:33'),
('2', 'sumitra sadan girlshostel', '180', 'East Campus Block C', '311', '2025-11-18 15:50:33'),
('3', 'P.D.karkhanis boys hostel', '120', 'West Campus Block D', '312', '2025-11-18 15:50:33'),
('4', 'Haribhaupathak Boys hostel', '160', 'North Campus Block F', '313', '2025-11-18 15:50:33'),
('5', 'lajpat sankul Boys hostel', '220', 'Central Campus Block E', '314', '2025-11-18 15:50:33'),
('6', 'Latika Jaywantrao Gaytonde Hostel', '200', 'North Campus Block A', '315', '2025-11-18 15:50:33'),
('7', 'New Girls Hostel A wing', '180', 'Campus Block G', '316', '2025-11-18 15:54:49');

DROP TABLE IF EXISTS `inventory`;
CREATE TABLE `inventory` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `low_stock_alert` int(11) DEFAULT 10,
  `hostel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `inventory` VALUES
('1', 'Rice', '500', 'kg', '50', '1'),
('2', 'Dal', '200', 'kg', '20', '1'),
('3', 'Vegetables', '100', 'kg', '10', '1'),
('4', 'Milk', '50', 'liters', '5', '1'),
('5', 'Bread', '100', 'packets', '10', '2'),
('6', 'Eggs', '200', 'pieces', '20', '2'),
('7', 'dal', '20', 'kg', '1', '7');

DROP TABLE IF EXISTS `leave_applications`;
CREATE TABLE `leave_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `leave_type` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `status` (`status`),
  KEY `applied_at` (`applied_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `leave_applications` VALUES
('1', '9', 'sick', '2025-11-18', '2025-11-21', 'health is not good ', 'approved', '2025-11-18 16:21:42', '2025-11-18 16:31:30', '32', '2025-11-18 16:31:30'),
('2', '9', 'emergency', '2025-11-22', '2025-11-23', 'work purpose', 'approved', '2025-11-18 17:00:20', '2025-11-18 17:00:42', '31', '2025-11-18 17:00:42');

DROP TABLE IF EXISTS `library_reminders`;
CREATE TABLE `library_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `book_issue_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sent_by` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `book_issue_id` (`book_issue_id`),
  KEY `sent_by` (`sent_by`),
  CONSTRAINT `library_reminders_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `library_reminders_ibfk_2` FOREIGN KEY (`book_issue_id`) REFERENCES `book_issues` (`id`),
  CONSTRAINT `library_reminders_ibfk_3` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `mentors`;
CREATE TABLE `mentors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `expertise` varchar(200) DEFAULT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `active_projects` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `mentors` VALUES
('1', 'Dr. Rajesh Kumar', 'Technology & Software Development', '9876543220', 'rajesh.kumar@email.com', '3'),
('2', 'Ms. Priya Sharma', 'Business Development & Marketing', '9876543221', 'priya.sharma@email.com', '2'),
('3', 'Mr. Amit Patel', 'Finance & Investment', '9876543222', 'amit.patel@email.com', '1'),
('4', 'Dr. Sunita Verma', 'Research & Innovation', '9876543223', 'sunita.verma@email.com', '4');

DROP TABLE IF EXISTS `mess_attendance`;
CREATE TABLE `mess_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner') NOT NULL,
  `taken` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_mess_attendance` (`student_id`,`date`,`meal_type`),
  CONSTRAINT `mess_attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `mess_feedback`;
CREATE TABLE `mess_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `feedback_type` enum('complaint','suggestion','compliment') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `message` text NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','reviewed','resolved') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `response_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `mess_feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `placement_drives`;
CREATE TABLE `placement_drives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `package_offered` decimal(10,2) DEFAULT NULL,
  `eligibility_criteria` text DEFAULT NULL,
  `drive_date` date NOT NULL,
  `registration_deadline` date DEFAULT NULL,
  `hostel_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `placement_drives_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `placement_records`;
CREATE TABLE `placement_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `package_amount` decimal(10,2) DEFAULT NULL,
  `placement_type` enum('internship','full_time') NOT NULL,
  `status` enum('applied','interview','selected','rejected','joined') DEFAULT 'applied',
  `application_date` date NOT NULL,
  `interview_date` date DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `placement_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `qr_attendance_sessions`;
CREATE TABLE `qr_attendance_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_code` varchar(50) NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner') NOT NULL,
  `date` date NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_code` (`session_code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `qr_attendance_sessions` VALUES
('1', 'MESS_20251125_095715_LUNCH', 'lunch', '2025-11-25', '7', '31', '2025-11-25 14:27:15', '2025-11-25 10:27:15', '1'),
('2', 'MESS_20251125_100018_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:30:18', '2025-11-25 11:00:18', '1'),
('3', 'MESS_20251125_100320_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:33:20', '2025-11-25 10:33:20', '1'),
('4', 'MESS_20251125_100338_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:33:38', '2025-11-25 10:33:38', '1'),
('5', 'MESS_20251125_100510_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:35:10', '2025-11-25 10:35:10', '1'),
('6', 'MESS_20251125_100612_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:36:12', '2025-11-25 10:36:12', '1'),
('7', 'MESS_20251125_100629_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:36:29', '2025-11-25 11:06:29', '1'),
('8', 'MESS_20251125_100814_DINNER', 'dinner', '2025-11-25', '7', '31', '2025-11-25 14:38:14', '2025-11-25 10:38:14', '1'),
('9', 'MESS_20251128_102126_BREAKFAST', 'breakfast', '2025-11-28', '7', '31', '2025-11-28 14:51:26', '2025-11-28 11:21:26', '1');

DROP TABLE IF EXISTS `qr_mess_attendance`;
CREATE TABLE `qr_mess_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`session_id`,`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_number` varchar(10) NOT NULL,
  `capacity` int(11) NOT NULL,
  `hostel_id` int(11) NOT NULL,
  `occupied` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rooms` VALUES
('1', '101', '2', '1', '0'),
('2', '102', '2', '1', '0'),
('3', '103', '3', '1', '0'),
('4', '104', '2', '1', '0'),
('5', '105', '3', '1', '0'),
('6', '201', '2', '1', '0'),
('7', '202', '2', '1', '0'),
('8', '203', '3', '1', '0'),
('9', '204', '2', '1', '0'),
('10', '205', '3', '1', '0'),
('11', '101', '2', '2', '0'),
('12', '102', '2', '2', '0'),
('13', '103', '2', '2', '0'),
('14', '104', '3', '2', '0'),
('15', '105', '2', '2', '0'),
('16', '201', '2', '2', '0'),
('17', '202', '3', '2', '0'),
('18', '203', '2', '2', '0'),
('19', '204', '2', '2', '0'),
('20', '205', '3', '2', '0');

DROP TABLE IF EXISTS `scholarships`;
CREATE TABLE `scholarships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `scholarship_type` varchar(100) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `applied_date` date NOT NULL,
  `approved_date` date DEFAULT NULL,
  `documents` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `scholarships_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `staff`;
CREATE TABLE `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `hostel_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `csv_data` text DEFAULT NULL,
  `plain_password` varchar(255) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `rector_id` varchar(50) DEFAULT NULL,
  `hostel_name` varchar(255) DEFAULT NULL,
  `csv_hostel_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hostel_id` (`hostel_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`),
  CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=298 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `staff` VALUES
('20', 'Rakhi Nehate', 'library_head', '9632568745', '7', '33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('21', 'Nandini khandare', 'mess_head', '9589653256', '7', '34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('22', 'reshma gade', 'vvk_staff', '9865455238', '7', '36', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('288', 'Amit Deshmukh', 'rector', '9876543210', '1', '310', '[\"R001\",\"Amit Deshmukh\",\"amit.d@example.com\",\"9876543210\",\"Aapte girlshostel\",\"rector123\",\"Pune\"]', 'rector123', NULL, NULL, 'Pune', 'R001', NULL, 'Aapte girlshostel'),
('289', 'Sneha Patil', 'rector', '9823456712', '2', '311', '[\"R002\",\"Sneha Patil\",\"sneha.p@example.com\",\"9823456712\",\"sumitra sadan girlshostel\",\"spatil456\",\"Pune\"]', 'spatil456', NULL, NULL, 'Pune', 'R002', NULL, 'sumitra sadan girlshostel'),
('290', 'Rahul Shinde', 'rector', '9123456780', '3', '312', '[\"R003\",\"Rahul Shinde\",\"rahul.s@example.com\",\"9123456780\",\"P.D.karkhanis boys hostel\",\"rahul789\",\"Pune\"]', 'rahul789', NULL, NULL, 'Pune', 'R003', NULL, 'P.D.karkhanis boys hostel'),
('291', 'Priya Jadhav', 'rector', '9988776655', '4', '313', '[\"R004\",\"Priya Jadhav\",\"priya.j@example.com\",\"9988776655\",\"Haribhaupathak Boys hostel\",\"priya321\",\"Pune\"]', 'priya321', NULL, NULL, 'Pune', 'R004', NULL, 'Haribhaupathak Boys hostel'),
('292', 'Kiran Pawar', 'rector', '9012345678', '5', '314', '[\"R005\",\"Kiran Pawar\",\"kiran.p@example.com\",\"9012345678\",\"lajpat sankul Boys hostel\",\"kiran654\",\"Pune\"]', 'kiran654', NULL, NULL, 'Pune', 'R005', NULL, 'lajpat sankul Boys hostel'),
('293', 'Manish Kulkarni', 'rector', '9876512345', '6', '315', '[\"R006\",\"Manish Kulkarni\",\"manish.k@example.com\",\"9876512345\",\"Latika Jaywantrao Gaytonde Hostel\",\"manish987\",\"Pune\"]', 'manish987', NULL, NULL, 'Pune', 'R006', NULL, 'Latika Jaywantrao Gaytonde Hostel'),
('294', 'Pooja Bhosale', 'rector', '9765432180', '7', '316', '[\"R007\",\"Pooja Bhosale\",\"pooja.b@example.com\",\"9765432180\",\"New Girls Hostel A wing\",\"pooja111\",\"Pune\"]', 'pooja111', NULL, NULL, 'Pune', 'R007', NULL, 'New Girls Hostel A wing'),
('295', 'Sachin More', 'rector', '9090909090', NULL, '317', '[\"R008\",\"Sachin More\",\"sachin.m@example.com\",\"9090909090\",\"Medhavi girls hostel\",\"sachin222\",\"Pune\"]', 'sachin222', NULL, NULL, 'Pune', 'R008', NULL, 'Medhavi girls hostel'),
('296', 'Neha Shinde', 'rector', '9123987654', NULL, '318', '[\"R009\",\"Neha Shinde\",\"neha.s@example.com\",\"9123987654\",\"kalyanrav Jadhav boys hostel\",\"neha333\",\"Nagar\"]', 'neha333', NULL, NULL, 'Nagar', 'R009', NULL, 'kalyanrav Jadhav boys hostel'),
('297', 'Rohit Chavan', 'rector', '9988123456', NULL, '319', '[\"R0010\",\"Rohit Chavan\",\"rohit.c@example.com\",\"9988123456\",\"Madhubhau Chaudhari girls hostel\",\"rohit444\",\"Nagar\"]', 'rohit444', NULL, NULL, 'Nagar', 'R0010', NULL, 'Madhubhau Chaudhari girls hostel');

DROP TABLE IF EXISTS `staff_reports`;
CREATE TABLE `staff_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `report_type` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','forwarded') DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `staff_reports_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`),
  CONSTRAINT `staff_reports_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `startup_ideas`;
CREATE TABLE `startup_ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('submitted','under_review','approved','rejected','in_development') DEFAULT 'submitted',
  `mentor_id` int(11) DEFAULT NULL,
  `funding_requested` decimal(12,2) DEFAULT 0.00,
  `funding_approved` decimal(12,2) DEFAULT 0.00,
  `submitted_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `startup_ideas_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_complaints`;
CREATE TABLE `student_complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','resolved','forwarded') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `student_complaints_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `student_complaints_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `student_council`;
CREATE TABLE `student_council` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL,
  `wing_block` varchar(50) NOT NULL,
  `contact` varchar(15) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `appointed_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_council_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grn` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `hostel_id` int(11) DEFAULT NULL,
  `room_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password` varchar(255) DEFAULT NULL,
  `room_no` varchar(20) DEFAULT NULL,
  `grn_no` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `mothers_name` varchar(100) DEFAULT NULL,
  `student_mobile` varchar(20) DEFAULT NULL,
  `parents_mobile` varchar(20) DEFAULT NULL,
  `faculty` varchar(100) DEFAULT NULL,
  `samiti_year` int(11) DEFAULT NULL,
  `college_year` int(11) DEFAULT NULL,
  `course_duration` int(11) DEFAULT NULL,
  `hostel_allocation` varchar(100) DEFAULT NULL,
  `wing` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `room_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `grn` (`grn`),
  KEY `hostel_id` (`hostel_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`hostel_id`) REFERENCES `hostels` (`id`),
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=633 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `students` VALUES
('582', 'M2425-11522', 'Aditya V Deshmukh', 'BBA', '1', '7', NULL, NULL, 'aditya.deshmukh15@example.com', '8042750911', '2025-11-28 16:38:44', '$2y$10$sTDFu0r.tgYXFGk3T/OrOO6qTZRFJ35BvVQrOVRTLCS1ltSSFz..C', '101', 'M2425-11522', 'Aditya', 'V', 'Deshmukh', 'Usha Rane', '8042750911', '9387016711', 'Management', '2024', '1', '2', 'New Girls Hostel', 'A', '1', '101'),
('583', 'M2425-11523', 'Sneha T Joshi', 'B.Sc', '1', '7', NULL, NULL, 'sneha.joshi75@example.com', '7604280077', '2025-11-28 16:38:44', '$2y$10$WFJJROXuCY696eq/EjPwGe1AZ/XExHidDrtKkZUeq5.WMjNjzqboq', '102', 'M2425-11523', 'Sneha', 'T', 'Joshi', 'Lata Kulkarni', '7604280077', '8944260164', 'Science', '2021', '1', '2', 'New Girls Hostel', 'A', '1', '102'),
('584', 'M2425-11524', 'Aniket D Joshi', 'M.Com', '1', '7', NULL, NULL, 'aniket.joshi35@example.com', '9444455235', '2025-11-28 16:38:44', '$2y$10$eN9.cbUbn.AnmKSi4titqOaM9sZWNTEjmiQiW.R4D1.8Q3r1g61Wa', '103', 'M2425-11524', 'Aniket', 'D', 'Joshi', 'Lata Patil', '9444455235', '9595266365', 'Commerce', '2021', '1', '3', 'New Girls Hostel', 'A', '1', '103'),
('585', 'M2425-11525', 'Aarav T Kulkarni', 'B.A', '1', '7', NULL, NULL, 'aarav.kulkarni19@example.com', '8248374090', '2025-11-28 16:38:45', '$2y$10$Q2igcnpLu2tmyZB4veYH/un7cNVmlDpAmDjCCNLFc6L6UGFZZ0rrS', '104', 'M2425-11525', 'Aarav', 'T', 'Kulkarni', 'Rekha Deshmukh', '8248374090', '9399008489', 'Arts', '2024', '1', '2', 'New Girls Hostel', 'A', '1', '104'),
('586', 'M2425-11526', 'Aditya D Joshi', 'B.Tech', '1', '7', NULL, NULL, 'aditya.joshi77@example.com', '9294405596', '2025-11-28 16:38:45', '$2y$10$NbQTxzlY.7Karh6/nLed5.WsfNnUJoSoWC5yVUP5sfuFyBoPi0gEe', '105', 'M2425-11526', 'Aditya', 'D', 'Joshi', 'Sunita Joshi', '9294405596', '8861346454', 'Engineering', '2025', '1', '4', 'New Girls Hostel', 'A', '1', '105'),
('587', 'M2425-11527', 'Pooja L Rane', 'M.Com', '1', '7', NULL, NULL, 'pooja.rane69@example.com', '7486518344', '2025-11-28 16:38:45', '$2y$10$IoJnZgdZfg5Qon9Fmptr..k5GVPocC5kAjYJiTYEYZRvZ0IYkaSCC', '106', 'M2425-11527', 'Pooja', 'L', 'Rane', 'Sangeeta Joshi', '7486518344', '9517851392', 'Commerce', '2024', '1', '4', 'New Girls Hostel', 'A', '1', '106'),
('588', 'M2425-11528', 'Amol L Deshmukh', 'BCA', '1', '7', NULL, NULL, 'amol.deshmukh57@example.com', '8791436003', '2025-11-28 16:38:45', '$2y$10$5Xu3sRDvT6CFN3Z0rlez8O7ZQejbr9pQTGv6kQCFlx/KFHHpGJi5i', '107', 'M2425-11528', 'Amol', 'L', 'Deshmukh', 'Archana Joshi', '8791436003', '9934367860', 'Computer Science', '2023', '1', '3', 'New Girls Hostel', 'A', '1', '107'),
('589', 'M2425-11529', 'Aarav V Pawar', 'Diploma', '1', '7', NULL, NULL, 'aarav.pawar30@example.com', '9099925107', '2025-11-28 16:38:45', '$2y$10$D5xlWz/U7dNxYaAccD1ISupaCvz2D4915KuTR54zmAeTXKwCBngim', '108', 'M2425-11529', 'Aarav', 'V', 'Pawar', 'Sangeeta Kulkarni', '9099925107', '7615448292', 'Polytechnic', '2024', '1', '3', 'New Girls Hostel', 'A', '1', '108'),
('590', 'M2425-11530', 'Neha S Kadam', 'BCA', '1', '7', NULL, NULL, 'neha.kadam78@example.com', '6817914536', '2025-11-28 16:38:45', '$2y$10$O5bcm2EovLZTSIT5O3vEyemo8sL8kv7iggOxAxp0TNenurojogMje', '109', 'M2425-11530', 'Neha', 'S', 'Kadam', 'Sunita Pawar', '6817914536', '7904561960', 'Computer Science', '2025', '1', '3', 'New Girls Hostel', 'A', '1', '109'),
('591', 'M2425-11531', 'Rohan P Pawar', 'B.A', '1', '7', NULL, NULL, 'rohan.pawar28@example.com', '8861332812', '2025-11-28 16:38:45', '$2y$10$5a9Z/PKEZmodGk5Lim7xGuh/8sa5Q5IhXxn1gWmBNyJJMEg0N2Zyi', '110', 'M2425-11531', 'Rohan', 'P', 'Pawar', 'Sunita Pawar', '8861332812', '6899478170', 'Arts', '2024', '1', '4', 'New Girls Hostel', 'A', '1', '110'),
('592', 'M2425-11532', 'Karan S Joshi', 'B.Com', '1', '7', NULL, NULL, 'karan.joshi65@example.com', '8534894798', '2025-11-28 16:38:45', '$2y$10$d7fDJjhnktGrJ4XtsDAZT.YJ7kc5VJAzzAPmkk1TgujE2eg3aJkjy', '111', 'M2425-11532', 'Karan', 'S', 'Joshi', 'Meena Kadam', '8534894798', '8878668697', 'Commerce', '2024', '1', '3', 'New Girls Hostel', 'A', '1', '111'),
('593', 'M2425-11533', 'Karan T Gavankar', 'B.Tech', '1', '7', NULL, NULL, 'karan.gavankar68@example.com', '8452867953', '2025-11-28 16:38:45', '$2y$10$mA88BtfYTPFhJFNwtNQf8uBWlebGGiaBDsh2Itw9XHjhZOZ1g5zuy', '112', 'M2425-11533', 'Karan', 'T', 'Gavankar', 'Usha More', '8452867953', '7711502165', 'Engineering', '2023', '1', '3', 'New Girls Hostel', 'A', '1', '112'),
('594', 'M2425-11534', 'Aarav M More', 'M.Com', '1', '7', NULL, NULL, 'aarav.more90@example.com', '8341621086', '2025-11-28 16:38:45', '$2y$10$qJCc/kHzH2mtJXm8XNQnFutlBgeQIqodGE.z1fHukxQbR3P7vlZdW', '113', 'M2425-11534', 'Aarav', 'M', 'More', 'Archana Kadam', '8341621086', '7024187175', 'Commerce', '2024', '1', '3', 'New Girls Hostel', 'A', '1', '113'),
('595', 'M2425-11535', 'Mitali D Shinde', 'B.Com', '1', '7', NULL, NULL, 'mitali.shinde34@example.com', '9005921914', '2025-11-28 16:38:45', '$2y$10$EM3zIRiR1BicF2aqX9yJu.Q/vcpe3IpR4SDGW5e0ZDwuacCdlohHW', '201', 'M2425-11535', 'Mitali', 'D', 'Shinde', 'Usha Kulkarni', '9005921914', '6710934605', 'Commerce', '2021', '1', '4', 'New Girls Hostel', 'A', '2', '201'),
('596', 'M2425-11536', 'Isha R Kulkarni', 'M.Com', '1', '7', NULL, NULL, 'isha.kulkarni41@example.com', '7189145278', '2025-11-28 16:38:45', '$2y$10$Hq1b5dlTuZyx5cENxeQcZ.24BlrT4aHyofqxBzv/.KqOGPKXecTw2', '202', 'M2425-11536', 'Isha', 'R', 'Kulkarni', 'Sangeeta Kulkarni', '7189145278', '6840792345', 'Commerce', '2024', '1', '3', 'New Girls Hostel', 'A', '2', '202'),
('597', 'M2425-11537', 'Aniket T More', 'B.Sc', '1', '7', NULL, NULL, 'aniket.more46@example.com', '6203356306', '2025-11-28 16:38:45', '$2y$10$/wFQo4QxNS0fSsdRJFlncuZXZmIHWXDGxGfA4S4bQw3OH7pXacR1i', '203', 'M2425-11537', 'Aniket', 'T', 'More', 'Rekha Gavankar', '6203356306', '6977780267', 'Science', '2023', '1', '4', 'New Girls Hostel', 'A', '2', '203'),
('598', 'M2425-11538', 'Karan P Pawar', 'M.Com', '1', '7', NULL, NULL, 'karan.pawar93@example.com', '9757033045', '2025-11-28 16:38:45', '$2y$10$0/BrLF3BfQFqAK3o8nqYnenqvwIIneTmjyVcK0u9ZX/X.2JbqlmHa', '204', 'M2425-11538', 'Karan', 'P', 'Pawar', 'Lata Gavankar', '9757033045', '8256132116', 'Commerce', '2023', '1', '2', 'New Girls Hostel', 'A', '2', '204'),
('599', 'M2425-11539', 'Rohan P More', 'BCA', '1', '7', NULL, NULL, 'rohan.more96@example.com', '6764539919', '2025-11-28 16:38:45', '$2y$10$fp8GTDnN6G8neMGiv1hfFONZYx7q8ScUgP0/6.xTwtUyG16ItCP8a', '205', 'M2425-11539', 'Rohan', 'P', 'More', 'Rekha Pawar', '6764539919', '8813699371', 'Computer Science', '2023', '1', '3', 'New Girls Hostel', 'A', '2', '205'),
('600', 'M2425-11540', 'Rahul T Rane', 'BBA', '1', '7', NULL, NULL, 'rahul.rane26@example.com', '9405083255', '2025-11-28 16:38:45', '$2y$10$b0FGlK.bpYBgP/iE/hFjVukLjH8MsMCeQBbxK/0EJUqKywcgmgc/S', '206', 'M2425-11540', 'Rahul', 'T', 'Rane', 'Lata Pawar', '9405083255', '9477345907', 'Management', '2023', '1', '2', 'New Girls Hostel', 'A', '2', '206'),
('601', 'M2425-11541', 'Aarav K Patil', 'BBA', '1', '7', NULL, NULL, 'aarav.patil91@example.com', '8913468417', '2025-11-28 16:38:45', '$2y$10$WJHfKnFvHdklxynq.aqL0uRIf6Ze91RR2DqHdLaCLrmZ.KPvLBINe', '207', 'M2425-11541', 'Aarav', 'K', 'Patil', 'Neeta More', '8913468417', '6887025910', 'Management', '2024', '1', '2', 'New Girls Hostel', 'A', '2', '207'),
('602', 'M2425-11542', 'Rahul T Gavankar', 'Diploma', '1', '7', NULL, NULL, 'rahul.gavankar66@example.com', '7483701109', '2025-11-28 16:38:45', '$2y$10$Mx9PL6XOXChYMubZf2Xo2.DUEuhvfPezr4OP/gzZZjEsvWIr9aT1q', '208', 'M2425-11542', 'Rahul', 'T', 'Gavankar', 'Sunita Rane', '7483701109', '6212135564', 'Polytechnic', '2021', '1', '2', 'New Girls Hostel', 'A', '2', '208'),
('603', 'M2425-11543', 'Pooja L Joshi', 'B.Com', '1', '7', NULL, NULL, 'pooja.joshi88@example.com', '6790944338', '2025-11-28 16:38:46', '$2y$10$6rgbZua14t9WOQVgjFu04eEc9gjmeb8RqJdn9j2cFXFeI8nGI4epq', '209', 'M2425-11543', 'Pooja', 'L', 'Joshi', 'Lata Pawar', '6790944338', '8422925837', 'Commerce', '2022', '1', '2', 'New Girls Hostel', 'A', '2', '209'),
('604', 'M2425-11544', 'Isha M Shinde', 'B.Com', '1', '7', NULL, NULL, 'isha.shinde89@example.com', '6130476920', '2025-11-28 16:38:46', '$2y$10$oHVfKGuywGWuHE1oOlD0NeuCQfD4kL4ZMJqA7utjtdrlrWr6MaFI6', '210', 'M2425-11544', 'Isha', 'M', 'Shinde', 'Usha Kulkarni', '6130476920', '6072466245', 'Commerce', '2022', '1', '3', 'New Girls Hostel', 'A', '2', '210'),
('605', 'M2425-11545', 'Aniket S Gavankar', 'B.Com', '1', '7', NULL, NULL, 'aniket.gavankar17@example.com', '6926572016', '2025-11-28 16:38:46', '$2y$10$rE.foAgM.F/Dv0DTBdn9aO1Bz2rIz2eAbGELPfzExzNZdXVN/goz6', '211', 'M2425-11545', 'Aniket', 'S', 'Gavankar', 'Meena Rane', '6926572016', '9791098154', 'Commerce', '2022', '1', '2', 'New Girls Hostel', 'A', '2', '211'),
('606', 'M2425-11546', 'Sai T More', 'BBA', '1', '7', NULL, NULL, 'sai.more29@example.com', '8911641689', '2025-11-28 16:38:46', '$2y$10$wJX/mYFqTcj2qVTEk1OJv.W52D/P/Ux.RjjLslqczVas18.s8lsjC', '212', 'M2425-11546', 'Sai', 'T', 'More', 'Sunita Kadam', '8911641689', '8776386695', 'Management', '2025', '1', '4', 'New Girls Hostel', 'A', '2', '212'),
('607', 'M2425-11547', 'Vaishnavi T Patil', 'B.Tech', '1', '7', NULL, NULL, 'vaishnavi.patil75@example.com', '6134889547', '2025-11-28 16:38:46', '$2y$10$xKc8jrTEJ11Ix9KY/dT3KeffMSgAAPsP90U5l3kYiwlCKvXnfS9v.', '213', 'M2425-11547', 'Vaishnavi', 'T', 'Patil', 'Seema Gavankar', '6134889547', '6028742554', 'Engineering', '2024', '1', '4', 'New Girls Hostel', 'A', '2', '213'),
('608', 'M2425-11548', 'Isha V Pawar', 'B.Sc', '1', '7', NULL, NULL, 'isha.pawar11@example.com', '7719958855', '2025-11-28 16:38:46', '$2y$10$oK0yDyRb0a52rXyUACYJSOJ.1060yqUpbSFfe1wI9Yr4Ilmin3PuW', '301', 'M2425-11548', 'Isha', 'V', 'Pawar', 'Rekha Patil', '7719958855', '9266140073', 'Science', '2021', '1', '2', 'New Girls Hostel', 'A', '3', '301'),
('609', 'M2425-11549', 'Sneha N Rane', 'B.A', '1', '7', NULL, NULL, 'sneha.rane91@example.com', '7451325984', '2025-11-28 16:38:46', '$2y$10$CDfjwq.wA8//S32y8SnnouTjkujHS93XiLw3u9JUOQtep4ZHdf.aG', '302', 'M2425-11549', 'Sneha', 'N', 'Rane', 'Seema Deshmukh', '7451325984', '7449935313', 'Arts', '2021', '1', '3', 'New Girls Hostel', 'A', '3', '302'),
('610', 'M2425-11550', 'Prajakta V Rane', 'M.Com', '1', '7', NULL, NULL, 'prajakta.rane38@example.com', '8764446637', '2025-11-28 16:38:46', '$2y$10$UER9kw.5bYTvanS4ncPzUuu.LA0ofFUsLz2Lwt/ijtwefZ9t9Rovi', '303', 'M2425-11550', 'Prajakta', 'V', 'Rane', 'Lata Kadam', '8764446637', '8336131120', 'Commerce', '2024', '1', '3', 'New Girls Hostel', 'A', '3', '303'),
('611', 'M2425-11552', 'Amol L More', 'BBA', '1', '7', NULL, NULL, 'amol.more33@example.com', '9657526195', '2025-11-28 16:38:46', '$2y$10$M0w/r1LF68D/w2aQM3G1deqGQ9SdGu1Lj0a2IBjJqus3drahnMK.6', '305', 'M2425-11552', 'Amol', 'L', 'More', 'Meena Patil', '9657526195', '9018645729', 'Management', '2022', '1', '4', 'New Girls Hostel', 'A', '3', '305'),
('612', 'M2425-11553', 'Anjali K Pawar', 'BBA', '1', '7', NULL, NULL, 'anjali.pawar20@example.com', '6633067881', '2025-11-28 16:38:46', '$2y$10$a5wP3FgAw/iyDd3RAtKd6urWp218u/EjbHbc5oPCPNaDmnm21EYne', '306', 'M2425-11553', 'Anjali', 'K', 'Pawar', 'Sangeeta Gavankar', '6633067881', '7604989792', 'Management', '2021', '1', '3', 'New Girls Hostel', 'A', '3', '306'),
('613', 'M2425-11555', 'Aniket K Pawar', 'B.A', '1', '7', NULL, NULL, 'aniket.pawar64@example.com', '9987338953', '2025-11-28 16:38:46', '$2y$10$o/AtdcHHcuZqTKYcfZxrIe.LRmHmnOHbnaSLRAYzquLjGldIIGRbO', '308', 'M2425-11555', 'Aniket', 'K', 'Pawar', 'Kavita Kadam', '9987338953', '8200143553', 'Arts', '2025', '1', '4', 'New Girls Hostel', 'A', '3', '308'),
('614', 'M2425-11556', 'Anjali V Gavankar', 'Diploma', '1', '7', NULL, NULL, 'anjali.gavankar51@example.com', '8870593386', '2025-11-28 16:38:46', '$2y$10$fzgRjTbv5jWggmuYJ1bAbe6w.Up.zzKdLXP6hT3LOfTyjRRPtvYW6', '309', 'M2425-11556', 'Anjali', 'V', 'Gavankar', 'Sunita Rane', '8870593386', '7126232260', 'Polytechnic', '2021', '1', '4', 'New Girls Hostel', 'A', '3', '309'),
('615', 'M2425-11557', 'Aditya R Shinde', 'BBA', '1', '7', NULL, NULL, 'aditya.shinde11@example.com', '7048541065', '2025-11-28 16:38:46', '$2y$10$ymoqNz8ZkrsHoOq2oxm3/O6UNnf/eU.W5qDhMblbLk1PKyat8ySpm', '310', 'M2425-11557', 'Aditya', 'R', 'Shinde', 'Archana Kulkarni', '7048541065', '6962602592', 'Management', '2021', '1', '3', 'New Girls Hostel', 'A', '3', '310'),
('616', 'M2425-11558', 'Neha V Gavankar', 'BCA', '1', '7', NULL, NULL, 'neha.gavankar94@example.com', '9025358666', '2025-11-28 16:38:46', '$2y$10$lqIwbHhW5xUogDFUB5xYrOh0N0fGrm3mjcIfDEsraUxdVcO//eGr6', '311', 'M2425-11558', 'Neha', 'V', 'Gavankar', 'Archana Pawar', '9025358666', '7210098780', 'Computer Science', '2021', '1', '2', 'New Girls Hostel', 'A', '3', '311'),
('617', 'M2425-11559', 'Siddharth R Joshi', 'BCA', '1', '7', NULL, NULL, 'siddharth.joshi64@example.com', '8258159036', '2025-11-28 16:38:46', '$2y$10$quQb.A2/Dnx5Ne238TRL.OJbkyu7BSVfMeDq8HmGjaP23aOikD9xa', '312', 'M2425-11559', 'Siddharth', 'R', 'Joshi', 'Meena Rane', '8258159036', '9425795739', 'Computer Science', '2025', '1', '2', 'New Girls Hostel', 'A', '3', '312'),
('618', 'M2425-11560', 'Siddharth M Patil', 'B.A', '1', '7', NULL, NULL, 'siddharth.patil73@example.com', '8384764104', '2025-11-28 16:38:46', '$2y$10$8MEveMdC0puoTl8Rswq0wuUcKh8BdQ36/Jb087t3MIkdbwvBH/xNO', '313', 'M2425-11560', 'Siddharth', 'M', 'Patil', 'Meena Joshi', '8384764104', '6429058772', 'Arts', '2022', '1', '3', 'New Girls Hostel', 'A', '3', '313'),
('619', 'M2425-11561', 'Prajakta R Shinde', 'B.Tech', '1', '7', NULL, NULL, 'prajakta.shinde56@example.com', '6858337834', '2025-11-28 16:38:46', '$2y$10$ETnB99WEN2.iBpg0i/NijeGA1wm7g5v.UUJPVuV1O2Zt5rIszojSG', '401', 'M2425-11561', 'Prajakta', 'R', 'Shinde', 'Seema Pawar', '6858337834', '6687679964', 'Engineering', '2021', '1', '4', 'New Girls Hostel', 'A', '4', '401'),
('620', 'M2425-11563', 'Neha T Rane', 'Diploma', '1', '7', NULL, NULL, 'neha.rane85@example.com', '6575616078', '2025-11-28 16:38:47', '$2y$10$depinQIs0oJRra8/qAPSWe/a7qknGSKu0e.1Ol6CimIEhK9tLTSiC', '403', 'M2425-11563', 'Neha', 'T', 'Rane', 'Usha Gavankar', '6575616078', '6469340182', 'Polytechnic', '2021', '1', '3', 'New Girls Hostel', 'A', '4', '403'),
('621', 'M2425-11564', 'Siddharth D Rane', 'Diploma', '1', '7', NULL, NULL, 'siddharth.rane84@example.com', '7496377158', '2025-11-28 16:38:47', '$2y$10$SEq.IIiMdAgWCsD6aLPndeQtXFqCrvR0vPDIFN5V65TgVB/woBHi2', '404', 'M2425-11564', 'Siddharth', 'D', 'Rane', 'Lata Kadam', '7496377158', '7108992296', 'Polytechnic', '2023', '1', '2', 'New Girls Hostel', 'A', '4', '404'),
('622', 'M2425-11565', 'Isha P Deshmukh', 'B.Tech', '1', '7', NULL, NULL, 'isha.deshmukh24@example.com', '6468226959', '2025-11-28 16:38:47', '$2y$10$QB1vt97mBIuY8z94xockwenV.6HelFDptKy7.uJeHQthfkPsjEqcy', '405', 'M2425-11565', 'Isha', 'P', 'Deshmukh', 'Usha Shinde', '6468226959', '8196273667', 'Engineering', '2023', '1', '2', 'New Girls Hostel', 'A', '4', '405'),
('623', 'M2425-11566', 'Sai V Pawar', 'BBA', '1', '7', NULL, NULL, 'sai.pawar41@example.com', '9520302913', '2025-11-28 16:38:47', '$2y$10$5v1FJAjeC.8EInr.UKm5V.rSw.Kw.md7A4CLP.dsyRIzxMzQx1cu.', '406', 'M2425-11566', 'Sai', 'V', 'Pawar', 'Rekha Patil', '9520302913', '6681302039', 'Management', '2025', '1', '3', 'New Girls Hostel', 'A', '4', '406'),
('624', 'M2425-11567', 'Neha V More', 'BBA', '1', '7', NULL, NULL, 'neha.more31@example.com', '9115569821', '2025-11-28 16:38:47', '$2y$10$WIRecmYJD2ah.WyhPg3hL.dBVWpPCufvE812aGGhmXwa9Mk5vW/re', '407', 'M2425-11567', 'Neha', 'V', 'More', 'Usha Kulkarni', '9115569821', '6693702366', 'Management', '2022', '1', '3', 'New Girls Hostel', 'A', '4', '407'),
('625', 'M2425-11568', 'Siddharth T Gavankar', 'B.A', '1', '7', NULL, NULL, 'siddharth.gavankar54@example.com', '8115325740', '2025-11-28 16:38:47', '$2y$10$jIwE3UiUyhlxbN8ZgFnWaudzV20cidX4dvzi4axYTkuKSzbtjDi7u', '408', 'M2425-11568', 'Siddharth', 'T', 'Gavankar', 'Seema Rane', '8115325740', '8393142422', 'Arts', '2021', '1', '3', 'New Girls Hostel', 'A', '4', '408'),
('626', 'M2425-11569', 'Neha N Patil', 'BBA', '1', '7', NULL, NULL, 'neha.patil73@example.com', '8927354097', '2025-11-28 16:38:47', '$2y$10$yfv1XCTIt2aTqt5cUrOdKODYFvEcH0.DqemP6eOySCe2cea2RykU.', '409', 'M2425-11569', 'Neha', 'N', 'Patil', 'Lata Rane', '8927354097', '6175286999', 'Management', '2023', '1', '4', 'New Girls Hostel', 'A', '4', '409'),
('627', 'M2425-11570', 'Aniket D Shinde', 'BBA', '1', '7', NULL, NULL, 'aniket.shinde94@example.com', '9370513948', '2025-11-28 16:38:47', '$2y$10$goVecZ/UfODHekLwEBcy/OpOZqFqGZk.8/US.gzTE4YpZ6d/BZhWa', '410', 'M2425-11570', 'Aniket', 'D', 'Shinde', 'Neeta Gavankar', '9370513948', '6914882840', 'Management', '2025', '1', '4', 'New Girls Hostel', 'A', '4', '410'),
('628', 'M2425-11571', 'Aarav L Gavankar', 'B.Tech', '1', '7', NULL, NULL, 'aarav.gavankar62@example.com', '8331765827', '2025-11-28 16:38:47', '$2y$10$r5dl2cFSApQBScRuKJ6IVOrrsDZZ4hZdwBsBQIR6qs8ih.afMQ.9i', '411', 'M2425-11571', 'Aarav', 'L', 'Gavankar', 'Lata Deshmukh', '8331765827', '9683328318', 'Engineering', '2025', '1', '3', 'New Girls Hostel', 'A', '4', '411'),
('630', 'M2425-11573', 'Akaay L More', 'BBA', '1', '7', NULL, NULL, 'amol.more33@example.com', '9657526195', '2025-11-28 16:38:47', '$2y$10$lIIdYqONskuMtWgwOrLEk.AeKfE8ISYCJo6zmTcml6ME1mdsdWgSS', '413', 'M2425-11573', 'Akaay', 'L', 'More', 'Meena Patil', '9657526195', '9018645729', 'Management', '2022', '1', '4', 'New Girls Hostel', 'A', '4', '413'),
('631', 'M2425-11572', 'Aditi R Shinde', 'BBA', '1', '7', NULL, NULL, 'aditya.shinde11@example.com', '7048541065', '2025-11-28 17:08:55', '$2y$10$lr3gWhMUnXQvkSSmBGoiZ.UOryonycRNZpyQHn3g4fvE7VBO0p8Qi', '412', 'M2425-11572', 'Aditi', 'R', 'Shinde', 'Archana Kulkarni', '7048541065', '6962602592', 'Management', '2021', '1', '3', 'New Girls Hostel', 'A', '4', '412'),
('632', 'GRN-987', 'shruti rasal', 'Information Technology', '4', '7', NULL, '35', 'sakshi@gmail.com', '', '2025-11-28 17:18:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','rector','student_head','mess_head','library_head','health_staff','vvk_staff','placement_staff','ed_cell_staff','scholarship_staff','student') NOT NULL,
  `hostel_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=320 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES
('32', 'sakshi.bhudge@gmail.com', 'f0ba1fb718eba9f8f2b31a6d78bd1824', 'student', '7', '2025-11-18 16:20:51'),
('33', 'rakhi@gmail.com', '572fbd6a6e3f7304bcbb08b016dd50ef', 'library_head', '7', '2025-11-18 20:13:38'),
('34', 'nandini@gmail.com', '37bff309b273a7034e9a1dcbcdc657d3', 'mess_head', '7', '2025-11-18 20:15:10'),
('35', 'sakshi@gmail.com', 'f0ba1fb718eba9f8f2b31a6d78bd1824', 'student', '7', '2025-11-28 17:18:22'),
('36', 'reshma@gmail.com', '0f8593a605fc717e43de559a901ed8a1', 'vvk_staff', '7', '2025-11-28 17:20:39'),
('37', 'Durgesh Pawar', '0192023a7bbd73250516f069df18b500', 'super_admin', NULL, '2025-11-29 15:34:11'),
('38', 'admin@vsshostel.edu', '0192023a7bbd73250516f069df18b500', 'super_admin', NULL, '2025-11-29 15:34:11'),
('310', 'amit.d@example.com', '$2y$10$J1es1k6kfhH1EM9Obs6fP.Em0aLqidBCfR.XxqJPJ14f/0mzB0VUm', 'rector', '1', '2025-12-01 16:22:27'),
('311', 'sneha.p@example.com', '$2y$10$kYSmxM3uRReRzqRoYY/kMO8YJ1q64FFxK/PIK8HguWOUwSoZC27TC', 'rector', '2', '2025-12-01 16:22:27'),
('312', 'rahul.s@example.com', '$2y$10$oz7HlDEpmI0P4BU8IlLAf.CMpODo3xodKkMedyR04TjKYPwJPBECi', 'rector', '3', '2025-12-01 16:22:27'),
('313', 'priya.j@example.com', '$2y$10$/VQw8ZK4YKjsRIS3.Aplhukdpp65wA8qWuS1i9FaOIRqvOfxMbnxG', 'rector', '4', '2025-12-01 16:22:27'),
('314', 'kiran.p@example.com', '$2y$10$Z/hFg6CKLbIL/eZ1NX.1gO1iQbth0/CG5wk8BJLZKxv5FRKgLzgY6', 'rector', '5', '2025-12-01 16:22:28'),
('315', 'manish.k@example.com', '$2y$10$wHI2LzZ09CYMBgBzQXqqHeJreY7hNRuYMVneQ9ZuI1OmruKvyOdAK', 'rector', '6', '2025-12-01 16:22:28'),
('316', 'pooja.b@example.com', '$2y$10$.YHVYkbh4yqhhfrOlR8UTOrHXmz2Ml75dOOjGrqwJj8JTNdpsqQ/.', 'rector', '7', '2025-12-01 16:22:28'),
('317', 'sachin.m@example.com', '$2y$10$2zHEIkcc4G/mTfv632RE/OXjEniMfDAOkV4GQdBA06VhxrdrehgOG', 'rector', NULL, '2025-12-01 16:22:28'),
('318', 'neha.s@example.com', '$2y$10$c3pnclsyRPv0Mz/LNlCkQ.viR1T3hPWHlI3.P8/tFo6ZgkDvjJDzC', 'rector', NULL, '2025-12-01 16:22:28'),
('319', 'rohit.c@example.com', '$2y$10$7n69v0cHrYdFphwCWy1jdeQ/4KnoNaj/0o39e048OE6hDeMhnbESi', 'rector', NULL, '2025-12-01 16:22:28');

SET FOREIGN_KEY_CHECKS=1;
