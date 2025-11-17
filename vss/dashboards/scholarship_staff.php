<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if ($_SESSION['role'] != 'scholarship_staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this scholarship staff");
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['update_status'])) {
        $stmt = $pdo->prepare("UPDATE scholarships SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['scholarship_id']]);
        $success = "Scholarship status updated successfully";
    }
}

// Fetch students in this hostel
$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();

// Fetch scholarship applications
$scholarships = $pdo->prepare("
    SELECT sc.*, s.name, s.grn, s.course, s.year 
    FROM scholarships sc 
    JOIN students s ON sc.student_id = s.id 
    WHERE s.hostel_id = ? 
    ORDER BY sc.applied_date DESC
");
$scholarships->execute([$hostel_id]);
$scholarship_list = $scholarships->fetchAll();
?>

<div class="container-fluid mt-4">
    <h2><?php echo $hostel_info['name']; ?> - Scholarship Committee</h2>
    <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Student Financial Aid</p>
    
    <?php if(isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Applications</h5>
                    <h3><?php echo count($scholarship_list); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Approved</h5>
                    <h3><?php echo count(array_filter($scholarship_list, function($s) { return $s['status'] == 'approved'; })); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Pending</h5>
                    <h3><?php echo count(array_filter($scholarship_list, function($s) { return $s['status'] == 'pending'; })); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5>Rejected</h5>
                    <h3><?php echo count(array_filter($scholarship_list, function($s) { return $s['status'] == 'rejected'; })); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Scholarship Applications</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Application ID</th>
                                <th>Student Name</th>
                                <th>GRN</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Scholarship Type</th>
                                <th>Amount</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($scholarship_list as $scholarship): ?>
                            <tr>
                                <td><?php echo $scholarship['id']; ?></td>
                                <td><?php echo $scholarship['name']; ?></td>
                                <td><?php echo $scholarship['grn']; ?></td>
                                <td><?php echo $scholarship['course']; ?></td>
                                <td><?php echo $scholarship['year']; ?></td>
                                <td><?php echo $scholarship['scholarship_type']; ?></td>
                                <td>₹<?php echo number_format($scholarship['amount']); ?></td>
                                <td><?php echo $scholarship['applied_date']; ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $scholarship['status'] == 'approved' ? 'bg-success' : 
                                            ($scholarship['status'] == 'rejected' ? 'bg-danger' : 'bg-warning'); 
                                    ?>">
                                        <?php echo ucfirst($scholarship['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $scholarship['id']; ?>">
                                        Update Status
                                    </button>
                                    <button class="btn btn-sm btn-info">View Documents</button>
                                </td>
                            </tr>
                            
                            <!-- Update Status Modal -->
                            <div class="modal fade" id="updateModal<?php echo $scholarship['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Scholarship Status</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="scholarship_id" value="<?php echo $scholarship['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Student: <?php echo $scholarship['name']; ?> (<?php echo $scholarship['grn']; ?>)</label>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Scholarship Type: <?php echo $scholarship['scholarship_type']; ?></label>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Amount: ₹<?php echo number_format($scholarship['amount']); ?></label>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select class="form-control" name="status" required>
                                                        <option value="pending" <?php echo $scholarship['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo $scholarship['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo $scholarship['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
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
                    <h5>Scholarship Types Available</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded">
                        <h6>Merit Scholarship</h6>
                        <p class="mb-1"><strong>Eligibility:</strong> GPA > 8.5</p>
                        <p class="mb-1"><strong>Amount:</strong> ₹25,000 per year</p>
                        <p class="mb-1"><strong>Available Slots:</strong> 10</p>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Need-Based Scholarship</h6>
                        <p class="mb-1"><strong>Eligibility:</strong> Family income < ₹2 Lakhs</p>
                        <p class="mb-1"><strong>Amount:</strong> ₹40,000 per year</p>
                        <p class="mb-1"><strong>Available Slots:</strong> 15</p>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Sports Scholarship</h6>
                        <p class="mb-1"><strong>Eligibility:</strong> State/National level player</p>
                        <p class="mb-1"><strong>Amount:</strong> ₹30,000 per year</p>
                        <p class="mb-1"><strong>Available Slots:</strong> 5</p>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Minority Scholarship</h6>
                        <p class="mb-1"><strong>Eligibility:</strong> Minority community</p>
                        <p class="mb-1"><strong>Amount:</strong> ₹20,000 per year</p>
                        <p class="mb-1"><strong>Available Slots:</strong> 8</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Reports & Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Monthly Statistics</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <h4 class="text-primary">₹8,50,000</h4>
                                    <small>Total Disbursed</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-2 bg-light rounded">
                                    <h4 class="text-success">34</h4>
                                    <small>Students Benefited</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Approval Rate by Category</h6>
                        <div class="mb-2">
                            <label>Merit Scholarship</label>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: 85%">85%</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label>Need-Based</label>
                            <div class="progress">
                                <div class="progress-bar bg-info" style="width: 70%">70%</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label>Sports</label>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: 60%">60%</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label>Minority</label>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: 75%">75%</div>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-success">Generate Report</button>
                    <button class="btn btn-info">Export Data</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>