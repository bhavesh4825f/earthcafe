<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/password_reset.php';

$role = strtolower(trim((string)($_GET['role'] ?? $_POST['role'] ?? 'citizen')));
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$message = '';
$message_type = '';
$valid = false;
$token_row = null;

if ($role === 'citizen' && $token !== '') {
    $token_row = password_reset_verify_token($conn, $role, $token);
    $valid = $token_row !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (!$valid) {
        $message = 'Invalid or expired reset token.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'danger';
    } else {
        $ok = password_reset_apply_new_password($conn, $role, (int)$token_row['user_id'], $new_password);
        if ($ok) {
            password_reset_mark_used($conn, (int)$token_row['id']);
            $message = 'Password reset successful. Please login.';
            $message_type = 'success';
        } else {
            $message = 'Could not reset password.';
            $message_type = 'danger';
        }
    }
}

$loginLink = 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Earth Cafe</title>
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
            max-width: 340px;
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

        .reset-card {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 22px;
            box-shadow: 0 20px 65px rgba(4, 42, 35, 0.18);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .reset-head {
            padding: 28px 30px 20px;
            border-bottom: 1px solid #edf2ef;
        }

        .reset-title {
            margin: 0;
            font-size: 1.55rem;
            font-weight: 800;
            color: #173c36;
        }

        .reset-sub {
            margin: 10px 0 0;
            font-size: 0.95rem;
            color: #5f7872;
            line-height: 1.5;
        }

        .reset-body {
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

        .reset-footer {
            padding: 18px 30px 24px;
            text-align: center;
            border-top: 1px solid #edf2ef;
            background: #fbfdfc;
        }

        .reset-footer a {
            color: #0f6a5d;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .reset-footer a:hover {
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
            <p class="brand-tagline">Create a new secure password for your citizen account and continue using services safely.</p>

            <ul class="brand-features">
                <li><span class="feat-icon"><i class="fas fa-shield-halved"></i></span> Citizen account protected reset flow</li>
                <li><span class="feat-icon"><i class="fas fa-lock"></i></span> Strong password recommended</li>
                <li><span class="feat-icon"><i class="fas fa-clock"></i></span> Reset link expires for security</li>
            </ul>
        </div>
    </section>

    <section class="form-panel">
        <div class="reset-card">
            <div class="reset-head">
                <h3 class="reset-title">Set New Password</h3>
                <p class="reset-sub">Enter your new password below to complete account recovery.</p>
            </div>

            <div class="reset-body">
                <?php if ($message !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <?php if ($valid): ?>
                    <form method="POST" novalidate>
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                            <input type="password" class="form-control" name="new_password" minlength="8" placeholder="Enter new password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" minlength="8" placeholder="Re-enter new password" required>
                        </div>

                        <button type="submit" class="btn btn-reset">
                            <i class="fas fa-key"></i> Reset Password
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">This reset link is invalid or expired.</div>
                <?php endif; ?>
            </div>

            <div class="reset-footer">
                <a href="<?php echo htmlspecialchars($loginLink); ?>"><i class="fas fa-sign-in-alt"></i> Back to Citizen Login</a>
                <a href="terms_of_service.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
                <a href="privacy_policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
