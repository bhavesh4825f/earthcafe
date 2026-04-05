<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/mailer.php';

require_employee('../admin_login.php');

$employee_id = (int)$_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$message = (string)($_SESSION['flash_message'] ?? '');
$message_type = (string)($_SESSION['flash_message_type'] ?? '');
unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);
$request_id = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

function formatFieldLabel(string $key): string
{
    $label = str_replace(['_', '-'], ' ', $key);
    $label = preg_replace('/\s+/', ' ', trim($label));
    return ucwords($label);
}

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0) > 0;
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

if ($request_id <= 0) {
    header("Location: all_applications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_request'])) {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $new_status = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $request_docs = isset($_POST['request_docs']) ? 1 : 0;

    $allowed_status = ['pending', 'in_progress', 'completed', 'rejected'];
    if (!in_array($new_status, $allowed_status, true)) {
        $message = "Invalid status selected.";
        $message_type = "danger";
    } else {
        $req_stmt = $conn->prepare("SELECT id, user_id FROM service_requests WHERE id = ?");
        $req_stmt->bind_param("i", $request_id);
        $req_stmt->execute();
        $request_data = $req_stmt->get_result()->fetch_assoc();
        $req_stmt->close();

        if (!$request_data) {
            $message = "Application not found.";
            $message_type = "danger";
        } else {
            $approved_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
            $effective_request_docs = ($new_status === 'completed' || $new_status === 'rejected') ? 0 : $request_docs;

            $update_stmt = $conn->prepare("
                UPDATE service_requests
                SET status = ?, employee_notes = ?, additional_docs_requested = ?, approved_at = ?
                WHERE id = ?
            ");
            $update_stmt->bind_param("ssisi", $new_status, $notes, $effective_request_docs, $approved_at, $request_id);
            $updated = $update_stmt->execute();
            $update_stmt->close();

            if (!$updated) {
                $message = "Failed to update application.";
                $message_type = "danger";
            } else {
                audit_log(
                    $conn,
                    'employee',
                    $employee_id,
                    'update_status',
                    'service_request',
                    (string)$request_id,
                    'status=' . $new_status . '; request_docs=' . $effective_request_docs
                );

                if ($new_status === 'completed' && isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] === UPLOAD_ERR_OK) {
                    $cert_name = $_FILES['certificate_file']['name'];
                    $cert_tmp = $_FILES['certificate_file']['tmp_name'];
                    $cert_size = (int)$_FILES['certificate_file']['size'];
                    if (is_allowed_upload($cert_tmp, $cert_name, $cert_size, 8 * 1024 * 1024)) {
                        $cert_dir = __DIR__ . '/../uploads/certificates/request_' . $request_id;
                        if (!is_dir($cert_dir)) {
                            @mkdir($cert_dir, 0777, true);
                        }
                        $cert_file = 'cert_' . time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($cert_name));
                        $abs_path = $cert_dir . '/' . $cert_file;
                        $relative_path = 'uploads/certificates/request_' . $request_id . '/' . $cert_file;
                        if (move_uploaded_file($cert_tmp, $abs_path)) {
                            $cert_stmt = $conn->prepare("
                                UPDATE service_requests
                                SET certificate_file = ?, certificate_generated_at = NOW()
                                WHERE id = ?
                            ");
                            $cert_stmt->bind_param("si", $relative_path, $request_id);
                            $cert_stmt->execute();
                            $cert_stmt->close();

                            // log upload, but do not store in documents table
                            audit_log(
                                $conn,
                                'employee',
                                $employee_id,
                                'upload_completion_document',
                                'service_request',
                                (string)$request_id,
                                $relative_path
                            );
                        }
                    } else {
                        $message = "Completion document was rejected. Allowed: PDF/JPG/PNG up to 8 MB.";
                        $message_type = "danger";
                    }
                } elseif ($new_status === 'completed' && isset($_FILES['certificate_file']) && $_FILES['certificate_file']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['certificate_file']['error'] !== UPLOAD_ERR_OK) {
                    $message = "Completion document upload failed. Please try again.";
                    $message_type = "danger";
                }

                $remark_type = 'status_update';
                if ($effective_request_docs === 1) {
                    $remark_type = 'request_document';
                } elseif ($new_status === 'completed') {
                    $remark_type = 'approval';
                } elseif ($new_status === 'rejected') {
                    $remark_type = 'rejection';
                }

                $remark_message = $notes !== '' ? $notes : ("Status updated to " . str_replace('_', ' ', $new_status) . ".");
                if ($effective_request_docs === 1 && strpos(strtolower($remark_message), 'document') === false) {
                    $remark_message .= " Additional documents requested.";
                }

                $remark_by_type = 'employee';
                $remark_stmt = $conn->prepare("
                    INSERT INTO application_remarks (request_id, remark_by_type, remark_by_id, remark_type, message)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $remark_stmt->bind_param("isiss", $request_id, $remark_by_type, $employee_id, $remark_type, $remark_message);
                $remark_stmt->execute();
                $remark_stmt->close();

                $userEmail = '';
                $uStmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                if ($uStmt) {
                    $uid = (int)$request_data['user_id'];
                    $uStmt->bind_param("i", $uid);
                    $uStmt->execute();
                    $uRow = $uStmt->get_result()->fetch_assoc();
                    $uStmt->close();
                    $userEmail = (string)($uRow['email'] ?? '');
                }

                $statusText = ucwords(str_replace('_', ' ', $new_status));
                create_notification(
                    $conn,
                    'citizen',
                    (int)$request_data['user_id'],
                    'Application #' . $request_id . ' Updated',
                    'Status changed to ' . $statusText . ($effective_request_docs === 1 ? '. Additional documents requested.' : '.'),
                    '../citizen_application_details.php?request_id=' . $request_id
                );

                if ($userEmail !== '') {
                    $detailsLink = ec_app_url('/citizen_application_details.php?request_id=' . (int)$request_id);
                    $statusMail = ec_compose_application_status_email(
                        (int)$request_id,
                        $statusText,
                        $detailsLink,
                        $effective_request_docs === 1,
                        $notes
                    );
                    send_system_email($userEmail, $statusMail['subject'], $statusMail['html'], $statusMail['text']);
                }

                if ($message === '') {
                    $message = "Application updated successfully.";
                    $message_type = "success";
                }

                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_message_type'] = $message_type;
                header("Location: application_details.php?request_id=" . (int)$request_id);
                exit();
            }
        }
    }
}

$has_dynamic_form_data = columnExists($conn, 'service_requests', 'dynamic_form_data');
$dynamic_select = $has_dynamic_form_data ? "sr.dynamic_form_data" : "NULL AS dynamic_form_data";

$req_stmt = $conn->prepare("
    SELECT
        sr.id,
        sr.user_id,
        sr.service_name,
        sr.description,
        $dynamic_select,
        sr.total_fee,
        sr.payment_status,
        sr.status,
        sr.employee_notes,
        sr.additional_docs_requested,
        sr.certificate_file,
        sr.created_at,
        u.username,
        u.email
    FROM service_requests sr
    LEFT JOIN users u ON sr.user_id = u.id
    WHERE sr.id = ? AND sr.payment_status = 'paid'
    LIMIT 1
");
$req_stmt->bind_param("i", $request_id);
$req_stmt->execute();
$request = $req_stmt->get_result()->fetch_assoc();
$req_stmt->close();

if (!$request) {
    header("Location: all_applications.php");
    exit();
}

$docs = [];
$doc_stmt = $conn->prepare("
    SELECT id, document_name, file_path, uploaded_by, created_at
    FROM application_documents
    WHERE request_id = ?
    ORDER BY created_at DESC
");
$doc_stmt->bind_param("i", $request_id);
$doc_stmt->execute();
$doc_rs = $doc_stmt->get_result();
while ($row = $doc_rs->fetch_assoc()) {
    $docs[] = $row;
}
$doc_stmt->close();

$dynamic_form_data = json_decode((string)($request['dynamic_form_data'] ?? ''), true);
$has_dynamic_data = is_array($dynamic_form_data) && !empty($dynamic_form_data);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('employee', (string)$employee_name); ?>
    <?php render_panel_sidebar('employee', 'all_applications.php'); ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0"><i class="fas fa-file-alt"></i> Application #<?php echo (int)$request['id']; ?> Details & Management</h3>
            <a href="all_applications.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-wrap p-3">
            <div class="mb-3">
                <strong>Application Timeline</strong>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach (timeline_steps($request) as $step): ?>
                        <span class="badge <?php echo $step['done'] ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($step['label']); ?></span>
                        <span class="text-muted">></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mb-3">
                <strong>Service:</strong> <?php echo htmlspecialchars($request['service_name']); ?><br>
                <strong>Applicant:</strong> <?php echo htmlspecialchars($request['username'] ?? 'N/A'); ?><br>
                <strong>Email:</strong> <?php echo htmlspecialchars($request['email'] ?? '-'); ?><br>
                <strong>Amount:</strong> <?php echo number_format((float)$request['total_fee'], 2); ?><br>
                <strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($request['created_at'])); ?>
            </div>

            <div class="mb-3">
                <strong>Description:</strong>
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['description'] ?? 'No description')); ?></p>
            </div>

            <div class="mb-3">
                <strong>Application Form Details</strong>
                <?php if (!$has_dynamic_data): ?>
                    <div class="text-muted small mt-1">No extra form fields submitted.</div>
                <?php else: ?>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 35%;">Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dynamic_form_data as $field_key => $field_value): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars(formatFieldLabel((string)$field_key)); ?></strong></td>
                                        <td>
                                            <?php
                                                if (is_array($field_value)) {
                                                    $flat = array_map(fn($v) => is_scalar($v) ? (string)$v : json_encode($v), $field_value);
                                                    echo nl2br(htmlspecialchars(implode(', ', $flat)));
                                                } elseif (is_bool($field_value)) {
                                                    echo $field_value ? 'Yes' : 'No';
                                                } else {
                                                    echo nl2br(htmlspecialchars((string)$field_value));
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

            <div class="mb-3">
                <strong>Submitted Documents</strong>
                <div style="max-height: 200px; overflow-y: auto; background: #f8f9fa; border-radius: 8px; padding: 8px; margin-top: 6px;">
                    <?php if (empty($docs)): ?>
                        <span class="text-muted">No documents uploaded.</span>
                    <?php else: ?>
                        <?php foreach ($docs as $doc): ?>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                <div>
                                    <strong><?php echo htmlspecialchars($doc['document_name']); ?></strong>
                                    <small class="text-muted d-block">(<?php echo htmlspecialchars($doc['uploaded_by']); ?>, <?php echo date('M d, H:i', strtotime($doc['created_at'])); ?>)</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" target="_blank" href="../download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>&mode=view">View</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="../download_application_document.php?doc_id=<?php echo (int)$doc['id']; ?>">Download</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($request['certificate_file'])): ?>
                <div class="alert alert-success py-2">
                    Completion document available.
                    <a class="btn btn-sm btn-outline-success ms-2" href="../download_certificate.php?request_id=<?php echo (int)$request['id']; ?>">Download</a>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_input(); ?>
                <input type="hidden" name="update_request" value="1">
                <input type="hidden" name="request_id" value="<?php echo (int)$request['id']; ?>">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" required>
                            <option value="pending" <?php echo $request['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $request['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $request['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Completion Document (upload after completed)</label>
                        <input type="file" name="certificate_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="request_docs" id="reqDocs" <?php echo !empty($request['additional_docs_requested']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="reqDocs">Request additional documents from citizen</label>
                </div>

                <div class="mt-3">
                    <label class="form-label">Remarks / Comments</label>
                    <textarea class="form-control" name="notes" rows="4" placeholder="Add approval/rejection reason or requested documents"><?php echo htmlspecialchars($request['employee_notes'] ?? ''); ?></textarea>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-info text-white">Save Update</button>
                </div>
            </form>
        </div>
    </main>
    <?php render_panel_footer('employee'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
