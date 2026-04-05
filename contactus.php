<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
$username = '';
$email = '';
$message_text = '';
$flash = '';
$flash_type = '';

if ($is_logged_in) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $username = $user['username'] ?? '';
        $email = $user['email'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    if (!enforce_rate_limit('contact_submit', 8, 300)) {
        $flash = "Too many messages submitted. Please wait and try again.";
        $flash_type = "danger";
    } elseif (!$is_logged_in) {
        $flash = "Please login to send a message.";
        $flash_type = "danger";
    } else {
        $message_text = trim($_POST['message'] ?? '');
        if ($message_text === '') {
            $flash = "Message field cannot be empty.";
            $flash_type = "danger";
        } else {
            $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $message_text);
            if ($stmt->execute()) {
                $flash = "Message sent successfully.";
                $flash_type = "success";
                $message_text = '';
            } else {
                $flash = "Unable to send message right now.";
                $flash_type = "danger";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Earth Cafe</title>
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
            background: linear-gradient(135deg, rgba(10, 40, 35, 0.7) 0%, rgba(8, 50, 61, 0.58) 100%), url('./img/contact-bg.jpg') center/cover no-repeat;
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

        /* CONTACT SECTION */
        .contact-section {
            padding: 80px 0;
            background: var(--ec-surface-soft);
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: start;
        }

        .contact-info h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 40px;
            color: #11342e;
        }

        .contact-item {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: var(--ec-radius-md);
            border: 1px solid var(--ec-border);
            box-shadow: var(--ec-shadow-sm);
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--ec-shadow-md);
            border-color: #bfd0bf;
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            flex-shrink: 0;
        }

        .contact-item-text h3 {
            font-weight: 700;
            margin-bottom: 8px;
            color: #11342e;
        }

        .contact-item-text p {
            color: var(--ec-text-muted);
            font-size: 0.95rem;
            margin: 0;
        }

        .contact-form-wrapper {
            background: white;
            padding: 40px;
            border-radius: var(--ec-radius-lg);
            border: 1px solid var(--ec-border);
            box-shadow: var(--ec-shadow-md);
        }

        .contact-form-wrapper h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 30px;
            color: #11342e;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--ec-text);
        }

        .form-control, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--ec-border);
            border-radius: var(--ec-radius-sm);
            font-size: 0.95rem;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s ease;
            color: var(--ec-text);
        }

        .form-control:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--ec-primary);
            box-shadow: 0 0 0 3px rgba(15, 106, 93, 0.12);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        .alert {
            padding: 14px 18px;
            border-radius: var(--ec-radius-sm);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .btn-send {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--ec-primary) 0%, var(--ec-primary-strong) 100%);
            color: white;
            border: none;
            border-radius: var(--ec-radius-sm);
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-send:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(9, 72, 63, 0.35);
        }

        .btn-send:disabled {
            opacity: 0.6;
            cursor: not-allowed;
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

            .contact-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .contact-info h2 {
                font-size: 1.8rem;
            }

            .contact-form-wrapper {
                padding: 30px 20px;
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

            .contact-section {
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
                        <a class="nav-link" href="About.php"><i class="fas fa-info-circle"></i> About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a>
                    </li>
                    <li class="nav-item">
                        <?php if (!$is_logged_in): ?>
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
            <h1>Get in Touch</h1>
            <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
        </div>
    </section>

    <!-- CONTACT SECTION -->
    <section class="contact-section">
        <div class="container">
            <div class="contact-container">
                <!-- Contact Info -->
                <div class="contact-info">
                    <h2>Contact Information</h2>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-item-text">
                            <h3>Address</h3>
                            <p>Earth Cafe, Surat, Gujarat, India</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="contact-item-text">
                            <h3>Phone</h3>
                            <p>+91 635 126 0524</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-item-text">
                            <h3>Email</h3>
                            <p>earthcafe@gmail.com</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-item-text">
                            <h3>Working Hours</h3>
                            <p>Monday - Friday: 9:00 AM - 6:00 PM<br>Saturday: 10:00 AM - 4:00 PM<br>Sunday: Closed</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="contact-form-wrapper">
                    <h3><i class="fas fa-comment-dots"></i> Send us a Message</h3>

                    <?php if ($flash !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?>">
                            <i class="fas fa-<?php echo $flash_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                            <?php echo htmlspecialchars($flash); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="contactus.php">
                        <?php echo csrf_input(); ?>

                        <div class="form-group">
                            <label for="name" class="form-label"><i class="fas fa-user"></i> Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                value="<?php echo htmlspecialchars($username); ?>" readonly 
                                placeholder="Your name">
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                value="<?php echo htmlspecialchars($email); ?>" readonly 
                                placeholder="your.email@example.com">
                        </div>

                        <div class="form-group">
                            <label for="message" class="form-label"><i class="fas fa-pen"></i> Message</label>
                            <textarea id="message" name="message" class="form-textarea" required 
                                placeholder="Enter your message here..."><?php echo htmlspecialchars($message_text); ?></textarea>
                        </div>

                        <?php if ($is_logged_in): ?>
                            <button type="submit" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-send" disabled>
                                <i class="fas fa-lock"></i> Login to Send Message
                            </button>
                            <p style="text-align: center; margin-top: 15px; color: var(--ec-text-muted);">
                                <a href="login.php" style="color: var(--ec-primary); text-decoration: none; font-weight: 600;">Click here to login</a> and send us a message
                            </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
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
