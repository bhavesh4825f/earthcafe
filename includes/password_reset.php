<?php
require_once __DIR__ . '/mailer.php';

if (!function_exists('ensure_password_reset_tables')) {
    function ensure_password_reset_tables(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role_type VARCHAR(20) NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(190) NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lookup (role_type, email),
            INDEX idx_exp (expires_at)
        )");
    }
}

if (!function_exists('password_reset_find_user')) {
    function password_reset_find_user(mysqli $conn, string $roleType, string $email): ?array
    {
        $roleType = strtolower($roleType);
        if ($roleType === 'citizen') {
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        } elseif ($roleType === 'employee') {
            $stmt = $conn->prepare("SELECT id, email FROM employees WHERE email = ? AND is_active = 1 LIMIT 1");
        } elseif ($roleType === 'admin') {
            $stmt = $conn->prepare("SELECT id, email FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
        } else {
            return null;
        }
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('password_reset_issue_token')) {
    function password_reset_issue_token(mysqli $conn, string $roleType, int $userId, string $email): string
    {
        ensure_password_reset_tables($conn);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $invalidate = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE role_type = ? AND email = ? AND used_at IS NULL");
        if ($invalidate) {
            $invalidate->bind_param('ss', $roleType, $email);
            $invalidate->execute();
            $invalidate->close();
        }

        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (role_type, user_id, email, token_hash, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sisss', $roleType, $userId, $email, $hash, $expires);
        $stmt->execute();
        $stmt->close();

        return $token;
    }
}

if (!function_exists('password_reset_send_email')) {
    function password_reset_send_email(string $email, string $roleType, string $token): bool
    {
        $baseUrl = rtrim((string)(getenv('APP_URL') ?: 'http://localhost/earthcafe'), '/');
        $link = $baseUrl . '/reset_password.php?role=' . urlencode($roleType) . '&token=' . urlencode($token);
        $subject = 'Earth Cafe Password Reset';
        $accountType = htmlspecialchars(ucfirst($roleType), ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        $html = '<div style="margin:0;padding:24px;background:#f2f8f6;font-family:Segoe UI,Arial,sans-serif;color:#18322e;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dceae5;">'
            . '<tr><td style="padding:20px 24px;background:linear-gradient(135deg,#0f6a5d,#0b4f45);color:#ffffff;">'
            . '<div style="font-size:13px;opacity:0.9;letter-spacing:0.08em;text-transform:uppercase;">Earth Cafe Security</div>'
            . '<h2 style="margin:8px 0 0;font-size:24px;line-height:1.2;">Password Reset Request</h2>'
            . '</td></tr>'
            . '<tr><td style="padding:24px;">'
            . '<p style="margin:0 0 12px;font-size:15px;line-height:1.55;">We received a password reset request for your <strong>' . $accountType . '</strong> account.</p>'
            . '<p style="margin:0 0 18px;font-size:14px;color:#4f6c66;line-height:1.5;">Use the button below to set a new password. This link expires in 1 hour.</p>'
            . '<p style="margin:0 0 18px;">'
            . '<a href="' . $safeLink . '" style="display:inline-block;background:#0f6a5d;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:700;">Reset Password</a>'
            . '</p>'
            . '<p style="margin:0;font-size:13px;color:#56736c;line-height:1.45;">If you did not request this, you can safely ignore this email.</p>'
            . '</td></tr>'
            . '</table>'
            . '</div>';

        $text = 'Earth Cafe Password Reset'
            . "\nAccount type: " . ucfirst($roleType)
            . "\nReset link: " . $link
            . "\nThis link expires in 1 hour.";

        return send_system_email($email, $subject, $html, $text);
    }
}

if (!function_exists('password_reset_verify_token')) {
    function password_reset_verify_token(mysqli $conn, string $roleType, string $token): ?array
    {
        ensure_password_reset_tables($conn);
        $hash = hash('sha256', $token);
        $stmt = $conn->prepare("SELECT id, user_id, email, expires_at, used_at FROM password_reset_tokens WHERE role_type = ? AND token_hash = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ss', $roleType, $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$row) return null;
        if (!empty($row['used_at'])) return null;
        if (strtotime((string)$row['expires_at']) < time()) return null;
        return $row;
    }
}

if (!function_exists('password_reset_apply_new_password')) {
    function password_reset_apply_new_password(mysqli $conn, string $roleType, int $userId, string $newPassword): bool
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($roleType === 'citizen') {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        } elseif ($roleType === 'employee') {
            $stmt = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
        } elseif ($roleType === 'admin') {
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        } else {
            return false;
        }
        if (!$stmt) return false;
        $stmt->bind_param('si', $hash, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('password_reset_mark_used')) {
    function password_reset_mark_used(mysqli $conn, int $tokenId): void
    {
        $stmt = $conn->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
        if (!$stmt) return;
        $stmt->bind_param('i', $tokenId);
        $stmt->execute();
        $stmt->close();
    }
}
