<?php
include 'DataBaseConnection.php';
session_start();
require_once __DIR__ . '/includes/security.php';

verify_csrf_or_fail($_POST['csrf_token'] ?? null);
if (!enforce_rate_limit('newsletter_subscribe', 12, 300)) {
    header("Location: index.php?newsletter=error");
    exit();
}

$email = trim($_POST['newsletter_email'] ?? '');
$name = trim($_POST['newsletter_name'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.php?newsletter=invalid");
    exit();
}

$stmt = $conn->prepare("
    INSERT INTO newsletter_subscribers (name, email, status)
    VALUES (?, ?, 'subscribed')
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        status = 'subscribed',
        updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("ss", $name, $email);

if ($stmt->execute()) {
    header("Location: index.php?newsletter=subscribed");
} else {
    header("Location: index.php?newsletter=error");
}
$stmt->close();
exit();
?>
