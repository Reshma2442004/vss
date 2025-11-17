<?php
require_once '../includes/header.php';
require_once '../config/database.php';

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
        $success = "Event added successfully";
    }
    
    if (isset($_POST['register_student'])) {
        $stmt = $pdo->prepare("INSERT INTO event_registrations (student_id, event_id) VALUES (?, ?)");
        $stmt->execute([$_POST['student_id'], $_POST['event_id']]);
        $success = "Student registered for event successfully";
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
?>

<div class="container-fluid mt-4">
    <h2><?php echo $hostel_info['name']; ?> - VVK Staff Dashboard</h2>
    <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Student Development & Activities</p>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Events</h5>
                    <h3><?php echo count($events_list); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Registrations</h5>
                    <h3><?php echo count($registration_list); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Active Students</h5>
                    <h3><?php echo count($students_list); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>This Month Events</h5>
                    <h3>8</h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Add New Event</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="title" placeholder="Event Title" required>
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" name="description" placeholder="Event Description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <input type="date" class="form-control" name="date" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" name="venue" placeholder="Venue" required>
                        </div>
                        <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Register Student for Event</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <select class="form-control" name="student_id" required>
                                <option value="">Select Student</option>
                                <?php foreach($students_list as $student): ?>
                                    <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <select class="form-control" name="event_id" required>
                                <option value="">Select Event</option>
                                <?php foreach($events_list as $event): ?>
                                    <option value="<?php echo $event['id']; ?>"><?php echo $event['title']; ?> - <?php echo $event['date']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="register_student" class="btn btn-success">Register Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Events Management</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
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
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Event Registrations</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
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
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Upcoming Events</h5>
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
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Student Feedback</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded">
                        <strong>Cultural Night Event</strong>
                        <p class="mb-1">"Great organization and wonderful performances!"</p>
                        <small class="text-muted">- John Doe (GRN001)</small>
                        <div class="text-warning">★★★★★</div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <strong>Technical Workshop</strong>
                        <p class="mb-1">"Very informative and well-structured content."</p>
                        <small class="text-muted">- Jane Smith (GRN002)</small>
                        <div class="text-warning">★★★★☆</div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <strong>Sports Tournament</strong>
                        <p class="mb-1">"Excellent facilities and fair competition."</p>
                        <small class="text-muted">- Mike Johnson (GRN003)</small>
                        <div class="text-warning">★★★★★</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>