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

// Create table if not exists
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
} catch (Exception $e) {
    // Table creation error - will be handled by AJAX
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

// Get all feedback (mess and general) for the student
$mess_feedback_query = executeQuery($pdo, "SELECT *, 'mess' as feedback_category FROM mess_feedback WHERE student_id = ? ORDER BY created_at DESC", [$student['id']]);
$mess_feedback = $mess_feedback_query ? $mess_feedback_query->fetchAll() : [];

$general_feedback_query = executeQuery($pdo, "SELECT *, feedback_category FROM general_feedback WHERE student_id = ? ORDER BY created_at DESC", [$student['id']]);
$general_feedback = $general_feedback_query ? $general_feedback_query->fetchAll() : [];

// Combine all feedback
$my_feedback = array_merge($mess_feedback, $general_feedback);
// Sort by created_at descending
usort($my_feedback, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$my_feedback = array_slice($my_feedback, 0, 10); // Show last 10 feedback
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
    <style>
        .rating-stars { font-size: 30px; cursor: pointer; user-select: none; }
        .rating-stars span { transition: color 0.2s ease; }
        .rating-stars span.star-hover { color: #ffc107 !important; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-user-graduate me-2"></i>Student Portal
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#profile">
                            <i class="fas fa-user me-2"></i>Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#attendance">
                            <i class="fas fa-calendar-check me-2"></i>Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback">
                            <i class="fas fa-comment me-2"></i>Feedback
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo $student['name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-body text-center py-4">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <div class="bg-primary rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user-graduate text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;">Welcome, <?php echo $student['name']; ?>!</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-id-card me-1"></i>Student ID: <?php echo $student['grn']; ?> | <?php echo $student['course']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Quick Stats -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $attendance_percentage; ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                        <div class="stat-meta">Last 30 days performance</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $issued_count; ?></div>
                        <div class="stat-label">Active Books</div>
                        <div class="stat-meta">Currently borrowed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($events_list); ?></div>
                        <div class="stat-label">Upcoming Events</div>
                        <div class="stat-meta">Hostel activities</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $student['year']; ?></div>
                        <div class="stat-label">Academic Year</div>
                        <div class="stat-meta">Current semester</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Personal Information -->
                <div id="profile" class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user"></i> Personal Information
                        </h3>
                    </div>
                    <div class="card-content">
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
                <div id="attendance" class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-check"></i> Recent Attendance
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="modern-table-container">
                            <table class="modern-table">
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
                                            <span class="status-badge <?php echo $record['status'] == 'present' ? 'success' : 'danger'; ?>">
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

                <!-- Feedback System -->
                <div id="feedback" class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-comment"></i> Submit Feedback
                        </h3>
                    </div>
                    <div class="card-content">
                        <div id="feedbackAlert" style="display: none;"></div>
                        
                        <!-- Feedback Category Selection -->
                        <div class="form-group mb-4">
                            <label class="form-label">Select Feedback Category *</label>
                            <select class="form-input" id="feedbackCategory" required>
                                <option value="">Choose feedback category</option>
                                <option value="mess">🍽️ Mess Feedback</option>
                                <option value="library">📚 Library Feedback</option>
                                <option value="event">🎉 Event Feedback</option>
                                <option value="staff">👥 Staff Feedback</option>
                            </select>
                        </div>
                        
                        <!-- Mess Feedback Form -->
                        <div id="messFeedbackForm" class="feedback-form" style="display: none;">
                            <form method="POST" id="feedbackForm" class="modern-form">
                                <input type="hidden" name="feedback_category" value="mess">
                                <div class="dashboard-grid">
                                    <div class="modern-form">
                                        <div class="form-group">
                                            <label class="form-label">Feedback Type *</label>
                                            <select class="form-input" name="feedback_type" id="feedback_type" required>
                                                <option value="">Select type</option>
                                                <option value="complaint">🔴 Complaint</option>
                                                <option value="suggestion">💡 Suggestion</option>
                                                <option value="compliment">👍 Compliment</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Category *</label>
                                            <select class="form-input" name="category" id="category" required>
                                                <option value="">Select category</option>
                                                <option value="Food Quality">🍽️ Food Quality</option>
                                                <option value="Food Quantity">📏 Food Quantity</option>
                                                <option value="Hygiene">🧼 Hygiene & Cleanliness</option>
                                                <option value="Service">👥 Service & Staff</option>
                                                <option value="Menu Variety">🍜 Menu Variety</option>
                                                <option value="Timing">⏰ Meal Timing</option>
                                                <option value="Infrastructure">🏢 Infrastructure</option>
                                                <option value="Other">📝 Other</option>
                                            </select>
                                        </div>
                                    <div class="form-group">
                                        <label class="form-label">Subject *</label>
                                        <input type="text" class="form-input" name="subject" id="subject" placeholder="Brief subject of your feedback" maxlength="100" required>
                                        <small class="text-muted"><span id="subjectCount">0</span>/100 characters</small>
                                        <div class="invalid-feedback">Please enter a subject</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Priority</label>
                                        <select class="form-input" name="priority" id="priority">
                                            <option value="low">🟢 Low</option>
                                            <option value="medium" selected>🟡 Medium</option>
                                            <option value="high">🟠 High</option>
                                            <option value="urgent">🔴 Urgent</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="modern-form">
                                    <div class="form-group">
                                        <label class="form-label">Rating (1-5) *</label>
                                        <div class="rating-container">
                                            <div class="rating-stars" id="ratingStars">
                                                <span data-rating="1" style="color: #ddd;">★</span>
                                                <span data-rating="2" style="color: #ddd;">★</span>
                                                <span data-rating="3" style="color: #ddd;">★</span>
                                                <span data-rating="4" style="color: #ddd;">★</span>
                                                <span data-rating="5" style="color: #ddd;">★</span>
                                            </div>
                                            <div class="rating-text" id="ratingText" style="margin-top: 5px; font-weight: 600; color: #666;"></div>
                                        </div>
                                        <input type="hidden" name="rating" id="rating" required>
                                        <small class="text-muted">Click stars to rate your experience</small>
                                        <div id="ratingError" class="text-danger" style="display: none;">Please select a rating</div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Detailed Message *</label>
                                        <textarea class="form-input" name="message" id="message" rows="4" placeholder="Please provide detailed feedback..." maxlength="500" required></textarea>
                                        <small class="text-muted"><span id="messageCount">0</span>/500 characters</small>
                                        <div class="invalid-feedback">Please enter your feedback message</div>
                                    </div>
                                    <div class="d-grid gap-2">
                                            <button type="submit" name="submit_feedback" id="submitBtn" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Mess Feedback
                                            </button>
                                            <button type="button" id="resetBtn" class="btn btn-outline-secondary">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Other Feedback Forms -->
                        <div id="otherFeedbackForm" class="feedback-form" style="display: none;">
                            <form method="POST" id="otherForm" class="modern-form">
                                <input type="hidden" name="feedback_category" id="otherCategory" value="">
                                <div class="dashboard-grid">
                                    <div class="modern-form">
                                        <div class="form-group">
                                            <label class="form-label">Feedback Type *</label>
                                            <select class="form-input" name="feedback_type" required>
                                                <option value="">Select type</option>
                                                <option value="complaint">🔴 Complaint</option>
                                                <option value="suggestion">💡 Suggestion</option>
                                                <option value="compliment">👍 Compliment</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Subject *</label>
                                            <input type="text" class="form-input" name="subject" placeholder="Brief subject" maxlength="100" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Priority</label>
                                            <select class="form-input" name="priority">
                                                <option value="low">🟢 Low</option>
                                                <option value="medium" selected>🟡 Medium</option>
                                                <option value="high">🟠 High</option>
                                                <option value="urgent">🔴 Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modern-form">
                                        <div class="form-group">
                                            <label class="form-label">Rating (1-5) *</label>
                                            <div class="rating-container">
                                                <div class="rating-stars" id="otherRatingStars">
                                                    <span data-rating="1" style="color: #ddd;">★</span>
                                                    <span data-rating="2" style="color: #ddd;">★</span>
                                                    <span data-rating="3" style="color: #ddd;">★</span>
                                                    <span data-rating="4" style="color: #ddd;">★</span>
                                                    <span data-rating="5" style="color: #ddd;">★</span>
                                                </div>
                                            </div>
                                            <input type="hidden" name="rating" id="otherRating" required>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Message *</label>
                                            <textarea class="form-input" name="message" rows="4" placeholder="Detailed feedback..." maxlength="500" required></textarea>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="resetOtherForm()">
                                                <i class="fas fa-undo me-2"></i>Reset Form
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- My Previous Feedback -->
                <div class="modern-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title">
                            <i class="fas fa-history"></i> My Previous Feedback
                        </h3>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshFeedback()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="modern-table-container">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Subject</th>
                                        <th>Rating</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="feedbackTableBody">
                                    <?php if (empty($my_feedback)): ?>
                                        <tr><td colspan="9" class="text-center text-muted">No feedback submitted yet</td></tr>
                                    <?php else: ?>
                                        <?php foreach($my_feedback as $feedback): ?>
                                        <tr>
                                            <td><strong>#<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?><br><small class="text-muted"><?php echo date('h:i A', strtotime($feedback['created_at'])); ?></small></td>
                                            <td><span class="status-badge <?php echo $feedback['feedback_type'] == 'complaint' ? 'danger' : ($feedback['feedback_type'] == 'suggestion' ? 'warning' : 'success'); ?>"><?php echo ucfirst($feedback['feedback_type']); ?></span></td>
                                            <td>
                                                <span class="badge bg-<?php echo $feedback['feedback_category'] == 'mess' ? 'primary' : ($feedback['feedback_category'] == 'library' ? 'info' : ($feedback['feedback_category'] == 'event' ? 'warning' : 'secondary')); ?>">
                                                    <?php echo ucfirst($feedback['feedback_category']); ?>
                                                </span>
                                                <?php if($feedback['feedback_category'] == 'mess' && isset($feedback['category'])): ?>
                                                    <br><small class="text-muted"><?php echo $feedback['category']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($feedback['subject']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <span style="color: <?php echo $i <= $feedback['rating'] ? '#ffc107' : '#ddd'; ?>; font-size: 16px;">★</span>
                                                    <?php endfor; ?>
                                                    <span class="ms-2 small"><?php echo $feedback['rating']; ?>/5</span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-<?php echo $feedback['priority'] == 'urgent' ? 'danger' : ($feedback['priority'] == 'high' ? 'warning' : ($feedback['priority'] == 'medium' ? 'info' : 'secondary')); ?>"><?php echo ucfirst($feedback['priority']); ?></span></td>
                                            <td><span class="status-badge <?php echo $feedback['status'] == 'pending' ? 'warning' : ($feedback['status'] == 'resolved' ? 'success' : 'info'); ?>"><?php echo ucfirst($feedback['status']); ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-1" onclick="viewFeedbackDetails(<?php echo $feedback['id']; ?>, '<?php echo $feedback['feedback_category']; ?>')" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteFeedback(<?php echo $feedback['id']; ?>, '<?php echo $feedback['feedback_category']; ?>')" title="Delete Feedback">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
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
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt"></i> Quick Actions
                        </h3>
                    </div>
                    <div class="card-content">
                        <button class="btn btn-warning w-100 mb-2" onclick="submitComplaint()">
                            <i class="fas fa-exclamation-triangle me-2"></i>Submit Complaint
                        </button>
                        <button class="btn btn-success w-100 mb-2" onclick="applyScholarship()">
                            <i class="fas fa-award me-2"></i>Apply for Scholarship
                        </button>
                        <button class="btn btn-info w-100 mb-2" onclick="bookHealthAppointment()">
                            <i class="fas fa-user-md me-2"></i>Book Health Appointment
                        </button>
                        <button class="btn btn-primary w-100 mb-2" onclick="scrollToFeedback()">
                            <i class="fas fa-comment me-2"></i>Give Mess Feedback
                        </button>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i> Upcoming Events
                        </h3>
                    </div>
                    <div class="card-content">
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
                            <select class="form-input" name="category" required>
                                <option value="">Select category</option>
                                <option value="room">Room Issues</option>
                                <option value="mess">Mess Issues</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-input" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-input" name="description" rows="4" required></textarea>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Feedback category selection
            document.getElementById('feedbackCategory').addEventListener('change', function() {
                const category = this.value;
                const messFeedback = document.getElementById('messFeedbackForm');
                const otherFeedback = document.getElementById('otherFeedbackForm');
                const otherCategory = document.getElementById('otherCategory');
                
                if (category === 'mess') {
                    messFeedback.style.display = 'block';
                    otherFeedback.style.display = 'none';
                } else if (category) {
                    messFeedback.style.display = 'none';
                    otherFeedback.style.display = 'block';
                    otherCategory.value = category;
                } else {
                    messFeedback.style.display = 'none';
                    otherFeedback.style.display = 'none';
                }
            });
            
            var currentRating = 0;
            var stars = document.querySelectorAll('#ratingStars span');
            var ratingInput = document.getElementById('rating');
            var ratingText = document.getElementById('ratingText');
            var ratingLabels = ['', 'Very Poor', 'Poor', 'Average', 'Good', 'Excellent'];
            
            // Other form rating
            var otherCurrentRating = 0;
            var otherStars = document.querySelectorAll('#otherRatingStars span');
            var otherRatingInput = document.getElementById('otherRating');
            
            function fillStars(rating) {
                stars[0].style.setProperty('color', rating >= 1 ? '#ffc107' : '#ddd', 'important');
                stars[1].style.setProperty('color', rating >= 2 ? '#ffc107' : '#ddd', 'important');
                stars[2].style.setProperty('color', rating >= 3 ? '#ffc107' : '#ddd', 'important');
                stars[3].style.setProperty('color', rating >= 4 ? '#ffc107' : '#ddd', 'important');
                stars[4].style.setProperty('color', rating >= 5 ? '#ffc107' : '#ddd', 'important');
            }
            
            stars[0].onclick = function() { currentRating = 1; ratingInput.value = 1; fillStars(1); ratingText.textContent = ratingLabels[1]; };
            stars[1].onclick = function() { currentRating = 2; ratingInput.value = 2; fillStars(2); ratingText.textContent = ratingLabels[2]; };
            stars[2].onclick = function() { currentRating = 3; ratingInput.value = 3; fillStars(3); ratingText.textContent = ratingLabels[3]; };
            stars[3].onclick = function() { currentRating = 4; ratingInput.value = 4; fillStars(4); ratingText.textContent = ratingLabels[4]; };
            stars[4].onclick = function() { currentRating = 5; ratingInput.value = 5; fillStars(5); ratingText.textContent = ratingLabels[5]; };
            
            stars[0].onmouseenter = function() { fillStars(1); ratingText.textContent = ratingLabels[1]; };
            stars[1].onmouseenter = function() { fillStars(2); ratingText.textContent = ratingLabels[2]; };
            stars[2].onmouseenter = function() { fillStars(3); ratingText.textContent = ratingLabels[3]; };
            stars[3].onmouseenter = function() { fillStars(4); ratingText.textContent = ratingLabels[4]; };
            stars[4].onmouseenter = function() { fillStars(5); ratingText.textContent = ratingLabels[5]; };
            
            document.getElementById('ratingStars').onmouseleave = function() {
                fillStars(currentRating);
                ratingText.textContent = currentRating ? ratingLabels[currentRating] : '';
            };
            
            // Other form rating handlers
            function fillOtherStars(rating) {
                otherStars[0].style.setProperty('color', rating >= 1 ? '#ffc107' : '#ddd', 'important');
                otherStars[1].style.setProperty('color', rating >= 2 ? '#ffc107' : '#ddd', 'important');
                otherStars[2].style.setProperty('color', rating >= 3 ? '#ffc107' : '#ddd', 'important');
                otherStars[3].style.setProperty('color', rating >= 4 ? '#ffc107' : '#ddd', 'important');
                otherStars[4].style.setProperty('color', rating >= 5 ? '#ffc107' : '#ddd', 'important');
            }
            
            otherStars[0].onclick = function() { otherCurrentRating = 1; otherRatingInput.value = 1; fillOtherStars(1); };
            otherStars[1].onclick = function() { otherCurrentRating = 2; otherRatingInput.value = 2; fillOtherStars(2); };
            otherStars[2].onclick = function() { otherCurrentRating = 3; otherRatingInput.value = 3; fillOtherStars(3); };
            otherStars[3].onclick = function() { otherCurrentRating = 4; otherRatingInput.value = 4; fillOtherStars(4); };
            otherStars[4].onclick = function() { otherCurrentRating = 5; otherRatingInput.value = 5; fillOtherStars(5); };
            
            document.getElementById('otherRatingStars').onmouseleave = function() {
                fillOtherStars(otherCurrentRating);
            };
            
            // Character counters
            document.getElementById('subject').addEventListener('input', function() {
                document.getElementById('subjectCount').textContent = this.value.length;
            });
            
            document.getElementById('message').addEventListener('input', function() {
                document.getElementById('messageCount').textContent = this.value.length;
            });
            
            // Enhanced form validation and submission
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate rating
                if (!ratingInput.value) {
                    document.getElementById('ratingError').style.display = 'block';
                    document.getElementById('ratingStars').scrollIntoView({ behavior: 'smooth' });
                    return;
                }
                
                // Submit via AJAX
                const formData = new FormData(this);
                formData.append('action', 'submit_feedback');
                
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                
                fetch('../handlers/dashboard_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message + ' (ID: #' + String(data.feedback_id).padStart(4, '0') + ')');
                        document.getElementById('feedbackForm').reset();
                        fillStars(0);
                        currentRating = 0;
                        ratingText.textContent = '';
                        ratingInput.value = '';
                        document.getElementById('subjectCount').textContent = '0';
                        document.getElementById('messageCount').textContent = '0';
                        document.getElementById('ratingError').style.display = 'none';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Network error: ' + error.message);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Feedback';
                });
            });
            
            // Other form submission
            document.getElementById('otherForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!otherRatingInput.value) {
                    alert('Please select a rating');
                    return;
                }
                
                const formData = new FormData(this);
                formData.append('action', 'submit_feedback');
                
                fetch('../handlers/dashboard_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        this.reset();
                        fillOtherStars(0);
                        otherCurrentRating = 0;
                        otherRatingInput.value = '';
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Error: ' + error.message);
                });
            });
            
            // Reset form functionality
            document.getElementById('resetBtn').addEventListener('click', function() {
                if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                    document.getElementById('feedbackForm').reset();
                    fillStars(0);
                    currentRating = 0;
                    ratingText.textContent = '';
                    ratingInput.value = '';

                    document.getElementById('subjectCount').textContent = '0';
                    document.getElementById('messageCount').textContent = '0';
                    document.getElementById('ratingError').style.display = 'none';
                }
            });
        });
        
        function resetOtherForm() {
            if (confirm('Reset form?')) {
                document.getElementById('otherForm').reset();
                document.querySelectorAll('#otherRatingStars span').forEach(star => {
                    star.style.setProperty('color', '#ddd', 'important');
                });
                document.getElementById('otherRating').value = '';
            }
        }
        
        // Show alert function
        function showAlert(type, message) {
            const alertDiv = document.getElementById('feedbackAlert');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertDiv.style.display = 'block';
            alertDiv.scrollIntoView({ behavior: 'smooth' });
        }
        
        // View feedback details
        function viewFeedbackDetails(feedbackId) {
            // This would typically fetch details via AJAX
            alert('Feedback details view will be implemented. ID: ' + feedbackId);
        }
        
        // Delete feedback with confirmation
        function deleteFeedback(feedbackId, category) {
            if (confirm('⚠️ Are you sure you want to delete this feedback?\n\nThis action cannot be undone!')) {
                const formData = new FormData();
                formData.append('action', category === 'mess' ? 'delete_feedback' : 'delete_general_feedback');
                formData.append('feedback_id', feedbackId);
                
                fetch('../handlers/dashboard_actions.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    showAlert('danger', 'Error deleting feedback: ' + error.message);
                });
            }
        }
        
        // View feedback details
        function viewFeedbackDetails(feedbackId, category) {
            alert('Viewing ' + category + ' feedback details (ID: ' + feedbackId + ')\n\nDetailed view will be implemented soon.');
        }
        
        // Refresh feedback table
        function refreshFeedback() {
            location.reload();
        }

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
            document.getElementById('feedback').scrollIntoView({ behavior: 'smooth' });
            // Highlight the feedback form briefly
            const feedbackCard = document.getElementById('feedback');
            feedbackCard.style.border = '2px solid #007bff';
            setTimeout(() => {
                feedbackCard.style.border = '';
            }, 2000);
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