<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_admin('../admin_login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'open';
    $reply = trim($_POST['admin_reply'] ?? '');
    $resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

    if (!in_array($status, ['open', 'in_progress', 'resolved'], true)) {
        $status = 'open';
    }

    $stmt = $conn->prepare("UPDATE contact_messages SET status = ?, admin_reply = ?, resolved_at = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $reply, $resolved_at, $id);
    if ($stmt->execute()) {
        $message = "Contact query updated successfully.";
        $message_type = "success";
    } else {
        $message = "Unable to update contact query.";
        $message_type = "danger";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Contact query deleted.";
        $message_type = "success";
    } else {
        $message = "Unable to delete query.";
        $message_type = "danger";
    }
    $stmt->close();
}

$contacts = [];
$stmt = $conn->prepare("SELECT id, name, email, message, status, admin_reply, created_at, resolved_at FROM contact_messages ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $contacts[] = $row;
}
$stmt->close();

$open_count = 0;
$progress_count = 0;
$resolved_count = 0;
foreach ($contacts as $contact) {
    if ($contact['status'] === 'resolved') {
        $resolved_count++;
    } elseif ($contact['status'] === 'in_progress') {
        $progress_count++;
    } else {
        $open_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Query Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .container-layout { display: flex; margin-top: 60px; }
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px; height: calc(100vh - 60px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px; color: white; overflow-y: auto;
        }
        .sidebar a { display: block; color: rgba(255,255,255,0.8); text-decoration: none; padding: 10px 15px; margin: 5px 0; border-radius: 5px; }
        .sidebar a:hover { background: rgba(255,255,255,0.2); color: white; }
        .main-content { margin-left: 250px; padding: 30px; flex: 1; }
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 10px; padding: 18px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card .title { font-size: 12px; color: #777; text-transform: uppercase; font-weight: 600; }
        .stat-card .value { font-size: 28px; font-weight: 700; margin-top: 4px; }
        .query-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .status-open { background: #fff3cd; color: #664d03; }
        .status-in_progress { background: #cff4fc; color: #055160; }
        .status-resolved { background: #d1e7dd; color: #0f5132; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>

    <div class="container-layout">
        <?php render_panel_sidebar('admin', 'manage_contactus.php'); ?>

        <main class="main-content">
            <h2 style="color:#333; margin-bottom: 20px;"><i class="fas fa-envelope"></i> Contact Query Management</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="title">Open</div>
                    <div class="value" style="color:#b8860b;"><?php echo $open_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="title">In Progress</div>
                    <div class="value" style="color:#0c63e4;"><?php echo $progress_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="title">Resolved</div>
                    <div class="value" style="color:#198754;"><?php echo $resolved_count; ?></div>
                </div>
                <div class="stat-card">
                    <div class="title">Total</div>
                    <div class="value"><?php echo count($contacts); ?></div>
                </div>
            </div>

            <?php if (empty($contacts)): ?>
                <div class="alert alert-info">No contact queries found.</div>
            <?php else: ?>
                <?php foreach ($contacts as $contact): ?>
                    <div class="query-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 style="margin:0;"><?php echo htmlspecialchars($contact['name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($contact['email']); ?></small>
                            </div>
                            <span class="badge status-<?php echo htmlspecialchars($contact['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $contact['status'])); ?>
                            </span>
                        </div>
                        <p style="margin:12px 0 10px;"><?php echo nl2br(htmlspecialchars($contact['message'])); ?></p>
                        <small class="text-muted">Created: <?php echo date('M d, Y H:i', strtotime($contact['created_at'])); ?></small>
                        <?php if (!empty($contact['resolved_at'])): ?>
                            <small class="text-muted ms-3">Resolved: <?php echo date('M d, Y H:i', strtotime($contact['resolved_at'])); ?></small>
                        <?php endif; ?>

                        <form method="POST" class="row g-2 mt-3">
                            <?php echo csrf_input(); ?>
                            <input type="hidden" name="update_message" value="1">
                            <input type="hidden" name="id" value="<?php echo (int)$contact['id']; ?>">
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="open" <?php echo $contact['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $contact['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $contact['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </div>
                            <div class="col-md-7">
                                <input type="text" class="form-control" name="admin_reply" value="<?php echo htmlspecialchars($contact['admin_reply'] ?? ''); ?>" placeholder="Admin reply / internal note">
                            </div>
                            <div class="col-md-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm w-100">Save</button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this query?')">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="delete_message" value="1">
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$contact['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
    <?php render_panel_footer('admin'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
