<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/auth_otp.php';

$signup_error = '';
$signup_success = '';
$username = '';
$email = '';
$terms_accepted = false;

const SIGNUP_PENDING_TTL_SECONDS = 900;
const SIGNUP_OTP_TTL_SECONDS = 600;
const SIGNUP_RESEND_COOLDOWN_SECONDS = 60;
const SIGNUP_MAX_RESENDS = 3;

function normalize_signup_email(string $email): string
{
    return strtolower(trim($email));
}

function signup_password_error(string $password): string
{
    if ($password === '') {
        return 'Password is required!';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long!';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must include at least one lowercase letter!';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must include at least one uppercase letter!';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must include at least one number!';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'Password must include at least one special character!';
    }

    return '';
}

function signup_request_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }

        $firstValue = trim(explode(',', $candidate)[0]);
        if (filter_var($firstValue, FILTER_VALIDATE_IP)) {
            return substr($firstValue, 0, 45);
        }
    }

    return 'unknown';
}

function clear_pending_signup_session(): void
{
    $pendingSignup = $_SESSION['pending_signup'] ?? null;
    if (is_array($pendingSignup) && !empty($pendingSignup['email'])) {
        otp_clear_context('signup_verify', (string)$pendingSignup['email']);
    }
    unset($_SESSION['pending_signup']);
}

function signup_pending_is_expired(?array $pendingSignup): bool
{
    if (!is_array($pendingSignup)) {
        return false;
    }

    $createdAt = (int)($pendingSignup['created_at'] ?? 0);
    if ($createdAt <= 0) {
        return true;
    }

    return ($createdAt + SIGNUP_PENDING_TTL_SECONDS) <= time();
}

function signup_pending_seconds_remaining(?array $pendingSignup): int
{
    if (!is_array($pendingSignup)) {
        return 0;
    }

    $createdAt = (int)($pendingSignup['created_at'] ?? 0);
    if ($createdAt <= 0) {
        return 0;
    }

    return max(0, ($createdAt + SIGNUP_PENDING_TTL_SECONDS) - time());
}

function signup_pending_has_consent(?array $pendingSignup): bool
{
    return is_array($pendingSignup)
        && !empty($pendingSignup['terms_accepted_at'])
        && !empty($pendingSignup['privacy_accepted_at']);
}

function user_table_column_exists(mysqli $conn, string $column): bool
{
    static $cache = [];

    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $stmt = $conn->prepare(
        'SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $table = 'users';
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$column] = (int)($row['cnt'] ?? 0) > 0;
    return $cache[$column];
}

$pending_signup = $_SESSION['pending_signup'] ?? null;
if (signup_pending_is_expired($pending_signup)) {
    clear_pending_signup_session();
    $pending_signup = null;
    $signup_error = 'Your signup session expired. Please enter your details again.';
} elseif (is_array($pending_signup) && !signup_pending_has_consent($pending_signup)) {
    clear_pending_signup_session();
    $pending_signup = null;
    $signup_error = 'Your previous signup draft is no longer valid. Please enter your details again.';
}

if (is_array($pending_signup)) {
    $username = (string)($pending_signup['username'] ?? '');
    $email = (string)($pending_signup['email'] ?? '');
    $terms_accepted = !empty($pending_signup['terms_accepted_at']);
}

