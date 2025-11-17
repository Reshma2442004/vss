<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if ($_SESSION['role'] != 'placement_staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this placement staff");
}

// Fetch students in this hostel
$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();
?>

<div class="container-fluid mt-4">
    <h2><?php echo $hostel_info['name']; ?> - Placement Cell</h2>
    <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Career Services & Placement</p>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Students</h5>
                    <h3><?php echo count($students_list); ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Placed Students</h5>
                    <h3>45</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Active Drives</h5>
                    <h3>8</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Internships</h5>
                    <h3>23</h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Student Profiles</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>GRN</th>
                                <th>Name</th>
                                <th>Course</th>
                                <th>GPA</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($students_list as $student): ?>
                            <tr>
                                <td><?php echo $student['grn']; ?></td>
                                <td><?php echo $student['name']; ?></td>
                                <td><?php echo $student['course']; ?></td>
                                <td>8.5</td>
                                <td><span class="badge bg-success">Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Placement Drives</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded">
                        <h6>TCS Campus Drive</h6>
                        <p class="mb-1">Date: 15/12/2024 | Package: 3.5 LPA</p>
                        <small class="text-muted">Eligibility: 70% and above</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-success">Register Students</button>
                        </div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Infosys Recruitment</h6>
                        <p class="mb-1">Date: 20/12/2024 | Package: 4.0 LPA</p>
                        <small class="text-muted">Eligibility: 75% and above</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-success">Register Students</button>
                        </div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Wipro Walk-in</h6>
                        <p class="mb-1">Date: 25/12/2024 | Package: 3.2 LPA</p>
                        <small class="text-muted">Eligibility: 65% and above</small>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-success">Register Students</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Internship & Job Allocation Records</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>GRN</th>
                                <th>Company</th>
                                <th>Position</th>
                                <th>Package</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>John Doe</td>
                                <td>GRN001</td>
                                <td>TCS</td>
                                <td>Software Developer</td>
                                <td>3.5 LPA</td>
                                <td><span class="badge bg-success">Full-time</span></td>
                                <td><span class="badge bg-success">Placed</span></td>
                                <td><button class="btn btn-sm btn-info">View</button></td>
                            </tr>
                            <tr>
                                <td>Jane Smith</td>
                                <td>GRN002</td>
                                <td>Infosys</td>
                                <td>System Engineer</td>
                                <td>4.0 LPA</td>
                                <td><span class="badge bg-success">Full-time</span></td>
                                <td><span class="badge bg-warning">Interview</span></td>
                                <td><button class="btn btn-sm btn-info">View</button></td>
                            </tr>
                            <tr>
                                <td>Mike Johnson</td>
                                <td>GRN003</td>
                                <td>Google</td>
                                <td>Software Intern</td>
                                <td>50k/month</td>
                                <td><span class="badge bg-info">Internship</span></td>
                                <td><span class="badge bg-success">Selected</span></td>
                                <td><button class="btn btn-sm btn-info">View</button></td>
                            </tr>
                            <tr>
                                <td>Sarah Wilson</td>
                                <td>GRN004</td>
                                <td>Microsoft</td>
                                <td>Data Analyst</td>
                                <td>6.0 LPA</td>
                                <td><span class="badge bg-success">Full-time</span></td>
                                <td><span class="badge bg-primary">Applied</span></td>
                                <td><button class="btn btn-sm btn-info">View</button></td>
                            </tr>
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
                    <h5>Placement Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label>Placement Rate</label>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: 75%">75%</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Average Package</label>
                        <h4 class="text-success">₹4.2 LPA</h4>
                    </div>
                    <div class="mb-3">
                        <label>Highest Package</label>
                        <h4 class="text-primary">₹12.0 LPA</h4>
                    </div>
                    <div class="mb-3">
                        <p><strong>Total Companies:</strong> 25</p>
                        <p><strong>Students Placed:</strong> 45/60</p>
                        <p><strong>Internships:</strong> 23</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Skill Development</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Popular Skills in Demand</h6>
                        <div class="mb-2">
                            <span class="badge bg-primary me-1">Java</span>
                            <span class="badge bg-primary me-1">Python</span>
                            <span class="badge bg-primary me-1">React</span>
                            <span class="badge bg-primary me-1">Node.js</span>
                        </div>
                        <div class="mb-2">
                            <span class="badge bg-secondary me-1">Machine Learning</span>
                            <span class="badge bg-secondary me-1">Data Science</span>
                            <span class="badge bg-secondary me-1">Cloud Computing</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>Upcoming Training Sessions</h6>
                        <ul class="list-unstyled">
                            <li>• Full Stack Development - 18/12/2024</li>
                            <li>• Data Analytics - 22/12/2024</li>
                            <li>• Interview Preparation - 28/12/2024</li>
                        </ul>
                    </div>
                    <button class="btn btn-success">Schedule Training</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>