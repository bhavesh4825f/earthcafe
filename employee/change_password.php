<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_employee('../admin_login.php');

$employee_id = (int)$_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {
        $message = 'All password fields are required.';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'New password must be at least 8 characters.';
        $message_type = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirm password do not match.';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT password FROM employees WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$employee || !password_verify($current_password, $employee['password'])) {
            $message = 'Current password is incorrect.';
            $message_type = 'danger';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
            $update->bind_param("si", $hashed_password, $employee_id);
            if ($update->execute()) {
                $message = 'Password updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to update password. Please try again.';
                $message_type = 'danger';
            }
            $update->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 250px; height: 100vh;
            background: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            padding: 26px 18px; color: #fff; overflow-y: auto;
        }
        .sidebar h4 { margin-bottom: 20px; }
        .sidebar a { display: block; color: rgba(255,255,255,0.84); text-decoration: none; padding: 10px 12px; border-radius: 6px; margin-bottom: 6px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.24); color: #fff; }
        .main-content { margin-left: 250px; padding: 24px; }
        .card { border: none; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); max-width: 650px; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('employee', (string)$employee_name); ?>
    <?php render_panel_sidebar('employee', 'change_password.php'); ?>

    <main class="main-content">
        <h3 class="mb-3"><i class="fas fa-lock"></i> Change Password</h3>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body p-4">
                <form method="POST" autocomplete="off">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="change_password" value="1">

                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" minlength="8" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" minlength="8" required>
                    </div>

                    <button type="submit" class="btn btn-info text-white">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </main>
    <?php render_panel_footer('employee'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
