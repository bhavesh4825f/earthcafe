<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/panel_layout.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin_login.php");
    exit();
}

$rows = [];
$stmt = $conn->prepare("
    SELECT
        sr.id AS request_id,
        u.username,
        u.email,
        sr.service_name,
        sr.status,
        sr.payment_status,
        COUNT(ad.id) AS total_documents,
        MAX(ad.created_at) AS last_uploaded_at
    FROM service_requests sr
    LEFT JOIN users u ON u.id = sr.user_id
    LEFT JOIN application_documents ad ON ad.request_id = sr.id
    GROUP BY sr.id, u.username, u.email, sr.service_name, sr.status, sr.payment_status
    ORDER BY sr.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/jpg" href="../img/ashok-stambh.jpg">
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>
    <?php render_panel_sidebar('admin', 'service_requests.php'); ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="fas fa-folder-open"></i> Document History</h3>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <div class="table-wrap">
            <?php if (empty($rows)): ?>
                <div class="alert alert-info mb-0">No application history found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Request</th>
                                <th>Citizen</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Documents</th>
                                <th>Last Upload</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>#<?php echo (int)$row['request_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['username'] ?? 'N/A'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['service_name'] ?? '-'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$row['status'])); ?></span></td>
                                    <td><span class="badge <?php echo (($row['payment_status'] ?? '') === 'paid') ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo htmlspecialchars($row['payment_status'] ?? 'unpaid'); ?></span></td>
                                    <td><?php echo (int)$row['total_documents']; ?></td>
                                    <td>
                                        <?php echo !empty($row['last_uploaded_at']) ? date('M d, Y H:i', strtotime($row['last_uploaded_at'])) : '-'; ?>
                                    </td>
                                    <td>
                                        <a href="service_requests.php" class="btn btn-sm btn-outline-primary">Open Requests</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php render_panel_footer('admin'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
