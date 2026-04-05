<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Citizen';
$request_id = (int)($_GET['request_id'] ?? 0);

if ($request_id <= 0) {
    header('Location: citizen_applications.php');
    exit();
}

$req_stmt = $conn->prepare("SELECT * FROM service_requests WHERE id = ? AND user_id = ? LIMIT 1");
$req_stmt->bind_param('ii', $request_id, $user_id);
$req_stmt->execute();
$request = $req_stmt->get_result()->fetch_assoc();
$req_stmt->close();

if (!$request) {
    header('Location: citizen_applications.php');
    exit();
}

$docs = [];
$employee_docs = [];
$doc_stmt = $conn->prepare(
    "SELECT id, document_name, file_path, uploaded_by, created_at
     FROM application_documents
     WHERE request_id = ?
     ORDER BY created_at DESC"
);
$doc_stmt->bind_param('i', $request_id);
$doc_stmt->execute();
$doc_rs = $doc_stmt->get_result();
while ($d = $doc_rs->fetch_assoc()) {
    if ($d['uploaded_by'] === 'employee') {
        // skip certificate itself, as it's shown separately
        if (strtolower(trim($d['document_name'])) === 'completion document') {
            continue;
        }
        $employee_docs[] = $d;
    } else {
        $docs[] = $d;
    }
}
$doc_stmt->close();

$remarks = [];
$remark_stmt = $conn->prepare("SELECT remark_by_type, remark_type, message, created_at FROM application_remarks WHERE request_id = ? ORDER BY created_at DESC LIMIT 15");
$remark_stmt->bind_param('i', $request_id);
$remark_stmt->execute();
$remark_rs = $remark_stmt->get_result();
while ($r = $remark_rs->fetch_assoc()) {
    $remarks[] = $r;
}
$remark_stmt->close();

$dynamic_form_data = json_decode((string)($request['dynamic_form_data'] ?? ''), true);
$has_dynamic_data = is_array($dynamic_form_data) && !empty($dynamic_form_data);

function status_badge_class(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'pending') return 'bg-warning text-dark';
    if ($s === 'in_progress') return 'bg-primary';
    if ($s === 'completed') return 'bg-success';
    if ($s === 'rejected') return 'bg-danger';
    return 'bg-secondary';
}

function labelize(string $key): string
{
    return ucwords(trim((string)preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $key))));
}

