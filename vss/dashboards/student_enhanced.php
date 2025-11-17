<?php
require_once '../config/database.php';
require_once '../includes/db_check.php';
session_start();

if ($_SESSION['role'] != 'student') {
    header('Location: ../auth/login.php');
    exit;
}

// Get student details
$student_query = executeQuery($pdo, "
    SELECT s.*, h.name as hostel_name, h.location, r.room_number, r.hostel_id 
    FROM students s 
    LEFT JOIN rooms r ON s.room_id = r.id 
    LEFT JOIN hostels h ON r.hostel_id = h.id 
    WHERE s.user_id = ?
", [$_SESSION['user_id']]);

$student = $student_query ? $student_query->fetch() : null;

if (!$student) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Student record not found.</div></div>");
}

// Handle feedback submission
if ($_POST && isset($_POST['submit_feedback'])) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS mess_feedback (
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
        )");
        
        $stmt = $pdo->prepare("INSERT INTO mess_feedback (student_id, feedback_type, subject, category, message, rating, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $student['id'], 
            $_POST['feedback_type'], 
            $_POST['subject'], 
            $_POST['category'], 
            $_POST['message'], 
            $_POST['rating'], 
            $_POST['priority']
        ]);
        
        $feedback_id = $pdo->lastInsertId();
        header("Location: " . $_SERVER['PHP_SELF'] . "?success=" . $feedback_id);
        exit;
    } catch (Exception $e) {
        $error = "Error submitting feedback: " . $e->getMessage();
    }
}

// Get data
$attendance_query = executeQuery($pdo, "SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 10", [$student['id']]);
$attendance_records = $attendance_query ? $attendance_query->fetchAll() : [];

