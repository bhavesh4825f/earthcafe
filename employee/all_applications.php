<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_employee('../admin_login.php');

$employee_name = $_SESSION['employee_name'] ?? 'Employee';

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = "WHERE sr.payment_status = 'paid'";
$count_types = '';
$count_params = [];
if ($q !== '') {
    $where .= " AND (sr.service_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR sr.id = ?)";
    $like = '%' . $q . '%';
    $exact_id = ctype_digit($q) ? (int)$q : 0;
    $count_types = 'sssi';
    $count_params = [$like, $like, $like, $exact_id];
}

$total = 0;
$count_sql = "
    SELECT COUNT(DISTINCT sr.id) AS total
    FROM service_requests sr
    LEFT JOIN users u ON sr.user_id = u.id
    $where
";
$count_stmt = $conn->prepare($count_sql);
if ($count_types !== '') {
    bind_params_dynamic($count_stmt, $count_types, $count_params);
}
$count_stmt->execute();
$total = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$requests = [];
$list_types = $count_types . 'ii';
$list_params = $count_params;
$list_params[] = $per_page;
$list_params[] = $offset;
$stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.service_name,
        sr.total_fee,
        sr.status,
        sr.created_at,
        u.username,
        u.email,
        COUNT(ad.id) AS doc_count
    FROM service_requests sr
    LEFT JOIN users u ON sr.user_id = u.id
    LEFT JOIN application_documents ad ON ad.request_id = sr.id
    $where
    GROUP BY sr.id, sr.service_name, sr.total_fee, sr.status, sr.created_at, u.username, u.email
    ORDER BY sr.created_at DESC
    LIMIT ? OFFSET ?
");
bind_params_dynamic($stmt, $list_types, $list_params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

$stats = [
    'total' => count($requests),
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0
];
foreach ($requests as $r) {
    if ($r['status'] === 'pending') {
        $stats['pending']++;
    } elseif ($r['status'] === 'in_progress') {
        $stats['in_progress']++;
    } elseif ($r['status'] === 'completed') {
        $stats['completed']++;
    } elseif ($r['status'] === 'rejected') {
        $stats['rejected']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('employee', (string)$employee_name); ?>
    <?php render_panel_sidebar('employee', 'all_applications.php'); ?>

    <main class="main-content">
        <h3 style="margin-bottom: 18px;"><i class="fas fa-list"></i> All Citizen Applications</h3>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by app id, service, applicant or email">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="col-auto">
                <a href="all_applications.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px;">
            <div class="stat-card"><div class="t">Total</div><div class="n"><?php echo $stats['total']; ?></div></div>
            <div class="stat-card"><div class="t">Pending</div><div class="n" style="color:#b8860b;"><?php echo $stats['pending']; ?></div></div>
            <div class="stat-card"><div class="t">In Progress</div><div class="n" style="color:#0d6efd;"><?php echo $stats['in_progress']; ?></div></div>
            <div class="stat-card"><div class="t">Completed</div><div class="n" style="color:#198754;"><?php echo $stats['completed']; ?></div></div>
            <div class="stat-card"><div class="t">Rejected</div><div class="n" style="color:#dc3545;"><?php echo $stats['rejected']; ?></div></div>
        </div>

        <div class="table-wrap">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">No applications available right now.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Applicant</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Docs</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td>#<?php echo (int)$req['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['username'] ?? 'N/A'); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($req['email'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($req['service_name']); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars(str_replace('_', ' ', $req['status'])); ?></span></td>
                                    <td><?php echo (int)$req['doc_count']; ?> file(s)</td>
                                    <td><?php echo number_format((float)$req['total_fee'], 2); ?></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-info text-white" href="application_details.php?request_id=<?php echo (int)$req['id']; ?>">
                                            Open
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Applications pages" class="mt-3">
                <ul class="pagination">
                    <?php
                        $prev = max(1, $page - 1);
                        $next = min($total_pages, $page + 1);
                    ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $prev; ?>">Previous</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span></li>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $next; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
    <?php render_panel_footer('employee'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
