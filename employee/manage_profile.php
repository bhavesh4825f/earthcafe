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

function normalize_contact(string $contact): string
{
    return preg_replace('/\D+/', '', $contact) ?? '';
}

function normalize_aadhar(string $aadhar): string
{
    return preg_replace('/\D+/', '', $aadhar) ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $name = trim($_POST['name'] ?? '');
    $contact = normalize_contact((string)($_POST['contact'] ?? ''));
    $aadhar = normalize_aadhar((string)($_POST['aadhar'] ?? ''));
    $address = trim($_POST['address'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $allowedGender = ['', 'Male', 'Female', 'Other'];

    if ($name === '' || $contact === '') {
        $message = "Name and contact are required.";
        $message_type = "danger";
    } elseif (!preg_match('/^\d{10}$/', $contact)) {
        $message = "Please enter a valid 10-digit contact number.";
        $message_type = "danger";
    } elseif ($aadhar === '') {
        $message = "Aadhar number is required.";
        $message_type = "danger";
    } elseif (!preg_match('/^\d{12}$/', $aadhar)) {
        $message = "Please enter a valid 12-digit Aadhar number.";
        $message_type = "danger";
    } elseif (!in_array($gender, $allowedGender, true)) {
        $message = "Please select a valid gender option.";
        $message_type = "danger";
    } elseif ($birthdate !== '' && strtotime($birthdate) > time()) {
        $message = "Birthdate cannot be in the future.";
        $message_type = "danger";
    } else {
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array($ext, $allowed, true) && (int)$_FILES['photo']['size'] <= 4 * 1024 * 1024) {
                $dir = __DIR__ . '/../uploads/employee/' . $employee_id;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $fname = 'profile_' . time() . '.' . $ext;
                $target = $dir . '/' . $fname;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                    $photo_path = 'uploads/employee/' . $employee_id . '/' . $fname;
                }
            } else {
                $message = "Photo must be JPG/PNG and up to 4 MB.";
                $message_type = "danger";
            }
        }

        if ($message === '' && $photo_path !== null) {
            $stmt = $conn->prepare("
                UPDATE employees
                SET name = ?, contact = ?, aadhar = ?, address = ?, birthdate = ?, state = ?, gender = ?, photo = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssssi", $name, $contact, $aadhar, $address, $birthdate, $state, $gender, $photo_path, $employee_id);
        } elseif ($message === '') {
            $stmt = $conn->prepare("\
                UPDATE employees
                SET name = ?, contact = ?, aadhar = ?, address = ?, birthdate = ?, state = ?, gender = ?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $name, $contact, $aadhar, $address, $birthdate, $state, $gender, $employee_id);
        }

        if ($message === '') {
            if ($stmt->execute()) {
                $_SESSION['employee_name'] = $name;
                $message = "Profile updated successfully.";
                $message_type = "success";
            } else {
                $message = "Unable to update profile.";
                $message_type = "danger";
            }
            $stmt->close();
        }
    }
}

$stmt = $conn->prepare("SELECT name, email, contact, aadhar, address, birthdate, state, gender, photo FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #eef3f1; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-layout { display: flex; margin-top: 60px; }
        .container-box {
            max-width: 980px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #d9e5df;
            box-shadow: 0 12px 28px rgba(10, 39, 33, 0.08);
            padding: 24px;
        }
        .page-title { font-weight: 800; color: #173c36; }
        .form-label { font-weight: 600; color: #224841; }
        .form-control, .form-select {
            border-radius: 10px;
            border: 1.4px solid #d3e2db;
            padding: 10px 12px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f6a5d;
            box-shadow: 0 0 0 0.2rem rgba(15, 106, 93, 0.15);
        }
        .profile-avatar {
            width: 88px;
            height: 88px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #d9e5df;
            box-shadow: 0 6px 14px rgba(10, 39, 33, 0.12);
        }
        .btn-update {
            background: linear-gradient(135deg, #0f6a5d, #0b4f45);
            border: none;
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            font-weight: 700;
        }
        .btn-update:hover { color: #fff; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('employee', (string)$employee_name); ?>

    <div class="container-layout">
        <?php render_panel_sidebar('employee', 'manage_profile.php'); ?>

        <main class="main-content">
            <div class="container-box">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="page-title"><i class="fas fa-user-edit"></i> Manage Profile</h4>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($employee['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact *</label>
                            <input type="text" class="form-control" name="contact" maxlength="10" pattern="\d{10}" value="<?php echo htmlspecialchars((string)($employee['contact'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Aadhar Number *</label>
                            <input type="text" class="form-control" name="aadhar" maxlength="12" pattern="\d{12}" value="<?php echo htmlspecialchars((string)($employee['aadhar'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Birthdate</label>
                            <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($employee['birthdate'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($employee['state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($employee['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($employee['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($employee['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="col-md-4">
                            <?php if (!empty($employee['photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($employee['photo']); ?>" alt="Profile" class="profile-avatar">
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-update">Update Profile</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <?php render_panel_footer('employee'); ?>

    <script src="../js/panel-unified.js"></script>
</body>
</html>
