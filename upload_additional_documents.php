<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';

require_citizen('login.php');

$user_id = (int)$_SESSION['user_id'];
$request_id = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$message = '';
$message_type = '';

if ($request_id <= 0) {
    http_response_code(400);
    exit("Invalid request id.");
}

$req_stmt = $conn->prepare("
    SELECT id, service_name, status, additional_docs_requested
    FROM service_requests
    WHERE id = ? AND user_id = ?
");
$req_stmt->bind_param("ii", $request_id, $user_id);
$req_stmt->execute();
$request_data = $req_stmt->get_result()->fetch_assoc();
$req_stmt->close();

if (!$request_data) {
    http_response_code(403);
    exit("Application not found.");
}

// only allow when employee has asked for additional docs or the application has been rejected
if (empty($request_data['additional_docs_requested']) && $request_data['status'] !== 'rejected') {
    http_response_code(403);
    exit("Not allowed to upload documents at this stage.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_docs'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $doc_name = trim($_POST['document_name'] ?? '');
    if ($doc_name === '') {
        $doc_name = 'Additional Document';
    }

    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $message = "Please select a valid file.";
        $message_type = "danger";
    } else {
        $size = (int)$_FILES['document_file']['size'];

        if (!is_allowed_upload($_FILES['document_file']['tmp_name'], $_FILES['document_file']['name'], $size, 6 * 1024 * 1024)) {
            $message = "Only PDF/JPG/JPEG/PNG files up to 6MB are allowed.";
            $message_type = "danger";
        } else {
            $dir = __DIR__ . '/uploads/' . $user_id . '/request_' . $request_id;
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $fname = 'extra_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['document_file']['name']));
            $abs_path = $dir . '/' . $fname;
            $relative = 'uploads/' . $user_id . '/request_' . $request_id . '/' . $fname;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $abs_path)) {
                $ins = $conn->prepare("
                    INSERT INTO application_documents (request_id, user_id, document_name, file_path, uploaded_by)
                    VALUES (?, ?, ?, ?, 'citizen')
                ");
                $ins->bind_param("iiss", $request_id, $user_id, $doc_name, $relative);
                $ins->execute();
                $ins->close();

                $upd = $conn->prepare("UPDATE service_requests SET additional_docs_requested = 0 WHERE id = ? AND user_id = ?");
                $upd->bind_param("ii", $request_id, $user_id);
                $upd->execute();
                $upd->close();

                $remark_by_type = 'citizen';
                $remark_by_id = $user_id;
                $remark_type = 'note';
                $remark_message = "Citizen uploaded additional document: " . $doc_name;
                $remark = $conn->prepare("
                    INSERT INTO application_remarks (request_id, remark_by_type, remark_by_id, remark_type, message)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $remark->bind_param("isiss", $request_id, $remark_by_type, $remark_by_id, $remark_type, $remark_message);
                $remark->execute();
                $remark->close();

                $message = "Additional document uploaded successfully.";
                $message_type = "success";
            } else {
                $message = "Unable to upload file.";
                $message_type = "danger";
            }
        }
    }
}

$documents = [];
$doc_stmt = $conn->prepare("
    SELECT id, document_name, file_path, uploaded_by, created_at
    FROM application_documents
    WHERE request_id = ?
    ORDER BY created_at DESC
");
$doc_stmt->bind_param("i", $request_id);
$doc_stmt->execute();
$res = $doc_stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $documents[] = $row;
}
$doc_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Additional Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .box { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
    </style>
</head>
<body>
    <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4>Upload Additional Documents</h4>
            <a href="client_dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>
        <p><strong>Application #<?php echo (int)$request_data['id']; ?></strong> | <?php echo htmlspecialchars($request_data['service_name']); ?></p>
        <?php if (!$request_data['additional_docs_requested'] && $request_data['status'] === 'rejected'): ?>
            <div class="alert alert-info">Your application was rejected. You may upload corrected documents.</div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="row g-3">
            <?php echo csrf_input(); ?>
            <input type="hidden" name="upload_docs" value="1">
            <input type="hidden" name="request_id" value="<?php echo (int)$request_id; ?>">
            <div class="col-md-6">
                <label class="form-label">Document Name *</label>
                <input type="text" class="form-control" name="document_name" placeholder="e.g. Income Proof Update" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">File *</label>
                <input type="file" class="form-control" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Upload Document</button>
            </div>
        </form>

        <hr>
        <h6>Existing Uploaded Documents</h6>
        <?php if (empty($documents)): ?>
            <div class="text-muted">No documents uploaded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['document_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploaded_by']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($doc['created_at'])); ?></td>
                                <td><a href="download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
