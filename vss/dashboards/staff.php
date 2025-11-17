<?php
require_once '../includes/header.php';
require_once '../config/database.php';
require_once '../includes/db_check.php';

if ($_SESSION['role'] != 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Handle form submissions
if ($_POST) {
    if (isset($_POST['mark_meal'])) {
        $stmt = $pdo->prepare("INSERT INTO mess_attendance (student_id, date, meal_type, taken) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE taken = ?");
        $stmt->execute([$_POST['student_id'], date('Y-m-d'), $_POST['meal_type'], 1, 1]);
        $success = "Meal attendance marked successfully";
    }
    
    if (isset($_POST['add_book'])) {
        $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, stock, hostel_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['author'], $_POST['isbn'], $_POST['stock'], $hostel_id]);
        $success = "Book added successfully";
    }
    
    if (isset($_POST['issue_book'])) {
        $stmt = $pdo->prepare("INSERT INTO book_issues (student_id, book_id, issue_date) VALUES (?, ?, CURDATE())");
        $stmt->execute([$_POST['student_id'], $_POST['book_id']]);
        
        $stmt = $pdo->prepare("UPDATE books SET stock = stock - 1 WHERE id = ?");
        $stmt->execute([$_POST['book_id']]);
        $success = "Book issued successfully";
    }
    
    if (isset($_POST['add_event'])) {
        $stmt = $pdo->prepare("INSERT INTO events (title, description, date, venue, hostel_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['title'], $_POST['description'], $_POST['date'], $_POST['venue'], $hostel_id]);
        $success = "Event added successfully";
    }
}

// Fetch data
$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

$books = $pdo->prepare("SELECT * FROM books WHERE hostel_id = ?");
$books->execute([$hostel_id]);
$books_list = $books->fetchAll();

$events = $pdo->prepare("SELECT * FROM events WHERE hostel_id = ? ORDER BY date DESC LIMIT 5");
$events->execute([$hostel_id]);
$events_list = $events->fetchAll();

$meal_attendance = $pdo->prepare("
    SELECT ma.*, s.name, s.grn 
    FROM mess_attendance ma 
    JOIN students s ON ma.student_id = s.id 
    WHERE s.hostel_id = ? AND ma.date = CURDATE()
");
$meal_attendance->execute([$hostel_id]);
$meal_list = $meal_attendance->fetchAll();
?>

<div class="container-fluid mt-4">
    <h2 class="dashboard-title"><i class="fas fa-user-tie me-2"></i>Staff Dashboard</h2>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success alert-custom"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card primary">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-white text-primary me-3">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Total Students</h5>
                        <h3 class="mb-0"><?php echo count($students_list); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-white text-success me-3">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Today's Meals</h5>
                        <h3 class="mb-0"><?php echo count($meal_list); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-white text-warning me-3">
                        <i class="fas fa-book"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Total Books</h5>
                        <h3 class="mb-0"><?php echo count($books_list); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="d-flex align-items-center">
                    <div class="icon-box bg-white text-info me-3">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div>
                        <h5 class="mb-1">Events</h5>
                        <h3 class="mb-0"><?php echo count($events_list); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs for different functions -->
    <div class="row mt-4">
        <div class="col-12">
            <ul class="nav nav-tabs" id="staffTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="mess-tab" data-bs-toggle="tab" data-bs-target="#mess" type="button">
                        <i class="fas fa-utensils me-1"></i>Mess Management
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="library-tab" data-bs-toggle="tab" data-bs-target="#library" type="button">
                        <i class="fas fa-book me-1"></i>Library Management
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="events-tab" data-bs-toggle="tab" data-bs-target="#events" type="button">
                        <i class="fas fa-calendar me-1"></i>Events Management
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="staffTabsContent">
                <!-- Mess Management Tab -->
                <div class="tab-pane fade show active" id="mess" role="tabpanel">
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Mark Meal Attendance</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <select class="form-control form-control-custom" name="student_id" required>
                                                <option value="">Select Student</option>
                                                <?php foreach($students_list as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <select class="form-control form-control-custom" name="meal_type" required>
                                                <option value="">Select Meal Type</option>
                                                <option value="breakfast">Breakfast</option>
                                                <option value="lunch">Lunch</option>
                                                <option value="dinner">Dinner</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="mark_meal" class="btn btn-primary btn-custom">Mark Meal</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Today's Meal Attendance</h5>
                                </div>
                                <div class="card-body">
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <?php foreach($meal_list as $meal): ?>
                                            <div class="mb-2 p-2 border rounded">
                                                <strong><?php echo $meal['name']; ?></strong> (<?php echo $meal['grn']; ?>)
                                                <br><small><?php echo ucfirst($meal['meal_type']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Library Management Tab -->
                <div class="tab-pane fade" id="library" role="tabpanel">
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Book</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <input type="text" class="form-control form-control-custom" name="title" placeholder="Book Title" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control form-control-custom" name="author" placeholder="Author" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control form-control-custom" name="isbn" placeholder="ISBN">
                                        </div>
                                        <div class="mb-3">
                                            <input type="number" class="form-control form-control-custom" name="stock" placeholder="Stock Quantity" required>
                                        </div>
                                        <button type="submit" name="add_book" class="btn btn-primary btn-custom">Add Book</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-book-open me-2"></i>Issue Book</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <select class="form-control form-control-custom" name="student_id" required>
                                                <option value="">Select Student</option>
                                                <?php foreach($students_list as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <select class="form-control form-control-custom" name="book_id" required>
                                                <option value="">Select Book</option>
                                                <?php foreach($books_list as $book): ?>
                                                    <?php if($book['stock'] > 0): ?>
                                                        <option value="<?php echo $book['id']; ?>"><?php echo $book['title']; ?> (Stock: <?php echo $book['stock']; ?>)</option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" name="issue_book" class="btn btn-success btn-custom">Issue Book</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Events Management Tab -->
                <div class="tab-pane fade" id="events" role="tabpanel">
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Event</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <input type="text" class="form-control form-control-custom" name="title" placeholder="Event Title" required>
                                        </div>
                                        <div class="mb-3">
                                            <textarea class="form-control form-control-custom" name="description" placeholder="Event Description" rows="3"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <input type="date" class="form-control form-control-custom" name="date" required>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control form-control-custom" name="venue" placeholder="Venue" required>
                                        </div>
                                        <button type="submit" name="add_event" class="btn btn-primary btn-custom">Add Event</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card dashboard-card">
                                <div class="card-header card-header-custom">
                                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Events</h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach($events_list as $event): ?>
                                        <div class="mb-3 p-2 border rounded">
                                            <h6><?php echo $event['title']; ?></h6>
                                            <p class="mb-1"><?php echo $event['description']; ?></p>
                                            <small class="text-muted">Date: <?php echo $event['date']; ?> | Venue: <?php echo $event['venue']; ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>