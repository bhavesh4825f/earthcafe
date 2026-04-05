<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/panel_layout.php';

// Check if employee is logged in
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

$employee_name = $_SESSION['employee_name'];
$employee_id = $_SESSION['employee_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/modern-design.css">
    <link rel="icon" type="image/jpg" href="../img/ashok-stambh.jpg">
    
    <?php render_panel_styles(); ?>
    
    <style>
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card-item {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }

        .card-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 26px rgba(15,106,93,0.2);
            color: inherit;
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .card-description {
            font-size: 13px;
            color: #999;
        }

        .logout-btn {
            background: #be2f2f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #982222;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('employee', (string)$employee_name); ?>

    <?php render_panel_sidebar('employee', 'dashboard.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-header">
            <div>
                <h1>Employee Dashboard</h1>
                <p class="welcome-text">Manage service requests and track progress</p>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
            <a href="all_applications.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="card-title">All Applications</div>
                <div class="card-description">View details and manage every citizen application</div>
            </a>

            <a href="change_password.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="card-title">Change Password</div>
                <div class="card-description">Update your account password</div>
            </a>

            <a href="manage_profile.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="card-title">Manage Profile</div>
                <div class="card-description">Update employee profile details</div>
            </a>
        </div>
    </main>
    <?php render_panel_footer('employee'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
