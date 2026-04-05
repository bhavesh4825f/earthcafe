<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/mailer.php';

require_admin('../admin_login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_campaign'])) {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');

    if ($subject === '' || $body === '') {
        $message = "Subject and message body are required.";
        $message_type = "danger";
    } else {
        $sent_count = 0;
        $failed_count = 0;
        $admin_id = (int)$_SESSION['admin_id'];

        $plainBody = trim($body);
        $plainBodySafe = htmlspecialchars($plainBody, ENT_QUOTES, 'UTF-8');
        $htmlBody = ec_email_shell(
            'Earth Cafe Newsletter',
            $subject,
            'You are receiving this update because you subscribed to Earth Cafe notifications.',
            '<div style="font-size:14px;line-height:1.6;color:#173630;">' . nl2br($plainBodySafe) . '</div>'
            . '<p style="margin-top:16px;font-size:12px;color:#5b7770;">If you no longer want these emails, please contact support to unsubscribe.</p>',
            '#0f6a5d',
            'Earth Cafe newsletter service'
        );
        $textBody = $subject . "\n\n" . $plainBody;

        $sub_stmt = $conn->prepare("SELECT email FROM newsletter_subscribers WHERE status = 'subscribed'");
        if ($sub_stmt) {
            $sub_stmt->execute();
            $sub_rs = $sub_stmt->get_result();
            while ($sub_rs && ($sub = $sub_rs->fetch_assoc())) {
                $email = (string)($sub['email'] ?? '');
                if ($email === '') {
                    continue;
                }

                if (send_system_email($email, $subject, $htmlBody, $textBody)) {
                    $sent_count++;
                } else {
                    $failed_count++;
                }
            }
            $sub_stmt->close();
        } else {
            $message = "Failed to load subscriber list.";
            $message_type = "danger";
        }

        $stmt = $conn->prepare("
            INSERT INTO newsletter_campaigns (subject, body, sent_by, sent_count)
            VALUES (?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("ssii", $subject, $body, $admin_id, $sent_count);
            if ($stmt->execute()) {
                if ($message === '') {
                    if ($failed_count > 0) {
                        $message = "Campaign sent to {$sent_count} subscribers, {$failed_count} failed.";
                        $message_type = $sent_count > 0 ? "warning" : "danger";
                    } else {
                        $message = "Campaign sent successfully to {$sent_count} subscribers.";
                        $message_type = "success";
                    }
                }
            } else {
                $message = "Email dispatch completed, but campaign log could not be saved.";
                $message_type = "warning";
            }
            $stmt->close();
        } elseif ($message === '') {
            $message = "Campaign could not be logged.";
            $message_type = "warning";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_subscriber'])) {
    $id = (int)($_POST['toggle_id'] ?? 0);
    $status = ($_POST['action'] ?? '') === 'unsubscribe' ? 'unsubscribed' : 'subscribed';

    $stmt = $conn->prepare("UPDATE newsletter_subscribers SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        $message = "Subscriber status updated.";
        $message_type = "success";
    } else {
        $message = "Failed to update subscriber status.";
        $message_type = "danger";
    }
    $stmt->close();
}

$subscribers = [];
$sub_rs = $conn->query("SELECT id, name, email, status, created_at FROM newsletter_subscribers ORDER BY created_at DESC");
while ($row = $sub_rs->fetch_assoc()) {
    $subscribers[] = $row;
}

$campaigns = [];
$camp_rs = $conn->query("SELECT id, subject, sent_count, created_at FROM newsletter_campaigns ORDER BY created_at DESC LIMIT 20");
while ($row = $camp_rs->fetch_assoc()) {
    $campaigns[] = $row;
}

$subscribed_count = 0;
foreach ($subscribers as $s) {
    if ($s['status'] === 'subscribed') {
        $subscribed_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .layout { display: flex; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 250px; height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; padding: 24px 18px; overflow-y: auto;
        }
        .sidebar a { display: block; color: rgba(255,255,255,0.82); text-decoration: none; padding: 10px 12px; border-radius: 6px; margin-bottom: 6px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.24); color: #fff; }
        .main { margin-left: 250px; width: calc(100% - 250px); padding: 24px; }
        .box { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 18px; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>
    <div class="layout">
        <?php render_panel_sidebar('admin', 'newsletter.php'); ?>

        <main class="main">
            <h3 style="margin-bottom: 18px;"><i class="fas fa-paper-plane"></i> Newsletter Distribution</h3>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="box">
                <div class="row">
                    <div class="col-md-4">
                        <h5><?php echo count($subscribers); ?></h5>
                        <small class="text-muted">Total Subscribers</small>
                    </div>
                    <div class="col-md-4">
                        <h5 style="color:#198754;"><?php echo $subscribed_count; ?></h5>
                        <small class="text-muted">Active Subscriptions</small>
                    </div>
                    <div class="col-md-4">
                        <h5><?php echo count($campaigns); ?></h5>
                        <small class="text-muted">Campaigns Logged</small>
                    </div>
                </div>
            </div>

            <div class="box">
                <h5>Create Campaign</h5>
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="send_campaign" value="1">
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="body" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Campaign</button>
                </form>
            </div>

            <div class="box">
                <h5>Subscribers</h5>
                <?php if (empty($subscribers)): ?>
                    <div class="alert alert-info">No newsletter subscribers yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Subscribed At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscribers as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['name'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($s['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $s['status'] === 'subscribed' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo htmlspecialchars($s['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($s['created_at'])); ?></td>
                                        <td>
                                            <?php if ($s['status'] === 'subscribed'): ?>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="toggle_subscriber" value="1">
                                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$s['id']; ?>">
                                                    <input type="hidden" name="action" value="unsubscribe">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Unsubscribe</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="toggle_subscriber" value="1">
                                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$s['id']; ?>">
                                                    <input type="hidden" name="action" value="subscribe">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Subscribe</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="box">
                <h5>Recent Campaigns</h5>
                <?php if (empty($campaigns)): ?>
                    <div class="alert alert-info">No campaigns recorded yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Subject</th>
                                    <th>Sent Count</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $c): ?>
                                    <tr>
                                        <td>#<?php echo (int)$c['id']; ?></td>
                                        <td><?php echo htmlspecialchars($c['subject']); ?></td>
                                        <td><?php echo (int)$c['sent_count']; ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($c['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php render_panel_footer('admin'); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
