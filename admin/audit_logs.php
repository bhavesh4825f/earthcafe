<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_admin('../admin_login.php');

$actor = trim((string)($_GET['actor'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = ' WHERE 1=1 ';
$types = '';
$params = [];
if ($actor !== '') { $where .= ' AND actor_type = ? '; $types .= 's'; $params[] = $actor; }
if ($action !== '') { $where .= ' AND action LIKE ? '; $types .= 's'; $params[] = '%' . $action . '%'; }
if ($dateFrom !== '') { $where .= ' AND DATE(created_at) >= ? '; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where .= ' AND DATE(created_at) <= ? '; $types .= 's'; $params[] = $dateTo; }

$sql = "SELECT id, actor_type, actor_id, action, entity_type, entity_id, details, ip_address, created_at FROM audit_logs $where ORDER BY created_at DESC LIMIT 500";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    $refs = [];
    $refs[] = &$types;
    foreach ($params as $k => $v) { $refs[] = &$params[$k]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
$stmt->execute();
$rs = $stmt->get_result();
$rows = [];
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
<?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>
<?php render_panel_sidebar('admin', 'audit_logs.php'); ?>
<main class="main-content">
    <h3><i class="fas fa-clipboard-list"></i> Audit Logs</h3>
    <form class="row g-2 my-3" method="GET">
        <div class="col-md-2">
            <select class="form-select" name="actor">
                <option value="">All Actors</option>
                <option value="admin" <?php echo $actor==='admin'?'selected':''; ?>>Admin</option>
                <option value="employee" <?php echo $actor==='employee'?'selected':''; ?>>Employee</option>
                <option value="citizen" <?php echo $actor==='citizen'?'selected':''; ?>>Citizen</option>
            </select>
        </div>
        <div class="col-md-3"><input class="form-control" name="action" value="<?php echo htmlspecialchars($action); ?>" placeholder="Action"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Go</button></div>
        <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="audit_logs.php">Reset</a></div>
    </form>
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table table-hover table-card-responsive">
                <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No logs found.</td></tr>
                <?php else: foreach($rows as $r): ?>
                    <tr>
                        <td data-label="ID">#<?php echo (int)$r['id']; ?></td>
                        <td data-label="Actor"><?php echo htmlspecialchars((string)$r['actor_type']); ?> #<?php echo (int)$r['actor_id']; ?></td>
                        <td data-label="Action"><?php echo htmlspecialchars((string)$r['action']); ?></td>
                        <td data-label="Entity"><?php echo htmlspecialchars((string)$r['entity_type']); ?> <?php echo htmlspecialchars((string)$r['entity_id']); ?></td>
                        <td data-label="Details"><?php echo htmlspecialchars((string)$r['details']); ?></td>
                        <td data-label="IP"><?php echo htmlspecialchars((string)$r['ip_address']); ?></td>
                        <td data-label="Time"><?php echo date('M d, Y H:i', strtotime((string)$r['created_at'])); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php render_panel_footer('admin'); ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/panel-unified.js"></script>
</body>
</html>
