<?php

if (!function_exists('ec_load_env_file_once')) {
    function ec_load_env_file_once(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $envFile = __DIR__ . '/../.env';
        if (!is_file($envFile) || !is_readable($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                (strlen($value) >= 2) &&
                (($value[0] === '"' && $value[strlen($value) - 1] === '"') || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
            }
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
            if (!isset($_SERVER[$key])) {
                $_SERVER[$key] = $value;
            }
        }
    }
}

if (!function_exists('ec_env')) {
    function ec_env(string $key, string $default = ''): string
    {
        ec_load_env_file_once();
        $val = getenv($key);
        if ($val !== false) {
            return (string)$val;
        }
        if (isset($_ENV[$key])) {
            return (string)$_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return (string)$_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('ec_app_url')) {
    function ec_app_url(string $path = ''): string
    {
        $base = rtrim(ec_env('APP_URL', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $base = $scheme . '://' . $host . ($dir !== '' ? $dir : '');
        }

        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('ec_smtp_expect')) {
    function ec_smtp_expect($socket, array $okCodes): bool
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            return false;
        }
        $code = (int)substr($response, 0, 3);
        return in_array($code, $okCodes, true);
    }
}

if (!function_exists('ec_email_shell')) {
    function ec_email_shell(
        string $eyebrow,
        string $title,
        string $intro,
        string $contentHtml,
        string $accent = '#0f6a5d',
        string $footerText = 'This is an automated email from Earth Cafe.'
    ): string {
        $safeEyebrow = htmlspecialchars($eyebrow, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeAccent = htmlspecialchars($accent, ENT_QUOTES, 'UTF-8');
        $safeFooter = htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8');

        return '<div style="margin:0;padding:24px;background:#f2f8f6;font-family:Segoe UI,Arial,sans-serif;color:#18322e;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #dceae5;">'
            . '<tr><td style="padding:20px 24px;background:linear-gradient(135deg,' . $safeAccent . ',#0b4f45);color:#ffffff;">'
            . '<div style="font-size:12px;opacity:0.92;letter-spacing:0.08em;text-transform:uppercase;">' . $safeEyebrow . '</div>'
            . '<h2 style="margin:8px 0 0;font-size:24px;line-height:1.2;">' . $safeTitle . '</h2>'
            . '</td></tr>'
            . '<tr><td style="padding:24px;">'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;">' . $safeIntro . '</p>'
            . $contentHtml
            . '<p style="margin:18px 0 0;font-size:12px;color:#5b7770;line-height:1.5;">' . $safeFooter . '</p>'
            . '</td></tr>'
            . '</table>'
            . '</div>';
    }
}

if (!function_exists('ec_compose_document_shared_email')) {
    function ec_compose_document_shared_email(int $requestId, string $documentName, string $downloadLink): array
    {
        $safeName = htmlspecialchars($documentName, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($downloadLink, ENT_QUOTES, 'UTF-8');
        $subject = 'Document Shared - Application #' . $requestId;

        $content = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:separate;border-spacing:0 10px;">'
            . '<tr><td style="padding:12px 14px;background:#f7fcfa;border:1px solid #dceae5;border-radius:10px;">'
            . '<div style="font-size:12px;color:#4f6c66;text-transform:uppercase;letter-spacing:0.04em;">Application ID</div>'
            . '<div style="font-size:15px;font-weight:700;color:#16352f;">#' . $requestId . '</div>'
            . '</td></tr>'
            . '<tr><td style="padding:12px 14px;background:#f7fcfa;border:1px solid #dceae5;border-radius:10px;">'
            . '<div style="font-size:12px;color:#4f6c66;text-transform:uppercase;letter-spacing:0.04em;">Document</div>'
            . '<div style="font-size:15px;font-weight:700;color:#16352f;">' . $safeName . '</div>'
            . '</td></tr>'
            . '</table>'
            . '<p style="margin:14px 0 0;">'
            . '<a href="' . $safeLink . '" style="display:inline-block;background:#0f6a5d;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:700;">Download Document</a>'
            . '</p>'
            . '<p style="margin:10px 0 0;font-size:13px;color:#56736c;">You may be asked to log in before accessing the file.</p>';

        $html = ec_email_shell(
            'Earth Cafe Documents',
            'Application Document Shared',
            'A new document has been shared for your application.',
            $content,
            '#0f6a5d',
            'For security, only download files from official Earth Cafe links.'
        );

        $text = 'Application Document Shared'
            . "\nApplication ID: #" . $requestId
            . "\nDocument: " . $documentName
            . "\nDownload link: " . $downloadLink;

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}

if (!function_exists('ec_compose_application_status_email')) {
    function ec_compose_application_status_email(
        int $requestId,
        string $statusText,
        string $detailsLink,
        bool $needsDocuments = false,
        string $remark = ''
    ): array {
        $safeStatus = htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($detailsLink, ENT_QUOTES, 'UTF-8');
        $safeRemark = htmlspecialchars($remark, ENT_QUOTES, 'UTF-8');
        $subject = 'Application Status Update - #' . $requestId;

        $statusTone = '#0f6a5d';
        $normalized = strtolower(trim($statusText));
        if ($normalized === 'rejected') {
            $statusTone = '#9b2c2c';
        } elseif ($normalized === 'in progress') {
            $statusTone = '#9a6700';
        } elseif ($normalized === 'completed') {
            $statusTone = '#1f7a3f';
        }

        $content = '<div style="padding:12px 14px;background:#f7fcfa;border:1px solid #dceae5;border-radius:10px;">'
            . '<div style="font-size:12px;color:#4f6c66;text-transform:uppercase;letter-spacing:0.04em;">Current Status</div>'
            . '<div style="font-size:15px;font-weight:800;color:' . htmlspecialchars($statusTone, ENT_QUOTES, 'UTF-8') . ';">' . $safeStatus . '</div>'
            . '<div style="font-size:13px;color:#4f6c66;margin-top:6px;">Application ID: #' . $requestId . '</div>'
            . '</div>';

        if ($needsDocuments) {
            $content .= '<div style="margin-top:12px;padding:12px 14px;background:#fff7ea;border:1px solid #f2d9aa;border-radius:10px;color:#7a5312;font-size:14px;">Additional documents are required. Please upload the requested files in your portal.</div>';
        }

        if ($safeRemark !== '') {
            $content .= '<div style="margin-top:12px;padding:12px 14px;background:#f4faf7;border:1px solid #dceae5;border-radius:10px;">'
                . '<div style="font-size:12px;color:#4f6c66;text-transform:uppercase;letter-spacing:0.04em;">Note</div>'
                . '<div style="font-size:14px;color:#18322e;line-height:1.45;">' . nl2br($safeRemark) . '</div>'
                . '</div>';
        }

        $content .= '<p style="margin:14px 0 0;">'
            . '<a href="' . $safeLink . '" style="display:inline-block;background:#0f6a5d;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:700;">View Application Details</a>'
            . '</p>';

        $html = ec_email_shell(
            'Earth Cafe Applications',
            'Your Application Was Updated',
            'We updated the latest processing status of your application.',
            $content,
            '#0f6a5d',
            'Need help? Contact Earth Cafe support from the portal Contact Us section.'
        );

        $text = 'Application Status Update'
            . "\nApplication ID: #" . $requestId
            . "\nStatus: " . $statusText
            . ($needsDocuments ? "\nAdditional documents required: Yes" : '')
            . ($remark !== '' ? "\nNote: " . $remark : '')
            . "\nDetails: " . $detailsLink;

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}

if (!function_exists('ec_compose_payment_slip_email')) {
    function ec_compose_payment_slip_email(array $paymentData, string $historyLink, string $detailsLink): array
    {
        $requestId = (int)($paymentData['request_id'] ?? 0);
        $service = (string)($paymentData['service_name'] ?? 'Service');
        $reference = (string)($paymentData['payment_reference'] ?? '-');
        $method = strtoupper((string)($paymentData['payment_method'] ?? '-'));
        $amount = number_format((float)($paymentData['total_fee'] ?? 0), 2);
        $paidAt = (string)($paymentData['paid_at'] ?? '');

        $safeService = htmlspecialchars($service, ENT_QUOTES, 'UTF-8');
        $safeReference = htmlspecialchars($reference, ENT_QUOTES, 'UTF-8');
        $safeMethod = htmlspecialchars($method, ENT_QUOTES, 'UTF-8');
        $safePaidAt = htmlspecialchars($paidAt, ENT_QUOTES, 'UTF-8');
        $safeHistoryLink = htmlspecialchars($historyLink, ENT_QUOTES, 'UTF-8');
        $safeDetailsLink = htmlspecialchars($detailsLink, ENT_QUOTES, 'UTF-8');
        $subject = 'Payment Receipt - Application #' . $requestId;

        $content = '<p style="margin:0 0 12px;font-size:14px;color:#4f6c66;">Your payment was successful. The PDF payment slip is attached to this email.</p>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #d7e1f2;border-radius:10px;overflow:hidden;">'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;border-bottom:1px solid #d7e1f2;font-weight:700;color:#1b365d;">Application ID</td><td style="padding:10px 12px;background:#ffffff;border-bottom:1px solid #d7e1f2;">#' . $requestId . '</td></tr>'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;border-bottom:1px solid #d7e1f2;font-weight:700;color:#1b365d;">Service</td><td style="padding:10px 12px;background:#ffffff;border-bottom:1px solid #d7e1f2;">' . $safeService . '</td></tr>'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;border-bottom:1px solid #d7e1f2;font-weight:700;color:#1b365d;">Transaction ID</td><td style="padding:10px 12px;background:#ffffff;border-bottom:1px solid #d7e1f2;">' . $safeReference . '</td></tr>'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;border-bottom:1px solid #d7e1f2;font-weight:700;color:#1b365d;">Payment Method</td><td style="padding:10px 12px;background:#ffffff;border-bottom:1px solid #d7e1f2;">' . $safeMethod . '</td></tr>'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;border-bottom:1px solid #d7e1f2;font-weight:700;color:#1b365d;">Amount Paid</td><td style="padding:10px 12px;background:#ffffff;border-bottom:1px solid #d7e1f2;">INR ' . $amount . '</td></tr>'
            . '<tr><td style="padding:10px 12px;background:#f4f8ff;font-weight:700;color:#1b365d;">Paid On</td><td style="padding:10px 12px;background:#ffffff;">' . $safePaidAt . '</td></tr>'
            . '</table>'
            . '<p style="margin:14px 0 0;">'
            . '<a href="' . $safeHistoryLink . '" style="display:inline-block;background:#1b4fa3;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:700;margin-right:8px;">View Payment History</a>'
            . '<a href="' . $safeDetailsLink . '" style="display:inline-block;background:#24527a;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:10px;font-weight:700;">View Application</a>'
            . '</p>';

        $html = ec_email_shell(
            'Earth Cafe Payments',
            'Payment Receipt',
            'Thank you. Your payment has been recorded successfully.',
            $content,
            '#1b4fa3',
            'If you did not make this payment, contact support immediately.'
        );

        $text = 'Payment Receipt'
            . "\nApplication ID: #" . $requestId
            . "\nService: " . $service
            . "\nTransaction ID: " . $reference
            . "\nPayment Method: " . $method
            . "\nAmount Paid: INR " . $amount
            . "\nPaid On: " . $paidAt
            . "\nPayment History: " . $historyLink
            . "\nApplication Details: " . $detailsLink;

        return ['subject' => $subject, 'html' => $html, 'text' => $text];
    }
}

if (!function_exists('ec_smtp_cmd')) {
    function ec_smtp_cmd($socket, string $command, array $okCodes): bool
    {
        if (fwrite($socket, $command . "\r\n") === false) {
            return false;
        }
        return ec_smtp_expect($socket, $okCodes);
    }
}

if (!function_exists('ec_email_build_payload')) {
    function ec_email_build_payload(string $from, string $fromName, string $to, string $subject, string $htmlBody, string $textBody = '', ?array $attachment = null): array
    {
        $safeSubject = str_replace(["\r", "\n"], '', $subject);
        $safeTo = str_replace(["\r", "\n"], '', $to);
        $safeFrom = str_replace(["\r", "\n"], '', $from);
        $safeFromName = str_replace(["\r", "\n"], '', $fromName);
        $plain = $textBody !== '' ? $textBody : trim(strip_tags($htmlBody));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $safeFromName . ' <' . $safeFrom . '>',
            'To: <' . $safeTo . '>',
            'Subject: ' . $safeSubject,
        ];

        $hasAttachment = is_array($attachment)
            && isset($attachment['filename'], $attachment['content'])
            && (string)$attachment['filename'] !== ''
            && (string)$attachment['content'] !== '';

        if ($hasAttachment) {
            $filename = basename(str_replace(["\r", "\n", '"'], '', (string)$attachment['filename']));
            if ($filename === '') {
                $filename = 'attachment.bin';
            }
            $mime = trim((string)($attachment['mime_type'] ?? 'application/octet-stream'));
            if ($mime === '') {
                $mime = 'application/octet-stream';
            }

            $mixedBoundary = 'mix_' . bin2hex(random_bytes(8));
            $altBoundary = 'alt_' . bin2hex(random_bytes(8));

            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

            $body = '';
            $body .= '--' . $mixedBoundary . "\r\n";
            $body .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n";

            $body .= '--' . $altBoundary . "\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain . "\r\n";
            $body .= '--' . $altBoundary . "\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n";
            $body .= '--' . $altBoundary . "--\r\n";

            $body .= '--' . $mixedBoundary . "\r\n";
            $body .= 'Content-Type: ' . $mime . '; name="' . $filename . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
            $body .= chunk_split(base64_encode((string)$attachment['content'])) . "\r\n";
            $body .= '--' . $mixedBoundary . "--\r\n";

            return ['headers' => $headers, 'body' => $body];
        }

        $altBoundary = 'b_' . bin2hex(random_bytes(8));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"';

        $body = '';
        $body .= '--' . $altBoundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $plain . "\r\n";
        $body .= '--' . $altBoundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n" . $htmlBody . "\r\n";
        $body .= '--' . $altBoundary . "--\r\n";

        return ['headers' => $headers, 'body' => $body];
    }
}

if (!function_exists('ec_send_via_smtp')) {
    function ec_send_via_smtp(string $to, string $subject, string $htmlBody, string $textBody = '', ?array $attachment = null): bool
    {
        $host = ec_env('SMTP_HOST', 'smtp.gmail.com');
        $port = (int)ec_env('SMTP_PORT', '587');
        $secure = strtolower(ec_env('SMTP_SECURE', 'tls'));
        $user = ec_env('EMAIL_USER', '');
        $pass = ec_env('EMAIL_PASSWORD', '');
        $from = ec_env('EMAIL_FROM', $user);
        $brevoSender = ec_env('BREVO_SENDER_EMAIL', '');
        $fromName = ec_env('EMAIL_FROM_NAME', 'Earth Cafe');

        $isBrevoHost = (stripos($host, 'smtp-relay.brevo.com') !== false);
        if ($isBrevoHost && $brevoSender !== '') {
            $from = $brevoSender;
        }

        // Brevo rejects SMTP login identity as sender; require a verified sender/domain address instead.
        if ($isBrevoHost && preg_match('/@smtp-brevo\.com$/i', $from)) {
            error_log('SMTP send blocked: invalid Brevo sender address configured in EMAIL_FROM/BREVO_SENDER_EMAIL');
            return false;
        }

        if ($user === '' || $pass === '' || $from === '') {
            return false;
        }

        $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            error_log('SMTP connect failed: ' . $errno . ' ' . $errstr);
            return false;
        }
        stream_set_timeout($socket, 30);

        if (!ec_smtp_expect($socket, [220])) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_cmd($socket, 'EHLO localhost', [250])) {
            fclose($socket);
            return false;
        }

        if ($secure === 'tls') {
            if (!ec_smtp_cmd($socket, 'STARTTLS', [220])) {
                fclose($socket);
                return false;
            }
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return false;
            }
            if (!ec_smtp_cmd($socket, 'EHLO localhost', [250])) {
                fclose($socket);
                return false;
            }
        }

        if (!ec_smtp_cmd($socket, 'AUTH LOGIN', [334])) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_cmd($socket, base64_encode($user), [334])) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_cmd($socket, base64_encode($pass), [235])) {
            fclose($socket);
            return false;
        }

        if (!ec_smtp_cmd($socket, 'MAIL FROM:<' . $from . '>', [250])) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_cmd($socket, 'RCPT TO:<' . $to . '>', [250, 251])) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_cmd($socket, 'DATA', [354])) {
            fclose($socket);
            return false;
        }

        $payload = ec_email_build_payload($from, $fromName, $to, $subject, $htmlBody, $textBody, $attachment);
        $data = implode("\r\n", $payload['headers']) . "\r\n\r\n" . $payload['body'];
        $data .= ".";

        if (fwrite($socket, $data . "\r\n") === false) {
            fclose($socket);
            return false;
        }
        if (!ec_smtp_expect($socket, [250])) {
            fclose($socket);
            return false;
        }

        ec_smtp_cmd($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    }
}

