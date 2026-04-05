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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_employee'])) {
    $employee_id = (int)($_POST['toggle_id'] ?? 0);
    $new_status = ($_POST['action'] ?? '') === 'deactivate' ? 0 : 1;
    $toggle_stmt = $conn->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
    $toggle_stmt->bind_param("ii", $new_status, $employee_id);
    if ($toggle_stmt->execute()) {
        $message = $new_status === 1 ? "Employee activated." : "Employee deactivated.";
        $message_type = "success";
        audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'toggle_employee', 'employee', (string)$employee_id, 'is_active=' . $new_status);
    } else {
        $message = "Unable to update employee status.";
        $message_type = "danger";
    }
    $toggle_stmt->close();
}

// edit existing employee aadhar/contact/email
$edit_mode = false;
$edit_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt_e = $conn->prepare("SELECT id, name, contact, aadhar, email FROM employees WHERE id = ?");
    $stmt_e->bind_param("i", $eid);
    $stmt_e->execute();
    $edit_data = $stmt_e->get_result()->fetch_assoc() ?: [];
    if ($edit_data) {
        $edit_mode = true;
    }
    $stmt_e->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_employee'])) {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name)) {
        $message = "Name is required!";
        $message_type = "danger";
    } elseif (empty($contact)) {
        $message = "Contact is required!";
        $message_type = "danger";
    } elseif (empty($email)) {
        $message = "Email is required!";
        $message_type = "danger";
    } elseif (empty($password) || strlen($password) < 8) {
        $message = "Password must be at least 8 characters!";
        $message_type = "danger";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords don't match!";
        $message_type = "danger";
    } else {
        // Check if email exists
        $stmt_check = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $message = "Email already registered!";
            $message_type = "danger";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO employees (name, contact, aadhar, email, password, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssss", $name, $contact, $aadhar, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $message = "Employee added successfully!";
                $message_type = "success";
                $new_id = (int)$conn->insert_id;
                audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'create_employee', 'employee', (string)$new_id, $email);
            } else {
                $message = "Error adding employee!";
                $message_type = "danger";
            }
            $stmt->close();
        }
        $stmt_check->close();
    }
}

// update existing employee if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $eid = (int)($_POST['employee_id'] ?? 0);
    $aadhar = trim($_POST['aadhar'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($eid <= 0) {
        $message = 'Invalid employee.';
        $message_type = 'danger';
    } elseif ($aadhar === '') {
        $message = 'Aadhar is required.';
        $message_type = 'danger';
    } else {
        $stmt_up = $conn->prepare("UPDATE employees SET aadhar = ?, contact = ?, email = ? WHERE id = ?");
        $stmt_up->bind_param("sssi", $aadhar, $contact, $email, $eid);
        if ($stmt_up->execute()) {
            $message = 'Employee updated successfully.';
            $message_type = 'success';
            audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'update_employee', 'employee', (string)$eid, 'aadhar,contact,email');
        } else {
            $message = 'Unable to update employee.';
            $message_type = 'danger';
        }
        $stmt_up->close();
    }
}


// Fetch employees
$employees = [];
$stmt = $conn->prepare("SELECT id, name, contact, aadhar, email, is_active FROM employees ORDER BY id DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
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
        .form-card { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-card h2 { color: #333; margin-bottom: 25px; font-weight: 700; }
        .form-group label { font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-group input { border: 2px solid #e0e0e0; border-radius: 5px; padding: 10px; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .btn-submit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 30px; border-radius: 5px; font-weight: 600; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3); color: white; }
        .table-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table { margin: 0; }
        .table thead { background: #f8f9fa; }
        .table th { border: none; color: #333; font-weight: 600; }
        .badge { padding: 8px 12px; border-radius: 20px; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>

    <div class="container-layout">
        <?php render_panel_sidebar('admin', 'add_employee.php'); ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Message -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <h2><i class="fas fa-user-plus"></i> Add New Employee</h2>
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="update_employee" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo (int)($edit_data['id'] ?? 0); ?>">
                    <?php else: ?>
                        <input type="hidden" name="create_employee" value="1">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label for="name">Employee Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($edit_data['name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label for="contact">Contact Number *</label>
                            <input type="text" class="form-control" id="contact" name="contact" required value="<?php echo htmlspecialchars($edit_data['contact'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label for="aadhar">Aadhar Number *</label>
                            <input type="text" class="form-control" id="aadhar" name="aadhar" required value="<?php echo htmlspecialchars($edit_data['aadhar'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($edit_data['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <?php if (! $edit_mode): ?>
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="password">Password *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($edit_mode): ?>
                        <button type="submit" class="btn btn-submit"><i class="fas fa-save"></i> Update Employee</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-submit"><i class="fas fa-plus"></i> Add Employee</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Employees List -->
            <div class="table-container">
                <h3 style="color: #333; margin-bottom: 20px;"><i class="fas fa-list"></i> employees</h3>
                <div style="overflow-x: auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Aadhar</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td><?php echo $emp['id']; ?></td>
                                    <td><?php echo htmlspecialchars($emp['name']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['contact']); ?></td>
                                    <td><?php echo htmlspecialchars($emp['aadhar'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                    <td>
                                        <?php if ((int)$emp['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="add_employee.php?edit=<?php echo $emp['id']; ?>" class="btn btn-sm btn-primary me-1">Edit</a>
                                        <?php if ((int)$emp['is_active'] === 1): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this employee?')">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="toggle_employee" value="1">
                                                <input type="hidden" name="toggle_id" value="<?php echo (int)$emp['id']; ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Activate this employee?')">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="toggle_employee" value="1">
                                                <input type="hidden" name="toggle_id" value="<?php echo (int)$emp['id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
