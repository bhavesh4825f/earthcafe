<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Citizen';
$user_email = $_SESSION['email'] ?? '';
$curPage = basename($_SERVER['PHP_SELF']);

$message = '';
$message_type = '';
if (isset($_GET['complete']) && $_GET['complete'] == '1') {
    $message = 'Please complete your profile by providing Aadhar number before applying for services.';
    $message_type = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);

    if (isset($_POST['update_profile'])) {
        // username/email are fixed and cannot be changed by the user
        // keep them for display purposes only
        $aadhar = trim($_POST['aadhar'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $state = trim($_POST['state'] ?? '');

        // username/email are fixed and not editable on this screen.
        if ($aadhar === '') {
            $message = 'Aadhar number is required.';
            $message_type = 'danger';
        } elseif (!preg_match('/^\d{12}$/', $aadhar)) {
            $message = 'Aadhar must be exactly 12 digits.';
            $message_type = 'danger';
        } elseif ($phone !== '' && !preg_match('/^\d{10}$/', $phone)) {
            $message = 'Phone number must be exactly 10 digits.';
            $message_type = 'danger';
        }
        $photo_path = null;
        if ($message === '' && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $photoTmp = (string)($_FILES['photo']['tmp_name'] ?? '');
            $photoName = (string)($_FILES['photo']['name'] ?? '');
            $photoSize = (int)($_FILES['photo']['size'] ?? 0);
            $ext = strtolower(pathinfo($photoName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                $message = 'Profile photo must be JPG, JPEG, or PNG.';
                $message_type = 'danger';
            } elseif (!is_allowed_upload($photoTmp, $photoName, $photoSize, 4 * 1024 * 1024)) {
                $message = 'Profile photo must be a valid image up to 4MB.';
                $message_type = 'danger';
            } else {
                $dir = __DIR__ . '/uploads/' . $user_id;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $fname = 'profile_' . time() . '.' . $ext;
                $target = $dir . '/' . $fname;
                if (move_uploaded_file($photoTmp, $target)) {
                    $photo_path = 'uploads/' . $user_id . '/' . $fname;
                }
            }
        }

        if ($message === '') {
            if ($photo_path !== null) {
                $stmt = $conn->prepare("UPDATE users SET aadhar = ?, phone = ?, address = ?, birthdate = ?, gender = ?, state = ?, photo = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("sssssssi", $aadhar, $phone, $address, $birthdate, $gender, $state, $photo_path, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET aadhar = ?, phone = ?, address = ?, birthdate = ?, gender = ?, state = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param("ssssssi", $aadhar, $phone, $address, $birthdate, $gender, $state, $user_id);
            }

            if ($stmt->execute()) {
                $message = 'Profile updated successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to update profile.';
                $message_type = 'danger';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new === '' || $confirm === '') {
            $message = 'All password fields are required.';
            $message_type = 'danger';
        } elseif ($new !== $confirm) {
            $message = 'New passwords do not match.';
            $message_type = 'danger';
        } elseif (strlen($new) < 8) {
            $message = 'New password must be at least 8 characters.';
            $message_type = 'danger';
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!isset($row['password']) || !password_verify($current, $row['password'])) {
                $message = 'Current password is incorrect.';
                $message_type = 'danger';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt2->bind_param("si", $hash, $user_id);
                if ($stmt2->execute()) {
                    $message = 'Password changed successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Could not update password.';
                    $message_type = 'danger';
                }
                $stmt2->close();
            }
        }
    }
}

// reload latest data (including aadhar)
$stmt = $conn->prepare("SELECT username, email, phone, address, birthdate, gender, state, photo, aadhar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="stylesheet" href="css/panel-unified.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
    <style>
        body { background: #edf3ee; font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        html { scrollbar-gutter: stable; }
        .navbar { background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-brand { font-weight: 700; font-size: 24px; color: #fff !important; }
        .nav-link { color: rgba(255,255,255,0.86) !important; font-weight: 500; }
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px; height: calc(100vh - 60px);
            background: linear-gradient(180deg, #143730 0%, #0f2924 100%);
            padding: 20px; color: #fff; overflow-y: auto; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1020;
        }
        .nav-menu { list-style: none; margin-top: 25px; }
        .nav-menu li { margin-bottom: 10px; }
        .nav-menu a {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.84);
            text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-weight: 500;
        }
        .nav-menu a:hover, .nav-menu a.active { background: rgba(255,255,255,0.2); color: #fff; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 30px; }
        .form-section { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 8px 24px rgba(12,37,32,0.08); border: 1px solid #d2dfd4; margin-bottom: 25px; }
        .form-section h4 {
            font-size: clamp(1.2rem, 2vw, 1.45rem);
            font-weight: 700;
            color: #123933;
            margin-bottom: 2px;
        }
        .form-note { color: #5f746b; font-size: 0.85rem; }
        .photo-preview { max-width: 100px; border-radius: 8px; border: 1px solid #cfe0d2; }
        .form-section .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        .is-invalid + .invalid-feedback,
        .is-invalid ~ .invalid-feedback { display: block; }
        @media (max-width: 768px) {
            .sidebar { position: static; width: 100%; height: auto; margin-top: 60px; }
            .main-content { margin-left: 0; margin-top: 0; padding: 14px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#"><i class="fas fa-user-shield"></i> Earth Cafe Citizen</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><span class="nav-link">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <aside class="sidebar">
        <h5 class="fw-bold mb-3"><i class="fas fa-bars"></i> Menu</h5>
        <ul class="nav-menu">
            <li><a href="client_dashboard.php"<?php if($curPage==='client_dashboard.php') echo ' class="active"'; ?>><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="user_profile.php"<?php if($curPage==='user_profile.php') echo ' class="active"'; ?>><i class="fas fa-user-edit"></i> Profile</a></li>
            <li><a href="apply_service.php"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
            <li><a href="citizen_applications.php"><i class="fas fa-tasks"></i> My Applications</a></li>
            <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
            <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h4><i class="fas fa-user-edit"></i> Update Profile</h4>
            <form method="POST" enctype="multipart/form-data" class="row g-3 mt-2" id="profileForm" novalidate>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="update_profile" value="1">

                <div class="col-md-6">
                    <label class="form-label">Name *</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Aadhar Number *</label>
                    <input type="text" maxlength="12" class="form-control" name="aadhar" value="<?php echo htmlspecialchars($user['aadhar'] ?? ''); ?>" required pattern="[0-9]{12}">
                    <div class="invalid-feedback">Enter exactly 12 digits for Aadhar.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone</label>
                    <input type="text" maxlength="10" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" pattern="[0-9]{10}">
                    <div class="form-note">Optional, but if provided use exactly 10 digits.</div>
                    <div class="invalid-feedback">Enter exactly 10 digits for phone.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Birthdate</label>
                    <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($user['state'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select</option>
                        <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png">
                    <div class="form-note">Allowed formats: JPG, JPEG, PNG up to 4MB.</div>
                </div>
                <div class="col-md-4">
                    <?php if (!empty($user['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Profile" class="photo-preview">
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" id="profileSubmitBtn"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>

        <div class="form-section">
            <h4><i class="fas fa-lock"></i> Change Password</h4>
            <form method="POST" class="row g-3 mt-2" id="passwordForm" novalidate>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="change_password" value="1">
                <div class="col-md-4">
                    <label class="form-label">Current Password *</label>
                    <input type="password" class="form-control" name="current_password" required>
                    <div class="invalid-feedback">Current password is required.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New Password *</label>
                    <input type="password" class="form-control" name="new_password" required minlength="8">
                    <div class="invalid-feedback">New password must be at least 8 characters.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirm New *</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                    <div class="invalid-feedback">Please confirm the new password.</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-warning" id="passwordSubmitBtn"><i class="fas fa-key"></i> Change Password</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        (function () {
            var profileForm = document.getElementById('profileForm');
            var passwordForm = document.getElementById('passwordForm');

            function validateRequired(form) {
                var fields = form.querySelectorAll('input[required], select[required], textarea[required]');
                var valid = true;
                fields.forEach(function (field) {
                    var value = (field.value || '').trim();
                    var fieldValid = value !== '' && field.checkValidity();
                    field.classList.toggle('is-invalid', !fieldValid);
                    if (!fieldValid) {
                        valid = false;
                    }
                });
                return valid;
            }

            if (profileForm) {
                profileForm.addEventListener('submit', function (e) {
                    if (!validateRequired(profileForm)) {
                        e.preventDefault();
                        return;
                    }

                    var submitBtn = document.getElementById('profileSubmitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                    }
                });
            }

            if (passwordForm) {
                passwordForm.addEventListener('submit', function (e) {
                    if (!validateRequired(passwordForm)) {
                        e.preventDefault();
                        return;
                    }

                    var newPw = passwordForm.querySelector('input[name="new_password"]');
                    var confirmPw = passwordForm.querySelector('input[name="confirm_password"]');
                    if (newPw && confirmPw && newPw.value !== confirmPw.value) {
                        e.preventDefault();
                        confirmPw.classList.add('is-invalid');
                        return;
                    }

                    var submitBtn = document.getElementById('passwordSubmitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    }
                });
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>