if (!function_exists('send_system_email')) {
    function send_system_email(string $to, string $subject, string $htmlBody, string $textBody = '', ?array $attachment = null): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $service = strtolower(ec_env('EMAIL_SERVICE', 'mail'));
        if ($service === 'gmail' || $service === 'smtp') {
            $sent = ec_send_via_smtp($to, $subject, $htmlBody, $textBody, $attachment);
            if ($sent) {
                return true;
            }
            error_log('SMTP send failed, falling back to mail() for ' . $to . ' subject=' . $subject);
        }

        $from = ec_env('EMAIL_FROM', 'no-reply@earthcafe.local');
        $fromName = ec_env('EMAIL_FROM_NAME', 'Earth Cafe');
        $payload = ec_email_build_payload($from, $fromName, $to, $subject, $htmlBody, $textBody, $attachment);
        $mailHeaders = [];
        foreach ($payload['headers'] as $headerLine) {
            if (stripos($headerLine, 'To:') === 0 || stripos($headerLine, 'Subject:') === 0) {
                continue;
            }
            $mailHeaders[] = $headerLine;
        }

        $ok = @mail($to, $subject, $payload['body'], implode("\r\n", $mailHeaders));
        if (!$ok) {
            error_log('Mail send failed to ' . $to . ' subject=' . $subject);
        }
        return $ok;
    }
}
