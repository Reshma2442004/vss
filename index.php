
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VSS Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 100px 0; }
        .feature-card { transition: transform 0.3s ease; }
        .feature-card:hover { transform: translateY(-5px); }
        .navbar-brand { font-weight: 700; }
        .btn-custom { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-custom:hover { transform: translateY(-2px); }
        
        /* Mobile responsiveness */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                background: rgba(255, 255, 255, 0.98);
                border-radius: 8px;
                margin-top: 10px;
                padding: 15px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            .hero-section {
                padding: 80px 0 60px;
            }
            .hero-section h1 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .hero-section h1 {
                font-size: 2rem;
            }
            .hero-section .lead {
                font-size: 1rem;
            }
        }
        
        /* Ensure navbar is always visible */
        .navbar {
            z-index: 1030;
        }
        
        .navbar-toggler {
            border: 2px solid rgba(102, 126, 234, 0.3);
            padding: 8px 12px;
            font-size: 1.2rem;
        }
        
        .navbar-toggler:focus {
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        
        .navbar-toggler-icon {
            width: 24px;
            height: 24px;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%2833, 37, 41, 0.75%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-university me-2 text-primary"></i>VSS Hostel Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2 mt-3 mt-lg-0">
                    <a href="vss/auth/student_login.php" class="btn btn-outline-primary">Student Login</a>
                    <a href="vss/auth/login.php" class="btn btn-custom text-white">Staff Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Modern Hostel Management System</h1>
            <p class="lead mb-5">Streamline your hostel operations with our comprehensive digital solution</p>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <h5>Student Management</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                    <h5>Attendance Tracking</h5>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-white bg-opacity-10 border-0 text-white">
                                <div class="card-body text-center">
                                    <i class="fas fa-utensils fa-2x mb-2"></i>
                                    <h5>Mess Management</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">About VSS Hostel Management</h2>
                    <p class="lead">Our comprehensive hostel management system is designed to digitize and streamline all aspects of hostel administration.</p>
                    <p>From student registration and room allocation to mess management and attendance tracking, we provide a complete solution that enhances efficiency and improves the overall hostel experience.</p>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>Digital student records management</li>
                        <li><i class="fas fa-check text-success me-2"></i>Automated attendance system</li>
                        <li><i class="fas fa-check text-success me-2"></i>Mess feedback and management</li>
                        <li><i class="fas fa-check text-success me-2"></i>Leave application processing</li>
                    </ul>
                </div>
                <div class="col-lg-6">
                    <img src="vss\Apte-photo.png" class="img-fluid rounded shadow" alt="Hostel Management">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Key Features</h2>
                <p class="lead">Everything you need to manage your hostel efficiently</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-user-graduate fa-3x text-primary mb-3"></i>
                            <h5>Student Portal</h5>
                            <p>Complete student dashboard with profile management, attendance tracking, and feedback system.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-users-cog fa-3x text-primary mb-3"></i>
                            <h5>Staff Management</h5>
                            <p>Role-based access for rectors, mess heads, library staff, and other hostel personnel.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-qrcode fa-3x text-primary mb-3"></i>
                            <h5>QR Attendance</h5>
                            <p>Modern QR code-based attendance system for mess and hostel check-ins.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                            <h5>Analytics & Reports</h5>
                            <p>Comprehensive reporting and analytics for better decision making.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                            <h5>Mobile Responsive</h5>
                            <p>Access the system from any device with our fully responsive design.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 feature-card border-0 shadow-sm">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                            <h5>Secure & Reliable</h5>
                            <p>Built with security best practices and reliable data management.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto text-center">
                    <h2 class="fw-bold mb-4">Get In Touch</h2>
                    <p class="lead mb-5">Have questions about our hostel management system? We're here to help!</p>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-envelope fa-2x text-primary mb-3"></i>
                                <h5>Email</h5>
                                <p>info@vsshostel.com</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-phone fa-2x text-primary mb-3"></i>
                                <h5>Phone</h5>
                                <p>+91 98765 43210</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <i class="fas fa-map-marker-alt fa-2x text-primary mb-3"></i>
                                <h5>Address</h5>
                                <p>VSS Campus, Education City</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-university me-2"></i>VSS Hostel Management</h5>
                    <p>Modern digital solution for hostel administration and student management.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 VSS Hostel Management System. All rights reserved.</p>
                    <div class="mt-2">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('bg-white', 'shadow');
            } else {
                navbar.classList.remove('shadow');
            }
        });
    </script>
</body>
</html>