<?php
session_start();
include 'DataBaseConnection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$curPage = basename($_SERVER['PHP_SELF']);

$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$user_email = $user['email'] ?? '';

$stats = [
    'total_requests' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0
];

$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected
    FROM service_requests
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($row) {
    $stats['total_requests'] = (int)($row['total_requests'] ?? 0);
    $stats['pending'] = (int)($row['pending'] ?? 0);
    $stats['in_progress'] = (int)($row['in_progress'] ?? 0);
    $stats['completed'] = (int)($row['completed'] ?? 0);
    $stats['rejected'] = (int)($row['rejected'] ?? 0);
}

$paid_total = 0.0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(total_fees), 0) AS total_paid FROM payments WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$paid_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$paid_total = (float)($paid_row['total_paid'] ?? 0);

$avg_days = 0.0;
$stmt = $conn->prepare("SELECT AVG(TIMESTAMPDIFF(DAY, created_at, COALESCE(approved_at, NOW()))) AS avg_days FROM service_requests WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$avg_days = (float)($avg_row['avg_days'] ?? 0);

$today_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM service_requests WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$payment_flash = '';
$payment_flash_type = '';
if (isset($_GET['payment'])) {
    if ($_GET['payment'] === 'success') {
        $payment_flash = 'Payment completed successfully.';
        $payment_flash_type = 'success';
    } elseif ($_GET['payment'] === 'failed') {
        $payment_flash = 'Payment failed. Please try again.';
        $payment_flash_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="stylesheet" href="css/panel-unified.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scrollbar-gutter: stable; }
        body { background: #edf3ee; font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); box-shadow: 0 2px 10px rgba(0,0,0,0.1); position: fixed; top: 0; left: 0; right: 0; z-index: 1030; }
        .navbar-brand { font-weight: 700; font-size: 24px; color: #fff !important; }
        .nav-link { color: #ffffff !important; font-weight: 500; }
        .nav-link:hover { color: #ffffff !important; }
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px; height: calc(100vh - 60px);
            background: linear-gradient(180deg, #143730 0%, #0f2924 100%);
            padding: 20px; color: #fff; overflow-y: auto; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1020;
        }
        .nav-menu { list-style: none; margin-top: 25px; }
        .nav-menu li { margin-bottom: 10px; }
        .nav-menu a {
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #ffffff;
            text-decoration: none; border-radius: 8px; transition: all 0.3s ease; font-weight: 500; font-size: 14px;
        }
        .nav-menu a:hover, .nav-menu a.active { background: rgba(255,255,255,0.2); color: #ffffff; }
        .main-content { margin-left: 250px; margin-top: 60px; padding: 30px; }
        .dashboard-header {
            background: #fff; padding: 20px 30px; border-radius: 10px; margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;
        }
        .dashboard-header h1 { color: #333; margin: 0; font-size: 28px; font-weight: 700; }
        .welcome-text { color: #666; font-size: 14px; }
        .logout-btn {
            background: #be2f2f; color: #fff; border: none; padding: 10px 20px; border-radius: 8px;
            font-weight: 600; text-decoration: none;
        }
        .logout-btn:hover { background: #982222; color: #fff; }
        .quick-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .quick-card {
            background: #fff; border-radius: 10px; padding: 22px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-decoration: none; color: inherit; transition: all 0.3s ease;
        }
        .quick-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(15,106,93,0.2); color: inherit; }
        .quick-icon {
            width: 56px; height: 56px; margin: 0 auto 12px; border-radius: 10px;
            background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
        }
        .quick-title { font-weight: 600; color: #333; margin-bottom: 4px; }
        .quick-desc { font-size: 13px; color: #999; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 16px; margin-bottom: 25px; }
        .stat-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 20px; text-align: center; }
        .stat-label { color: #666; font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .stat-number { font-size: 30px; font-weight: 700; color: #333; margin-top: 6px; }
        .stat-pending { color: #9a6900 !important; }
        .stat-progress { color: #0c5ca9 !important; }
        .stat-completed { color: #1e7e46 !important; }
        .stat-rejected { color: #b32727 !important; }
        .stat-paid { color: #1e7e46 !important; }
        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; padding: 20px; }
            .dashboard-header { flex-direction: column; align-items: flex-start; gap: 12px; }
        }
        @media (max-width: 576px) {
            .sidebar { width: 150px; padding: 15px; }
            .main-content { margin-left: 150px; padding: 15px; }
            .dashboard-header h1 { font-size: 21px; }
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
        <h5 style="font-weight:700; margin-bottom:20px;"><i class="fas fa-bars"></i> Menu</h5>
        <ul class="nav-menu">
            <li><a href="client_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="user_profile.php"<?php if(
                $curPage==='user_profile.php') echo ' class="active"'; ?>><i class="fas fa-user-edit"></i> Profile</a></li>
            <li><a href="apply_service.php"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
            <li><a href="citizen_applications.php"><i class="fas fa-tasks"></i> My Applications</a></li>
            <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
            <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <?php if ($payment_flash !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($payment_flash_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($payment_flash); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <div>
                <h1>Citizen Dashboard</h1>
                <p class="welcome-text"><?php echo htmlspecialchars($user_email); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="apply_service.php" class="btn btn-warning"><i class="fas fa-plus-circle"></i> New Application</a>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="quick-cards">
            <a href="apply_service.php" class="quick-card">
                <div class="quick-icon"><i class="fas fa-file-signature"></i></div>
                <div class="quick-title">Apply for Service</div>
                <div class="quick-desc">Submit new applications</div>
            </a>
            <a href="citizen_applications.php" class="quick-card">
                <div class="quick-icon"><i class="fas fa-search"></i></div>
                <div class="quick-title">Track Status</div>
                <div class="quick-desc">See live application progress</div>
            </a>
            <a href="citizen_payment_history.php" class="quick-card">
                <div class="quick-icon"><i class="fas fa-credit-card"></i></div>
                <div class="quick-title">Payment History</div>
                <div class="quick-desc">View paid transactions</div>
            </a>
            <a href="citizen_document_center.php" class="quick-card">
                <div class="quick-icon"><i class="fas fa-folder-open"></i></div>
                <div class="quick-title">Document Center</div>
                <div class="quick-desc">Preview and replace uploaded files</div>
            </a>
            <a href="index.php" class="quick-card">
                <div class="quick-icon"><i class="fas fa-home"></i></div>
                <div class="quick-title">Back to Home</div>
                <div class="quick-desc">Visit main portal</div>
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Requests</div><div class="stat-number"><?php echo $stats['total_requests']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-number stat-pending"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-card"><div class="stat-label">In Progress</div><div class="stat-number stat-progress"><?php echo $stats['in_progress']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Completed</div><div class="stat-number stat-completed"><?php echo $stats['completed']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Rejected</div><div class="stat-number stat-rejected"><?php echo $stats['rejected']; ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Spent (INR)</div><div class="stat-number stat-paid"><?php echo number_format($paid_total, 2); ?></div></div>
            <div class="stat-card"><div class="stat-label">Today's Applications</div><div class="stat-number"><?php echo (int)$today_count; ?></div></div>
            <div class="stat-card"><div class="stat-label">Avg Processing Days</div><div class="stat-number"><?php echo number_format($avg_days, 1); ?></div></div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
