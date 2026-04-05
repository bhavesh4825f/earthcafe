<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/mailer.php';

require_admin('../admin_login.php');

$message = '';
$message_type = '';
$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_request'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $request_id = (int)($_POST['request_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'pending');
    $request_docs = isset($_POST['request_docs']) ? 1 : 0;
    $admin_remark = trim($_POST['admin_remark'] ?? '');
    $assigned_to = (int)($_POST['assigned_to'] ?? 0);
    $assigned_to = $assigned_to > 0 ? $assigned_to : null;

    $allowed_status = ['pending', 'in_progress', 'completed', 'rejected'];
    if (!in_array($status, $allowed_status, true)) {
        $message = "Invalid status.";
        $message_type = "danger";
    } else {
        $approved_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
        $effective_request_docs = ($status === 'completed' || $status === 'rejected') ? 0 : $request_docs;

        $stmt = $conn->prepare("
            UPDATE service_requests
            SET status = ?, additional_docs_requested = ?, approved_at = ?, assigned_to = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sisii", $status, $effective_request_docs, $approved_at, $assigned_to, $request_id);
        if ($stmt->execute()) {
            $message = "Application updated successfully.";
            $message_type = "success";
            audit_log(
                $conn,
                'admin',
                (int)$_SESSION['admin_id'],
                'update_status',
                'service_request',
                (string)$request_id,
                'status=' . $status . '; request_docs=' . $effective_request_docs . '; assigned_to=' . ($assigned_to ?? 'null')
            );

            $remark_type = 'status_update';
            if ($effective_request_docs === 1) {
                $remark_type = 'request_document';
            } elseif ($status === 'completed') {
                $remark_type = 'approval';
            } elseif ($status === 'rejected') {
                $remark_type = 'rejection';
            }

            $remark_message = $admin_remark !== '' ? $admin_remark : ("Admin updated application to " . str_replace('_', ' ', $status) . ".");
            $admin_id = (int)$_SESSION['admin_id'];
            $remark_by_type = 'admin';
            $remark_stmt = $conn->prepare("
                INSERT INTO application_remarks (request_id, remark_by_type, remark_by_id, remark_type, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $remark_stmt->bind_param("isiss", $request_id, $remark_by_type, $admin_id, $remark_type, $remark_message);
            $remark_stmt->execute();
            $remark_stmt->close();

            $uStmt = $conn->prepare("SELECT u.id, u.email FROM service_requests sr INNER JOIN users u ON u.id = sr.user_id WHERE sr.id = ? LIMIT 1");
            if ($uStmt) {
                $uStmt->bind_param("i", $request_id);
                $uStmt->execute();
                $uRow = $uStmt->get_result()->fetch_assoc();
                $uStmt->close();
                if ($uRow) {
                    $statusText = ucwords(str_replace('_', ' ', $status));
                    create_notification(
                        $conn,
                        'citizen',
                        (int)$uRow['id'],
                        'Application #' . $request_id . ' Updated',
                        'Status changed to ' . $statusText . ($effective_request_docs === 1 ? '. Additional documents requested.' : '.'),
                        'citizen_application_details.php?request_id=' . $request_id
                    );
                    $body = '<p>Your application #' . (int)$request_id . ' status is now <strong>' . htmlspecialchars($statusText) . '</strong>.</p>';
                    if ($effective_request_docs === 1) {
                        $body .= '<p>Please upload additional documents.</p>';
                    }
                    $detailsLink = ec_app_url('/citizen_application_details.php?request_id=' . (int)$request_id);
                    $statusMail = ec_compose_application_status_email(
                        (int)$request_id,
                        $statusText,
                        $detailsLink,
                        $effective_request_docs === 1,
                        $admin_remark
                    );
                    send_system_email((string)$uRow['email'], $statusMail['subject'], $statusMail['html'], $statusMail['text']);
                }
            }

            // Notify assigned employee if set
            if ($assigned_to) {
                $service_name = '';
                $snStmt = $conn->prepare("SELECT service_name FROM service_requests WHERE id = ? LIMIT 1");
                $snStmt->bind_param("i", $request_id);
                $snStmt->execute();
                $snRow = $snStmt->get_result()->fetch_assoc();
                $snStmt->close();
                if ($snRow) {
                    $service_name = $snRow['service_name'];
                }
                create_notification(
                    $conn,
                    'employee',
                    $assigned_to,
                    'New Application Assigned',
                    'You have been assigned to handle Application #' . $request_id . ' for ' . htmlspecialchars($service_name),
                    'application_details.php?request_id=' . $request_id
                );
            }
        } else {
            $message = "Unable to update application.";
            $message_type = "danger";
        }
        $stmt->close();
    }
}

$whereSql = " WHERE sr.payment_status = 'paid' ";
$params = [];
$types = '';
if ($search !== '') {
    $whereSql .= " AND (sr.service_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?) ";
    $searchLike = '%' . $search . '%';
    $params = [$searchLike, $searchLike, $searchLike];
    $types = 'sss';
}

$countSql = "
    SELECT COUNT(*) AS total
    FROM service_requests sr
    LEFT JOIN users u ON sr.user_id = u.id
    $whereSql
";
$countStmt = $conn->prepare($countSql);
if ($types !== '') {
    bind_params_dynamic($countStmt, $types, $params);
}
$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$requests = [];
$stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.user_id,
        sr.service_name,
        sr.description,
        sr.total_fee,
        sr.status,
        sr.payment_status,
        sr.additional_docs_requested,
        sr.certificate_file,
        sr.assigned_to,
        sr.created_at,
        u.username,
        u.email
    FROM service_requests sr
    LEFT JOIN users u ON sr.user_id = u.id
    $whereSql
    ORDER BY sr.created_at DESC
    LIMIT ? OFFSET ?
");
$runParams = $params;
$runTypes = $types . 'ii';
$runParams[] = $perPage;
$runParams[] = $offset;
bind_params_dynamic($stmt, $runTypes, $runParams);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();

$request_ids = array_map(fn($r) => (int)$r['id'], $requests);
$doc_counts = [];
$docs_by_request = [];
if (!empty($request_ids)) {
    $placeholders = implode(',', array_fill(0, count($request_ids), '?'));
    $types = str_repeat('i', count($request_ids));
    $values = $request_ids;

    $doc_stmt = $conn->prepare("SELECT request_id, COUNT(*) AS cnt FROM application_documents WHERE request_id IN ($placeholders) GROUP BY request_id");
    bind_params_dynamic($doc_stmt, $types, $values);
    $doc_stmt->execute();
    $doc_rs = $doc_stmt->get_result();
    while ($d = $doc_rs->fetch_assoc()) {
        $doc_counts[(int)$d['request_id']] = (int)$d['cnt'];
    }
    $doc_stmt->close();

    $docs_stmt = $conn->prepare("
        SELECT id, request_id, document_name, uploaded_by, created_at
        FROM application_documents
        WHERE request_id IN ($placeholders)
        ORDER BY created_at DESC
    ");
    bind_params_dynamic($docs_stmt, $types, $values);
    $docs_stmt->execute();
    $docs_rs = $docs_stmt->get_result();
    while ($doc = $docs_rs->fetch_assoc()) {
        $rid = (int)$doc['request_id'];
        if (!isset($docs_by_request[$rid])) {
            $docs_by_request[$rid] = [];
        }
        $docs_by_request[$rid][] = $doc;
    }
    $docs_stmt->close();
}

$employees = [];
$emp_stmt = $conn->prepare("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name");
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
while ($emp = $emp_result->fetch_assoc()) {
    $employees[] = $emp;
}
$emp_stmt->close();

$stats = [
    'total_requests' => count($requests),
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0,
    'total_revenue' => 0
];
foreach ($requests as $req) {
    if (isset($stats[$req['status']])) {
        $stats[$req['status']]++;
    }
    $stats['total_revenue'] += (float)$req['total_fee'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 250px; height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff; padding: 24px 18px; overflow-y: auto;
        }
        .sidebar a { display: block; color: rgba(255,255,255,0.82); text-decoration: none; padding: 10px 12px; border-radius: 6px; margin-bottom: 6px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.24); color: #fff; }
        .main { margin-left: 250px; padding: 24px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .s { background: #fff; border-radius: 10px; padding: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .s .t { font-size: 12px; color: #666; text-transform: uppercase; font-weight: 600; }
        .s .n { font-size: 30px; font-weight: 700; }
        .table-wrap { background: #fff; border-radius: 10px; padding: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); }
        .badge-payment-paid { background: #d1e7dd; color: #0f5132; }
        .badge-payment-unpaid { background: #f8d7da; color: #842029; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>
    <?php render_panel_sidebar('admin', 'service_requests.php'); ?>

    <main class="main">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h3 style="margin-bottom: 14px;"><i class="fas fa-tasks"></i> Application Management</h3>
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by service, citizen name, or email">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Search</button>
            </div>
            <div class="col-md-2">
                <a href="service_requests.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
        <div class="stats">
            <div class="s"><div class="t">Total</div><div class="n"><?php echo $stats['total_requests']; ?></div></div>
            <div class="s"><div class="t">Pending</div><div class="n" style="color:#b8860b;"><?php echo $stats['pending']; ?></div></div>
            <div class="s"><div class="t">In Progress</div><div class="n" style="color:#0d6efd;"><?php echo $stats['in_progress']; ?></div></div>
            <div class="s"><div class="t">Completed</div><div class="n" style="color:#198754;"><?php echo $stats['completed']; ?></div></div>
            <div class="s"><div class="t">Rejected</div><div class="n" style="color:#dc3545;"><?php echo $stats['rejected']; ?></div></div>
            <div class="s"><div class="t">Revenue</div><div class="n" style="font-size:22px;"><?php echo number_format($stats['total_revenue'], 2); ?></div></div>
        </div>

        <div class="table-wrap">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">No applications found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Citizen</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Assigned</th>
                                <th>Payment</th>
                                <th>Docs</th>
                                <th>Date</th>
                                <th>Action</th>
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
                                    <td>
                                        <?php
                                            $assigned_name = '';
                                            if (!empty($req['assigned_to'])) {
                                                foreach ($employees as $emp) {
                                                    if ((int)$emp['id'] === (int)$req['assigned_to']) {
                                                        $assigned_name = $emp['name'];
                                                        break;
                                                    }
                                                }
                                            }
                                            echo htmlspecialchars($assigned_name ?: 'Unassigned');
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($req['payment_status'] === 'paid') ? 'badge-payment-paid' : 'badge-payment-unpaid'; ?>">
                                            <?php echo htmlspecialchars($req['payment_status'] ?? 'unpaid'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo (int)($doc_counts[(int)$req['id']] ?? 0); ?> file(s)</td>
                                    <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal<?php echo (int)$req['id']; ?>">
                                            Manage
                                        </button>
                                    </td>
                                </tr>

                                <div class="modal fade" id="modal<?php echo (int)$req['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Manage Application #<?php echo (int)$req['id']; ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <?php echo csrf_input(); ?>
                                                <div class="modal-body">
                                                    <input type="hidden" name="save_request" value="1">
                                                    <input type="hidden" name="request_id" value="<?php echo (int)$req['id']; ?>">

                                                    <div class="mb-2">
                                                        <strong>Service:</strong> <?php echo htmlspecialchars($req['service_name']); ?>
                                                        <br>
                                                        <strong>Citizen:</strong> <?php echo htmlspecialchars($req['username'] ?? 'N/A'); ?>
                                                        <br>
                                                        <strong>Email:</strong> <?php echo htmlspecialchars($req['email'] ?? '-'); ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <strong>Uploaded Documents</strong>
                                                        <div style="max-height: 160px; overflow-y: auto; background: #f8f9fa; border-radius: 8px; padding: 8px; margin-top: 6px;">
                                                            <?php if (empty($docs_by_request[(int)$req['id']])): ?>
                                                                <span class="text-muted">No documents uploaded yet.</span>
                                                            <?php else: ?>
                                                                <?php foreach ($docs_by_request[(int)$req['id']] as $doc): ?>
                                                                    <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                                                        <div>
                                                                            <a href="../download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>">
                                                                                <?php echo htmlspecialchars($doc['document_name']); ?>
                                                                            </a>
                                                                            <small class="text-muted d-block"><?php echo htmlspecialchars($doc['uploaded_by']); ?>, <?php echo date('M d, H:i', strtotime($doc['created_at'])); ?></small>
                                                                        </div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($req['certificate_file'])): ?>
                                                        <div class="alert alert-success py-2">
                                                            Completion document available.
                                                            <a class="btn btn-sm btn-outline-success ms-2" href="../download_certificate.php?request_id=<?php echo (int)$req['id']; ?>">
                                                                Download
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Status</label>
                                                            <select class="form-select" name="status" required>
                                                                <option value="pending" <?php echo $req['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="in_progress" <?php echo $req['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                <option value="completed" <?php echo $req['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="rejected" <?php echo $req['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Assign to Employee</label>
                                                            <select class="form-select" name="assigned_to">
                                                                <option value="">-- Select Employee --</option>
                                                                <?php foreach ($employees as $emp): ?>
                                                                    <option value="<?php echo (int)$emp['id']; ?>" <?php echo ((int)$req['assigned_to'] === (int)$emp['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="form-check mt-3">
                                                        <input class="form-check-input" type="checkbox" name="request_docs" id="reqDocsAdmin<?php echo (int)$req['id']; ?>" <?php echo !empty($req['additional_docs_requested']) ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="reqDocsAdmin<?php echo (int)$req['id']; ?>">
                                                            Request additional documents
                                                        </label>
                                                    </div>

                                                    <div class="mt-3">
                                                        <label class="form-label">Admin Remark</label>
                                                        <textarea class="form-control" name="admin_remark" rows="4" placeholder="Add approval/rejection reason or instructions"></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?> | Total: <?php echo $totalRows; ?></small>
                    <div class="d-flex gap-2">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="?q=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php render_panel_footer('admin'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
