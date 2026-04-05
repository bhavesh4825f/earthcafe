<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/audit.php';

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Citizen';
$curPage = basename($_SERVER['PHP_SELF']);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_doc'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $doc_id = (int)($_POST['doc_id'] ?? 0);

    // fetch document and its parent request status
    $stmt = $conn->prepare(
        "SELECT ad.id, ad.request_id, ad.document_name, sr.status, sr.additional_docs_requested
         FROM application_documents ad
         INNER JOIN service_requests sr ON sr.id = ad.request_id
         WHERE ad.id = ? AND ad.user_id = ? LIMIT 1"
    );
    $stmt->bind_param('ii', $doc_id, $user_id);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc) {
        $message = 'Document not found.';
        $message_type = 'danger';
    } elseif (!in_array($doc['status'], ['rejected'], true) && empty($doc['additional_docs_requested'])) {
        // only allow replacement when the application was rejected or the employee explicitly requested documents
        $message = 'Document cannot be changed at this stage.';
        $message_type = 'danger';
    } elseif (!isset($_FILES['new_file']) || $_FILES['new_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please choose a valid file.';
        $message_type = 'danger';
    } else {
        $name = $_FILES['new_file']['name'];
        $tmp = $_FILES['new_file']['tmp_name'];
        $size = (int)$_FILES['new_file']['size'];
        if (!is_allowed_upload($tmp, $name, $size, 5 * 1024 * 1024)) {
            $message = 'Invalid file type or size.';
            $message_type = 'danger';
        } else {
            $dir = __DIR__ . '/uploads/' . $user_id . '/request_' . (int)$doc['request_id'];
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            $file = 'replace_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
            $abs = $dir . '/' . $file;
            $rel = 'uploads/' . $user_id . '/request_' . (int)$doc['request_id'] . '/' . $file;
            if (move_uploaded_file($tmp, $abs)) {
                $up = $conn->prepare("UPDATE application_documents SET file_path = ?, created_at = NOW() WHERE id = ?");
                $up->bind_param('si', $rel, $doc_id);
                $up->execute();
                $up->close();
                audit_log($conn, 'citizen', $user_id, 'replace_document', 'application_document', (string)$doc_id, $rel);
                $message = 'Document replaced successfully.';
                $message_type = 'success';
            } else {
                $message = 'Unable to upload file.';
                $message_type = 'danger';
            }
        }
    }
}

$rows = [];
// include status and additional_docs_requested so we can lock editing when appropriate
$stmt = $conn->prepare("SELECT ad.id, ad.request_id, ad.document_name, ad.file_path, ad.created_at, sr.service_name, sr.status, sr.additional_docs_requested
    FROM application_documents ad
    INNER JOIN service_requests sr ON sr.id = ad.request_id
    WHERE ad.user_id = ? AND ad.uploaded_by = 'citizen'
    ORDER BY ad.created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rs = $stmt->get_result();
while ($r = $rs->fetch_assoc()) $rows[] = $r;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Center</title>
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
        <li><a href="citizen_payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
        <li><a href="citizen_document_center.php" class="active"><i class="fas fa-folder-open"></i> Document Center</a></li>
        <li><a href="index.php"><i class="fas fa-globe"></i> Website Home</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="card-box">
        <div class="d-flex justify-content-between align-items-center mb-0">
            <h4 class="mb-0"><i class="fas fa-folder-open"></i> Document Center</h4>
            <a href="client_dashboard.php" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <p class="text-muted" style="font-size:0.9rem;">Documents are locked after submission unless the application has been rejected or additional files were requested by staff.</p>

    <?php if ($message): ?><div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <div class="table-responsive card-box">
        <table class="table table-hover table-card-responsive">
            <thead><tr><th>Doc</th><th>Service</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if(empty($rows)): ?><tr><td colspan="4" class="text-center text-muted">No documents found.</td></tr><?php endif; ?>
            <?php foreach($rows as $r): ?>
                <tr>
                    <td data-label="Doc"><?php echo htmlspecialchars((string)$r['document_name']); ?><br><small>#<?php echo (int)$r['request_id']; ?></small></td>
                    <td data-label="Service"><?php echo htmlspecialchars((string)$r['service_name']); ?></td>
                    <td data-label="Updated"><?php echo date('M d, Y H:i', strtotime((string)$r['created_at'])); ?></td>
                    <td data-label="Actions">
                        <a class="btn btn-sm btn-outline-primary" target="_blank" href="download_application_document.php?doc_id=<?php echo (int)$r['id']; ?>&mode=view">Preview</a>
                        <a class="btn btn-sm btn-outline-secondary" href="download_application_document.php?doc_id=<?php echo (int)$r['id']; ?>">Download</a>
                        <?php
                            // only show replacement option if request was rejected or documents specifically requested
                            $canReplace = ($r['status'] === 'rejected' || !empty($r['additional_docs_requested']));
                        ?>
                        <?php if ($canReplace): ?>
                            <form class="d-inline replace-form" method="POST" enctype="multipart/form-data">
                                <?php echo csrf_input(); ?>
                                <input type="hidden" name="replace_doc" value="1">
                                <input type="hidden" name="doc_id" value="<?php echo (int)$r['id']; ?>">
                                <input type="file" name="new_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <button class="btn btn-sm btn-warning" type="submit">Replace</button>
                                <progress class="upload-progress" value="0" max="100" style="width:90px;display:none;"></progress>
                            </form>
                        <?php else: ?>
                            <span class="text-muted small">Locked</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.replace-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var progress = form.querySelector('.upload-progress');
        progress.style.display = 'inline-block';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'citizen_document_center.php');
        xhr.upload.onprogress = function (evt) {
            if (evt.lengthComputable) progress.value = Math.round((evt.loaded / evt.total) * 100);
        };
        xhr.onload = function () { window.location.reload(); };
        xhr.send(new FormData(form));
    });
});
</script>
</body>
</html>
