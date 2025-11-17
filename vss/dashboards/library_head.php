<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'library_head') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this library head");
}

// Handle form submissions
if ($_POST) {
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
    
    if (isset($_POST['return_book'])) {
        $stmt = $pdo->prepare("UPDATE book_issues SET return_date = CURDATE() WHERE id = ?");
        $stmt->execute([$_POST['issue_id']]);
        
        $stmt = $pdo->prepare("UPDATE books SET stock = stock + 1 WHERE id = (SELECT book_id FROM book_issues WHERE id = ?)");
        $stmt->execute([$_POST['issue_id']]);
        $success = "Book returned successfully";
    }
    
    if (isset($_POST['send_reminder'])) {
        try {
            // Create table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS library_reminders (
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
            )");
            
            $book_issue_id = $_POST['book_issue_id'];
            $student_id = $_POST['student_id'];
            $book_title = $_POST['book_title'];
            
            $days = floor((strtotime('now') - strtotime($_POST['issue_date'])) / (60 * 60 * 24));
            $message = "Reminder: Please return the book '{$book_title}' issued {$days} days ago. Contact library for assistance.";
            
            $stmt = $pdo->prepare("INSERT INTO library_reminders (student_id, book_issue_id, message, sent_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$student_id, $book_issue_id, $message, $_SESSION['user_id']]);
            $success = "Reminder sent successfully";
        } catch (Exception $e) {
            $error = "Error sending reminder: " . $e->getMessage();
        }
    }
}

// Fetch data
$books = $pdo->prepare("SELECT * FROM books WHERE hostel_id = ?");
$books->execute([$hostel_id]);
$books_list = $books->fetchAll();

$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

