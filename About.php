<?php 
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Earth Cafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="icon" type="image/jpg" href="img/ashok-stambh.jpg">
    
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
            width: 50px;
            object-fit: contain;
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
            background: linear-gradient(135deg, rgba(10, 40, 35, 0.7) 0%, rgba(8, 50, 61, 0.58) 100%), url('./img/about-home-image.jpg') center/cover no-repeat;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            background-attachment: fixed;
            background-size: cover;
        }

        .hero .container {
            position: relative;
            z-index: 1;
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
            margin-bottom: 20px;
            opacity: 0.95;
            position: relative;
            z-index: 2;
            color: rgba(255, 255, 255, 0.92);
            text-shadow: 0 4px 16px rgba(0, 0, 0, 0.28);
        }

        /* SECTIONS */
        .section-padding {
            padding: 80px 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #11342e;
        }

        .section-subtitle {
            color: var(--ec-accent);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }

        /* WHO WE ARE */
        .about-section {
            background: white;
        }

        .about-intro {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .about-intro-content h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #11342e;
        }

        .about-intro-content p {
            color: var(--ec-text-muted);
            margin-bottom: 15px;
            line-height: 1.8;
            font-size: 0.95rem;
        }

        .about-intro-img {
            border-radius: 15px;
            overflow: hidden;
        }

        .about-intro-img img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* VALUES SECTION */
        .values-section {
            background: var(--ec-surface-soft);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .value-card {
            background: white;
            padding: 40px;
            border-radius: var(--ec-radius-lg);
            text-align: center;
            box-shadow: var(--ec-shadow-sm);
            border: 1px solid var(--ec-border);
            transition: all 0.3s ease;
        }

        .value-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--ec-shadow-md);
            border-color: #bfd0bf;
        }

        .value-icon {
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

        .value-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #11342e;
        }

        .value-card p {
            color: var(--ec-text-muted);
            line-height: 1.8;
            font-size: 0.95rem;
        }

        /* BRANDS SECTION */
        .brands-section {
            background: white;
            padding: 80px 0;
        }

        .brands-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .brands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 30px;
            align-items: center;
            justify-items: center;
        }

        .brand-item {
            text-align: center;
            transition: all 0.3s ease;
        }

        .brand-item img {
            max-height: 80px;
            max-width: 100%;
            filter: grayscale(100%);
            transition: all 0.3s ease;
        }

        .brand-item:hover img {
            filter: grayscale(0%);
            transform: scale(1.1);
        }

        /* TEAM SECTION */
        .team-section {
            background: var(--ec-surface-soft);
            padding: 80px 0;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }

        .team-card {
            background: white;
            border-radius: var(--ec-radius-lg);
            overflow: hidden;
            box-shadow: var(--ec-shadow-sm);
            border: 1px solid var(--ec-border);
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--ec-shadow-md);
            border-color: #bfd0bf;
        }

        .team-card img {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .team-card-content {
            padding: 30px 20px;
            text-align: center;
        }

        .team-card h3 {
            font-weight: 700;
            color: #11342e;
            margin-bottom: 5px;
        }

        .team-card p {
            color: var(--ec-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* CTA SECTION */
        .cta-section {
            background: linear-gradient(135deg, rgba(9, 72, 63, 0.9) 0%, rgba(15, 97, 112, 0.86) 100%), url('./img/footer-bg.jpg') center/cover no-repeat;
            background-attachment: fixed;
            background-size: cover;
            color: white;
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: white;
        }

        .cta-section p {
            font-size: 1.1rem;
            margin-bottom: 40px;
            opacity: 0.95;
            color: rgba(255, 255, 255, 0.92);
        }

        .btn-cta {
            background: white;
            color: var(--ec-primary-strong);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 50px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        /* FOOTER */
        .footer {
            background: linear-gradient(135deg, rgba(11, 35, 30, 0.95) 0%, rgba(18, 58, 68, 0.94) 100%), url('./img/footer-bg.jpg') center/cover no-repeat fixed;
            background-size: cover;
            color: white;
            padding: 60px 0 20px;
            position: relative;
            margin-top: 0;
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
            width: 60px;
            object-fit: contain;
            filter: brightness(0) invert(1);
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

            .about-intro {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .about-intro-content h2 {
                font-size: 1.8rem;
            }

            .cta-section h2 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 576px) {
            .nav-link {
                margin: 5px 0;
            }

            .hero {
                padding: 80px 0;
            }

            .hero h1 {
                font-size: 1.5rem;
            }

            .section-padding {
                padding: 40px 0;
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
                        <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="apply_service.php"><i class="fas fa-briefcase"></i> Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="About.php"><i class="fas fa-info-circle"></i> About</a>
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

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container">
            <h1>About Earth Cafe</h1>
            <p>Your Trusted Partner for Online Government Services</p>
        </div>
    </section>

    <!-- ABOUT INTRO SECTION -->
    <section class="about-section section-padding">
        <div class="container">
            <div class="about-intro">
                <div class="about-intro-content">
                    <div class="section-subtitle">Who We Are</div>
                    <h2>Your Gateway to Government Services</h2>
                    <p>At Earth Cafe, we are dedicated to providing comprehensive online government services that cater to your everyday digital needs. With years of experience and a commitment to customer satisfaction, we offer reliable solutions for certificate applications, document creation, and more.</p>
                    <p>Our mission is simple: to make online government services simple, secure, and accessible for everyone. We bridge the gap between citizens and government services, ensuring a seamless experience from start to finish.</p>
                </div>
                <div class="about-intro-img">
                    <img src="img/who-we-are.jpg" alt="About Earth Cafe">
                </div>
            </div>
        </div>
    </section>

    <!-- VALUES SECTION -->
    <section class="values-section section-padding">
        <div class="container">
            <div class="section-subtitle" style="text-align: center;">OUR CORE VALUES</div>
            <h2 class="section-title" style="text-align: center;">Why Choose Earth Cafe?</h2>
            
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Reliable</h3>
                    <p>We pride ourselves on being a trusted partner for all your online service needs. Consistent and reliable results every time, ensuring your peace of mind.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>Professional</h3>
                    <p>Our team of professionals is dedicated to providing high-quality services with attention to detail. We adhere to the highest standards and expertise.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-smile"></i>
                    </div>
                    <h3>Customer-Focused</h3>
                    <p>Your satisfaction is our top priority. We listen to your needs and work hard to provide services tailored to your specific requirements.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Secure</h3>
                    <p>Your data is encrypted and protected with enterprise-grade security standards. We ensure complete confidentiality and compliance with all regulations.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Fast</h3>
                    <p>Get your services processed quickly with our optimized workflow and dedicated team. Most requests completed within 24-48 hours.</p>
                </div>

                <div class="value-card">
                    <div class="value-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Available round the clock to assist you. Our support team is always ready to help with any questions or concerns you may have.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- TEAM SECTION -->
    <section class="team-section section-padding">
        <div class="container">
            <div class="section-subtitle" style="text-align: center;">MEET OUR TEAM</div>
            <h2 class="section-title" style="text-align: center;">Our Leadership</h2>
            
            <div class="team-grid">
                <div class="team-card">
                    <img src="img/employee-1.jpg" alt="Bhavesh Boricha">
                    <div class="team-card-content">
                        <h3>Bhavesh Boricha</h3>
                        <p>Founder & CEO</p>
                    </div>
                </div>

                <div class="team-card">
                    <img src="img/employee-2.jpg" alt="Dharmik Devganiya">
                    <div class="team-card-content">
                        <h3>Dharmik Devganiya</h3>
                        <p>Co-Founder & COO</p>
                    </div>
                </div>

                <div class="team-card">
                    <img src="img/employee-3.jpg" alt="Rajveer Rathod">
                    <div class="team-card-content">
                        <h3>Rajveer Rathod</h3>
                        <p>Director of Operations</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BRANDS SECTION -->
    <section class="brands-section">
        <div class="container">
            <div class="brands-title">
                <div class="section-subtitle" style="text-align: center;">OUR PARTNERS</div>
                <h2 class="section-title" style="text-align: center;">Trusted by Leading Organizations</h2>
                <p style="text-align: center; color: #666; font-size: 1rem; margin-top: 20px;">We work with leading government institutions, agencies, and organizations to deliver seamless online services</p>
            </div>

            <div class="brands-grid">
                <div class="brand-item">
                    <img src="img/gog.png" alt="Government of Gujarat">
                </div>
                <div class="brand-item">
                    <img src="img/nsdl.png" alt="NSDL">
                </div>
                <div class="brand-item">
                    <img src="img/vnsgu.jpg" alt="VNSGU">
                </div>
                <div class="brand-item">
                    <img src="img/fssi.png" alt="FSSI">
                </div>
                <div class="brand-item">
                    <img src="img/dg.png" alt="DG">
                </div>
                <div class="brand-item">
                    <img src="img/mysy.png" alt="MYSY">
                </div>
                <div class="brand-item">
                    <img src="img/tata.png" alt="TATA">
                </div>
                <div class="brand-item">
                    <img src="img/sbi.png" alt="SBI">
                </div>
            </div>
        </div>
    </section>

    <!-- CTA SECTION -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of satisfied customers who trust Earth Cafe for their online government services</p>
            <a href="apply_service.php" class="btn btn-cta">Apply for Services Now</a>
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
                        <a href="apply_service.php" class="footer-link">Services</a>
                        <a href="About.php" class="footer-link">About Us</a>
                        <a href="contactus.php" class="footer-link">Contact Us</a>
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
                        <h5>Contact &amp; Newsletter</h5>
                        <p class="footer-contact">
                            <i class="fas fa-envelope"></i> earthcafe@gmail.com<br>
                            <i class="fas fa-phone"></i> +91 635 126 0524
                        </p>
                        <form method="POST" action="newsletter_subscribe.php">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>

</html>
