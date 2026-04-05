<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function otp_storage_init(): void
{
    if (!isset($_SESSION['_email_otp']) || !is_array($_SESSION['_email_otp'])) {
        $_SESSION['_email_otp'] = [];
    }
}

function otp_context_key(string $context, string $email): string
{
    return strtolower(trim($context)) . '|' . strtolower(trim($email));
}

function otp_context_meta(string $context): array
{
    $normalized = strtolower(trim($context));

    if ($normalized === 'login_otp') {
        return [
            'title' => 'Login Verification',
            'subject' => 'Earth Cafe Login OTP',
            'accent' => '#0f6a5d',
        ];
    }

    if ($normalized === 'signup_verify') {
        return [
            'title' => 'Registration Verification',
            'subject' => 'Earth Cafe Registration OTP',
            'accent' => '#1f7a3f',
        ];
    }

    return [
        'title' => 'Security Verification',
        'subject' => 'Earth Cafe OTP Verification',
        'accent' => '#0b4f45',
    ];
}

function otp_generate_request_id(): string
{
    return 'ECOTP-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
}

function otp_build_modern_email_html(string $context, string $purposeText, string $otp, string $otpId, int $ttlSeconds): string
{
    $meta = otp_context_meta($context);
    $minutes = (int)floor(max(120, $ttlSeconds) / 60);
    $safeTitle = htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8');
    $safePurpose = htmlspecialchars($purposeText, ENT_QUOTES, 'UTF-8');
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $safeOtpId = htmlspecialchars($otpId, ENT_QUOTES, 'UTF-8');
    $safeAccent = htmlspecialchars($meta['accent'], ENT_QUOTES, 'UTF-8');

    return '<div style="margin:0;padding:24px;background:#f2f8f6;font-family:Segoe UI,Arial,sans-serif;color:#18322e;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dceae5;">'
        . '<tr><td style="padding:20px 24px;background:linear-gradient(135deg,' . $safeAccent . ',#0b4f45);color:#ffffff;">'
        . '<div style="font-size:13px;opacity:0.9;letter-spacing:0.08em;text-transform:uppercase;">Earth Cafe Security</div>'
        . '<h2 style="margin:8px 0 0;font-size:24px;line-height:1.2;">' . $safeTitle . '</h2>'
        . '</td></tr>'
        . '<tr><td style="padding:24px;">'
        . '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;">Use this One-Time Password to continue with <strong>' . $safePurpose . '</strong>.</p>'
        . '<div style="margin:18px 0;padding:14px;border:1px dashed #b8d7cd;border-radius:12px;background:#f7fcfa;text-align:center;">'
        . '<div style="font-size:12px;color:#4f6c66;letter-spacing:0.04em;text-transform:uppercase;margin-bottom:6px;">Your OTP</div>'
        . '<div style="font-size:34px;letter-spacing:7px;font-weight:800;color:#102f2a;">' . $safeOtp . '</div>'
        . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:14px 0 6px;">'
        . '<tr><td style="padding:10px 12px;background:#f4faf7;border:1px solid #dceae5;border-radius:10px;">'
        . '<div style="font-size:12px;color:#4f6c66;">OTP Request ID</div>'
        . '<div style="font-size:14px;font-weight:700;color:#16352f;">' . $safeOtpId . '</div>'
        . '</td></tr>'
        . '</table>'
        . '<p style="margin:10px 0 0;font-size:14px;line-height:1.5;">This OTP expires in <strong>' . $minutes . ' minutes</strong>. For your safety, never share this code.</p>'
        . '<p style="margin:10px 0 0;font-size:13px;color:#56736c;">If you did not request this, ignore this email.</p>'
        . '</td></tr>'
        . '</table>'
        . '</div>';
}

function otp_get_email_status(string $context, string $email): array
{
    otp_storage_init();
    $attemptLimit = 6;
    $email = strtolower(trim($email));
    $key = otp_context_key($context, $email);

    if (!isset($_SESSION['_email_otp'][$key])) {
        return [
            'exists' => false,
            'expired' => false,
            'otp_id' => '',
            'attempts' => 0,
            'attempt_limit' => $attemptLimit,
            'attempts_remaining' => $attemptLimit,
            'expires_at' => 0,
            'seconds_until_expiry' => 0,
        ];
    }

    $data = $_SESSION['_email_otp'][$key];
    $expiresAt = (int)($data['expires_at'] ?? 0);
    $attempts = (int)($data['attempts'] ?? 0);
    $expired = $expiresAt <= time();

    return [
        'exists' => true,
        'expired' => $expired,
            'otp_id' => (string)($data['otp_id'] ?? ''),
        'attempts' => $attempts,
        'attempt_limit' => $attemptLimit,
        'attempts_remaining' => max(0, $attemptLimit - $attempts),
        'expires_at' => $expiresAt,
        'seconds_until_expiry' => max(0, $expiresAt - time()),
    ];
}

