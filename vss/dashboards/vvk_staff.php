<?php
require_once '../config/database.php';
require_once '../includes/db_check.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SESSION['role'] != 'vvk_staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this VVK staff");
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_event'])) {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, date, venue, hostel_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['date'], $_POST['venue'], $hostel_id]);
        $_SESSION['success'] = "Event added successfully";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    
    if (isset($_POST['register_student'])) {
        // Check if student is already registered for this event
        $check = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE student_id = ? AND event_id = ?");
        $check->execute([$_POST['student_id'], $_POST['event_id']]);
        
        if ($check->fetchColumn() > 0) {
            $_SESSION['error'] = "Student is already registered for this event";
        } else {
            $stmt = $pdo->prepare("INSERT INTO event_registrations (student_id, event_id) VALUES (?, ?)");
            $stmt->execute([$_POST['student_id'], $_POST['event_id']]);
            $_SESSION['success'] = "Student registered for event successfully";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch data
$events = $pdo->prepare("SELECT * FROM events WHERE hostel_id = ? ORDER BY date DESC");
$events->execute([$hostel_id]);
$events_list = $events->fetchAll();

$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

$registrations = $pdo->prepare("
    SELECT er.*, e.title, s.name, s.grn 
    FROM event_registrations er 
    JOIN events e ON er.event_id = e.id 
    JOIN students s ON er.student_id = s.id 
    WHERE e.hostel_id = ? 
    ORDER BY er.registered_at DESC
");
$registrations->execute([$hostel_id]);
$registration_list = $registrations->fetchAll();

// Get VVK feedback for this hostel
try {
    $vvk_feedback_query = $pdo->prepare("
        SELECT vf.*, s.name as student_name, s.grn, e.title as event_title
        FROM vvk_feedback vf 
        JOIN students s ON vf.student_id = s.id 
        LEFT JOIN events e ON vf.event_id = e.id
        WHERE s.hostel_id = ?
        ORDER BY vf.created_at DESC
    ");
    $vvk_feedback_query->execute([$hostel_id]);
    $vvk_feedback_list = $vvk_feedback_query->fetchAll() ?: [];
} catch (Exception $e) {
    $vvk_feedback_list = [];
}

$page_title = 'VVK Staff Dashboard';
$dashboard_title = $hostel_info['name'] . ' - VVK Staff Dashboard';
$dashboard_subtitle = $hostel_info['location'] . ' | Student Development & Activities';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/modern-dashboard.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-white" href="#" style="font-size: 1.25rem;">
                <i class="fas fa-calendar-alt me-2"></i>VVK Staff Dashboard
            </a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview">
                            <i class="fas fa-tachometer-alt me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#events">
                            <i class="fas fa-calendar-plus me-2"></i>Events
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#registrations">
                            <i class="fas fa-users me-2"></i>Registrations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback">
                            <i class="fas fa-comments me-2"></i>Feedback
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i><?php echo $_SESSION['username']; ?>
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
                                <i class="fas fa-calendar-alt text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;"><?php echo $dashboard_title; ?></h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-map-marker-alt me-1"></i><?php echo $dashboard_subtitle; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <!-- Quick Stats -->
        <div id="overview" class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($events_list); ?></div>
                        <div class="stat-label">Total Events</div>
                        <div class="stat-meta">All events created</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($registration_list); ?></div>
                        <div class="stat-label">Registrations</div>
                        <div class="stat-meta">Student registrations</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($students_list); ?></div>
                        <div class="stat-label">Active Students</div>
                        <div class="stat-meta">In this hostel</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo count($vvk_feedback_list); ?></div>
                        <div class="stat-label">Feedback</div>
                        <div class="stat-meta">Student feedback</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Event Management -->
        <div id="events" class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus me-2"></i>Add New Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="mb-3">
                                <label class="form-label">Event Title</label>
                                <input type="text" class="form-input" name="title" placeholder="Enter event title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-input" name="description" placeholder="Event description" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Event Date</label>
                                <input type="date" class="form-input" name="date" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Venue</label>
                                <input type="text" class="form-input" name="venue" placeholder="Event venue" required>
                            </div>
                            <button type="submit" name="add_event" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Event
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-user-plus me-2"></i>Register Student for Event</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="modern-form">
                            <div class="mb-3">
                                <label class="form-label">Select Student</label>
                                <select class="form-input" name="student_id" required>
                                    <option value="">Choose student...</option>
                                    <?php foreach($students_list as $student): ?>
                                        <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Select Event</label>
                                <select class="form-input" name="event_id" required>
                                    <option value="">Choose event...</option>
                                    <?php foreach($events_list as $event): ?>
                                        <option value="<?php echo $event['id']; ?>"><?php echo $event['title']; ?> - <?php echo $event['date']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="register_student" class="btn btn-success">
                                <i class="fas fa-user-plus me-2"></i>Register Student
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Events Management Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-alt me-2"></i>Events Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Date</th>
                                <th>Venue</th>
                                <th>Registrations</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($events_list as $event): ?>
                            <tr>
                                <td><?php echo $event['id']; ?></td>
                                <td><?php echo $event['title']; ?></td>
                                <td><?php echo substr($event['description'], 0, 50) . '...'; ?></td>
                                <td><?php echo $event['date']; ?></td>
                                <td><?php echo $event['venue']; ?></td>
                                <td>
                                    <?php 
                                    $count = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
                                    $count->execute([$event['id']]);
                                    echo $count->fetchColumn();
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary">Edit</button>
                                    <button class="btn btn-sm btn-info">View Registrations</button>
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Event Registrations -->
        <div id="registrations" class="row mb-4">
            <div class="col-12">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-users me-2"></i>Event Registrations</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table modern-table">
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Student Name</th>
                                <th>GRN</th>
                                <th>Registration Date</th>
                                <th>Attended</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($registration_list as $registration): ?>
                            <tr>
                                <td><?php echo $registration['title']; ?></td>
                                <td><?php echo $registration['name']; ?></td>
                                <td><?php echo $registration['grn']; ?></td>
                                <td><?php echo date('Y-m-d', strtotime($registration['registered_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $registration['attended'] ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo $registration['attended'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success">Mark Attended</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Row -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5><i class="fas fa-calendar-check me-2"></i>Upcoming Events</h5>
                    </div>
                    <div class="card-body">
                    <?php 
                    $upcoming = $pdo->prepare("SELECT * FROM events WHERE hostel_id = ? AND date >= CURDATE() ORDER BY date ASC LIMIT 5");
                    $upcoming->execute([$hostel_id]);
                    $upcoming_events = $upcoming->fetchAll();
                    ?>
                    <?php foreach($upcoming_events as $event): ?>
                        <div class="mb-3 p-2 border rounded">
                            <h6><?php echo $event['title']; ?></h6>
                            <p class="mb-1"><?php echo $event['description']; ?></p>
                            <small class="text-muted">Date: <?php echo $event['date']; ?> | Venue: <?php echo $event['venue']; ?></small>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="modern-card">
                    <div class="card-header">
                        <h5 id="feedback"><i class="fas fa-comments me-2"></i>Student Feedback</h5>
                    </div>
                    <div class="card-body">
                    <?php if (empty($vvk_feedback_list)): ?>
                        <p class="text-muted">No feedback received yet</p>
                    <?php else: ?>
                        <?php foreach($vvk_feedback_list as $feedback): ?>
                        <div class="mb-3 p-2 border rounded">
                            <strong><?php echo htmlspecialchars($feedback['subject']); ?></strong>
                            <?php if($feedback['event_title']): ?>
                                <span class="badge bg-info ms-2"><?php echo htmlspecialchars($feedback['event_title']); ?></span>
                            <?php endif; ?>
                            <p class="mb-1">"<?php echo htmlspecialchars($feedback['message']); ?>"</p>
                            <small class="text-muted">- <?php echo htmlspecialchars($feedback['student_name']); ?> (<?php echo htmlspecialchars($feedback['grn']); ?>)</small>
                            <div class="text-warning">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= $feedback['rating'] ? '★' : '☆'; ?>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted d-block mt-1"><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    alert.classList.remove('show');
                }
            });
        }, 5000);
    </script>
</body>
</html>