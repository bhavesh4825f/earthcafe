<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth_otp.php';

$login_error = '';
$login_success = '';
$email = '';
$login_otp_id = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $action = trim((string)($_POST['action'] ?? 'password_login'));
    $email = trim((string)($_POST['email'] ?? ''));

    if ($action === 'send_login_otp') {
        if (empty($email)) {
            $login_error = "Email is required to send OTP!";
        } else {
            $stmt = $conn->prepare("SELECT id, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $login_error = "User not found! Please sign up first.";
            } elseif ((int)$row['is_active'] !== 1) {
                $login_error = "Your account is deactivated. Please contact administrator.";
            } else {
                $otpResult = otp_issue_email('login_otp', $email, 'logging in to your Earth Cafe account');
                if ($otpResult['ok']) {
                    $login_success = "OTP sent successfully. Please check your email.";
                } else {
                    $login_error = $otpResult['message'];
                }
            }
        }
    } elseif ($action === 'otp_login') {
        $otp = trim((string)($_POST['otp_code'] ?? ''));
        $otp_id = trim((string)($_POST['otp_id'] ?? ''));

        if (empty($email)) {
            $login_error = "Email is required!";
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $login_error = "Please enter a valid 6-digit OTP.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, role, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $login_error = "User not found! Please sign up first.";
            } elseif ((int)$row['is_active'] !== 1) {
                $login_error = "Your account is deactivated. Please contact administrator.";
            } elseif (!otp_verify_email_code('login_otp', $email, $otp, $otp_id)) {
                $login_error = "Invalid or expired OTP.";
            } else {
                rotate_session_on_login();
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['logged_in'] = true;
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'] ?? 'citizen';
                header("Location: client_dashboard.php");
                exit();
            }
        }
    } else {
        if (!enforce_rate_limit('citizen_login', 12, 300)) {
            $login_error = "Too many attempts. Please wait a few minutes.";
        }
        $password = $_POST['password'] ?? '';

        if (empty($email) && $login_error === '') {
            $login_error = "Email is required!";
        } elseif (empty($password) && $login_error === '') {
            $login_error = "Password is required!";
        } elseif ($login_error === '') {
            $stmt = $conn->prepare("SELECT id, username, password, email, role, is_active FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                $login_error = "User not found! Please sign up first.";
            } else {
                $row = $result->fetch_assoc();
                if ((int)$row['is_active'] !== 1) {
                    $login_error = "Your account is deactivated. Please contact administrator.";
                } elseif (password_verify($password, $row['password'])) {
                    rotate_session_on_login();
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['role'] = $row['role'] ?? 'citizen';
                    header("Location: client_dashboard.php");
                    exit();
                } else {
                    $login_error = "Password is incorrect!";
                }
            }
            $stmt->close();
        }
    }
}

$active_tab = 'password';
if (!empty($login_error) || !empty($login_success)) {
    $posted_action = $_POST['action'] ?? '';
    if ($posted_action === 'send_login_otp' || $posted_action === 'otp_login') {
        $active_tab = 'otp';
    }
}