function otp_issue_email(string $context, string $email, string $purposeText, int $ttlSeconds = 600): array
{
    otp_storage_init();
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    $rateKey = 'otp_send_' . md5($context . '|' . $email);
    if (function_exists('enforce_rate_limit') && !enforce_rate_limit($rateKey, 3, 300)) {
        return ['ok' => false, 'message' => 'Too many OTP requests. Please wait a few minutes.'];
    }

    $otp = (string)random_int(100000, 999999);
    $otpId = otp_generate_request_id();
    $expiresAt = time() + max(120, $ttlSeconds);
    $key = otp_context_key($context, $email);

    $_SESSION['_email_otp'][$key] = [
        'otp_id' => $otpId,
        'otp_hash' => hash('sha256', $otp),
        'expires_at' => $expiresAt,
        'attempts' => 0,
        'created_at' => time()
    ];

    $meta = otp_context_meta($context);
    $subject = (string)$meta['subject'];
    $html = otp_build_modern_email_html($context, $purposeText, $otp, $otpId, $ttlSeconds);
    $textBody = 'Earth Cafe ' . (string)$meta['title']
        . "\nOTP: " . $otp
        . "\nOTP Request ID: " . $otpId
        . "\nValid for: " . (int)floor(max(120, $ttlSeconds) / 60) . " minutes."
        . "\nIf you did not request this OTP, ignore this email.";

    $sent = send_system_email($email, $subject, $html, $textBody);
    if (!$sent) {
        unset($_SESSION['_email_otp'][$key]);
        return ['ok' => false, 'message' => 'Failed to send OTP email. Please try again.'];
    }

    return [
        'ok' => true,
        'message' => 'OTP sent to your email address.',
        'otp_id' => $otpId,
        'expires_at' => $expiresAt,
    ];
}

function otp_verify_email_code_detailed(string $context, string $email, string $otp, ?string $otpId = null): array
{
    otp_storage_init();
    $attemptLimit = 6;
    $email = strtolower(trim($email));
    $otp = trim($otp);
    $key = otp_context_key($context, $email);

    if (!isset($_SESSION['_email_otp'][$key])) {
        return [
            'ok' => false,
            'reason' => 'not_found',
            'attempts_remaining' => 0,
            'expires_at' => 0,
        ];
    }

    $data = $_SESSION['_email_otp'][$key];
    $storedOtpId = (string)($data['otp_id'] ?? '');
    $expiresAt = (int)($data['expires_at'] ?? 0);
    $attempts = (int)($data['attempts'] ?? 0);

    if ($otpId !== null && trim($otpId) !== '' && $storedOtpId !== '' && !hash_equals($storedOtpId, trim($otpId))) {
        return [
            'ok' => false,
            'reason' => 'otp_id_mismatch',
            'attempts_remaining' => max(0, $attemptLimit - $attempts),
            'expires_at' => $expiresAt,
            'otp_id' => $storedOtpId,
        ];
    }

    if ($expiresAt <= time()) {
        unset($_SESSION['_email_otp'][$key]);
        return [
            'ok' => false,
            'reason' => 'expired',
            'attempts_remaining' => 0,
            'expires_at' => $expiresAt,
        ];
    }

    if ($attempts >= $attemptLimit) {
        unset($_SESSION['_email_otp'][$key]);
        return [
            'ok' => false,
            'reason' => 'attempts_exceeded',
            'attempts_remaining' => 0,
            'expires_at' => $expiresAt,
        ];
    }

    $attempts++;
    $_SESSION['_email_otp'][$key]['attempts'] = $attempts;
    $providedHash = hash('sha256', $otp);
    $storedHash = (string)($data['otp_hash'] ?? '');

    if ($storedHash !== '' && hash_equals($storedHash, $providedHash)) {
        unset($_SESSION['_email_otp'][$key]);
        return [
            'ok' => true,
            'reason' => 'verified',
            'attempts_remaining' => max(0, $attemptLimit - $attempts),
            'expires_at' => $expiresAt,
            'otp_id' => $storedOtpId,
        ];
    }

    if ($attempts >= $attemptLimit) {
        unset($_SESSION['_email_otp'][$key]);
        return [
            'ok' => false,
            'reason' => 'attempts_exceeded',
            'attempts_remaining' => 0,
            'expires_at' => $expiresAt,
        ];
    }

    return [
        'ok' => false,
        'reason' => 'invalid',
        'attempts_remaining' => max(0, $attemptLimit - $attempts),
        'expires_at' => $expiresAt,
        'otp_id' => $storedOtpId,
    ];
}

function otp_verify_email_code(string $context, string $email, string $otp, ?string $otpId = null): bool
{
    return otp_verify_email_code_detailed($context, $email, $otp, $otpId)['ok'];
}

function otp_clear_context(string $context, string $email): void
{
    otp_storage_init();
    $key = otp_context_key($context, $email);
    unset($_SESSION['_email_otp'][$key]);
}