$attendance_stats = executeQuery($pdo, "
    SELECT COUNT(*) as total_days, COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days
    FROM attendance WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
", [$student['id']]);
$attendance_data = $attendance_stats ? $attendance_stats->fetch() : ['total_days' => 0, 'present_days' => 0];
$attendance_percentage = $attendance_data['total_days'] > 0 ? round(($attendance_data['present_days'] / $attendance_data['total_days']) * 100) : 0;

$books_query = executeQuery($pdo, "SELECT COUNT(*) as count FROM book_issues WHERE student_id = ? AND return_date IS NULL", [$student['id']]);
$issued_count = $books_query ? $books_query->fetch()['count'] : 0;

$events_query = executeQuery($pdo, "SELECT * FROM events WHERE hostel_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 5", [$student['hostel_id'] ?? 1]);
$events_list = $events_query ? $events_query->fetchAll() : [];

$feedback_query = executeQuery($pdo, "SELECT * FROM mess_feedback WHERE student_id = ? ORDER BY created_at DESC LIMIT 5", [$student['id']]);
$my_feedback = $feedback_query ? $feedback_query->fetchAll() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .stat-card { background: white; border-radius: 15px; padding: 25px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2.5rem; font-weight: 700; margin-bottom: 5px; }
        .stat-label { color: #6c757d; font-size: 0.9rem; }
        .btn-action { padding: 12px 20px; border-radius: 10px; font-weight: 600; margin-bottom: 10px; }
        .rating-stars { font-size: 30px; cursor: pointer; user-select: none; }
        .rating-stars span { transition: color 0.2s ease; }
        .rating-stars span:hover { color: #ffc107 !important; }
        .table th { background: #f8f9fa; border: none; font-weight: 600; }
        .badge { font-size: 0.8rem; padding: 6px 12px; }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="#">
                <i class="fas fa-user-graduate me-2"></i>Student Portal
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo $student['name']; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Success Message -->
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Feedback submitted successfully! (ID: #<?php echo str_pad($_GET['success'], 4, '0', STR_PAD_LEFT); ?>)
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-primary rounded-circle p-3 me-3" style="width: 60px; height: 60px;">
                                <i class="fas fa-user-graduate text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1">Welcome, <?php echo $student['name']; ?>!</h2>
                                <p class="mb-0 text-muted">Student ID: <?php echo $student['grn']; ?> | <?php echo $student['course']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $attendance_percentage; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $issued_count; ?></div>
                    <div class="stat-label">Books Issued</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo count($events_list); ?></div>
                    <div class="stat-label">Upcoming Events</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $student['year']; ?></div>
                    <div class="stat-label">Academic Year</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Student ID:</strong> <?php echo $student['grn']; ?></p>
                                <p><strong>Name:</strong> <?php echo $student['name']; ?></p>
                                <p><strong>Course:</strong> <?php echo $student['course']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Hostel:</strong> <?php echo $student['hostel_name']; ?></p>
                                <p><strong>Room:</strong> <?php echo $student['room_number'] ?? 'Not Allocated'; ?></p>
                                <p><strong>Year:</strong> <?php echo $student['year']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attendance -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Recent Attendance</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($record['date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $record['status'] == 'present' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Mess Feedback -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-comment me-2"></i>Submit Mess Feedback</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="feedbackForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Feedback Type</label>
                                        <select class="form-select" name="feedback_type" required>
                                            <option value="">Select type</option>
                                            <option value="complaint">Complaint</option>
                                            <option value="suggestion">Suggestion</option>
                                            <option value="compliment">Compliment</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Category</label>
                                        <select class="form-select" name="category" required>
                                            <option value="">Select category</option>
                                            <option value="Food Quality">Food Quality</option>
                                            <option value="Food Quantity">Food Quantity</option>
                                            <option value="Hygiene">Hygiene</option>
                                            <option value="Service">Service</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Subject</label>
                                        <input type="text" class="form-control" name="subject" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Priority</label>
                                        <select class="form-select" name="priority">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Rating (1-5)</label>
                                        <div class="rating-stars" id="ratingStars">
                                            <span data-rating="1" style="color: #ddd;">★</span>
                                            <span data-rating="2" style="color: #ddd;">★</span>
                                            <span data-rating="3" style="color: #ddd;">★</span>
                                            <span data-rating="4" style="color: #ddd;">★</span>
                                            <span data-rating="5" style="color: #ddd;">★</span>
                                        </div>
                                        <input type="hidden" name="rating" id="rating" required>
                                        <small class="text-muted">Click stars to rate</small>
                                        <div id="ratingError" class="text-danger" style="display: none;">Please select a rating</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Message</label>
                                        <textarea class="form-control" name="message" rows="4" required></textarea>
                                    </div>
                                    <button type="submit" name="submit_feedback" class="btn btn-primary btn-action w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- My Previous Feedback -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Previous Feedback</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($my_feedback)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No feedback submitted yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach($my_feedback as $feedback): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></td>
                                            <td><span class="badge bg-<?php echo $feedback['feedback_type'] == 'complaint' ? 'danger' : ($feedback['feedback_type'] == 'suggestion' ? 'warning' : 'success'); ?>"><?php echo ucfirst($feedback['feedback_type']); ?></span></td>
                                            <td><?php echo $feedback['subject']; ?></td>
                                            <td>
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <span style="color: <?php echo $i <= $feedback['rating'] ? '#ffc107' : '#ddd'; ?>;">★</span>
                                                <?php endfor; ?>
                                            </td>
                                            <td><span class="badge bg-<?php echo $feedback['status'] == 'pending' ? 'warning' : ($feedback['status'] == 'resolved' ? 'success' : 'info'); ?>"><?php echo ucfirst($feedback['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-warning btn-action w-100" onclick="submitComplaint()">
                            <i class="fas fa-exclamation-triangle me-2"></i>Submit Complaint
                        </button>
                        <button class="btn btn-success btn-action w-100" onclick="applyScholarship()">
                            <i class="fas fa-award me-2"></i>Apply for Scholarship
                        </button>
                        <button class="btn btn-info btn-action w-100" onclick="bookHealthAppointment()">
                            <i class="fas fa-user-md me-2"></i>Book Health Appointment
                        </button>
                        <button class="btn btn-primary btn-action w-100" onclick="scrollToFeedback()">
                            <i class="fas fa-comment me-2"></i>Give Mess Feedback
                        </button>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events_list)): ?>
                            <p class="text-muted">No upcoming events</p>
                        <?php else: ?>
                            <?php foreach($events_list as $event): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <h6 class="mb-1"><?php echo $event['title']; ?></h6>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($event['date'])); ?></small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="complaintModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="complaintForm">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select category</option>
                                <option value="room">Room Issues</option>
                                <option value="mess">Mess Issues</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="submitComplaintForm()">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Star Rating Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.rating-stars span');
            const ratingInput = document.getElementById('rating');
            
            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    
                    // Update star colors
                    stars.forEach((s, i) => {
                        s.style.color = i < rating ? '#ffc107' : '#ddd';
                    });
                    
                    document.getElementById('ratingError').style.display = 'none';
                });
                
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    stars.forEach((s, i) => {
                        s.style.color = i < rating ? '#ffc107' : '#ddd';
                    });
                });
            });
            
            document.getElementById('ratingStars').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value) || 0;
                stars.forEach((s, i) => {
                    s.style.color = i < currentRating ? '#ffc107' : '#ddd';
                });
            });
            
            // Form validation
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                if (!ratingInput.value) {
                    e.preventDefault();
                    document.getElementById('ratingError').style.display = 'block';
                    document.getElementById('ratingStars').scrollIntoView({ behavior: 'smooth' });
                }
            });
        });

        // Quick Actions
        function submitComplaint() {
            new bootstrap.Modal(document.getElementById('complaintModal')).show();
        }

        function applyScholarship() {
            alert('Scholarship application feature will be available soon!');
        }

        function bookHealthAppointment() {
            alert('Health appointment booking feature will be available soon!');
        }

        function scrollToFeedback() {
            document.querySelector('.card:has(#feedbackForm)').scrollIntoView({ behavior: 'smooth' });
        }

        function submitComplaintForm() {
            alert('Complaint submitted successfully!');
            bootstrap.Modal.getInstance(document.getElementById('complaintModal')).hide();
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.classList.remove('show'));
        }, 5000);
    </script>
</body>
</html>