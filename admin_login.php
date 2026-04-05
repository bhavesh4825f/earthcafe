<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

$login_error = '';

// Bootstrap admin table/default account once for secure DB-backed admin auth.
$conn->query("
    CREATE TABLE IF NOT EXISTS admins (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$default_admin_email = 'admin@gmail.com';
$check_admin = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
$check_admin->bind_param("s", $default_admin_email);
$check_admin->execute();
$has_admin = $check_admin->get_result()->fetch_assoc();
$check_admin->close();
if (!$has_admin) {
    $name = 'Administrator';
    $hashed_password = password_hash('admin@1234', PASSWORD_DEFAULT);
    $insert_admin = $conn->prepare("INSERT INTO admins (name, email, password, is_active) VALUES (?, ?, ?, 1)");
    $insert_admin->bind_param("sss", $name, $default_admin_email, $hashed_password);
    $insert_admin->execute();
    $insert_admin->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    if (!enforce_rate_limit('admin_login', 10, 300)) {
        $login_error = "Too many attempts. Please wait a few minutes.";
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login_error === '' && $email === '') {
        $login_error = "Email is required.";
    } elseif ($login_error === '' && $password === '') {
        $login_error = "Password is required.";
    } elseif ($login_error === '') {
        $stmt = $conn->prepare("SELECT id, name, email, password, is_active FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && (int)$admin['is_active'] === 1 && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_email'] = $admin['email'];
            header("Location: admin/dashboard.php");
            exit();
        }

        $login_error = "Invalid admin credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Earth Cafe</title>
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

        /* LEFT brand panel */
        .brand-panel {
            flex: 1 1 0;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 52px;
            background: linear-gradient(160deg, #1a1a2e 0%, #16213e 55%, #0f3460 100%);
            overflow: hidden;
            min-height: 100vh;
        }

        .brand-panel::before,
        .brand-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.08);
        }
        .brand-panel::before { width: 520px; height: 520px; bottom: -160px; right: -160px; }
        .brand-panel::after  { width: 320px; height: 320px; top: -100px; left: -100px; }

        .brand-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
        }
        .brand-glow-1 { width: 360px; height: 360px; background: #e2bc17; top: -60px; right: -60px; }
        .brand-glow-2 { width: 280px; height: 280px; background: #ffffff; bottom: -80px; left: -60px; }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: #fff;
        }

        .brand-logo-wrap {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.13);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 2px solid rgba(255,255,255,0.22);
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
        }
        .brand-logo-wrap img { width: 56px; height: 56px; object-fit: contain; }

        .brand-name {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0.01em;
            margin-bottom: 14px;
        }
        .brand-name span { color: #f0c84a; }

        .brand-tagline {
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.85;
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto 40px;
        }

        .brand-features {
            list-style: none;
            text-align: left;
            max-width: 300px;
            margin: 0 auto 42px;
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
            width: 34px; height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.13);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .brand-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.7);
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.22s;
        }
        .brand-back:hover { color: #fff; }

        /* RIGHT form panel */
        .form-panel {
            width: min(520px, 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #ffffff;
            padding: 56px 52px;
            overflow-y: auto;
        }

        .fp-head { margin-bottom: 32px; }
        .fp-head h2 {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            color: #0a2820;
            margin-bottom: 6px;
        }
        .fp-head p { color: #6b8080; font-size: 0.95rem; margin: 0; }

        .field-group { margin-bottom: 20px; }

        .field-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: #0a2820;
            margin-bottom: 7px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .field-wrap { position: relative; }
        .field-wrap .field-icon {
            position: absolute;
            left: 15px; top: 50%;
            transform: translateY(-50%);
            color: #7da898;
            font-size: 0.9rem;
            pointer-events: none;
        }

        .field-input {
            width: 100%;
            border: 1.5px solid #ccddd6;
            border-radius: 10px;
            padding: 12px 44px 12px 42px;
            font-size: 0.95rem;
            font-family: inherit;
            color: #0a2820;
            background: #f8fdfb;
            transition: border-color 0.22s, box-shadow 0.22s, background 0.22s;
            outline: none;
        }
        .field-input:focus {
            border-color: #0f6a5d;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(15, 106, 93, 0.13);
        }
        .field-input.has-toggle { padding-right: 48px; }

        .toggle-pw {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #7da898;
            cursor: pointer;
            font-size: 0.9rem;
            padding: 4px;
            transition: color 0.22s;
        }
        .toggle-pw:hover { color: #0f6a5d; }

        .btn-main {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%);
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: transform 0.22s, box-shadow 0.22s;
            box-shadow: 0 4px 18px rgba(15, 52, 96, 0.34);
            margin-top: 8px;
        }
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(15, 52, 96, 0.46);
        }
        .btn-main:active { transform: translateY(0); }

        .auth-alert {
            border-radius: 10px;
            padding: 13px 16px;
            margin-bottom: 22px;
            font-size: 0.92rem;
            font-weight: 600;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .auth-alert-danger {
            background: #fff1f1;
            color: #8b1f1f;
            border-left: 4px solid #c0392b;
        }
        .auth-alert i { margin-top: 1px; flex-shrink: 0; }

        .link-subtle {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f6a5d;
            text-decoration: none;
            transition: color 0.22s;
        }
        .link-subtle:hover { color: #09483f; }

        .footer-links {
            margin-top: 28px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }
        .footer-links a {
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            color: #5a7a72;
            transition: color 0.22s;
        }
        .footer-links a:hover { color: #0f6a5d; }

        @media (max-width: 860px) {
            .brand-panel { display: none; }
            .form-panel { width: 100%; padding: 44px 28px; }
        }
        @media (max-width: 420px) {
            .form-panel { padding: 32px 18px; }
        }
    </style>
</head>
<body>
<div class="login-split">

    <!-- LEFT brand panel -->
    <div class="brand-panel">
        <div class="brand-glow brand-glow-1"></div>
        <div class="brand-glow brand-glow-2"></div>
        <div class="brand-content">
            <div class="brand-logo-wrap">
                <img src="./img/footer-logo.png" alt="Earth Cafe Logo">
            </div>
            <div class="brand-name">Earth<span>Cafe</span></div>
            <p class="brand-tagline">
                Secure administrator access — manage users, services, and operations from one central hub.
            </p>
            <ul class="brand-features">
                <li>
                    <span class="feat-icon"><i class="fas fa-users-cog"></i></span>
                    Full user &amp; employee management
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-clipboard-list"></i></span>
                    Review and approve service requests
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-chart-bar"></i></span>
                    Analytics, audit logs &amp; reports
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-shield-alt"></i></span>
                    Multi-layer security &amp; rate limiting
                </li>
            </ul>
            <a href="index.php" class="brand-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- RIGHT form panel -->
    <div class="form-panel">
        <div class="fp-head">
            <h2>Administrator Login</h2>
            <p>Sign in with your admin credentials to continue.</p>
        </div>

        <?php if ($login_error !== ''): ?>
            <div class="auth-alert auth-alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($login_error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <?php echo csrf_input(); ?>

            <div class="field-group">
                <label class="field-label" for="admin-email">Email Address</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="admin-email" name="email"
                           class="field-input"
                           placeholder="admin@gmail.com"
                           autocomplete="email" required>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label" for="admin-password">Password</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="admin-password" name="password"
                           class="field-input has-toggle"
                           placeholder="Enter your password"
                           autocomplete="current-password" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('admin-password','icon-pw')" tabindex="-1">
                        <i class="fas fa-eye" id="icon-pw"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-main">
                <i class="fas fa-sign-in-alt"></i> Sign In as Admin
            </button>
        </form>

        <div class="footer-links">
            <a href="forgot_password.php"><i class="fas fa-unlock-alt"></i> Forgot password?</a>
            <a href="employee/login.php"><i class="fas fa-user-tie"></i> Go to Employee Login</a>
            <a href="login.php"><i class="fas fa-user"></i> Go to Citizen Login</a>
            <a href="terms_of_service.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
            <a href="privacy_policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a>
        </div>
    </div>
</div>

<script>
    function togglePw(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
