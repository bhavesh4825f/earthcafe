<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/panel_layout.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

// Fetch payments
$payments = [];
$stmt = $conn->prepare("SELECT id, user_id, service_name, service_fees, consultancy_fees, total_fees, created_at, request_id, transaction_id FROM payments ORDER BY created_at DESC LIMIT 100");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}
$stmt->close();

// Calculate totals
$total_revenue = 0;
$total_payments = count($payments);
foreach ($payments as $payment) {
    $total_revenue += $payment['total_fees'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History</title>
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
        .stats-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h4 { color: #667eea; font-size: 12px; margin-bottom: 10px; }
        .stat-card .value { font-size: 28px; font-weight: 700; color: #333; }
        .table-container { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table { margin: 0; }
        .table thead { background: #f8f9fa; }
        .table th { border: none; color: #333; font-weight: 600; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>

    <div class="container-layout">
        <?php render_panel_sidebar('admin', 'payment_history.php'); ?>

        <!-- Main Content -->
        <main class="main-content">
            <h2 style="color: #333; margin-bottom: 25px;"><i class="fas fa-money-bill-wave"></i> Payment History</h2>

            <!-- Stats -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h4>TOTAL PAYMENTS</h4>
                    <div class="value"><?php echo $total_payments; ?></div>
                </div>
                <div class="stat-card">
                    <h4>TOTAL REVENUE</h4>
                    <div class="value">₹<?php echo number_format($total_revenue, 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4>AVG PAYMENT</h4>
                    <div class="value">₹<?php echo $total_payments > 0 ? number_format($total_revenue / $total_payments, 2) : '0.00'; ?></div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="table-container">
                <div style="overflow-x: auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>Service Name</th>
                                <th>Service Fee</th>
                                <th>Consultancy Fee</th>
                                <th>Total Amount</th>
                                <th>Request ID</th>
                                <th>Transaction ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo $payment['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['service_name']); ?></td>
                                    <td>₹<?php echo number_format($payment['service_fees'], 2); ?></td>
                                    <td>₹<?php echo number_format($payment['consultancy_fees'], 2); ?></td>
                                    <td><strong>₹<?php echo number_format($payment['total_fees'], 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars((string)$payment['request_id']); ?></td>
                                    <td><?php echo !empty($payment['transaction_id']) ? htmlspecialchars($payment['transaction_id']) : '<span style="color:#999;">—</span>'; ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
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
