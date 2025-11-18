<?php
session_start();
require_once 'config/database.php';
require_once 'includes/EmailNotification.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'rector', 'student_head', 'mess_head', 'library_head'])) {
    header('Location: auth/login.php');
    exit();
}

$emailNotification = new EmailNotification();
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $recipient_role = $_POST['recipient_role'];
    $hostel_id = $_POST['hostel_id'];
    $subject = $_POST['subject'];
    $email_message = $_POST['message'];
    
    $sent_count = $emailNotification->sendBulkNotification($recipient_role, $hostel_id, $subject, $email_message);
    $message = "Notification sent to {$sent_count} recipients successfully!";
}

// Get hostels for dropdown
$hostels_stmt = $pdo->query("SELECT id, name FROM hostels ORDER BY name");
$hostels = $hostels_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notification - VSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Send Email Notification</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="recipient_role" class="form-label">Send To</label>
                                <select class="form-select" id="recipient_role" name="recipient_role" required>
                                    <option value="">Select Recipients</option>
                                    <option value="student">All Students</option>
                                    <option value="mess_head">Mess Head</option>
                                    <option value="library_head">Library Head</option>
                                    <option value="health_staff">Health Staff</option>
                                    <option value="placement_staff">Placement Staff</option>
                                    <option value="scholarship_staff">Scholarship Staff</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="hostel_id" class="form-label">Hostel</label>
                                <select class="form-select" id="hostel_id" name="hostel_id" required>
                                    <option value="">Select Hostel</option>
                                    <?php foreach ($hostels as $hostel): ?>
                                        <option value="<?php echo $hostel['id']; ?>"><?php echo htmlspecialchars($hostel['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Send Notification</button>
                            <a href="dashboards/<?php echo $_SESSION['role']; ?>.php" class="btn btn-secondary">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>