function timeline_steps(array $request): array
{
    $status = strtolower((string)($request['status'] ?? 'pending'));
    $paid = strtolower((string)($request['payment_status'] ?? 'unpaid')) === 'paid';
    return [
        ['label' => 'Submitted', 'done' => true],
        ['label' => 'Paid', 'done' => $paid],
        ['label' => 'In Progress', 'done' => in_array($status, ['in_progress', 'completed'], true)],
        ['label' => 'Completed', 'done' => $status === 'completed']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/citizen-panel.css">
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
        <li><a href="apply_service.php"><i class="fas fa-plus-circle"></i> Apply Service</a></li>
        <li><a href="citizen_applications.php" class="active"><i class="fas fa-tasks"></i> My Applications</a></li>
        <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
        <li><a href="citizen_document_center.php"><i class="fas fa-folder-open"></i> Document Center</a></li>
        <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="fas fa-file-alt"></i> Application #<?php echo (int)$request['id']; ?> Details</h4>
        <a href="citizen_applications.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <div class="card-box">
        <h6>Application Timeline</h6>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <?php foreach (timeline_steps($request) as $step): ?>
                <span class="badge <?php echo $step['done'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($step['label']); ?></span>
                <span class="text-muted">></span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card-box">
        <div class="row g-2">
            <div class="col-md-6"><strong>Service:</strong> <?php echo htmlspecialchars((string)$request['service_name']); ?></div>
            <div class="col-md-3"><strong>Status:</strong> <span class="badge <?php echo status_badge_class((string)$request['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', (string)$request['status'])); ?></span></div>
            <div class="col-md-3"><strong>Payment:</strong> <span class="badge <?php echo ((string)$request['payment_status'] === 'paid') ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars((string)$request['payment_status']); ?></span></div>
            <div class="col-md-4"><strong>Amount:</strong> <?php echo number_format((float)$request['total_fee'], 2); ?> INR</div>
            <div class="col-md-4"><strong>Applied On:</strong> <?php echo date('M d, Y H:i', strtotime((string)$request['created_at'])); ?></div>
            <div class="col-md-4"><strong>Last Updated:</strong> <?php echo date('M d, Y H:i', strtotime((string)$request['updated_at'])); ?></div>
        </div>
    </div>

    <div class="card-box">
        <h6>Description</h6>
        <p class="mb-0"><?php echo nl2br(htmlspecialchars((string)($request['description'] ?? 'No description'))); ?></p>
    </div>

    <div class="card-box">
        <h6>Application Form Details</h6>
        <?php if (!$has_dynamic_data): ?>
            <p class="text-muted mb-0">No extra form fields submitted.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light"><tr><th style="width:35%">Field</th><th>Value</th></tr></thead>
                    <tbody>
                    <?php foreach ($dynamic_form_data as $k => $v): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars(labelize((string)$k)); ?></strong></td>
                            <td>
                                <?php
                                if (is_array($v)) {
                                    $flat = array_map(fn($item) => is_scalar($item) ? (string)$item : json_encode($item), $v);
                                    echo nl2br(htmlspecialchars(implode(', ', $flat)));
                                } elseif (is_bool($v)) {
                                    echo $v ? 'Yes' : 'No';
                                } else {
                                    echo nl2br(htmlspecialchars((string)$v));
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Submitted Documents</h6>
            <?php if ((int)($request['additional_docs_requested'] ?? 0) === 1 || $request['status'] === 'rejected'): ?>
                <a href="upload_additional_documents.php?request_id=<?php echo (int)$request['id']; ?>" class="btn btn-sm btn-warning">Upload Documents</a>
            <?php endif; ?>
        </div>
        <?php if (empty($docs)): ?>

            <p class="text-muted mb-0">No documents uploaded yet.</p>
        <?php else: ?>
            <?php foreach ($docs as $doc): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong><?php echo htmlspecialchars((string)$doc['document_name']); ?></strong>
                        <small class="text-muted d-block">(<?php echo date('M d, Y H:i', strtotime((string)$doc['created_at'])); ?>)</small>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>&mode=view">View</a>
                        <a class="btn btn-sm btn-outline-secondary" href="download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>">Download</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($employee_docs)): ?>
        <div class="card-box">
            <h6>Staff Documents</h6>
            <?php foreach ($employee_docs as $doc): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong><?php echo htmlspecialchars((string)$doc['document_name']); ?></strong>
                        <small class="text-muted d-block"><?php echo date('M d, Y H:i', strtotime((string)$doc['created_at'])); ?></small>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>&mode=view">View</a>
                        <a class="btn btn-sm btn-outline-secondary" href="download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>">Download</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    </div>

    <?php if (!empty($remarks)): ?>
        <div class="card-box">
            <h6>Recent Remarks</h6>
            <ul class="mb-0">
                <?php foreach ($remarks as $remark): ?>
                    <li>
                        <strong><?php echo htmlspecialchars(strtoupper((string)$remark['remark_by_type'])); ?></strong>:
                        <?php echo htmlspecialchars((string)$remark['message']); ?>
                        <small class="text-muted">(<?php echo date('M d, Y H:i', strtotime((string)$remark['created_at'])); ?>)</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card-box">
        <?php if ((string)$request['payment_status'] !== 'paid'): ?>
            <a class="btn btn-warning" href="payment_gateway.php?request_id=<?php echo (int)$request['id']; ?>"><i class="fas fa-credit-card"></i> Complete Payment</a>
        <?php endif; ?>
        <?php if ((string)$request['status'] === 'completed' && !empty($request['certificate_file'])): ?>
            <a class="btn btn-success" href="download_certificate.php?request_id=<?php echo (int)$request['id']; ?>"><i class="fas fa-download"></i> Download Certificate</a>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