function username_or_email_taken(mysqli $conn, string $username, string $email): array
{
    $stmt_uname = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt_uname->bind_param("s", $username);
    $stmt_uname->execute();
    $uname_taken = $stmt_uname->get_result()->num_rows > 0;
    $stmt_uname->close();

    $stmt_email = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt_email->bind_param("s", $email);
    $stmt_email->execute();
    $email_taken = $stmt_email->get_result()->num_rows > 0;
    $stmt_email->close();

    return ['username' => $uname_taken, 'email' => $email_taken];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $action = trim((string)($_POST['action'] ?? 'start_signup'));

    if ($action === 'start_signup') {
        $username = trim($_POST['username'] ?? '');
        $email = normalize_signup_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms_accepted = (string)($_POST['accept_terms'] ?? '') === '1';

        if (empty($username) || strlen($username) < 3) {
            $signup_error = "Username must be at least 3 characters long!";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $signup_error = "Username may only contain letters, numbers, and underscores!";
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $signup_error = "Please enter a valid email address!";
        } elseif (($passwordError = signup_password_error($password)) !== '') {
            $signup_error = $passwordError;
        } elseif ($password !== $confirm_password) {
            $signup_error = "Passwords do not match!";
        } elseif (!$terms_accepted) {
            $signup_error = "You must accept the Terms of Service and Privacy Policy to create an account.";
        } else {
            $taken = username_or_email_taken($conn, $username, $email);
            if ($taken['username']) {
                $signup_error = "Username already taken! Please choose a different one.";
            } elseif ($taken['email']) {
                $signup_error = "Email already registered! Please login instead.";
            } else {
                $consentAcceptedAt = date('Y-m-d H:i:s');
                $_SESSION['pending_signup'] = [
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'created_at' => time(),
                    'terms_accepted_at' => $consentAcceptedAt,
                    'privacy_accepted_at' => $consentAcceptedAt,
                    'consent_ip' => signup_request_ip(),
                    'resend_count' => 0,
                    'last_otp_sent_at' => 0,
                ];

                $pending_signup = $_SESSION['pending_signup'];
                $otpResult = otp_issue_email('signup_verify', $email, 'completing your Earth Cafe registration', SIGNUP_OTP_TTL_SECONDS);
                if ($otpResult['ok']) {
                    $_SESSION['pending_signup']['last_otp_sent_at'] = time();
                    $pending_signup = $_SESSION['pending_signup'];
                    $signup_success = "OTP sent to your email. Enter the code below to complete registration.";
                } else {
                    clear_pending_signup_session();
                    $pending_signup = null;
                    $signup_error = $otpResult['message'];
                }
            }
        }
    } elseif ($action === 'resend_signup_otp') {
        $pending_signup = $_SESSION['pending_signup'] ?? null;
        if (!is_array($pending_signup) || empty($pending_signup['email'])) {
            $signup_error = "Please fill registration details first.";
        } elseif (signup_pending_is_expired($pending_signup)) {
            clear_pending_signup_session();
            $pending_signup = null;
            $signup_error = 'Your signup session expired. Please enter your details again.';
        } elseif (!signup_pending_has_consent($pending_signup)) {
            clear_pending_signup_session();
            $pending_signup = null;
            $signup_error = 'Your previous signup draft is no longer valid. Please enter your details again.';
        } else {
            $email = (string)$pending_signup['email'];
            $username = (string)$pending_signup['username'];
            $terms_accepted = !empty($pending_signup['terms_accepted_at']);
            $lastOtpSentAt = (int)($pending_signup['last_otp_sent_at'] ?? 0);
            $resendCount = (int)($pending_signup['resend_count'] ?? 0);
            $cooldownRemaining = max(0, SIGNUP_RESEND_COOLDOWN_SECONDS - (time() - $lastOtpSentAt));

            if ($resendCount >= SIGNUP_MAX_RESENDS) {
                $signup_error = 'Maximum OTP resend attempts reached. Cancel and start signup again.';
            } elseif ($cooldownRemaining > 0) {
                $signup_error = 'Please wait ' . $cooldownRemaining . ' seconds before requesting another OTP.';
            } else {
                $otpResult = otp_issue_email('signup_verify', $email, 'completing your Earth Cafe registration', SIGNUP_OTP_TTL_SECONDS);
                if ($otpResult['ok']) {
                    $_SESSION['pending_signup']['resend_count'] = $resendCount + 1;
                    $_SESSION['pending_signup']['last_otp_sent_at'] = time();
                    $pending_signup = $_SESSION['pending_signup'];
                    $signup_success = 'A new OTP has been sent to your email.';
                } else {
                    $signup_error = $otpResult['message'];
                }
            }
        }
    } elseif ($action === 'cancel_signup_otp') {
        clear_pending_signup_session();
        $pending_signup = null;
        $username = '';
        $email = '';
        $terms_accepted = false;
        $signup_success = "Registration draft cleared. You can start again.";
    } elseif ($action === 'verify_signup_otp') {
        $pending_signup = $_SESSION['pending_signup'] ?? null;
        $otp = trim((string)($_POST['signup_otp'] ?? ''));
        $signup_otp_id = trim((string)($_POST['signup_otp_id'] ?? ''));

        if (!is_array($pending_signup)) {
            $signup_error = "Session expired. Please enter details and request OTP again.";
        } elseif (signup_pending_is_expired($pending_signup)) {
            clear_pending_signup_session();
            $pending_signup = null;
            $signup_error = 'Your signup session expired. Please enter your details again.';
        } elseif (!preg_match('/^\d{6}$/', $otp)) {
            $signup_error = "Please enter a valid 6-digit OTP.";
        } else {
            $username = (string)$pending_signup['username'];
            $email = (string)$pending_signup['email'];
            $password_hash = (string)$pending_signup['password_hash'];
            $termsAcceptedAt = (string)($pending_signup['terms_accepted_at'] ?? '');
            $privacyAcceptedAt = (string)($pending_signup['privacy_accepted_at'] ?? '');
            $consentIp = (string)($pending_signup['consent_ip'] ?? signup_request_ip());

            if ($termsAcceptedAt === '' || $privacyAcceptedAt === '') {
                clear_pending_signup_session();
                $pending_signup = null;
                $signup_error = 'Consent record is missing. Please start signup again.';
            } else {
                $verifyResult = otp_verify_email_code_detailed('signup_verify', $email, $otp, $signup_otp_id);

                if (!$verifyResult['ok']) {
                    if ($verifyResult['reason'] === 'expired' || $verifyResult['reason'] === 'not_found') {
                        $signup_error = 'OTP expired. Please request a new code.';
                    } elseif ($verifyResult['reason'] === 'otp_id_mismatch') {
                        $signup_error = 'OTP request mismatch detected. Please request a new code.';
                    } elseif ($verifyResult['reason'] === 'attempts_exceeded') {
                        $signup_error = 'Too many invalid OTP attempts. Please request a new code.';
                    } else {
                        $signup_error = 'Invalid OTP. ' . max(0, (int)($verifyResult['attempts_remaining'] ?? 0)) . ' attempt(s) remaining.';
                    }
                } else {
                    $taken = username_or_email_taken($conn, $username, $email);
                    if ($taken['username']) {
                        $signup_error = "Username already taken now. Please start again with a different username.";
                    } elseif ($taken['email']) {
                        $signup_error = "Email already registered now. Please login instead.";
                    } else {
                        $role = 'citizen';
                        $is_active = 1;
                        $insertColumns = ['username', 'email', 'password', 'role', 'is_active'];
                        $insertValues = [$username, $email, $password_hash, $role, $is_active];
                        $insertTypes = 'ssssi';

                        foreach ([
                            'terms_accepted_at' => $termsAcceptedAt,
                            'privacy_accepted_at' => $privacyAcceptedAt,
                            'consent_ip' => $consentIp,
                        ] as $column => $value) {
                            if (user_table_column_exists($conn, $column)) {
                                $insertColumns[] = $column;
                                $insertValues[] = $value;
                                $insertTypes .= 's';
                            }
                        }

                        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                        $sql = 'INSERT INTO users (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
                        $stmt = $conn->prepare($sql);

                        if (!$stmt) {
                            $signup_error = 'Error creating account! Please try again.';
                        } else {
                            bind_params_dynamic($stmt, $insertTypes, $insertValues);

                            if ($stmt->execute()) {
                                rotate_session_on_login();
                                $_SESSION['user_id'] = $conn->insert_id;
                                $_SESSION['username'] = $username;
                                $_SESSION['email'] = $email;
                                $_SESSION['role'] = 'citizen';
                                $_SESSION['logged_in'] = true;
                                clear_pending_signup_session();
                                header("Location: client_dashboard.php");
                                exit();
                            }

                            $signup_error = 'Error creating account! Please try again.';
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

$pending_signup = $_SESSION['pending_signup'] ?? null;
if (signup_pending_is_expired($pending_signup)) {
    clear_pending_signup_session();
    $pending_signup = null;
    if ($signup_error === '' && $signup_success === '') {
        $signup_error = 'Your signup session expired. Please enter your details again.';
    }
} elseif (is_array($pending_signup) && !signup_pending_has_consent($pending_signup)) {
    clear_pending_signup_session();
    $pending_signup = null;
    if ($signup_error === '' && $signup_success === '') {
        $signup_error = 'Your previous signup draft is no longer valid. Please enter your details again.';
    }
}

if (is_array($pending_signup)) {
    $username = (string)($pending_signup['username'] ?? $username);
    $email = (string)($pending_signup['email'] ?? $email);
    $terms_accepted = !empty($pending_signup['terms_accepted_at']);
}

$has_pending_signup = is_array($pending_signup);
$pending_signup_seconds_left = signup_pending_seconds_remaining($pending_signup);
$signup_otp_status = $has_pending_signup ? otp_get_email_status('signup_verify', (string)$pending_signup['email']) : null;
$signup_otp_id = is_array($signup_otp_status) ? (string)($signup_otp_status['otp_id'] ?? '') : '';
$signup_attempts_left = is_array($signup_otp_status) ? (int)($signup_otp_status['attempts_remaining'] ?? 0) : 0;
$signup_otp_seconds_left = is_array($signup_otp_status) ? (int)($signup_otp_status['seconds_until_expiry'] ?? 0) : 0;
$signup_resend_count = $has_pending_signup ? (int)($pending_signup['resend_count'] ?? 0) : 0;
$signup_resends_left = max(0, SIGNUP_MAX_RESENDS - $signup_resend_count);
$signup_resend_cooldown_remaining = 0;

if ($has_pending_signup) {
    $lastOtpSentAt = (int)($pending_signup['last_otp_sent_at'] ?? 0);
    if ($lastOtpSentAt > 0) {
        $signup_resend_cooldown_remaining = max(0, SIGNUP_RESEND_COOLDOWN_SECONDS - (time() - $lastOtpSentAt));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Earth Cafe</title>
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

        .login-split { display: flex; width: 100%; min-height: 100vh; }

        /* LEFT brand panel */
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
        .brand-panel::before, .brand-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,0.08);
        }
        .brand-panel::before { width: 520px; height: 520px; bottom: -160px; right: -160px; }
        .brand-panel::after  { width: 320px; height: 320px; top: -100px; left: -100px; }
        .brand-glow { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.18; }
        .brand-glow-1 { width: 360px; height: 360px; background: #d29a17; top: -60px; right: -60px; }
        .brand-glow-2 { width: 280px; height: 280px; background: #ffffff; bottom: -80px; left: -60px; }

        .brand-content { position: relative; z-index: 2; text-align: center; color: #fff; }

        .brand-logo-wrap {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 30px;
            border: 2px solid rgba(255,255,255,0.25);
            box-shadow: 0 8px 32px rgba(0,0,0,0.22);
        }
        .brand-logo-wrap img { width: 56px; height: 56px; object-fit: contain; }

        .brand-name {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 2.4rem; font-weight: 800;
            line-height: 1.1; letter-spacing: 0.01em;
            margin-bottom: 14px;
        }
        .brand-name span { color: #f0c84a; }

        .brand-tagline {
            font-size: 1rem; font-weight: 500; opacity: 0.85;
            line-height: 1.6; max-width: 320px; margin: 0 auto 40px;
        }

        .brand-features { list-style: none; text-align: left; max-width: 300px; margin: 0 auto 42px; }
        .brand-features li {
            display: flex; align-items: center; gap: 12px;
            font-size: 0.92rem; font-weight: 500; opacity: 0.9; margin-bottom: 14px;
        }
        .brand-features li .feat-icon {
            width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.85rem; flex-shrink: 0;
        }

        .brand-back {
            display: inline-flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.75); font-size: 0.88rem; font-weight: 600;
            text-decoration: none; transition: color 0.22s;
        }
        .brand-back:hover { color: #fff; }

        /* RIGHT form panel */
        .form-panel {
            width: min(560px, 100%);
            display: flex; flex-direction: column; justify-content: center;
            background: #ffffff; padding: 52px 52px; overflow-y: auto;
        }

        .fp-head { margin-bottom: 28px; }
        .fp-head h2 {
            font-family: 'Sora', 'Manrope', sans-serif;
            font-size: 1.9rem; font-weight: 800; color: #0a2820; margin-bottom: 6px;
        }
        .fp-head p { color: #6b8080; font-size: 0.95rem; margin: 0; }

        /* Fields */
        .field-group { margin-bottom: 18px; }
        .field-label {
            display: block; font-size: 0.82rem; font-weight: 700; color: #0a2820;
            margin-bottom: 7px; letter-spacing: 0.04em; text-transform: uppercase;
        }
        .field-wrap { position: relative; }
        .field-wrap .field-icon {
            position: absolute; left: 15px; top: 50%;
            transform: translateY(-50%); color: #7da898; font-size: 0.9rem; pointer-events: none;
        }
        .field-input {
            width: 100%; border: 1.5px solid #ccddd6; border-radius: 10px;
            padding: 12px 44px 12px 42px; font-size: 0.95rem; font-family: inherit;
            color: #0a2820; background: #f8fdfb;
            transition: border-color 0.22s, box-shadow 0.22s, background 0.22s; outline: none;
        }
        .field-input:focus { border-color: #0f6a5d; background: #ffffff; box-shadow: 0 0 0 4px rgba(15,106,93,0.13); }
        .field-input.has-toggle { padding-right: 48px; }
        .field-input[readonly] { background: #f0f6f3; color: #4d6d65; cursor: default; }
        .field-input.is-invalid { border-color: #c0392b; }

        .toggle-pw {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #7da898; cursor: pointer;
            font-size: 0.9rem; padding: 4px; transition: color 0.22s;
        }
        .toggle-pw:hover { color: #0f6a5d; }

        /* Password strength */
        .strength-track { margin-top: 8px; height: 4px; background: #e0eae6; border-radius: 2px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; background: #dc3545; border-radius: 2px; transition: width 0.3s, background 0.3s; }
        .strength-label { font-size: 0.78rem; color: #7da898; margin-top: 4px; display: block; }

        .pw-match-msg { font-size: 0.82rem; margin-top: 4px; }
        .hint-text { font-size: 0.8rem; color: #7da898; margin-top: 4px; display: block; }

        .invalid-feedback { font-size: 0.8rem; color: #c0392b; margin-top: 4px; display: none; }
        .field-input.is-invalid ~ .invalid-feedback,
        .field-input.is-invalid + .invalid-feedback { display: block; }

        /* Terms box */
        .terms-box {
            background: #eef7f2; border: 1px solid #c4ddd0; border-radius: 10px;
            padding: 14px 16px; margin: 18px 0; font-size: 0.88rem; color: #1f3d37;
        }
        .terms-box .terms-icon { color: #0f6a5d; margin-right: 5px; }
        .terms-check-row { display: flex; align-items: flex-start; gap: 10px; margin-top: 12px; }
        .terms-check-row input[type="checkbox"] {
            margin-top: 3px; accent-color: #0f6a5d; flex-shrink: 0; width: 16px; height: 16px;
        }
        .terms-check-row span { line-height: 1.5; }

        /* Status note */
        .status-note {
            background: #f1faf5; border: 1px solid #c6e2d2; border-radius: 10px;
            padding: 12px 16px; font-size: 0.88rem; color: #1f3d37; margin-bottom: 20px; line-height: 1.8;
        }
        .status-note strong { color: #0a2820; }

        /* Main CTA */
        .btn-main {
            width: 100%; padding: 13px; border: none; border-radius: 10px;
            font-size: 0.98rem; font-weight: 700; font-family: inherit;
            background: linear-gradient(135deg, #0f6a5d 0%, #09483f 100%); color: #ffffff;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 9px;
            transition: transform 0.22s, box-shadow 0.22s;
            box-shadow: 0 4px 18px rgba(9,72,63,0.28); margin-top: 8px;
        }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(9,72,63,0.38); }
        .btn-main:active { transform: translateY(0); }
        .btn-main:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        /* Ghost buttons */
        .btn-ghost {
            flex: 1; padding: 11px; border: 1.5px solid #ccddd6; border-radius: 10px;
            font-size: 0.92rem; font-weight: 700; font-family: inherit;
            background: transparent; color: #5a7a72; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: background 0.22s, border-color 0.22s, color 0.22s;
        }
        .btn-ghost:hover { background: #eef4f2; border-color: #aac8bc; color: #0a2820; }
        .btn-ghost:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-ghost-primary { border-color: #0f6a5d; color: #0f6a5d; }
        .btn-ghost-primary:hover { background: #e5f1ee; border-color: #09483f; color: #09483f; }

        .otp-btn-row { display: flex; gap: 10px; margin-top: 14px; }

        /* Alerts */
        .auth-alert {
            border-radius: 10px; padding: 13px 16px; margin-bottom: 20px;
            font-size: 0.9rem; font-weight: 600;
            display: flex; align-items: flex-start; gap: 10px;
        }
        .auth-alert-danger  { background: #fff1f1; color: #8b1f1f; border-left: 4px solid #c0392b; }
        .auth-alert-success { background: #edfbf2; color: #155724; border-left: 4px solid #22864a; }
        .auth-alert i { margin-top: 1px; flex-shrink: 0; }

        .otp-hint { font-size: 0.8rem; color: #7da898; margin-top: 6px; display: block; }

        .login-row { margin-top: 24px; text-align: center; font-size: 0.92rem; color: #6b8080; }
        .login-row a { color: #0f6a5d; font-weight: 700; text-decoration: none; }
        .login-row a:hover { color: #09483f; }

        .legal-row { margin-top: 10px; text-align: center; font-size: 0.84rem; color: #7b8f8d; }
        .legal-row a { color: #5f7872; text-decoration: none; font-weight: 600; margin: 0 8px; }
        .legal-row a:hover { color: #0f6a5d; text-decoration: underline; }

        @media (max-width: 900px) {
            .brand-panel { display: none; }
            .form-panel { width: 100%; padding: 44px 28px; }
        }
        @media (max-width: 420px) { .form-panel { padding: 32px 18px; } }
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
                Create your free account and access government services online - fast, paperless, and secure.
            </p>
            <ul class="brand-features">
                <li><span class="feat-icon"><i class="fas fa-file-alt"></i></span> Apply for certificates and documents</li>
                <li><span class="feat-icon"><i class="fas fa-search"></i></span> Track your application in real-time</li>
                <li><span class="feat-icon"><i class="fas fa-download"></i></span> Download approved certificates instantly</li>
                <li><span class="feat-icon"><i class="fas fa-shield-alt"></i></span> OTP-verified, secure registration</li>
            </ul>
            <a href="index.php" class="brand-back"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <!-- RIGHT form panel -->
    <div class="form-panel">
        <div class="fp-head">
            <?php if (!$has_pending_signup): ?>
                <h2>Create account</h2>
                <p>Fill in the details below to get started - it's free.</p>
            <?php else: ?>
                <h2>Verify your email</h2>
                <p>Enter the 6-digit code we sent to <strong><?php echo htmlspecialchars((string)$pending_signup['email']); ?></strong>.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($signup_error)): ?>
            <div class="auth-alert auth-alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($signup_error); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($signup_success)): ?>
            <div class="auth-alert auth-alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($signup_success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$has_pending_signup): ?>
        <!-- STEP 1: Registration form -->
        <form method="POST" id="signupForm" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="start_signup">

            <div class="field-group">
                <label class="field-label" for="f-username">Username</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="f-username" name="username" class="field-input"
                           placeholder="e.g. john_doe" minlength="3" pattern="[a-zA-Z0-9_]+"
                           value="<?php echo htmlspecialchars($username); ?>" autocomplete="username" required>
                </div>
                <span class="hint-text">Min 3 characters - letters, numbers, underscores only.</span>
                <div class="invalid-feedback">Choose a username with at least 3 characters (letters, numbers, underscores).</div>
            </div>

            <div class="field-group">
                <label class="field-label" for="f-email">Email Address</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="f-email" name="email" class="field-input"
                           placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>"
                           autocomplete="email" required>
                </div>
                <div class="invalid-feedback">Enter a valid email address.</div>
            </div>

            <div class="field-group">
                <label class="field-label" for="f-password">Password</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="f-password" name="password" class="field-input has-toggle"
                           placeholder="Create a strong password" minlength="8"
                           autocomplete="new-password" oninput="checkStrength(); checkPasswordMatch();" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('f-password','icon-pw1')" tabindex="-1">
                        <i class="fas fa-eye" id="icon-pw1"></i>
                    </button>
                </div>
                <div class="strength-track"><div class="strength-fill" id="strengthBar"></div></div>
                <span class="strength-label" id="strengthText">Password strength: Too weak</span>
                <span class="hint-text">8+ chars with uppercase, lowercase, number and special character.</span>
                <div class="invalid-feedback">Use at least 8 characters with uppercase, lowercase, number, and special character.</div>
            </div>

            <div class="field-group">
                <label class="field-label" for="f-confirm">Confirm Password</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-check-circle"></i></span>
                    <input type="password" id="f-confirm" name="confirm_password" class="field-input has-toggle"
                           placeholder="Re-enter your password"
                           autocomplete="new-password" oninput="checkPasswordMatch()" required>
                    <button type="button" class="toggle-pw" onclick="togglePw('f-confirm','icon-pw2')" tabindex="-1">
                        <i class="fas fa-eye" id="icon-pw2"></i>
                    </button>
                </div>
                <div class="pw-match-msg" id="passwordMatchMsg"></div>
                <div class="invalid-feedback">Please confirm your password.</div>
            </div>

            <div class="terms-box">
                <i class="fas fa-info-circle terms-icon"></i>
                <strong>Your acceptance time and request IP are recorded for account security.</strong>
                <div class="terms-check-row">
                    <input type="checkbox" name="accept_terms" id="accept-terms" value="1" <?php echo $terms_accepted ? 'checked' : ''; ?> required>
                    <label for="accept-terms"><span>I agree to the <a href="terms_of_service.php" target="_blank" rel="noopener noreferrer">Terms of Service</a> and <a href="privacy_policy.php" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and want to continue with email verification.</span></label>
                </div>
                <div class="invalid-feedback">You must accept the Terms of Service and Privacy Policy.</div>
            </div>

            <button type="submit" class="btn-main" id="submitSignupBtn">
                <i class="fas fa-envelope"></i> Send Email OTP
            </button>
        </form>

        <?php else: ?>
        <!-- STEP 2: OTP verification -->
        <?php $pending = $pending_signup; ?>
        <div class="status-note">
            <strong>Session expires in:</strong> <span id="pendingSignupCountdown"><?php echo (int)$pending_signup_seconds_left; ?></span>s &nbsp;|&nbsp;
            <strong>OTP valid for:</strong> <span id="otpExpiryCountdown"><?php echo (int)$signup_otp_seconds_left; ?></span>s<br>
            <strong>OTP attempts remaining:</strong> <?php echo (int)$signup_attempts_left; ?> &nbsp;|&nbsp;
            <strong>Resends remaining:</strong> <span id="resendsLeftValue"><?php echo (int)$signup_resends_left; ?></span>
        </div>

        <form method="POST" id="otpVerifyForm" novalidate>
            <?php echo csrf_input(); ?>
            <input type="hidden" name="action" value="verify_signup_otp">
            <input type="hidden" name="signup_otp_id" value="<?php echo htmlspecialchars($signup_otp_id); ?>">

            <div class="field-group">
                <label class="field-label" for="otp-username">Username</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-user"></i></span>
                    <input type="text" id="otp-username" class="field-input" value="<?php echo htmlspecialchars((string)$pending['username']); ?>" readonly>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label" for="otp-email-ro">Email Address</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="otp-email-ro" class="field-input" value="<?php echo htmlspecialchars((string)$pending['email']); ?>" readonly>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label" for="signup-otp">6-Digit OTP</label>
                <div class="field-wrap">
                    <span class="field-icon"><i class="fas fa-key"></i></span>
                    <input type="text" id="signup-otp" name="signup_otp" class="field-input"
                           maxlength="6" minlength="6" pattern="[0-9]{6}"
                           inputmode="numeric" autocomplete="one-time-code"
                           placeholder="e.g. 482915" required>
                </div>
                <span class="otp-hint"><i class="fas fa-info-circle"></i> Check your inbox - the code is valid for 10 minutes.</span>
                <div class="invalid-feedback">Enter a valid 6-digit OTP.</div>
            </div>

            <button type="submit" class="btn-main">
                <i class="fas fa-user-check"></i> Verify OTP and Create Account
            </button>
        </form>

        <div class="otp-btn-row">
            <form method="POST" style="flex:1;">
                <?php echo csrf_input(); ?>
                <button type="submit" class="btn-ghost btn-ghost-primary w-100"
                        id="resendOtpBtn" name="action" value="resend_signup_otp"
                        data-cooldown="<?php echo (int)$signup_resend_cooldown_remaining; ?>"
                        data-resends-left="<?php echo (int)$signup_resends_left; ?>"
                        <?php echo ($signup_resend_cooldown_remaining > 0 || $signup_resends_left <= 0) ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i> <span id="resendOtpLabel">Resend OTP</span>
                </button>
            </form>
            <form method="POST" style="flex:1;">
                <?php echo csrf_input(); ?>
                <button type="submit" class="btn-ghost w-100" name="action" value="cancel_signup_otp">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </form>
        </div>
        <span class="otp-hint" id="resendOtpMeta" style="margin-top:8px;">
            <?php if ($signup_resends_left <= 0): ?>
                Maximum resend attempts reached. Cancel and start again.
            <?php elseif ($signup_resend_cooldown_remaining > 0): ?>
                You can request another OTP in <?php echo (int)$signup_resend_cooldown_remaining; ?> seconds.
            <?php else: ?>
                You can request another OTP now.
            <?php endif; ?>
        </span>
        <?php endif; ?>

        <div class="login-row">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
        <div class="legal-row">
            <a href="terms_of_service.php">Terms of Service</a>
            <a href="privacy_policy.php">Privacy Policy</a>
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

    function checkPasswordMatch() {
        const pw  = document.getElementById('f-password');
        const cpw = document.getElementById('f-confirm');
        const msg = document.getElementById('passwordMatchMsg');
        if (!pw || !cpw || !msg) return;
        if (cpw.value === '') { msg.textContent = ''; return; }
        if (pw.value === cpw.value) {
            msg.style.color = '#22864a'; msg.textContent = '\u2713 Passwords match';
        } else {
            msg.style.color = '#c0392b'; msg.textContent = '\u2717 Passwords do not match';
        }
    }

    function checkStrength() {
        const pw  = document.getElementById('f-password');
        const bar = document.getElementById('strengthBar');
        const lbl = document.getElementById('strengthText');
        if (!pw || !bar || !lbl) return;
        const password = pw.value;
        let strength = 0;
        if (password.length >= 8)  strength += 20;
        if (password.length >= 12) strength += 10;
        if (/[a-z]/.test(password)) strength += 17.5;
        if (/[A-Z]/.test(password)) strength += 17.5;
        if (/[0-9]/.test(password)) strength += 17.5;
        if (/[^a-zA-Z0-9]/.test(password)) strength += 17.5;
        bar.style.width = strength + '%';
        if (strength < 50)      { bar.style.background = '#dc3545'; lbl.textContent = 'Password strength: Too weak'; }
        else if (strength < 75) { bar.style.background = '#ffc107'; lbl.textContent = 'Password strength: Almost valid'; }
        else if (strength < 95) { bar.style.background = '#17a2b8'; lbl.textContent = 'Password strength: Good'; }
        else                    { bar.style.background = '#28a745'; lbl.textContent = 'Password strength: Strong'; }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        /* OTP digit-only filter */
        const otpInput = document.getElementById('signup-otp');
        if (otpInput) {
            otpInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g,'').slice(0,6);
            });
        }

        const signupForm = document.getElementById('signupForm');
        if (signupForm) {
            signupForm.addEventListener('submit', function(e) {
                const requiredFields = signupForm.querySelectorAll('input[required]');
                let valid = true;
                requiredFields.forEach(function (field) {
                    const value = field.type === 'checkbox' ? field.checked : (field.value || '').trim();
                    const fieldValid = value !== '' && value !== false && field.checkValidity();
                    field.classList.toggle('is-invalid', !fieldValid);
                    if (!fieldValid) valid = false;
                });
                const passwordInput = document.getElementById('f-password');
                const confirmInput  = document.getElementById('f-confirm');
                const passwordMatchMsg = document.getElementById('passwordMatchMsg');
                const strongPassword = passwordInput &&
                    /[a-z]/.test(passwordInput.value) &&
                    /[A-Z]/.test(passwordInput.value) &&
                    /\d/.test(passwordInput.value) &&
                    /[^a-zA-Z0-9]/.test(passwordInput.value) &&
                    passwordInput.value.length >= 8;
                if (passwordInput && !strongPassword) {
                    e.preventDefault(); valid = false; passwordInput.classList.add('is-invalid');
                }
                if (passwordInput && confirmInput && passwordInput.value !== confirmInput.value) {
                    e.preventDefault(); valid = false; confirmInput.classList.add('is-invalid');
                    if (passwordMatchMsg) {
                        passwordMatchMsg.style.color = '#c0392b';
                        passwordMatchMsg.textContent = '\u2717 Passwords do not match';
                    }
                    confirmInput.scrollIntoView({behavior: 'smooth', block: 'center'});
                }
                if (!valid) { e.preventDefault(); return; }
                const submitBtn = signupForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
                }
            });
        }

        const otpForm = document.getElementById('otpVerifyForm');
        if (otpForm) {
            otpForm.addEventListener('submit', function (e) {
                const otpInp = otpForm.querySelector('input[name="signup_otp"]');
                if (!otpInp) return;
                const otpValue = (otpInp.value || '').trim();
                const otpValid = /^\d{6}$/.test(otpValue);
                otpInp.classList.toggle('is-invalid', !otpValid);
                if (!otpValid) { e.preventDefault(); return; }
                const submitBtn = otpForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying OTP...';
                }
            });
        }

        const resendButton = document.getElementById('resendOtpBtn');
        const resendLabel  = document.getElementById('resendOtpLabel');
        const resendMeta   = document.getElementById('resendOtpMeta');
        if (resendButton && resendLabel && resendMeta) {
            let cooldownSeconds = Number(resendButton.dataset.cooldown || 0);
            const resendsLeft   = Number(resendButton.dataset.resendsLeft || 0);
            const updateResendState = function () {
                if (resendsLeft <= 0) {
                    resendButton.disabled = true;
                    resendLabel.textContent = 'Resend limit reached';
                    resendMeta.textContent = 'Maximum resend attempts reached. Cancel and start again.';
                    return;
                }
                if (cooldownSeconds > 0) {
                    resendButton.disabled = true;
                    resendLabel.textContent = 'Resend OTP (' + cooldownSeconds + 's)';
                    resendMeta.textContent = 'You can request another OTP in ' + cooldownSeconds + ' seconds.';
                    cooldownSeconds -= 1;
                    return;
                }
                resendButton.disabled = false;
                resendLabel.textContent = 'Resend OTP';
                resendMeta.textContent = 'You can request another OTP now.';
            };
            updateResendState();
            if (cooldownSeconds > 0 && resendsLeft > 0) {
                window.setInterval(updateResendState, 1000);
            }
        }

        const countdownBindings = [
            { element: document.getElementById('pendingSignupCountdown'), value: Number(document.getElementById('pendingSignupCountdown')?.textContent || 0) },
            { element: document.getElementById('otpExpiryCountdown'),     value: Number(document.getElementById('otpExpiryCountdown')?.textContent || 0) }
        ].filter(function (b) { return b.element; });
        if (countdownBindings.length > 0) {
            window.setInterval(function () {
                countdownBindings.forEach(function (b) {
                    if (b.value > 0) { b.value -= 1; b.element.textContent = String(b.value); }
                });
            }, 1000);
        }
    })();
</script>
</body>
</html>
