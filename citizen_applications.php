<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Citizen';
$curPage = basename($_SERVER['PHP_SELF']);

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = 'WHERE sr.user_id = ?';
$types = 'i';
$params = [$user_id];

if ($q !== '') {
    $where .= ' AND (sr.service_name LIKE ? OR sr.status LIKE ? OR sr.id = ?)';
    $like = '%' . $q . '%';
    $exact_id = ctype_digit($q) ? (int)$q : 0;
    $types .= 'ssi';
    $params[] = $like;
    $params[] = $like;
    $params[] = $exact_id;
}

$count_sql = "SELECT COUNT(*) AS total FROM service_requests sr $where";
$count_stmt = $conn->prepare($count_sql);
bind_params_dynamic($count_stmt, $types, $params);
$count_stmt->execute();
$total = (int)($count_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$count_stmt->close();

$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$list_sql = "
    SELECT
        sr.id,
        sr.service_name,
        sr.total_fee,
        sr.status,
        sr.payment_status,
        sr.created_at,
        sr.additional_docs_requested,
        sr.certificate_file,
        COALESCE(docs.doc_count, 0) AS doc_count
    FROM service_requests sr
    LEFT JOIN (
        SELECT request_id, COUNT(*) AS doc_count
        FROM application_documents
        WHERE uploaded_by = 'citizen'
        GROUP BY request_id
    ) docs ON docs.request_id = sr.id
    $where
    ORDER BY sr.created_at DESC
    LIMIT ? OFFSET ?
";

$list_stmt = $conn->prepare($list_sql);
$list_types = $types . 'ii';
$list_params = $params;
$list_params[] = $per_page;
$list_params[] = $offset;
bind_params_dynamic($list_stmt, $list_types, $list_params);
$list_stmt->execute();
$rs = $list_stmt->get_result();
$requests = [];
while ($row = $rs->fetch_assoc()) {
    $requests[] = $row;
}
$list_stmt->close();

function status_badge_class(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'pending') return 'bg-warning text-dark';
    if ($s === 'in_progress') return 'bg-primary';
    if ($s === 'completed') return 'bg-success';
    if ($s === 'rejected') return 'bg-danger';
    return 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <link rel="stylesheet" href="css/panel-unified.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#"><i class="fas fa-user-shield"></i> Earth Cafe Citizen</a>
        <div class="ms-auto d-flex align-items-center gap-3 text-white">
            <span>Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            <a class="text-white text-decoration-none" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
</nav>

<aside class="sidebar">
    <h5><i class="fas fa-bars"></i> Menu</h5>
    <ul class="nav-menu">
        <li><a href="client_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="user_profile.php"<?php if($curPage==='user_profile.php') echo ' class="active"'; ?>><i class="fas fa-user-edit"></i> Profile</a></li>
        <li><a href="apply_service.php"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
        <li><a href="citizen_applications.php" class="active"><i class="fas fa-tasks"></i> My Applications</a></li>
        <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
        <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
        <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <section class="content-section">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h5 class="mb-0"><i class="fas fa-tasks"></i> Application Status Tracking</h5>
            <form method="GET" class="d-flex gap-2">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search by id/service/status">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <a href="citizen_applications.php" class="btn btn-outline-secondary">Reset</a>
            </form>
        </div>

        <?php if (empty($requests)): ?>
            <div class="alert alert-info mb-0">No applications found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Docs</th>
                            <th>Cert</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>#<?php echo (int)$req['id']; ?></td>
                                <td><?php echo htmlspecialchars($req['service_name']); ?></td>
                                <td><span class="badge <?php echo status_badge_class((string)$req['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$req['status'])); ?></span></td>
                                <td>
                                    <span class="badge <?php echo ((string)$req['payment_status'] === 'paid') ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo htmlspecialchars((string)$req['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo (int)$req['doc_count']; ?></td>
                                <td class="text-center">
                                    <?php if ($req['status'] === 'completed' && !empty($req['certificate_file'])): ?>
                                        <a class="btn btn-sm btn-success" title="Download certificate" href="download_certificate.php?request_id=<?php echo (int)$req['id']; ?>">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime((string)$req['created_at'])); ?></td>
                                <td>
                                    <a class="btn btn-sm btn-info text-white" href="citizen_application_details.php?request_id=<?php echo (int)$req['id']; ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total_pages > 1): ?>
            <?php $prev = max(1, $page - 1); $next = min($total_pages, $page + 1); ?>
            <nav class="mt-2">
                <ul class="pagination mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $prev; ?>">Previous</a></li>
                    <li class="page-item disabled"><span class="page-link">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span></li>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"><a class="page-link" href="?q=<?php echo urlencode($q); ?>&page=<?php echo $next; ?>">Next</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
