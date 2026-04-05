<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notifications.php';

$recipientType = '';
$recipientId = 0;
$back = 'index.php';

if (isset($_SESSION['admin_id'])) {
    $recipientType = 'admin';
    $recipientId = (int)$_SESSION['admin_id'];
    $back = 'admin/dashboard.php';
} elseif (isset($_SESSION['employee_id'])) {
    $recipientType = 'employee';
    $recipientId = (int)$_SESSION['employee_id'];
    $back = 'employee/dashboard.php';
} elseif (isset($_SESSION['user_id'])) {
    $recipientType = 'citizen';
    $recipientId = (int)$_SESSION['user_id'];
    $back = 'client_dashboard.php';
} else {
    header('Location: login.php');
    exit();
}

ensure_notifications_table($conn);

if (isset($_GET['mark']) && ctype_digit((string)$_GET['mark'])) {
    mark_notification_read($conn, (int)$_GET['mark'], $recipientType, $recipientId);
    header('Location: notifications.php');
    exit();
}
if (isset($_POST['mark_all'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $stmt = $conn->prepare('UPDATE notifications SET is_read = 1 WHERE recipient_type = ? AND recipient_id = ?');
    $stmt->bind_param('si', $recipientType, $recipientId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare('SELECT id, title, message, action_url, is_read, created_at FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY created_at DESC LIMIT 200');
$stmt->bind_param('si', $recipientType, $recipientId);
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f5f7fa;">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Notifications</h4>
        <a href="<?php echo htmlspecialchars($back); ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <form method="POST" class="mb-3">
        <?php echo csrf_input(); ?>
        <input type="hidden" name="mark_all" value="1">
        <button type="submit" class="btn btn-sm btn-primary">Mark all as read</button>
    </form>
    <div class="list-group shadow-sm">
        <?php if (empty($rows)): ?>
            <div class="list-group-item">No notifications.</div>
        <?php else: ?>
            <?php foreach ($rows as $n): ?>
                <div class="list-group-item <?php echo ((int)$n['is_read'] === 0) ? 'list-group-item-warning' : ''; ?>">
                    <div class="d-flex justify-content-between">
                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                        <small><?php echo date('M d, Y H:i', strtotime((string)$n['created_at'])); ?></small>
                    </div>
                    <div><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
                    <div class="mt-2 d-flex gap-2">
                        <?php if (!empty($n['action_url'])): ?>
                            <a href="<?php echo htmlspecialchars($n['action_url']); ?>" class="btn btn-sm btn-outline-primary">Open</a>
                        <?php endif; ?>
                        <?php if ((int)$n['is_read'] === 0): ?>
                            <a href="notifications.php?mark=<?php echo (int)$n['id']; ?>" class="btn btn-sm btn-outline-secondary">Mark read</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
