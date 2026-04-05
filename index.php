<?php 
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

// Get services count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM services WHERE active = 1");
$stmt->execute();
$result = $stmt->get_result();
$services_count = $result->fetch_assoc()['count'];
$stmt->close();

$newsletter_status = $_GET['newsletter'] ?? '';
$newsletter_message = '';
if ($newsletter_status === 'subscribed') {
    $newsletter_message = 'Newsletter subscription successful.';
} elseif ($newsletter_status === 'invalid') {
    $newsletter_message = 'Please enter a valid email for newsletter.';
} elseif ($newsletter_status === 'error') {
    $newsletter_message = 'Unable to subscribe right now. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earth Cafe - Government Services Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="icon" type="image/jpg" href="./img/ashok-stambh.jpg">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--ec-text);
        }

        /* NAVBAR */
        .navbar {
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 6px 18px rgba(12, 38, 32, 0.12);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 26px;
            color: var(--ec-primary-strong) !important;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: 0.5px;
            font-family: 'Sora', 'Segoe UI', Tahoma, Geneva, sans-serif;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: var(--ec-primary) !important;
        }

        .navbar-logo {
            height: 50px;
            width: auto;
            max-width: 50px;
            object-fit: contain;
            display: block;
        }

        .nav-link {
            color: #335450 !important;
            font-weight: 500;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--ec-primary) !important;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(9, 72, 63, 0.35);
        }

        /* HERO SECTION */
        .hero {
            background: linear-gradient(135deg, rgba(10, 40, 35, 0.7) 0%, rgba(8, 50, 61, 0.58) 100%), url('./img/home-image.jpg') center/cover no-repeat;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            background-attachment: fixed;
            background-size: cover;
        }

        .hero .container,
        .cta .container {
            position: relative;
            z-index: 1;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
            line-height: 1.2;
            color: #ffffff;
            text-shadow: 0 8px 24px rgba(0, 0, 0, 0.32);
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 40px;
            opacity: 0.95;
            position: relative;
            z-index: 2;
            color: rgba(255, 255, 255, 0.92);
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.28);
        }

        .btn-hero {
            background: #ffffff;
            color: var(--ec-primary-strong);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 10px;
        }

        .btn-hero-outline {
            background: transparent;
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.8);
        }

        .btn-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* STATS SECTION */
        .stats {
            background: var(--ec-surface-soft);
            padding: 80px 0;
        }

        .stat-item {
            text-align: center;
            padding: 40px;
            opacity: 0;
            animation: slideInUp 0.8s ease forwards;
        }

        .stat-item:nth-child(1) {
            animation-delay: 0.2s;
        }

        .stat-item:nth-child(2) {
            animation-delay: 0.4s;
        }

        .stat-item:nth-child(3) {
            animation-delay: 0.6s;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: scaleIn 0.6s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0.8);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .stat-label {
            color: var(--ec-text-muted);
            font-size: 1.1rem;
            margin-top: 10px;
            font-weight: 500;
            animation: fadeIn 0.8s ease 0.3s backwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* FEATURES SECTION */
        .features {
            padding: 100px 0;
            background: #ffffff;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 60px;
            color: #11342e;
        }

        .section-subtitle {
            color: var(--ec-accent);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .feature-card {
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--ec-border);
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 38px rgba(13, 36, 33, 0.16);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            margin: 0 auto 20px;
        }

        .feature-card h4 {
            font-weight: 700;
            margin-bottom: 15px;
            color: #11342e;
        }

        .feature-card p {
            color: var(--ec-text-muted);
            line-height: 1.8;
        }

        /* SERVICES SECTION */
        .services {
            background: linear-gradient(145deg, #f3f7f1 0%, #e9f0ec 100%);
            padding: 100px 0;
        }

        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .service-item {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 22px rgba(13, 36, 33, 0.1);
            transition: all 0.3s ease;
        }

        .service-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 40px rgba(15, 106, 93, 0.22);
        }

        .service-item i {
            font-size: 2.5rem;
            color: var(--ec-primary);
            margin-bottom: 15px;
        }

        .service-item h5 {
            font-weight: 700;
            margin-bottom: 10px;
        }

        .service-item p {
            color: var(--ec-text-muted);
            font-size: 0.95rem;
        }

        /* BENEFITS SECTION */
        .benefits {
            padding: 100px 0;
            background: #ffffff;
        }

        .benefit-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            margin: 50px 0;
        }

        .benefit-text h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #11342e;
        }

        .benefit-text p {
            color: var(--ec-text-muted);
            margin-bottom: 15px;
            line-height: 1.8;
        }

        .benefit-list {
            list-style: none;
            margin-top: 20px;
        }

        .benefit-list li {
            color: var(--ec-text-muted);
            margin: 12px 0;
            padding-left: 30px;
            position: relative;
        }

        .benefit-list li:before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--ec-primary);
            font-weight: 700;
        }

        .benefit-image {
            border-radius: 15px;
            overflow: hidden;
        }

        .benefit-image img {
            width: 100%;
            height: auto;
        }

        /* CTA SECTION */
        .cta {
            background: linear-gradient(135deg, rgba(9, 72, 63, 0.9) 0%, rgba(15, 97, 112, 0.86) 100%), url('./img/contact-bg.jpg') center/cover no-repeat;
            background-attachment: fixed;
            background-size: cover;
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #ffffff;
        }

        .cta p {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.95;
            color: rgba(255, 255, 255, 0.92);
        }

        .btn-cta {
            background: #ffffff;
            color: var(--ec-primary-strong);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.3s ease;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* TESTIMONIALS */
        .testimonials {
            background: #edf4ef;
            padding: 100px 0;
        }

        .testimonial-card {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(13, 36, 33, 0.1);
            margin: 20px 0;
        }

        .testimonial-stars {
            color: #ffc107;
            margin-bottom: 15px;
        }

        .testimonial-text {
            color: var(--ec-text-muted);
            margin-bottom: 20px;
            font-style: italic;
            line-height: 1.8;
        }

        .testimonial-author {
            font-weight: 700;
            color: #11342e;
        }

        .testimonial-position {
            color: var(--ec-primary);
            font-size: 0.9rem;
        }

        /* FOOTER */
        .footer {
            background: linear-gradient(135deg, rgba(11, 35, 30, 0.95) 0%, rgba(18, 58, 68, 0.94) 100%), url('./img/footer-bg.jpg') center/cover no-repeat fixed;
            background-size: cover;
            color: white;
            padding: 60px 0 20px;
            position: relative;
            margin-top: 100px;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            pointer-events: none;
        }

        .footer .container {
            position: relative;
            z-index: 1;
        }

        .footer-brand-section {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .footer-logo {
            height: 60px;
            width: auto;
            max-width: 60px;
            object-fit: contain;
            filter: brightness(0) invert(1);
            display: block;
        }

        .footer-brand-text h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3rem;
        }

        .footer-brand-text p {
            margin: 5px 0 0;
            color: #aaa;
            font-size: 0.9rem;
        }

        .footer-section h5 {
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
        }

        .footer-link {
            color: #aaa;
            text-decoration: none;
            display: block;
            margin: 10px 0;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: #efbe4e;
            padding-left: 5px;
        }

        .service-cta-wrap {
            text-align: center;
            margin-top: 50px;
        }

        .footer-muted {
            color: #acc2bb;
            margin-top: 15px;
            line-height: 1.6;
        }

        .footer-contact {
            color: #acc2bb;
            margin: 10px 0;
        }

        .footer-bottom {
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            margin-top: 40px;
            color: #aaa;
            position: relative;
            z-index: 1;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .benefit-row {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .stat-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .nav-link {
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="./img/footer-logo.png" alt="Earth Cafe Logo" class="navbar-logo"> Earth Cafe
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#home"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services"><i class="fas fa-briefcase"></i> Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="About.php"><i class="fas fa-info-circle"></i> About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a>
                    </li>
                    <li class="nav-item">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a class="btn btn-login ec-btn ec-btn-primary" href="login.php">
                                <i class="fas fa-sign-in-alt"></i> Login / Sign Up
                            </a>
                        <?php else: ?>
                            <a class="btn btn-login ec-btn ec-btn-primary" href="client_dashboard.php">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_login.php"><i class="fas fa-lock"></i> Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?php if ($newsletter_message !== ''): ?>
        <div class="container mt-3">
            <div class="alert <?php echo $newsletter_status === 'subscribed' ? 'alert-success' : 'alert-warning'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($newsletter_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- HERO SECTION -->
    <section class="hero" id="home">
        <div class="container">
            <h1>Online Government Services at Your Fingertips</h1>
            <p>Fast, Secure, and Reliable Platform for All Your Public Services</p>
            <div>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="login.php" class="btn btn-hero ec-btn ec-btn-accent">Get Started Now</a>
                    <a href="#services" class="btn btn-hero btn-hero-outline">Learn More</a>
                <?php else: ?>
                    <a href="apply_service.php" class="btn btn-hero ec-btn ec-btn-accent">Apply for Service</a>
                    <a href="client_dashboard.php" class="btn btn-hero btn-hero-outline">My Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- STATS SECTION -->
    <section class="stats">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-target="<?php echo $services_count; ?>" data-suffix="+">0+</div>
                        <div class="stat-label">Active Services</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-target="10000" data-suffix="K+">0K+</div>
                        <div class="stat-label">Satisfied Clients</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-item">
                        <div class="stat-number" data-target="24" data-suffix="/7" data-format="24/7">24/7</div>
                        <div class="stat-label">Customer Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES SECTION -->
    <section class="features" id="services-1">
        <div class="container">
            <div class="section-subtitle text-center">OUR ADVANTAGES</div>
            <h2 class="section-title">Why Choose Earth Cafe?</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h4>Lightning Fast</h4>
                        <p>Get your services processed within 24-48 hours with our optimized workflow and dedicated team.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Secure & Safe</h4>
                        <p>Your data is encrypted and protected with enterprise-grade security standards and compliance.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4>Expert Support</h4>
                        <p>Our trained professionals are ready to assist you at every step of your service journey.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Mobile Friendly</h4>
                        <p>Apply and track your services anytime, anywhere from your smartphone or tablet.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>100% Verified</h4>
                        <p>All services are government-approved and fully compliant with official regulations.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card ec-card">
                        <div class="feature-icon">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h4>Transparent Pricing</h4>
                        <p>No hidden fees. Know exactly what you're paying for before you apply.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- AVAILABLE SERVICES -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-subtitle text-center">WHAT WE OFFER</div>
            <h2 class="section-title">Our Services</h2>
            <div class="service-grid">
                <div class="service-item ec-card">
                    <i class="fas fa-certificate"></i>
                    <h5>Certificates</h5>
                    <p>Get income certificates, caste certificates, and residence certificates quickly and easily.</p>
                </div>
                <div class="service-item ec-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h5>Scholarships</h5>
                    <p>Find and apply for government scholarships tailored to your eligibility.</p>
                </div>
                <div class="service-item ec-card">
                    <i class="fas fa-file-alt"></i>
                    <h5>Documents</h5>
                    <p>Process and obtain official government documents without hassle.</p>
                </div>
                <div class="service-item ec-card">
                    <i class="fas fa-id-card"></i>
                    <h5>ID Services</h5>
                    <p>Fast-track your PAN, Aadhar, and other identity document applications.</p>
                </div>
                <div class="service-item ec-card">
                    <i class="fas fa-building"></i>
                    <h5>Property Services</h5>
                    <p>Verify and register property documents with complete transparency.</p>
                </div>
                <div class="service-item ec-card">
                    <i class="fas fa-handshake"></i>
                    <h5>And More</h5>
                    <p>Explore our wide range of government services and schemes.</p>
                </div>
            </div>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <div class="service-cta-wrap">
                    <a href="login.php" class="btn btn-hero ec-btn ec-btn-primary">Browse All Services</a>
                </div>
            <?php else: ?>
                <div class="service-cta-wrap">
                    <a href="apply_service.php" class="btn btn-hero ec-btn ec-btn-primary">Apply for Services</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- BENEFITS SECTION -->
    <section class="benefits" id="about">
        <div class="container">
            <div class="section-subtitle">WHY CHOOSE US</div>
            <h2 class="section-title">The Earth Cafe Difference</h2>

            <div class="benefit-row">
                <div>
                    <h3>Trusted by Thousands</h3>
                    <p>For over 25 years, Earth Cafe has been serving citizens with integrity and excellence. We've helped thousands of people access government services easily and securely.</p>
                    <ul class="benefit-list">
                        <li>Verified government partnerships</li>
                        <li>ISO certified processes</li>
                        <li>Zero security breaches</li>
                        <li>Award-winning customer service</li>
                    </ul>
                </div>
                <div class="benefit-image">
                    <img src="./img/service-1.jpg" alt="Benefits">
                </div>
            </div>

            <div class="benefit-row">
                <div class="benefit-image">
                    <img src="./img/service-2.jpg" alt="System">
                </div>
                <div>
                    <h3>Modern Digital Platform</h3>
                    <p>Our advanced technology platform eliminates paperwork and reduces processing time significantly. Track your applications in real-time through our intuitive dashboard.</p>
                    <ul class="benefit-list">
                        <li>Real-time status tracking</li>
                        <li>Automated notifications</li>
                        <li>Paperless processing</li>
                        <li>Digital document management</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="testimonials">
        <div class="container">
            <div class="section-subtitle text-center">CLIENT REVIEWS</div>
            <h2 class="section-title">What Our Clients Say</h2>
            <div class="row">
                <div class="col-md-6">
                    <div class="testimonial-card ec-card">
                        <div class="testimonial-stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"Earth Cafe made getting my income certificate incredibly easy. The process was straightforward and my document arrived within 2 days. Highly recommended!"</p>
                        <p class="testimonial-author">Rajesh Kumar</p>
                        <p class="testimonial-position">Hyderabad, India</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="testimonial-card ec-card">
                        <div class="testimonial-stars">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <p class="testimonial-text">"The customer support team was incredibly helpful when I had questions. They responded within minutes and resolved all my concerns. A++ service!"</p>
                        <p class="testimonial-author">Priya Sharma</p>
                        <p class="testimonial-position">Mumbai, India</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of satisfied clients who have made their lives easier with Earth Cafe</p>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php" class="btn btn-cta">Create Your Account Today</a>
            <?php else: ?>
                <a href="apply_service.php" class="btn btn-cta">Apply for a Service Now</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-3">
                    <div class="footer-section">
                        <div class="footer-brand-section">
                            <img src="./img/footer-logo.png" alt="Earth Cafe Logo" class="footer-logo">
                            <div class="footer-brand-text">
                                <h5>Earth Cafe</h5>
                                <p>Government Services</p>
                            </div>
                        </div>
                        <p class="footer-muted">Your trusted partner for government services online. Fast, secure, and reliable.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-section">
                        <h5>Quick Links</h5>
                        <a href="index.php" class="footer-link">Home</a>
                        <a href="#services" class="footer-link">Services</a>
                        <a href="#about" class="footer-link">About Us</a>
                        <a href="terms_of_service.php" class="footer-link">Terms of Service</a>
                        <a href="privacy_policy.php" class="footer-link">Privacy Policy</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-section">
                        <h5>Account</h5>
                        <a href="login.php" class="footer-link">Login</a>
                        <a href="login.php" class="footer-link">Sign Up</a>
                        <a href="client_dashboard.php" class="footer-link">Dashboard</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-section">
                        <h5>Contact & Newsletter</h5>
                        <p class="footer-contact">
                            <i class="fas fa-envelope"></i> support@earthcafe.com<br>
                            <i class="fas fa-phone"></i> +91 1234-567-890
                        </p>
                        <form method="POST" action="newsletter_subscribe.php">
                            <?php echo csrf_input(); ?>
                            <input type="text" name="newsletter_name" class="form-control form-control-sm mb-2" placeholder="Your name">
                            <input type="email" name="newsletter_email" class="form-control form-control-sm mb-2" placeholder="Your email" required>
                            <button type="submit" class="btn btn-warning btn-sm w-100">Subscribe</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Earth Cafe. All Rights Reserved. | Powered by Earth Cafe Team</p>
            </div>
        </div>
    </footer>

    <script>
        // Counter animation for stats
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target')) || 0;
                const suffix = counter.getAttribute('data-suffix') || '';
                const format = counter.getAttribute('data-format');
                
                if (format === '24/7') {
                    return; // Skip special format
                }
                
                let current = 0;
                const increment = Math.ceil(target / 50); // Adjust speed (lower = faster)
                
                const updateCounter = () => {
                    if (current < target) {
                        current += increment;
                        if (current > target) current = target;
                        
                        if (suffix === 'K+') {
                            counter.textContent = Math.floor(current / 1000) + 'K+';
                        } else {
                            counter.textContent = current + suffix;
                        }
                        requestAnimationFrame(updateCounter);
                    }
                };
                
                updateCounter();
            });
        }

        // Trigger animation when component is visible
        const observerOptions = {
            threshold: 0.5
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        const statsSection = document.querySelector('.stats');
        if (statsSection) {
            observer.observe(statsSection);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