// Get currently issued books (not returned)
$issued_books = $pdo->prepare("
    SELECT bi.*, b.title, b.author, s.name, s.grn 
    FROM book_issues bi 
    JOIN books b ON bi.book_id = b.id 
    JOIN students s ON bi.student_id = s.id 
    WHERE b.hostel_id = ? AND bi.return_date IS NULL
");
$issued_books->execute([$hostel_id]);
$issued_list = $issued_books->fetchAll();

// Get all book transactions (issued and returned)
$all_books = $pdo->prepare("
    SELECT bi.*, b.title, b.author, s.name, s.grn 
    FROM book_issues bi 
    JOIN books b ON bi.book_id = b.id 
    JOIN students s ON bi.student_id = s.id 
    WHERE b.hostel_id = ? 
    ORDER BY bi.issue_date DESC
");
$all_books->execute([$hostel_id]);
$all_books_list = $all_books->fetchAll();

// Get library feedback from students
$library_feedback = $pdo->prepare("
    SELECT gf.*, s.name, s.grn 
    FROM general_feedback gf 
    JOIN students st ON gf.student_id = st.id 
    JOIN students s ON gf.student_id = s.id 
    WHERE gf.feedback_category = 'library' AND st.hostel_id = ? 
    ORDER BY gf.created_at DESC
");
$library_feedback->execute([$hostel_id]);
$feedback_list = $library_feedback->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Head Dashboard - VSS</title>
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
                <i class="fas fa-book me-2"></i>Library Management Center
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: rgba(255,255,255,0.3);">
                <span class="navbar-toggler-icon" style="background-image: url('data:image/svg+xml,%3csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 30 30%27%3e%3cpath stroke=%27rgba%28255, 255, 255, 0.8%29%27 stroke-linecap=%27round%27 stroke-miterlimit=%2710%27 stroke-width=%272%27 d=%27M4 7h22M4 15h22M4 23h22%27/%3e%3c/svg%3e');"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#overview" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-tachometer-alt me-2"></i>Library Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#books" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-book me-2"></i>Book Catalog
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#transactions" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-exchange-alt me-2"></i>Issue & Return
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#history" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-history me-2"></i>Transaction History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#feedback" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-comment me-2"></i>Library Feedback
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white fw-semibold" href="#reports" style="transition: all 0.3s ease; padding: 0.75rem 1rem; border-radius: 8px;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-chart-bar me-2"></i>Reports
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white fw-semibold d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" style="padding: 0.75rem 1rem; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                            <i class="fas fa-user-circle me-2" style="font-size: 1.2rem;"></i>
                            <span><?php echo $_SESSION['username']; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.15); border-radius: 12px; padding: 0.5rem 0;">
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-user-edit me-2 text-primary"></i>My Profile</a></li>
                            <li><a class="dropdown-item py-2 px-3" href="#" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#f8f9fa'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-cog me-2 text-secondary"></i>Library Settings</a></li>
                            <li><hr class="dropdown-divider mx-2" style="margin: 0.5rem 0;"></li>
                            <li><a class="dropdown-item py-2 px-3 text-danger" href="../auth/login.php?logout=1" style="border-radius: 8px; margin: 0 0.5rem; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#fff5f5'" onmouseout="this.style.backgroundColor='transparent'"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
                                <i class="fas fa-book text-white" style="font-size: 24px;"></i>
                            </div>
                            <div class="text-start">
                                <h2 class="mb-1" style="color: var(--text-primary) !important; font-weight: 700;"><?php echo $hostel_info['name']; ?> - Library</h2>
                                <p class="mb-0" style="color: var(--text-secondary) !important;"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Manage books and library operations</p>
                            </div>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-primary mb-0"><?php echo count($books_list); ?></h4>
                                    <small class="text-muted">Total Books</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-success mb-0"><?php echo count($issued_list); ?></h4>
                                    <small class="text-muted">Currently Issued</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border-end">
                                    <h4 class="text-info mb-0"><?php echo array_sum(array_column($books_list, 'stock')); ?></h4>
                                    <small class="text-muted">Available Stock</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning mb-0"><?php echo count($students_list); ?></h4>
                                <small class="text-muted">Registered Students</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($books_list); ?></div>
                <div class="stat-label">Total Books</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($issued_list); ?></div>
                <div class="stat-label">Currently Issued</div>
                <div class="stat-meta">
                    <?php 
                    $overdue = 0;
                    foreach($issued_list as $issue) {
                        $days = floor((strtotime('now') - strtotime($issue['issue_date'])) / (60 * 60 * 24));
                        if($days > 14) $overdue++;
                    }
                    echo $overdue > 0 ? $overdue . ' overdue' : 'All on time';
                    ?>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-warehouse"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo array_sum(array_column($books_list, 'stock')); ?></div>
                <div class="stat-label">Available Books</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($students_list); ?></div>
                <div class="stat-label">Registered Students</div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Add New Book</h3>
            </div>
            <div class="card-content">
                <form method="POST" class="modern-form">
                    <div class="form-group">
                        <label class="form-label">Book Title</label>
                        <input type="text" class="form-input" name="title" placeholder="Enter book title" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Author</label>
                        <input type="text" class="form-input" name="author" placeholder="Enter author name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">ISBN</label>
                        <input type="text" class="form-input" name="isbn" placeholder="Enter ISBN">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" class="form-input" name="stock" placeholder="Enter quantity" required>
                    </div>
                    <button type="submit" name="add_book" class="btn btn-primary">Add Book</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book-open"></i> Issue Book</h3>
            </div>
            <div class="card-content">
                <form method="POST" class="modern-form">
                    <div class="form-group">
                        <label class="form-label">Select Student</label>
                        <select class="form-input" name="student_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach($students_list as $student): ?>
                                <option value="<?php echo $student['id']; ?>"><?php echo $student['name']; ?> (<?php echo $student['grn']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Select Book</label>
                        <select class="form-input" name="book_id" required>
                            <option value="">Choose a book...</option>
                            <?php foreach($books_list as $book): ?>
                                <?php if($book['stock'] > 0): ?>
                                    <option value="<?php echo $book['id']; ?>"><?php echo $book['title']; ?> by <?php echo $book['author']; ?> (Stock: <?php echo $book['stock']; ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="issue_book" class="btn btn-success">
                        <i class="fas fa-arrow-right"></i> Issue Book
                    </button>
                </form>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-undo"></i> Return Book</h3>
            </div>
            <div class="card-content">
                <form method="POST" class="modern-form">
                    <div class="form-group">
                        <label class="form-label">Select Issued Book</label>
                        <select class="form-input" name="issue_id" required>
                            <option value="">Choose issued book...</option>
                            <?php foreach($issued_list as $issue): ?>
                                <?php 
                                $days = floor((strtotime('now') - strtotime($issue['issue_date'])) / (60 * 60 * 24));
                                $status = $days > 14 ? ' (OVERDUE)' : ($days > 7 ? ' (DUE SOON)' : '');
                                ?>
                                <option value="<?php echo $issue['id']; ?>"><?php echo $issue['title']; ?> - <?php echo $issue['name']; ?> (<?php echo $days; ?> days)<?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Return Status</label>
                        <div style="background: rgba(255, 255, 255, 0.1); padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
                            <strong>Total Issued Books:</strong> <?php echo count($issued_list); ?><br>
                            <strong>Overdue Books:</strong> 
                            <?php 
                            $overdue = 0;
                            foreach($issued_list as $issue) {
                                $days = floor((strtotime('now') - strtotime($issue['issue_date'])) / (60 * 60 * 24));
                                if($days > 14) $overdue++;
                            }
                            echo $overdue;
                            ?>
                        </div>
                    </div>
                    <button type="submit" name="return_book" class="btn btn-warning">
                        <i class="fas fa-check"></i> Return Book
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card full-width">
        <div class="card-header">
            <h3 class="card-title">Books Master</h3>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($books_list as $book): ?>
                        <tr>
                            <td><span class="table-badge"><?php echo $book['id']; ?></span></td>
                            <td><?php echo $book['title']; ?></td>
                            <td><?php echo $book['author']; ?></td>
                            <td><?php echo $book['isbn']; ?></td>
                            <td>
                                <?php if($book['stock'] > 0): ?>
                                    <span class="status-badge success"><?php echo $book['stock']; ?> available</span>
                                <?php else: ?>
                                    <span class="status-badge danger">Out of stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline">Edit</button>
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Library Feedback Section -->
    <div id="feedback" class="dashboard-card full-width">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-comment"></i> Library Feedback from Students</h3>
        </div>
        <div class="card-content">
            <div class="modern-table-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #e0e0e0; border-radius: 8px;">
                <table class="modern-table">
                    <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                        <tr>
                            <th>ID</th>
                            <th>Student</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Rating</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($feedback_list)): ?>
                            <tr><td colspan="10" class="text-center text-muted">No library feedback received yet</td></tr>
                        <?php else: ?>
                            <?php foreach($feedback_list as $feedback): ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($feedback['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($feedback['name']); ?></strong><br>
                                    <small class="text-muted"><?php echo $feedback['grn']; ?></small>
                                </td>
                                <td><span class="status-badge <?php echo $feedback['feedback_type'] == 'complaint' ? 'danger' : ($feedback['feedback_type'] == 'suggestion' ? 'warning' : 'success'); ?>"><?php echo ucfirst($feedback['feedback_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($feedback['subject']); ?></td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars(substr($feedback['message'], 0, 100)); ?>
                                        <?php if(strlen($feedback['message']) > 100): ?>..<?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <span style="color: <?php echo $i <= $feedback['rating'] ? '#ffc107' : '#ddd'; ?>; font-size: 14px;">★</span>
                                        <?php endfor; ?>
                                        <span class="ms-1 small"><?php echo $feedback['rating']; ?>/5</span>
                                    </div>
                                </td>
                                <td><span class="badge bg-<?php echo $feedback['priority'] == 'urgent' ? 'danger' : ($feedback['priority'] == 'high' ? 'warning' : ($feedback['priority'] == 'medium' ? 'info' : 'secondary')); ?>"><?php echo ucfirst($feedback['priority']); ?></span></td>
                                <td><span class="status-badge <?php echo $feedback['status'] == 'pending' ? 'warning' : ($feedback['status'] == 'resolved' ? 'success' : 'info'); ?>"><?php echo ucfirst($feedback['status']); ?></span></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($feedback['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn btn-sm btn-outline-primary" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'reviewed')" title="Mark as Reviewed">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="updateFeedbackStatus(<?php echo $feedback['id']; ?>, 'resolved')" title="Mark as Resolved">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteFeedback(<?php echo $feedback['id']; ?>)" title="Delete Feedback">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card full-width">
        <div class="card-header">
            <h3 class="card-title">Book Issue & Return History</h3>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Student Name</th>
                            <th>GRN</th>
                            <th>Issue Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_books_list as $book): ?>
                        <tr>
                            <td><?php echo $book['title']; ?></td>
                            <td><?php echo $book['author']; ?></td>
                            <td><?php echo $book['name']; ?></td>
                            <td><span class="table-badge"><?php echo $book['grn']; ?></span></td>
                            <td><?php echo date('d-m-Y', strtotime($book['issue_date'])); ?></td>
                            <td>
                                <?php if($book['return_date']): ?>
                                    <span style="color: #16a34a; font-weight: 600;"><?php echo date('d-m-Y', strtotime($book['return_date'])); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc2626; font-weight: 600;">Not Returned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($book['return_date']): ?>
                                    <span class="status-badge success">Returned</span>
                                <?php else: ?>
                                    <?php 
                                    $days = floor((strtotime('now') - strtotime($book['issue_date'])) / (60 * 60 * 24));
                                    if($days > 14): ?>
                                        <span class="status-badge danger">Overdue (<?php echo $days; ?> days)</span>
                                    <?php elseif($days > 7): ?>
                                        <span class="status-badge warning">Due Soon (<?php echo $days; ?> days)</span>
                                    <?php else: ?>
                                        <span class="status-badge success">Active (<?php echo $days; ?> days)</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if(!$book['return_date']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="book_issue_id" value="<?php echo $book['id']; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo $book['student_id']; ?>">
                                        <input type="hidden" name="book_title" value="<?php echo $book['title']; ?>">
                                        <input type="hidden" name="issue_date" value="<?php echo $book['issue_date']; ?>">
                                        <button type="submit" name="send_reminder" class="btn btn-sm btn-outline" 
                                                onclick="return confirm('Send reminder to <?php echo $book['name']; ?> for book <?php echo $book['title']; ?>?')">
                                            <i class="fas fa-bell"></i> Send Reminder
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: #16a34a; font-size: 0.8rem;">✓ Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateFeedbackStatus(feedbackId, status) {
    if (confirm('Update feedback status to ' + status + '?')) {
        const formData = new FormData();
        formData.append('action', 'update_general_feedback_status');
        formData.append('feedback_id', feedbackId);
        formData.append('status', status);
        
        fetch('../handlers/dashboard_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update status badge in the row
                const row = document.querySelector(`button[onclick*="${feedbackId}"]`).closest('tr');
                const statusCell = row.cells[7]; // Status column
                const statusClass = status === 'pending' ? 'warning' : (status === 'resolved' ? 'success' : 'info');
                statusCell.innerHTML = `<span class="status-badge ${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                
                // Show success message
                showNotification(data.message, 'success');
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Network error: ' + error.message, 'error');
        });
    }
}

function deleteFeedback(feedbackId) {
    if (confirm('⚠️ Are you sure you want to delete this feedback?\n\nThis action cannot be undone!')) {
        const formData = new FormData();
        formData.append('action', 'delete_general_feedback');
        formData.append('feedback_id', feedbackId);
        
        fetch('../handlers/dashboard_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the row from table
                const row = document.querySelector(`button[onclick*="deleteFeedback(${feedbackId})"]`).closest('tr');
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => row.remove(), 300);
                
                showNotification(data.message, 'success');
            } else {
                showNotification('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('Network error: ' + error.message, 'error');
        });
    }
}

function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
}
</script>
</body>
</html>