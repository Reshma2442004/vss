<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#667eea">
    <title>VSS Hostel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe, #00f2fe, #667eea);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            min-height: 100vh;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            25% { background-position: 100% 50%; }
            50% { background-position: 50% 100%; }
            75% { background-position: 0% 100%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #2d1b69 0%, #11998e 100%) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.8rem 0;
            box-shadow: 0 2px 20px rgba(45, 27, 105, 0.3);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: white !important;
            font-size: 1.3rem;
        }
        
        .navbar-nav {
            margin-left: auto !important;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }
        
        .nav-link:hover {
            color: white !important;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero-title {
            font-size: 2.8rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2.5rem;
            font-weight: 400;
        }
        
        /* Statistics Cards */
        .stats-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            flex: 1;
            min-width: 120px;
        }
        
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            font-size: 1.2rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            box-shadow: 0 0 25px rgba(255, 255, 255, 0.4);
            transform: scale(1.1);
        }
        
        .stat-number {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            display: block;
            margin-bottom: 0.3rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        /* Glass Container */
        .glass-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: fadeInRight 1s ease-out 0.3s both;
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .glass-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: white;
            text-align: center;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        /* Login Cards */
        .login-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem 1.2rem;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s;
        }
        
        .login-card:hover::before {
            left: 100%;
        }
        
        .login-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        
        .login-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
            position: relative;
            transition: all 0.3s ease;
        }
        
        .student-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .rector-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .admin-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .login-card:hover .login-icon {
            transform: scale(1.1);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.5);
        }
        
        .login-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.8rem;
        }
        
        .login-desc {
            color: #6b7280;
            margin-bottom: 1.5rem;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .login-btn {
            padding: 0.6rem 1.5rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            color: white;
            font-size: 0.9rem;
        }
        
        .student-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .rector-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .admin-btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.4);
            color: white;
        }
        
        /* Features Section */
        .features-section {
            padding: 4rem 0;
            background: #f8fafc;
        }
        
        .section-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1f2937;
            text-align: center;
            margin-bottom: 0.8rem;
        }
        
        .section-subtitle {
            color: #6b7280;
            text-align: center;
            margin-bottom: 2.5rem;
            font-size: 1rem;
        }
        
        .feature-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover {
            background: white;
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            font-size: 1.5rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            box-shadow: 0 0 25px rgba(102, 126, 234, 0.3);
            transform: scale(1.1);
        }
        
        .feature-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.8rem;
        }
        
        .feature-desc {
            color: #6b7280;
            line-height: 1.5;
            font-size: 0.9rem;
        }
        
        /* About Section */
        .about-section {
            padding: 4rem 0;
            background: white;
        }
        
        .about-title {
            font-size: 2rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .about-subtitle {
            font-size: 1.1rem;
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .about-stat {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 2rem 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .about-stat:hover {
            background: white;
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: #667eea;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .about-stat:hover .stat-icon {
            transform: scale(1.1);
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.3);
        }
        
        .about-stat-number {
            font-size: 2.2rem;
            font-weight: 800;
            color: #1f2937;
            display: block;
            margin-bottom: 0.5rem;
        }
        
        .about-stat-label {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .content-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .content-text {
            color: #6b7280;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .benefits-grid {
            display: grid;
            gap: 1.5rem;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .benefit-item:hover {
            background: white;
            transform: translateX(10px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .benefit-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .benefit-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.3rem;
        }
        
        .benefit-desc {
            color: #6b7280;
            font-size: 0.9rem;
            margin: 0;
            line-height: 1.4;
        }
        
        .about-image-container {
            position: relative;
        }
        
        .image-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            height: 400px;
        }
        
        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(102, 126, 234, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            border-radius: 20px;
        }
        
        .image-wrapper:hover .image-overlay {
            opacity: 1;
        }
        
        .overlay-content {
            text-align: center;
            color: white;
        }
        
        .overlay-content i {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .overlay-content span {
            font-size: 1.1rem;
            font-weight: 600;
        }
        

        
        /* Contact Section */
        .contact-section {
            padding: 4rem 0;
            background: #f8fafc;
        }
        
        .contact-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .contact-card:hover {
            background: white;
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            border-color: #d1d5db;
        }
        
        .contact-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
            font-size: 1.5rem;
            color: white;
            transition: all 0.3s ease;
        }
        
        .contact-card:hover .contact-icon {
            box-shadow: 0 0 25px rgba(102, 126, 234, 0.3);
            transform: scale(1.1);
        }
        
        .contact-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.8rem;
        }
        
        .contact-info {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.2rem;
            }
            
            .login-cards {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-container {
                flex-direction: column;
                gap: 0.8rem;
            }
            
            .glass-container {
                padding: 2rem;
                margin-top: 2rem;
            }
            
            .navbar-nav {
                margin-left: 0 !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .login-card {
                padding: 1.5rem 1rem;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-university me-2"></i>VSS Hostel Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="hero-title">Welcome to VSS Hostel Management System</h1>
                        <p class="hero-subtitle">Experience premium hostel management with our cutting-edge digital solution designed for modern educational institutions.</p>
                        
                        <div class="stats-container">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <span class="stat-number">500+</span>
                                <span class="stat-label">Students</span>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <span class="stat-number">5+</span>
                                <span class="stat-label">Hostels</span>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <span class="stat-number">24/7</span>
                                <span class="stat-label">Availability</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="glass-container">
                        <h3 class="glass-title">Choose Your Login</h3>
                        
                        <div class="login-cards">
                            <div class="login-card">
                                <div class="login-icon student-icon">
                                    <i class="fas fa-user-graduate"></i>
                                </div>
                                <h5 class="login-title">Student</h5>
                                <p class="login-desc">Access your dashboard, track attendance, and manage your hostel experience</p>
                                <a href="auth/student_login.php" class="login-btn student-btn">Login</a>
                            </div>
                            
                            <div class="login-card">
                                <div class="login-icon rector-icon">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <h5 class="login-title">Rector</h5>
                                <p class="login-desc">Manage students, oversee operations, and maintain hostel standards</p>
                                <a href="auth/rector_login.php" class="login-btn rector-btn">Login</a>
                            </div>
                            
                            <div class="login-card">
                                <div class="login-icon admin-icon">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <h5 class="login-title">Admin</h5>
                                <p class="login-desc">System administration, analytics, and comprehensive management</p>
                                <a href="auth/admin_login.php" class="login-btn admin-btn">Login</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <div class="container">
            <h2 class="section-title">Powerful Features</h2>
            <p class="section-subtitle">Everything you need for modern hostel management</p>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <h5 class="feature-title">QR Code Attendance</h5>
                        <p class="feature-desc">Modern QR code-based attendance system for mess and hostel check-ins with real-time tracking.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <h5 class="feature-title">Role-Based Access</h5>
                        <p class="feature-desc">Secure role-based access control for students, rectors, mess heads, and administrative staff.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h5 class="feature-title">Mobile Responsive</h5>
                        <p class="feature-desc">Fully responsive design that works perfectly on all devices - desktop, tablet, and mobile.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h5 class="feature-title">Analytics & Reports</h5>
                        <p class="feature-desc">Comprehensive reporting and analytics dashboard for better decision making and insights.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h5 class="feature-title">Feedback System</h5>
                        <p class="feature-desc">Integrated feedback system for mess services, complaints, and suggestions with priority handling.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h5 class="feature-title">Secure & Reliable</h5>
                        <p class="feature-desc">Built with security best practices, data encryption, and reliable infrastructure for 24/7 availability.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="container">
            <!-- Section Header -->
            <div class="text-center mb-5">
                <h2 class="about-title">About VSS Hostel Management</h2>
                <p class="about-subtitle">A comprehensive digital solution designed to streamline hostel operations and enhance the student living experience.</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-5">
                <div class="col-lg-3 col-md-6">
                    <div class="about-stat text-center">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <span class="about-stat-number">500+</span>
                        <span class="about-stat-label">Active Students</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="about-stat text-center">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-building"></i>
                        </div>
                        <span class="about-stat-number">5+</span>
                        <span class="about-stat-label">Hostels Managed</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="about-stat text-center">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span class="about-stat-number">24/7</span>
                        <span class="about-stat-label">Support Available</span>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="about-stat text-center">
                        <div class="stat-icon mb-3">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <span class="about-stat-number">100%</span>
                        <span class="about-stat-label">Secure System</span>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <div class="about-content">
                        <h3 class="content-title mb-4">Transforming Hostel Management</h3>
                        <p class="content-text mb-4">Our platform revolutionizes traditional hostel administration by providing a unified digital ecosystem that connects students, staff, and administrators seamlessly.</p>
                        
                        <div class="benefits-grid">
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="benefit-content">
                                    <h6 class="benefit-title">Digital Records</h6>
                                    <p class="benefit-desc">Complete student profile management with secure data storage</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <div class="benefit-content">
                                    <h6 class="benefit-title">Smart Attendance</h6>
                                    <p class="benefit-desc">QR code-based attendance tracking with real-time monitoring</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div class="benefit-content">
                                    <h6 class="benefit-title">Mess Management</h6>
                                    <p class="benefit-desc">Comprehensive meal planning and feedback system</p>
                                </div>
                            </div>
                            
                            <div class="benefit-item">
                                <div class="benefit-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="benefit-content">
                                    <h6 class="benefit-title">Analytics Dashboard</h6>
                                    <p class="benefit-desc">Real-time insights and comprehensive reporting tools</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="about-image-container">
                        <div class="image-wrapper">
                            <img src="Apte-photo.png" class="img-fluid rounded-4 shadow-lg" alt="Hostel Management">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <i class="fas fa-play-circle"></i>
                                    <span>Watch Demo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Need help or have questions? Contact our support team</p>
            
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5 class="contact-title">Email Support</h5>
                        <p class="contact-info">support@vsshostel.edu</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5 class="contact-title">Phone Support</h5>
                        <p class="contact-info">+91 12345 67890</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5 class="contact-title">Office Location</h5>
                        <p class="contact-info">VSS Campus, Education City</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe feature cards
        document.querySelectorAll('.feature-card, .contact-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });
    </script>
</body>
</html>