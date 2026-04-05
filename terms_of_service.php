<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Earth Cafe</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="icon" type="image/jpg" href="img/ashok-stambh.jpg">
    <style>
        body {
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--ec-surface-soft);
            color: var(--ec-text);
        }

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
        }

        .nav-link:hover {
            color: var(--ec-primary) !important;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
        }

        .hero {
            background: linear-gradient(135deg, rgba(10, 40, 35, 0.78) 0%, rgba(8, 50, 61, 0.64) 100%), url('./img/about-home-image.jpg') center/cover no-repeat;
            color: #fff;
            padding: 94px 0;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 14px;
            text-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .hero p {
            font-size: 1.05rem;
            opacity: 0.95;
        }

        .policy-wrap {
            padding: 52px 0 70px;
        }

        .policy-wrap .container {
            max-width: 980px;
        }

        .policy-card {
            background: #fff;
            border: 1px solid var(--ec-border);
            border-radius: var(--ec-radius-lg);
            box-shadow: var(--ec-shadow-md);
            padding: 36px;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .policy-card h2 {
            color: #11342e;
            font-size: 1.35rem;
            font-weight: 700;
            margin-top: 22px;
            margin-bottom: 12px;
            font-family: 'Sora', 'Manrope', 'Segoe UI', sans-serif;
        }

        .policy-card p,
        .policy-card li {
            color: var(--ec-text-muted);
            line-height: 1.8;
            font-size: 0.98rem;
        }

        .policy-card ul {
            margin-bottom: 14px;
        }

        .policy-meta {
            background: var(--ec-surface-soft);
            border: 1px dashed #c7d7ce;
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 20px;
            color: #2c544c;
            font-weight: 600;
            font-size: 0.94rem;
        }

        .footer {
            background: linear-gradient(135deg, rgba(11, 35, 30, 0.95) 0%, rgba(18, 58, 68, 0.94) 100%), url('./img/footer-bg.jpg') center/cover no-repeat fixed;
            background-size: cover;
            color: white;
            padding: 60px 0 20px;
            position: relative;
            margin-top: 100px;
            overflow: hidden;
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

        .footer .row {
            row-gap: 24px;
        }

        .footer-section {
            height: 100%;
        }

        .footer-brand-section {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            gap: 14px;
        }

        .footer-logo {
            height: 64px;
            width: auto;
            max-width: 64px;
            object-fit: contain;
            display: block;
            filter: brightness(0) invert(1);
        }

        .footer-brand-text h5 {
            color: #ffffff;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .footer-brand-text p {
            color: rgba(255, 255, 255, 0.7);
            margin: 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-section h5 {
            margin-bottom: 20px;
            font-weight: 700;
            color: #ffffff;
        }

        .footer-link {
            color: rgba(255, 255, 255, 0.86);
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
            line-height: 1.5;
            transition: color 0.2s ease;
        }

        .footer-link:hover {
            color: #ffc107;
        }

        .footer-muted {
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.7;
            margin: 0;
        }

        .footer-contact {
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.8;
            margin-bottom: 10px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 28px;
            padding-top: 20px;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
        }

        .footer-bottom p {
            margin: 0;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .policy-card {
                padding: 24px;
            }

            .footer {
                background-attachment: scroll;
                padding: 52px 0 24px;
            }

            .footer-section h5 {
                margin-bottom: 12px;
            }
        }
    </style>
</head>
<body>
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
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="apply_service.php"><i class="fas fa-briefcase"></i> Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="About.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                    <li class="nav-item">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a class="btn btn-login ec-btn ec-btn-primary" href="login.php"><i class="fas fa-sign-in-alt"></i> Login / Sign Up</a>
                        <?php else: ?>
                            <a class="btn btn-login ec-btn ec-btn-primary" href="client_dashboard.php"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?></a>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="hero">
        <div class="container">
            <h1>Terms of Service</h1>
            <p>Please review these terms before using Earth Cafe services.</p>
        </div>
    </section>

    <section class="policy-wrap">
        <div class="container">
            <div class="policy-card">
                <div class="policy-meta">Effective Date: March 16, 2026 | Last Updated: March 16, 2026</div>

                <p>These Terms of Service govern your access to and use of Earth Cafe website and related services. By creating an account, applying for services, or using this platform, you agree to these terms.</p>

                <h2>1. Eligibility and Account Responsibility</h2>
                <ul>
                    <li>You must provide accurate information during registration and service applications.</li>
                    <li>You are responsible for maintaining the confidentiality of your login credentials and OTP codes.</li>
                    <li>You are responsible for all activity performed through your account.</li>
                </ul>

                <h2>2. Acceptable Use</h2>
                <ul>
                    <li>Use Earth Cafe only for lawful purposes and genuine service requests.</li>
                    <li>Do not submit false, misleading, forged, or unauthorized documents.</li>
                    <li>Do not attempt to disrupt, damage, or gain unauthorized access to the platform.</li>
                </ul>

                <h2>3. Service Processing and Timelines</h2>
                <p>Earth Cafe facilitates online service workflows and document submissions. Processing timelines may vary depending on application completeness, verification requirements, and external dependencies.</p>

                <h2>4. Payments and Refunds</h2>
                <ul>
                    <li>Applicable fees are shown before payment and must be paid using approved payment methods.</li>
                    <li>Payment confirmation is issued after successful transaction completion.</li>
                    <li>Refund requests, if applicable, are reviewed case-by-case according to service status and policy rules.</li>
                </ul>

                <h2>5. Document and Data Accuracy</h2>
                <p>You confirm that uploaded documents and submitted information are correct and legally valid. Incorrect information may result in rejection, delays, or account restrictions.</p>

                <h2>6. Suspension or Termination</h2>
                <p>Earth Cafe may suspend or terminate access where there is suspected fraud, repeated policy violations, misuse of services, or security risk.</p>

                <h2>7. Limitation of Liability</h2>
                <p>Earth Cafe is not liable for indirect or consequential losses resulting from delays, third-party outages, user-side errors, or inaccurate information provided by users.</p>

                <h2>8. Policy Updates</h2>
                <p>We may update these Terms of Service from time to time. The updated version will be posted on this page with a revised effective date.</p>

                <h2>9. Contact</h2>
                <p>If you have any questions about these terms, contact us at earthcafe@gmail.com.</p>
            </div>
        </div>
    </section>

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
                        <a href="index.php#services" class="footer-link">Services</a>
                        <a href="index.php#about" class="footer-link">About Us</a>
                        <a href="terms_of_service.php" class="footer-link">Terms of Service</a>
                        <a href="privacy_policy.php" class="footer-link">Privacy Policy</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-section">
                        <h5>Account</h5>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="footer-link">Login</a>
                            <a href="login.php" class="footer-link">Sign Up</a>
                        <?php else: ?>
                            <a href="client_dashboard.php" class="footer-link">Dashboard</a>
                            <a href="logout.php" class="footer-link">Logout</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="footer-section">
                        <h5>Contact</h5>
                        <p class="footer-contact">
                            <i class="fas fa-envelope"></i> support@earthcafe.com<br>
                            <i class="fas fa-phone"></i> +91 1234-567-890
                        </p>
                        <a href="contactus.php" class="footer-link">Contact Us</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 Earth Cafe. All Rights Reserved. | Powered by Earth Cafe Team</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
