<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }

    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_fail(?string $token): void
{
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $postedToken = (string)($token ?? '');

    if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function require_admin(string $redirect = '../admin_login.php'): void
{
    if (!isset($_SESSION['admin_id'])) {
        header("Location: $redirect");
        exit();
    }
}

function require_employee(string $redirect = '../admin_login.php'): void
{
    if (!isset($_SESSION['employee_id'])) {
        header("Location: $redirect");
        exit();
    }
}

function require_citizen(string $redirect = 'login.php'): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit();
    }
}

function rotate_session_on_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
}

function bind_params_dynamic(mysqli_stmt $stmt, string $types, array $values): void
{
    $refs = [];
    $refs[] = &$types;
    foreach ($values as $k => $v) {
        $refs[] = &$values[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function is_allowed_upload(string $tmpPath, string $originalName, int $size, int $maxBytes = 5242880): bool
{
    if ($size <= 0 || $size > $maxBytes || !is_file($tmpPath)) {
        return false;
    }

    $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpPath);
    $allowedMime = [
        'application/pdf',
        'image/jpeg',
        'image/png'
    ];

    return in_array($mime, $allowedMime, true);
}

function enforce_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    if (!isset($_SESSION['_rate_limits'])) {
        $_SESSION['_rate_limits'] = [];
    }
    $now = time();
    $bucket = $_SESSION['_rate_limits'][$key] ?? ['count' => 0, 'start' => $now];
    $elapsed = $now - (int)$bucket['start'];

    if ($elapsed > $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now];
    }

    $bucket['count'] = (int)$bucket['count'] + 1;
    $_SESSION['_rate_limits'][$key] = $bucket;

    return $bucket['count'] <= $maxAttempts;
}
