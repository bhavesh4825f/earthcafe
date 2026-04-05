<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_employee('../admin_login.php');

$q = trim((string)($_GET['q'] ?? ''));
$rows = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $idEq = ctype_digit($q) ? (int)$q : 0;
    $sql = "
        SELECT sr.id, sr.service_name, sr.status, sr.payment_status, sr.created_at, u.username, u.email
        FROM service_requests sr
        LEFT JOIN users u ON u.id = sr.user_id
        WHERE sr.payment_status = 'paid' AND (sr.id = ? OR sr.service_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)
        ORDER BY sr.created_at DESC
        LIMIT 200
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isss', $idEq, $like, $like, $like);
    $stmt->execute();
    $rs = $stmt->get_result();
    while ($r = $rs->fetch_assoc()) $rows[] = $r;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Search</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
<?php render_panel_header('employee', (string)($_SESSION['employee_name'] ?? 'Employee')); ?>
<?php render_panel_sidebar('employee', 'search.php'); ?>
<main class="main-content">
    <h3><i class="fas fa-search"></i> Global Search</h3>
    <form method="GET" class="row g-2 my-3">
        <div class="col-md-7"><input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search app id, name, email, service" required></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Search</button></div>
    </form>
    <div class="table-wrap">
        <?php if ($q === ''): ?>
            <div class="text-muted">Enter query to search.</div>
        <?php elseif (empty($rows)): ?>
            <div class="alert alert-info mb-0">No results found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-card-responsive">
                    <thead><tr><th>ID</th><th>Citizen</th><th>Service</th><th>Status</th><th>Payment</th><th>Date</th><th>Open</th></tr></thead>
                    <tbody>
                    <?php foreach($rows as $r): ?>
                        <tr>
                            <td data-label="ID">#<?php echo (int)$r['id']; ?></td>
                            <td data-label="Citizen"><?php echo htmlspecialchars((string)$r['username']); ?><br><small><?php echo htmlspecialchars((string)$r['email']); ?></small></td>
                            <td data-label="Service"><?php echo htmlspecialchars((string)$r['service_name']); ?></td>
                            <td data-label="Status"><?php echo htmlspecialchars((string)$r['status']); ?></td>
                            <td data-label="Payment"><?php echo htmlspecialchars((string)$r['payment_status']); ?></td>
                            <td data-label="Date"><?php echo date('M d, Y H:i', strtotime((string)$r['created_at'])); ?></td>
                            <td data-label="Open"><a class="btn btn-sm btn-outline-primary" href="application_details.php?request_id=<?php echo (int)$r['id']; ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php render_panel_footer('employee'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/panel-unified.js"></script>
</body>
</html>
