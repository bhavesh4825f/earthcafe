<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_admin('../admin_login.php');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    $user_id = (int)($_POST['toggle_id'] ?? 0);
    $action = ($_POST['action'] ?? '') === 'deactivate' ? 'deactivate' : 'activate';
    $new_status = $action === 'deactivate' ? 0 : 1;

    $toggle = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $toggle->bind_param("ii", $new_status, $user_id);
    if ($toggle->execute()) {
        $message = $action === 'deactivate' ? "User deactivated." : "User activated.";
        $message_type = "success";
        audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'toggle_user', 'user', (string)$user_id, 'is_active=' . $new_status);
    } else {
        $message = "Unable to update user status.";
        $message_type = "danger";
    }
    $toggle->close();
}

$users = [];
$stmt = $conn->prepare("SELECT id, username, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

$total_users = count($users);
$active_users = 0;
$inactive_users = 0;
foreach ($users as $user) {
    if ((int)$user['is_active'] === 1) {
        $active_users++;
    } else {
        $inactive_users++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
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
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h4 { color: #667eea; font-size: 12px; margin-bottom: 10px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #333; }
        .table-container, .form-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-inactive { background: #f8d7da; color: #842029; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>

    <div class="container-layout">
        <?php render_panel_sidebar('admin', 'manage_users.php'); ?>

        <main class="main-content">
            <h2 style="color: #333; margin-bottom: 20px;"><i class="fas fa-users"></i> User Management</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="stats-cards">
                <div class="stat-card">
                    <h4>TOTAL USERS</h4>
                    <div class="value"><?php echo $total_users; ?></div>
                </div>
                <div class="stat-card">
                    <h4>ACTIVE</h4>
                    <div class="value" style="color:#198754;"><?php echo $active_users; ?></div>
                </div>
                <div class="stat-card">
                    <h4>INACTIVE</h4>
                    <div class="value" style="color:#dc3545;"><?php echo $inactive_users; ?></div>
                </div>
            </div>

            <div class="table-container">
                <div style="overflow-x:auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="7" class="text-center text-muted">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo (int)$user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['role'] ?? 'citizen'); ?></td>
                                        <td>
                                            <?php if ((int)$user['is_active'] === 1): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '-'; ?></td>
                                        <td>
                                            <?php if ((int)$user['is_active'] === 1): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this user?')">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="toggle_user" value="1">
                                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$user['id']; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Activate this user?')">
                                                    <?php echo csrf_input(); ?>
                                                    <input type="hidden" name="toggle_user" value="1">
                                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$user['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    <?php render_panel_footer('admin'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
