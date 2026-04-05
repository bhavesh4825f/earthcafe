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

$payments = [];
$stmt = $conn->prepare("
    SELECT id, service_name, total_fees, request_id, transaction_id, created_at
    FROM payments
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 200
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();

$total_paid = 0.0;
foreach ($payments as $p) {
    $total_paid += (float)$p['total_fees'];
}

$unpaid_requests = [];
$stmt = $conn->prepare("
    SELECT id, service_name, total_fee, created_at
    FROM service_requests
    WHERE user_id = ? AND payment_status <> 'paid'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $unpaid_requests[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="stylesheet" href="css/panel-unified.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scrollbar-gutter: stable; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        .summary-item { background: #edf3ee; border-radius: 8px; padding: 14px; text-align: center; }
        .summary-item .label { color: #4d5d5d; font-size: 12px; text-transform: uppercase; font-weight: 600; }
        .summary-item .value { font-size: 28px; font-weight: 700; color: #11342e; margin-top: 4px; }
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
        <h5 style="font-weight:700; margin-bottom:20px;"><i class="fas fa-bars"></i> Menu</h5>
        <ul class="nav-menu">
            <li><a href="client_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="user_profile.php"<?php if($curPage==='user_profile.php') echo ' class="active"'; ?>><i class="fas fa-user-edit"></i> Profile</a></li>
            <li><a href="apply_service.php"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
            <li><a href="citizen_applications.php"><i class="fas fa-tasks"></i> My Applications</a></li>
            <li><a href="citizen_payment_history.php" class="active"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
            <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="card-box">
            <h5><i class="fas fa-credit-card"></i> Payment Summary</h5>
            <div class="summary-grid mt-3">
                <div class="summary-item">
                    <div class="label">Total Payments</div>
                    <div class="value"><?php echo count($payments); ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Paid (INR)</div>
                    <div class="value" style="font-size:22px;"><?php echo number_format($total_paid, 2); ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Unpaid Requests</div>
                    <div class="value" style="color:#dc3545;"><?php echo count($unpaid_requests); ?></div>
                </div>
            </div>
        </div>

        <?php if (!empty($unpaid_requests)): ?>
            <div class="card-box">
                <h5><i class="fas fa-exclamation-circle"></i> Pending Payments</h5>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Service</th>
                                <th>Amount (INR)</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unpaid_requests as $r): ?>
                                <tr>
                                    <td>#<?php echo (int)$r['id']; ?></td>
                                    <td><?php echo htmlspecialchars($r['service_name']); ?></td>
                                    <td><?php echo number_format((float)$r['total_fee'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                                    <td>
                                        <a href="payment_gateway.php?request_id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-warning">Pay Now</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="card-box">
            <h5><i class="fas fa-receipt"></i> Payment History</h5>
            <?php if (empty($payments)): ?>
                <div class="alert alert-info">No payment records found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Service</th>
                                <th>Application</th>
                                <th>Transaction ID</th>
                                <th>Amount (INR)</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $pay): ?>
                                <tr>
                                    <td>#<?php echo (int)$pay['id']; ?></td>
                                    <td><?php echo htmlspecialchars($pay['service_name']); ?></td>
                                    <td>#<?php echo htmlspecialchars((string)$pay['request_id']); ?></td>
                                    <td><?php echo !empty($pay['transaction_id']) ? htmlspecialchars($pay['transaction_id']) : '<span style="color:#999;">—</span>'; ?></td>
                                    <td><?php echo number_format((float)$pay['total_fees'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($pay['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
