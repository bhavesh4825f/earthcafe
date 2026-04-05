<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/password_reset.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $email = trim((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please provide a valid email.';
        $message_type = 'danger';
    } else {
        ensure_password_reset_tables($conn);
        $role = 'citizen';
        $user = password_reset_find_user($conn, $role, $email);
        if ($user) {
            $token = password_reset_issue_token($conn, $role, (int)$user['id'], $email);
            password_reset_send_email($email, $role, $token);
        }
        $message = 'If an account exists, a reset link has been sent to your email.';
        $message_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Earth Cafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="icon" type="image/jpg" href="./img/ashok-stambh.jpg">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0b2e28;
        }

        .login-split {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .brand-panel {
            flex: 1 1 0;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 52px;
            background: linear-gradient(160deg, #09483f 0%, #0f6a5d 55%, #0c7a6a 100%);
            overflow: hidden;
            min-height: 100vh;
        }

        .brand-panel::before,
        .brand-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(255, 255, 255, 0.08);
        }

        .brand-panel::before {
            width: 520px;
            height: 520px;
            bottom: -160px;
            right: -160px;
        }

        .brand-panel::after {
            width: 320px;
            height: 320px;
            top: -100px;
            left: -100px;
        }

        .brand-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
        }

        .brand-glow-1 {
            width: 360px;
            height: 360px;
            background: #d29a17;
            top: -60px;
            right: -60px;
        }

        .brand-glow-2 {
            width: 280px;
            height: 280px;
            background: #ffffff;
            bottom: -80px;
            left: -60px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: #fff;
        }

        .brand-logo-wrap {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 2px solid rgba(255, 255, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.22);
        }

        .brand-logo-wrap img {
            width: 56px;
            height: 56px;
            object-fit: contain;
        }

        .brand-name {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0.01em;
            margin-bottom: 14px;
        }

        .brand-name span {
            color: #f0c84a;
        }

        .brand-tagline {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto 40px;
        }

        .brand-features {
            list-style: none;
            text-align: left;
            max-width: 300px;
            margin: 0 auto;
        }

        .brand-features li {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.92rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 14px;
        }

        .brand-features li .feat-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #f3d46d;
        }

        .form-panel {
            flex: 1 1 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 38px;
            background: #f6fbf9;
            min-height: 100vh;
        }

        .forgot-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 20px 65px rgba(4, 42, 35, 0.18);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .forgot-head {
            padding: 28px 30px 20px;
            border-bottom: 1px solid #edf2ef;
        }

        .forgot-title {
            margin: 0;
            font-size: 1.55rem;
            font-weight: 800;
            color: #173c36;
        }

        .forgot-sub {
            margin: 10px 0 0;
            font-size: 0.95rem;
            color: #5f7872;
            line-height: 1.5;
        }

        .forgot-body {
            padding: 26px 30px 30px;
        }

        .form-label {
            font-weight: 700;
            margin-bottom: 8px;
            color: #1f4a43;
            font-size: 0.92rem;
        }

        .form-control {
            border-radius: 12px;
            border: 1.4px solid #d6e4df;
            padding: 12px 14px;
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }

        .form-control:focus {
            border-color: #0f6a5d;
            box-shadow: 0 0 0 0.2rem rgba(15, 106, 93, 0.15);
        }

        .btn-reset {
            background: linear-gradient(135deg, #0f6a5d 0%, #0b4f45 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .btn-reset:hover {
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(11, 79, 69, 0.28);
        }

        .forgot-footer {
            padding: 18px 30px 24px;
            text-align: center;
            border-top: 1px solid #edf2ef;
            background: #fbfdfc;
        }

        .forgot-footer a {
            color: #0f6a5d;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-footer a:hover {
            color: #0b4f45;
            text-decoration: underline;
        }

        .alert {
            font-size: 0.9rem;
            border-radius: 12px;
        }

        @media (max-width: 991px) {
            .login-split {
                flex-direction: column;
            }

            .brand-panel,
            .form-panel {
                min-height: auto;
            }

            .brand-panel {
                padding: 48px 28px;
            }

            .form-panel {
                padding: 24px 14px 30px;
            }
        }
    </style>
</head>
<body>
<div class="login-split">
    <section class="brand-panel">
        <span class="brand-glow brand-glow-1"></span>
        <span class="brand-glow brand-glow-2"></span>

        <div class="brand-content">
            <div class="brand-logo-wrap">
                <img src="./img/ashok-stambh.jpg" alt="Earth Cafe">
            </div>
            <h1 class="brand-name">Earth <span>Cafe</span></h1>
            <p class="brand-tagline">Secure password recovery for citizen accounts with trusted identity protection.</p>

            <ul class="brand-features">
                <li><span class="feat-icon"><i class="fas fa-shield-halved"></i></span> Citizens-only secure reset flow</li>
                <li><span class="feat-icon"><i class="fas fa-key"></i></span> One-time email reset link</li>
                <li><span class="feat-icon"><i class="fas fa-clock"></i></span> Link expires automatically in 1 hour</li>
            </ul>
        </div>
    </section>

    <section class="form-panel">
        <div class="forgot-card">
            <div class="forgot-head">
                <h3 class="forgot-title">Forgot Password</h3>
                <p class="forgot-sub">Enter your citizen account email and we will send a secure password reset link.</p>
            </div>

            <div class="forgot-body">
                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <?php echo csrf_input(); ?>

                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-envelope"></i> Citizen Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="Enter your registered citizen email" required>
                    </div>

                    <button type="submit" class="btn btn-reset">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>
            </div>

            <div class="forgot-footer">
                <span style="color:#5f7872; font-size:0.9rem;">Remember your password?</span>
                <a href="login.php"><i class="fas fa-sign-in-alt"></i> Back to Citizen Login</a>
                <a href="terms_of_service.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
                <a href="privacy_policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
