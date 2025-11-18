<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NotificationTriggers.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: auth/login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get student ID
        $student_query = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $student_query->execute([$_SESSION['user_id']]);
        $student = $student_query->fetch();
        
        if ($student) {
            // Insert complaint
            $stmt = $pdo->prepare("INSERT INTO student_complaints (student_id, category, subject, description, priority) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $student['id'],
                $_POST['category'],
                $_POST['subject'],
                $_POST['description'],
                $_POST['priority']
            ]);
            
            $complaint_id = $pdo->lastInsertId();
            
            // Trigger email notification
            NotificationTriggers::onComplaintSubmitted($complaint_id);
            
            $message = "Complaint submitted successfully! You will receive an email confirmation.";
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Submit Complaint</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Mess">Mess</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Internet">Internet</option>
                                    <option value="Cleanliness">Cleanliness</option>
                                    <option value="Security">Security</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Submit Complaint</button>
                            <a href="dashboards/student.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>