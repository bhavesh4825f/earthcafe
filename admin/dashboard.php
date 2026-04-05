<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/panel_layout.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

$stats = [
    'today' => 0,
    'pending' => 0,
    'completed' => 0,
    'avg_days' => 0.0
];
$rs = $conn->query("SELECT COUNT(*) AS c FROM service_requests WHERE DATE(created_at) = CURDATE()");
if ($rs) {
    $stats['today'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}
$rs = $conn->query("SELECT COUNT(*) AS c FROM service_requests WHERE status = 'pending'");
if ($rs) {
    $stats['pending'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}
$rs = $conn->query("SELECT COUNT(*) AS c FROM service_requests WHERE status = 'completed'");
if ($rs) {
    $stats['completed'] = (int)($rs->fetch_assoc()['c'] ?? 0);
}
$rs = $conn->query("SELECT AVG(TIMESTAMPDIFF(DAY, created_at, COALESCE(approved_at, NOW()))) AS avg_days FROM service_requests");
if ($rs) {
    $stats['avg_days'] = (float)($rs->fetch_assoc()['avg_days'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/modern-design.css">
    <link rel="icon" type="image/jpg" href="../img/ashoksthambh.jpg">
    
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 26px rgba(15,106,93,0.15);
        }

        .stat-card .t {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #888;
            margin-bottom: 8px;
        }

        .stat-card .n {
            font-size: 36px;
            font-weight: 800;
            color: #0f4c43;
            line-height: 1;
        }

        .n-pending { color: #9a6900 !important; }
        .n-completed { color: #1e7e46 !important; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)$admin_name); ?>

    <?php render_panel_sidebar('admin', 'dashboard.php'); ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-header">
            <div>
                <h1>Admin Dashboard</h1>
                <p class="welcome-text">Welcome back! Here you can manage all system operations.</p>
            </div>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Dashboard Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="t">Today</div><div class="n"><?php echo (int)$stats['today']; ?></div></div>
            <div class="stat-card"><div class="t">Pending</div><div class="n n-pending"><?php echo (int)$stats['pending']; ?></div></div>
            <div class="stat-card"><div class="t">Completed</div><div class="n n-completed"><?php echo (int)$stats['completed']; ?></div></div>
            <div class="stat-card"><div class="t">Avg Days</div><div class="n"><?php echo number_format((float)$stats['avg_days'], 1); ?></div></div>
        </div>
        <div class="dashboard-cards">
            <a href="add_employee.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="card-title">Add Employee</div>
                <div class="card-description">Create new employee accounts</div>
            </a>

            <a href="manage_services.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <div class="card-title">Service Builder</div>
                <div class="card-description">Create dynamic services</div>
            </a>

            <a href="service_requests.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="card-title">Service Requests</div>
                <div class="card-description">Manage client service requests</div>
            </a>

            <a href="payment_history.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="card-title">Payment History</div>
                <div class="card-description">View all payment records</div>
            </a>

            <a href="manage_contactus.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="card-title">Contact Queries</div>
                <div class="card-description">Track and resolve citizen queries</div>
            </a>

            <a href="newsletter.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="card-title">Newsletter</div>
                <div class="card-description">Send updates to subscribers</div>
            </a>

            <a href="manage_users.php" class="card-item">
                <div class="card-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="card-title">User Management</div>
                <div class="card-description">Create and deactivate users</div>
            </a>
        </div>
    </main>
    <?php render_panel_footer('admin'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
