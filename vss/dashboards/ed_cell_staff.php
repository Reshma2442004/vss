<?php
require_once '../includes/header.php';
require_once '../config/database.php';

if ($_SESSION['role'] != 'ed_cell_staff') {
    header('Location: ../auth/login.php');
    exit;
}

$hostel_id = $_SESSION['hostel_id'];

// Fetch hostel information
$hostel = $pdo->prepare("SELECT * FROM hostels WHERE id = ?");
$hostel->execute([$hostel_id]);
$hostel_info = $hostel->fetch();

if (!$hostel_info) {
    die("Hostel not found or not assigned to this ED cell staff");
}

// Fetch students in this hostel
$students = $pdo->prepare("SELECT * FROM students WHERE hostel_id = ?");
$students->execute([$hostel_id]);
$students_list = $students->fetchAll();
?>

<div class="container-fluid mt-4">
    <h2><?php echo $hostel_info['name']; ?> - ED Cell</h2>
    <p class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo $hostel_info['location']; ?> | Entrepreneurship Development</p>
    
    <div class="row">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Startup Ideas</h5>
                    <h3>15</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Active Projects</h5>
                    <h3>8</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5>Mentors</h5>
                    <h3>12</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Funded Projects</h5>
                    <h3>3</h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Startup Ideas Submitted</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded">
                        <h6>EcoFriendly Food Delivery</h6>
                        <p class="mb-1"><strong>Student:</strong> John Doe (GRN001)</p>
                        <p class="mb-1"><strong>Category:</strong> Sustainability</p>
                        <span class="badge bg-warning">Under Review</span>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-success">Approve</button>
                            <button class="btn btn-sm btn-danger">Reject</button>
                        </div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>AI-Powered Study Assistant</h6>
                        <p class="mb-1"><strong>Student:</strong> Jane Smith (GRN002)</p>
                        <p class="mb-1"><strong>Category:</strong> EdTech</p>
                        <span class="badge bg-success">Approved</span>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-info">Assign Mentor</button>
                        </div>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Smart Campus Navigation</h6>
                        <p class="mb-1"><strong>Student:</strong> Mike Johnson (GRN003)</p>
                        <p class="mb-1"><strong>Category:</strong> Technology</p>
                        <span class="badge bg-info">In Development</span>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary">View Details</button>
                            <button class="btn btn-sm btn-warning">Track Progress</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Mentor Assignments</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Mentor Name</th>
                                <th>Expertise</th>
                                <th>Projects</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Dr. Rajesh Kumar</td>
                                <td>Technology</td>
                                <td>3</td>
                                <td>
                                    <button class="btn btn-sm btn-info">Contact</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Ms. Priya Sharma</td>
                                <td>Business Development</td>
                                <td>2</td>
                                <td>
                                    <button class="btn btn-sm btn-info">Contact</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Mr. Amit Patel</td>
                                <td>Marketing</td>
                                <td>4</td>
                                <td>
                                    <button class="btn btn-sm btn-info">Contact</button>
                                </td>
                            </tr>
                            <tr>
                                <td>Dr. Sunita Verma</td>
                                <td>Finance</td>
                                <td>1</td>
                                <td>
                                    <button class="btn btn-sm btn-info">Contact</button>
                                </td>
                            </tr>
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
                    <h5>Workshop & Event Records</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Participants</th>
                                <th>Speaker</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Startup Pitch Competition</td>
                                <td>15/12/2024</td>
                                <td>Competition</td>
                                <td>25</td>
                                <td>Industry Expert</td>
                                <td><span class="badge bg-success">Completed</span></td>
                                <td><button class="btn btn-sm btn-info">View Results</button></td>
                            </tr>
                            <tr>
                                <td>Business Model Canvas Workshop</td>
                                <td>20/12/2024</td>
                                <td>Workshop</td>
                                <td>40</td>
                                <td>Dr. Rajesh Kumar</td>
                                <td><span class="badge bg-warning">Upcoming</span></td>
                                <td><button class="btn btn-sm btn-primary">Manage</button></td>
                            </tr>
                            <tr>
                                <td>Funding & Investment Seminar</td>
                                <td>25/12/2024</td>
                                <td>Seminar</td>
                                <td>60</td>
                                <td>Venture Capitalist</td>
                                <td><span class="badge bg-info">Planned</span></td>
                                <td><button class="btn btn-sm btn-success">Register</button></td>
                            </tr>
                            <tr>
                                <td>Digital Marketing for Startups</td>
                                <td>30/12/2024</td>
                                <td>Workshop</td>
                                <td>35</td>
                                <td>Marketing Expert</td>
                                <td><span class="badge bg-secondary">Draft</span></td>
                                <td><button class="btn btn-sm btn-warning">Edit</button></td>
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
                    <h5>Funding Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded">
                        <h6>EcoFriendly Food Delivery</h6>
                        <p class="mb-1"><strong>Funding Requested:</strong> ₹2,00,000</p>
                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-success">Approved</span></p>
                        <p class="mb-1"><strong>Amount Sanctioned:</strong> ₹1,50,000</p>
                        <small class="text-muted">Approved on: 10/12/2024</small>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>AI-Powered Study Assistant</h6>
                        <p class="mb-1"><strong>Funding Requested:</strong> ₹3,50,000</p>
                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning">Under Review</span></p>
                        <small class="text-muted">Applied on: 12/12/2024</small>
                    </div>
                    <div class="mb-3 p-2 border rounded">
                        <h6>Smart Campus Navigation</h6>
                        <p class="mb-1"><strong>Funding Requested:</strong> ₹1,00,000</p>
                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-danger">Rejected</span></p>
                        <small class="text-muted">Reason: Insufficient market research</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Success Stories</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 p-2 border rounded bg-light">
                        <h6 class="text-success">GreenTech Solutions</h6>
                        <p class="mb-1"><strong>Founder:</strong> Alumni - Rahul Mehta (2022)</p>
                        <p class="mb-1"><strong>Current Valuation:</strong> ₹50 Lakhs</p>
                        <p class="mb-1"><strong>Employees:</strong> 15</p>
                        <small class="text-muted">Started during final year, now a successful startup</small>
                    </div>
                    <div class="mb-3 p-2 border rounded bg-light">
                        <h6 class="text-success">EduConnect Platform</h6>
                        <p class="mb-1"><strong>Founder:</strong> Alumni - Sneha Gupta (2021)</p>
                        <p class="mb-1"><strong>Current Valuation:</strong> ₹1.2 Crores</p>
                        <p class="mb-1"><strong>Users:</strong> 10,000+</p>
                        <small class="text-muted">Connecting students with tutors nationwide</small>
                    </div>
                    <button class="btn btn-info">View All Success Stories</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>