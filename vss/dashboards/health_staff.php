<?php
require_once '../includes/modern-header.php';
require_once '../config/database.php';

if ($_SESSION['role'] != 'health_staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this health staff");
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['add_health_record'])) {
        $stmt = $pdo->prepare("INSERT INTO health_records (student_id, medical_history, allergies, insurance_no, vaccination_status) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE medical_history = ?, allergies = ?, insurance_no = ?, vaccination_status = ?");
        $stmt->execute([$_POST['student_id'], $_POST['medical_history'], $_POST['allergies'], $_POST['insurance_no'], $_POST['vaccination_status'], $_POST['medical_history'], $_POST['allergies'], $_POST['insurance_no'], $_POST['vaccination_status']]);
        $success = "Health record updated successfully";
    }
}

// Fetch students in this hostel
$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

// Fetch health records
$health_records = $pdo->prepare("
    SELECT hr.*, s.name, s.grn 
    FROM health_records hr 
    JOIN students s ON hr.student_id = s.id 
    WHERE s.hostel_id = ?
");
$health_records->execute([$hostel_id]);
$health_list = $health_records->fetchAll();
?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Health Centre Management</h1>
        <p class="dashboard-subtitle">Monitor student health records and medical visits</p>
    </div>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($students_list); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-file-medical"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo count($health_list); ?></div>
                <div class="stat-label">Health Records</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-syringe"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">5</div>
                <div class="stat-label">Vaccination Due</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number">12</div>
                <div class="stat-label">Health Visits</div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Add/Update Health Record</h3>
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
                        <label class="form-label">Medical History</label>
                        <textarea class="form-input" name="medical_history" placeholder="Enter medical history" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Allergies</label>
                        <input type="text" class="form-input" name="allergies" placeholder="Enter known allergies">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Insurance Number</label>
                        <input type="text" class="form-input" name="insurance_no" placeholder="Enter insurance number">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vaccination Status</label>
                        <textarea class="form-input" name="vaccination_status" placeholder="Enter vaccination details" rows="2"></textarea>
                    </div>
                    <button type="submit" name="add_health_record" class="btn btn-primary">Save Health Record</button>
                </form>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Vaccination Alerts</h3>
            </div>
            <div class="card-content">
                <div class="alert-card warning">
                    <div class="alert-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>COVID-19 Booster Due</strong>
                    </div>
                    <ul class="alert-list">
                        <li>John Doe (GRN001) - Due: 15/12/2024</li>
                        <li>Jane Smith (GRN002) - Due: 18/12/2024</li>
                        <li>Mike Johnson (GRN003) - Due: 20/12/2024</li>
                    </ul>
                </div>
                <div class="alert-card info">
                    <div class="alert-header">
                        <i class="fas fa-info-circle"></i>
                        <strong>Annual Health Checkup Due</strong>
                    </div>
                    <ul class="alert-list">
                        <li>Sarah Wilson (GRN004) - Due: 25/12/2024</li>
                        <li>Tom Brown (GRN005) - Due: 28/12/2024</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card full-width">
        <div class="card-header">
            <h3 class="card-title">Student Health Records</h3>
        </div>
        <div class="card-content">
            <div class="modern-table-container">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>GRN</th>
                            <th>Student Name</th>
                            <th>Medical History</th>
                            <th>Allergies</th>
                            <th>Insurance No</th>
                            <th>Vaccination Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($health_list as $record): ?>
                        <tr>
                            <td><span class="table-badge"><?php echo $record['grn']; ?></span></td>
                            <td><?php echo $record['name']; ?></td>
                            <td><?php echo substr($record['medical_history'], 0, 50) . '...'; ?></td>
                            <td>
                                <?php if($record['allergies']): ?>
                                    <span class="status-badge warning"><?php echo $record['allergies']; ?></span>
                                <?php else: ?>
                                    <span class="status-badge success">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $record['insurance_no']; ?></td>
                            <td><?php echo substr($record['vaccination_status'], 0, 30) . '...'; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline">View Full</button>
                                <button class="btn btn-sm btn-primary">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Recent Health Visits</h3>
            </div>
            <div class="card-content">
                <div class="visit-item">
                    <div class="visit-header">
                        <strong>John Doe (GRN001)</strong>
                        <span class="visit-date">10/12/2024 | 10:30 AM</span>
                    </div>
                    <p class="visit-complaint">Complaint: Fever and headache</p>
                </div>
                <div class="visit-item">
                    <div class="visit-header">
                        <strong>Jane Smith (GRN002)</strong>
                        <span class="visit-date">09/12/2024 | 2:15 PM</span>
                    </div>
                    <p class="visit-complaint">Complaint: Stomach pain</p>
                </div>
                <div class="visit-item">
                    <div class="visit-header">
                        <strong>Mike Johnson (GRN003)</strong>
                        <span class="visit-date">08/12/2024 | 11:45 AM</span>
                    </div>
                    <p class="visit-complaint">Complaint: Skin allergy</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Health Statistics</h3>
            </div>
            <div class="card-content">
                <div class="stats-section">
                    <h4 class="stats-title">Common Complaints This Month</h4>
                    <div class="progress-item">
                        <div class="progress-label">Fever</div>
                        <div class="progress-bar">
                            <div class="progress-fill danger" style="width: 40%"></div>
                        </div>
                        <span class="progress-value">40%</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-label">Headache</div>
                        <div class="progress-bar">
                            <div class="progress-fill warning" style="width: 25%"></div>
                        </div>
                        <span class="progress-value">25%</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-label">Stomach Issues</div>
                        <div class="progress-bar">
                            <div class="progress-fill info" style="width: 20%"></div>
                        </div>
                        <span class="progress-value">20%</span>
                    </div>
                    <div class="progress-item">
                        <div class="progress-label">Others</div>
                        <div class="progress-bar">
                            <div class="progress-fill success" style="width: 15%"></div>
                        </div>
                        <span class="progress-value">15%</span>
                    </div>
                </div>
                <div class="stats-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Visits This Month:</span>
                        <span class="summary-value">45</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Emergency Cases:</span>
                        <span class="summary-value">3</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Referrals to Hospital:</span>
                        <span class="summary-value">2</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>