if ($email !== '') {
    $loginOtpStatus = otp_get_email_status('login_otp', $email);
    if (!empty($loginOtpStatus['otp_id']) && empty($loginOtpStatus['expired'])) {
        $login_otp_id = (string)$loginOtpStatus['otp_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Earth Cafe</title>
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

        /* ── Split layout ───────────────────────────────── */
        .login-split {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* LEFT branding panel */
        .brand-panel {
            flex: 1 1 0;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 52px;
            background:
                linear-gradient(160deg, #09483f 0%, #0f6a5d 55%, #0c7a6a 100%);
            overflow: hidden;
            min-height: 100vh;
        }

        /* decorative rings */
        .brand-panel::before,
        .brand-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.08);
        }
        .brand-panel::before {
            width: 520px; height: 520px;
            bottom: -160px; right: -160px;
        }
        .brand-panel::after {
            width: 320px; height: 320px;
            top: -100px; left: -100px;
        }

        .brand-glow {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.18;
        }
        .brand-glow-1 {
            width: 360px; height: 360px;
            background: #d29a17;
            top: -60px; right: -60px;
        }
        .brand-glow-2 {
            width: 280px; height: 280px;
            background: #ffffff;
            bottom: -80px; left: -60px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: #fff;
        }

        .brand-logo-wrap {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 2px solid rgba(255,255,255,0.25);
            box-shadow: 0 8px 32px rgba(0,0,0,0.22);
        }

        .brand-logo-wrap img {
            width: 56px; height: 56px;
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
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .brand-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,255,255,0.75);
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

        .fp-head {
            margin-bottom: 32px;
        }
        .fp-head h2 {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 1.9rem;
            font-weight: 800;
            color: #0a2820;
            margin-bottom: 6px;
        }
        .fp-head p {
            color: #6b8080;
            font-size: 0.95rem;
            margin: 0;
        }

        /* Tab switcher */
        .auth-tabs {
            display: flex;
            background: #eef4f2;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 28px;
            gap: 4px;
        }
        .auth-tab-btn {
            flex: 1;
            border: none;
            background: transparent;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.88rem;
            font-weight: 700;
            color: #5a7a72;
            cursor: pointer;
            transition: all 0.22s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .auth-tab-btn.active {
            background: #ffffff;
            color: #0f6a5d;
            box-shadow: 0 2px 10px rgba(13, 36, 33, 0.12);
        }

        /* Tab panes */
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* Form fields */
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

        .field-wrap {
            position: relative;
        }
        .field-wrap .field-icon {
            position: absolute;
            left: 15px;
            top: 50%;
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
            right: 14px;
            top: 50%;
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

        /* OTP input */
        .otp-digit-row {
            display: flex;
            gap: 8px;
        }
        .otp-digit-row .field-input {
            flex: 1;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            padding-left: 0; padding-right: 0;
        }

        /* Row helpers */
        .field-row-split {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .check-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.88rem;
            color: #5a7a72;
            cursor: pointer;
        }
        .check-label input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #0f6a5d;
            cursor: pointer;
        }
        .link-subtle {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0f6a5d;
            text-decoration: none;
            transition: color 0.22s;
        }
        .link-subtle:hover { color: #09483f; }

        /* Main CTA */
        .btn-main {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            font-size: 0.98rem;
            font-weight: 700;
            font-family: inherit;
            background: linear-gradient(135deg, #0f6a5d 0%, #09483f 100%);
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: transform 0.22s, box-shadow 0.22s;
            box-shadow: 0 4px 18px rgba(9, 72, 63, 0.28);
        }
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(9, 72, 63, 0.38);
        }
        .btn-main:active { transform: translateY(0); }

        /* Secondary / ghost button */
        .btn-ghost {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #0f6a5d;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            background: transparent;
            color: #0f6a5d;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: background 0.22s, color 0.22s;
            margin-bottom: 10px;
        }
        .btn-ghost:hover {
            background: #eef4f2;
        }

        /* Alerts */
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
        .auth-alert-success {
            background: #edfbf2;
            color: #155724;
            border-left: 4px solid #22864a;
        }
        .auth-alert i { margin-top: 1px; flex-shrink: 0; }

        /* Divider */
        .or-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 4px 0 22px;
            color: #a0b8b0;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .or-divider::before, .or-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #d6e6df;
        }

        /* Signup link */
        .signup-row {
            margin-top: 28px;
            text-align: center;
            font-size: 0.92rem;
            color: #6b8080;
        }
        .signup-row a {
            color: #0f6a5d;
            font-weight: 700;
            text-decoration: none;
        }
        .signup-row a:hover { color: #09483f; }

        .legal-row {
            margin-top: 10px;
            text-align: center;
            font-size: 0.84rem;
            color: #7b8f8d;
        }
        .legal-row a {
            color: #5f7872;
            text-decoration: none;
            font-weight: 600;
            margin: 0 8px;
        }
        .legal-row a:hover {
            color: #0f6a5d;
            text-decoration: underline;
        }

        /* OTP send status */
        .otp-hint {
            font-size: 0.82rem;
            color: #7da898;
            margin-top: 6px;
        }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 860px) {
            .brand-panel { display: none; }
            .form-panel {
                width: 100%;
                padding: 44px 28px;
            }
        }
        @media (max-width: 420px) {
            .form-panel { padding: 32px 18px; }
        }
    </style>
</head>
<body>
<div class="login-split">

    <!-- ── LEFT brand panel ─────────────────────────── -->
    <div class="brand-panel">
        <div class="brand-glow brand-glow-1"></div>
        <div class="brand-glow brand-glow-2"></div>

        <div class="brand-content">
            <div class="brand-logo-wrap">
                <img src="./img/footer-logo.png" alt="Earth Cafe Logo">
            </div>

            <div class="brand-name">Earth<span>Cafe</span></div>
            <p class="brand-tagline">
                Your one-stop portal for government services — fast, transparent, and paperless.
            </p>

            <ul class="brand-features">
                <li>
                    <span class="feat-icon"><i class="fas fa-file-alt"></i></span>
                    Apply for certificates &amp; documents online
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-bell"></i></span>
                    Real-time status updates &amp; notifications
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-shield-alt"></i></span>
                    Secure, OTP-based authentication
                </li>
                <li>
                    <span class="feat-icon"><i class="fas fa-download"></i></span>
                    Download approved certificates instantly
                </li>
            </ul>

            <a href="index.php" class="brand-back">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <!-- ── RIGHT form panel ─────────────────────────── -->
    <div class="form-panel">

        <div class="fp-head">
            <h2>Welcome back</h2>
            <p>Sign in to your Earth Cafe account to continue.</p>
        </div>

        <!-- Alert messages -->
        <?php if (!empty($login_error)): ?>
            <div class="auth-alert auth-alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($login_error); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($login_success)): ?>
            <div class="auth-alert auth-alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($login_success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Tab switcher -->
        <div class="auth-tabs" role="tablist">
            <button class="auth-tab-btn <?php echo $active_tab === 'password' ? 'active' : ''; ?>"
                    id="tab-pw" role="tab" aria-selected="<?php echo $active_tab === 'password' ? 'true' : 'false'; ?>"
                    onclick="switchTab('password')">
                <i class="fas fa-lock"></i> Password
            </button>
            <button class="auth-tab-btn <?php echo $active_tab === 'otp' ? 'active' : ''; ?>"
                    id="tab-otp" role="tab" aria-selected="<?php echo $active_tab === 'otp' ? 'true' : 'false'; ?>"
                    onclick="switchTab('otp')">
                <i class="fas fa-mobile-alt"></i> One-Time Password
            </button>
        </div>

        <!-- ── TAB: Password login ── -->
        <div class="tab-pane <?php echo $active_tab === 'password' ? 'active' : ''; ?>" id="pane-password">
            <form method="POST" id="form-pw" novalidate>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="action" value="password_login">

                <div class="field-group">
                    <label class="field-label" for="pw-email">Email Address</label>
                    <div class="field-wrap">
                        <span class="field-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="pw-email" name="email"
                               class="field-input"
                               placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($active_tab === 'password' ? $email : ''); ?>"
                               autocomplete="email" required>
                    </div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="pw-password">Password</label>
                    <div class="field-wrap">
                        <span class="field-icon"><i class="fas fa-lock"></i></span>
                        <input type="password" id="pw-password" name="password"
                               class="field-input has-toggle"
                               placeholder="Enter your password"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" onclick="togglePw('pw-password','icon-pw')" tabindex="-1">
                            <i class="fas fa-eye" id="icon-pw"></i>
                        </button>
                    </div>
                </div>

                <div class="field-row-split">
                    <label class="check-label">
                        <input type="checkbox" name="remember"> Remember me
                    </label>
                    <a href="forgot_password.php" class="link-subtle">Forgot password?</a>
                </div>

                <button type="submit" class="btn-main">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
        </div>

        <!-- ── TAB: OTP login ── -->
        <div class="tab-pane <?php echo $active_tab === 'otp' ? 'active' : ''; ?>" id="pane-otp">
            <form method="POST" id="form-otp" novalidate>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="otp_id" value="<?php echo htmlspecialchars($login_otp_id); ?>">

                <div class="field-group">
                    <label class="field-label" for="otp-email">Registered Email</label>
                    <div class="field-wrap">
                        <span class="field-icon"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="otp-email" name="email"
                               class="field-input"
                               placeholder="you@example.com"
                               value="<?php echo htmlspecialchars($active_tab === 'otp' ? $email : ''); ?>"
                               autocomplete="email" required>
                    </div>
                </div>

                <button type="submit" name="action" value="send_login_otp" class="btn-ghost" id="btn-send-otp">
                    <i class="fas fa-paper-plane"></i> Send OTP to Email
                </button>

                <div class="or-divider">then enter code</div>

                <div class="field-group">
                    <label class="field-label" for="otp-code">6-Digit OTP</label>
                    <div class="field-wrap">
                        <span class="field-icon"><i class="fas fa-key"></i></span>
                        <input type="text" id="otp-code" name="otp_code"
                               class="field-input"
                               maxlength="6" minlength="6" pattern="[0-9]{6}"
                               placeholder="e.g. 482 915"
                               inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    <p class="otp-hint"><i class="fas fa-info-circle"></i> Check your inbox — the code is valid for 10 minutes.</p>
                </div>

                <button type="submit" name="action" value="otp_login" class="btn-main">
                    <i class="fas fa-mobile-alt"></i> Verify &amp; Sign In
                </button>
            </form>
        </div>

        <div class="signup-row">
            Don't have an account? <a href="Signup.php">Create one — it's free</a>
        </div>
        <div class="legal-row">
            <a href="terms_of_service.php">Terms of Service</a>
            <a href="privacy_policy.php">Privacy Policy</a>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.auth-tab-btn').forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));

        document.getElementById('tab-' + (tab === 'password' ? 'pw' : 'otp')).classList.add('active');
        document.getElementById('tab-' + (tab === 'password' ? 'pw' : 'otp')).setAttribute('aria-selected', 'true');
        document.getElementById('pane-' + tab).classList.add('active');
    }

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

    /* OTP: auto-format spaces */
    const otpInput = document.getElementById('otp-code');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    /* Prevent duplicate OTP send without losing submit button action */
    const otpForm = document.getElementById('form-otp');
    if (otpForm) {
        otpForm.addEventListener('submit', function (event) {
            const submitter = event.submitter;
            if (!submitter || submitter.value !== 'send_login_otp') return;

            const email = document.getElementById('otp-email').value.trim();
            if (!email) return;

            // Delay disable so browser can include submitter name/value in form payload.
            setTimeout(() => {
                submitter.disabled = true;
                submitter.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            }, 0);
        });
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
