<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/drafts.php';

require_citizen('login.php');

// require completed profile (aadhar)
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT aadhar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$ur = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (empty($ur['aadhar'])) {
    header("Location: user_profile.php?complete=1");
    exit();
}

$username = $_SESSION['username'];
$message = '';
$message_type = '';

function safeJsonArray($value): array
{
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

$service_id = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);
if ($service_id <= 0) {
    header("Location: apply_service.php");
    exit();
}

$svc_stmt = $conn->prepare("
    SELECT id, name, description, category, service_fee, document_fee, consultancy_fee, required_documents, dynamic_form_schema, processing_time_days
    FROM services
    WHERE id = ? AND active = 1
");
$svc_stmt->bind_param("i", $service_id);
$svc_stmt->execute();
$service = $svc_stmt->get_result()->fetch_assoc();
$svc_stmt->close();

if (!$service) {
    header("Location: apply_service.php");
    exit();
}

$required_documents = safeJsonArray($service['required_documents'] ?? '[]');
$dynamic_schema = safeJsonArray($service['dynamic_form_schema'] ?? '[]');
$submitted_dynamic = $_POST['dynamic_form_data'] ?? [];
$draft = load_application_draft($conn, $user_id, $service_id);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && is_array($draft)) {
    if (!isset($_POST['description']) && isset($draft['description'])) {
        $_POST['description'] = (string)$draft['description'];
    }
    if (isset($draft['dynamic_form_data']) && is_array($draft['dynamic_form_data'])) {
        $submitted_dynamic = $draft['dynamic_form_data'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $description = trim($_POST['description'] ?? '');
    $normalized_dynamic = [];

    foreach ($dynamic_schema as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = trim((string)($field['key'] ?? ''));
        if ($key === '') {
            continue;
        }
        $label = (string)($field['label'] ?? $key);
        $required = !empty($field['required']);
        $value = isset($submitted_dynamic[$key]) ? trim((string)$submitted_dynamic[$key]) : '';

        if ($required && $value === '') {
            $message = "Please fill required field: " . $label;
            $message_type = "danger";
            break;
        }
        $normalized_dynamic[$key] = $value;
    }

    if ($message === '') {
        // Validate required document uploads before creating the request record.
        if (!empty($required_documents)) {
            if (!isset($_FILES['required_docs']) || !is_array($_FILES['required_docs']['name'] ?? null)) {
                $message = 'Please upload all required documents.';
                $message_type = 'danger';
            } else {
                foreach ($required_documents as $docIndex => $docName) {
                    $docLabel = trim((string)$docName);
                    if ($docLabel === '') {
                        continue;
                    }
                    $uploadError = (int)($_FILES['required_docs']['error'][$docIndex] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        $message = 'Please upload required document: ' . $docLabel;
                        $message_type = 'danger';
                        break;
                    }
                    $tmpName = (string)($_FILES['required_docs']['tmp_name'][$docIndex] ?? '');
                    $originalName = (string)($_FILES['required_docs']['name'][$docIndex] ?? '');
                    $size = (int)($_FILES['required_docs']['size'][$docIndex] ?? 0);
                    if (!is_allowed_upload($tmpName, $originalName, $size, 5 * 1024 * 1024)) {
                        $message = 'Invalid file for required document: ' . $docLabel . '. Use PDF/JPG/JPEG/PNG up to 5MB.';
                        $message_type = 'danger';
                        break;
                    }
                }
            }
        }
    }

    if ($message === '') {
        $service_fee = (float)$service['service_fee'];
        $document_fee = (float)$service['document_fee'];
        $consultancy_fee = (float)$service['consultancy_fee'];
        $total_fee = $service_fee + $document_fee + $consultancy_fee;

        $payment_status = 'unpaid';
        $status = 'pending';
        $dynamic_json = json_encode($normalized_dynamic);

        $insert_request = $conn->prepare("
            INSERT INTO service_requests
            (user_id, service_id, service_name, description, dynamic_form_data, service_fee, document_fee, consultancy_fee, total_fee, payment_status, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_request->bind_param(
            "iisssddddss",
            $user_id,
            $service_id,
            $service['name'],
            $description,
            $dynamic_json,
            $service_fee,
            $document_fee,
            $consultancy_fee,
            $total_fee,
            $payment_status,
            $status
        );

        if (!$insert_request->execute()) {
            $message = "Unable to submit application.";
            $message_type = "danger";
        } else {
            $request_id = (int)$conn->insert_id;

            $remark_message = "Application submitted by citizen. Awaiting payment.";
            $remark_type = 'note';
            $remark_by_type = 'citizen';
            $remark_by_id = $user_id;
            $remark_stmt = $conn->prepare("
                INSERT INTO application_remarks (request_id, remark_by_type, remark_by_id, remark_type, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $remark_stmt->bind_param("isiss", $request_id, $remark_by_type, $remark_by_id, $remark_type, $remark_message);
            $remark_stmt->execute();
            $remark_stmt->close();

            $adminRs = $conn->query("SELECT id FROM admins WHERE is_active = 1");
            if ($adminRs) {
                while ($a = $adminRs->fetch_assoc()) {
                    create_notification(
                        $conn,
                        'admin',
                        (int)$a['id'],
                        'New Application #' . $request_id,
                        'New application submitted by citizen for ' . (string)$service['name'] . '.',
                        'service_requests.php?q=' . $request_id
                    );
                }
            }

            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $user_id . DIRECTORY_SEPARATOR . 'request_' . $request_id;
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png'];
            $required_doc_names = $_POST['required_doc_names'] ?? [];
            if (isset($_FILES['required_docs']) && is_array($_FILES['required_docs']['name'])) {
                $file_count = count($_FILES['required_docs']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    if ((int)$_FILES['required_docs']['error'][$i] !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $original_name = $_FILES['required_docs']['name'][$i];
                    $tmp_name = $_FILES['required_docs']['tmp_name'][$i];
                    $size = (int)$_FILES['required_docs']['size'][$i];
                    if (!is_allowed_upload($tmp_name, $original_name, $size, 5 * 1024 * 1024)) {
                        continue;
                    }

                    $doc_name = isset($required_doc_names[$i]) ? trim((string)$required_doc_names[$i]) : ('Document ' . ($i + 1));
                    if ($doc_name === '') {
                        $doc_name = 'Document ' . ($i + 1);
                    }

                    $file_name = time() . '_' . $i . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($original_name));
                    $absolute_path = $upload_dir . DIRECTORY_SEPARATOR . $file_name;
                    $relative_path = 'uploads/' . $user_id . '/request_' . $request_id . '/' . $file_name;

                    if (move_uploaded_file($tmp_name, $absolute_path)) {
                        $doc_stmt = $conn->prepare("
                            INSERT INTO application_documents (request_id, user_id, document_name, file_path, uploaded_by)
                            VALUES (?, ?, ?, ?, 'citizen')
                        ");
                        $doc_stmt->bind_param("iiss", $request_id, $user_id, $doc_name, $relative_path);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
            }

            $insert_request->close();
            delete_application_draft($conn, $user_id, $service_id);
            header("Location: payment_gateway.php?request_id=" . $request_id);
            exit();
        }
        $insert_request->close();
    }
}

$service_total = (float)$service['service_fee'] + (float)$service['document_fee'] + (float)$service['consultancy_fee'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/modern-design.css">
    <style>
        body { background: linear-gradient(145deg, #eef4ef 0%, #e7efe9 100%); font-family: 'Manrope', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding-top: 75px; }
        .navbar { background: #fff; box-shadow: 0 4px 14px rgba(12,37,32,0.08); }
        .navbar-brand { color: #0f6a5d !important; font-weight: 700; }
        .nav-link { color: #365550 !important; }
        .nav-link:hover { color: #0f6a5d !important; }
        .container { max-width: 1100px; margin-top: 20px; margin-bottom: 30px; }
        .card-box { background: #fff; border-radius: 14px; box-shadow: 0 8px 24px rgba(12,37,32,0.08); border: 1px solid #d2dfd4; padding: 24px; margin-bottom: 20px; }
        .btn-submit { background: linear-gradient(135deg, #0f6a5d 0%, #0a4a41 100%); border: none; color: #fff; font-weight: 600; }
        .btn-submit:hover { color: #fff; opacity: 0.95; }
        .fee-box { border-left: 4px solid #0f6a5d; background: #eef7f1; padding: 12px; border-radius: 8px; margin-top: 8px; }
        .step-badge { border: 1px solid #bfd0bf; color: #2c4d47; background: #edf4ef; }
        .step-badge.active { border-color: #0f6a5d; color: #fff; background: #0f6a5d; }
        .form-note { color: #4a645f; font-size: 13px; }
        .draft-status { min-height: 20px; }
        .is-invalid + .invalid-feedback,
        .is-invalid ~ .invalid-feedback { display: block; }
        .is-hidden { display: none !important; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-home"></i> Earth Cafe</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMain">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><span class="nav-link"><strong><?php echo htmlspecialchars($username); ?></strong></span></li>
                    <li class="nav-item"><a class="nav-link" href="client_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="apply_service.php"><i class="fas fa-layer-group"></i> Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-box">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($service['name']); ?></h4>
                    <p class="text-muted mb-1"><?php echo htmlspecialchars($service['category']); ?> | <?php echo (int)$service['processing_time_days']; ?> day(s)</p>
                    <p class="mb-0"><?php echo htmlspecialchars($service['description'] ?: 'No description provided.'); ?></p>
                </div>
                <a href="apply_service.php" class="btn btn-outline-secondary btn-sm">Change Service</a>
            </div>
            <div class="fee-box mt-3">
                <div class="row">
                    <div class="col-md-3">Service Fee: <strong><?php echo number_format((float)$service['service_fee'], 2); ?></strong></div>
                    <div class="col-md-3">Document Fee: <strong><?php echo number_format((float)$service['document_fee'], 2); ?></strong></div>
                    <div class="col-md-3">Consultancy Fee: <strong><?php echo number_format((float)$service['consultancy_fee'], 2); ?></strong></div>
                    <div class="col-md-3">Total: <strong><?php echo number_format($service_total, 2); ?></strong></div>
                </div>
            </div>
        </div>

        <div class="card-box">
            <form method="POST" enctype="multipart/form-data" id="serviceApplicationForm" novalidate>
                <?php echo csrf_input(); ?>
                <input type="hidden" name="service_id" value="<?php echo (int)$service['id']; ?>">
                <div class="alert alert-info py-2"><small>Draft autosave is enabled for this service form.</small></div>
                <div class="d-flex gap-2 mb-3" id="stepperBar">
                    <span class="badge step-badge active">Step 1: Notes</span>
                    <span class="badge step-badge">Step 2: Form</span>
                    <span class="badge step-badge">Step 3: Documents</span>
                </div>

                <div class="mb-3 step-pane" data-step="1">
                    <label class="form-label">Application Notes</label>
                    <textarea class="form-control" name="description" rows="3" placeholder="Any additional context for this application"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    <div class="form-note mt-2">Add context that can help faster verification (optional).</div>
                </div>

                <div class="step-pane is-hidden" data-step="2">
                <?php if (!empty($dynamic_schema)): ?>
                    <hr>
                    <h6><i class="fas fa-wpforms"></i> Dynamic Form Fields</h6>
                    <div class="row g-3">
                        <?php foreach ($dynamic_schema as $field): ?>
                            <?php
                                if (!is_array($field) || empty($field['key'])) {
                                    continue;
                                }
                                $key = (string)$field['key'];
                                $label = (string)($field['label'] ?? $key);
                                $type = strtolower((string)($field['type'] ?? 'text'));
                                $required = !empty($field['required']);
                                $value = isset($submitted_dynamic[$key]) ? (string)$submitted_dynamic[$key] : '';
                            ?>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo htmlspecialchars($label); ?><?php echo $required ? ' *' : ''; ?></label>
                                <?php if ($type === 'textarea'): ?>
                                    <textarea class="form-control" name="dynamic_form_data[<?php echo htmlspecialchars($key); ?>]" <?php echo $required ? 'required' : ''; ?>><?php echo htmlspecialchars($value); ?></textarea>
                                <?php elseif ($type === 'select'): ?>
                                    <?php $options = is_array($field['options'] ?? null) ? $field['options'] : []; ?>
                                    <select class="form-select" name="dynamic_form_data[<?php echo htmlspecialchars($key); ?>]" <?php echo $required ? 'required' : ''; ?>>
                                        <option value="">Select</option>
                                        <?php foreach ($options as $option): ?>
                                            <?php $opt = (string)$option; ?>
                                            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $value === $opt ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <?php $input_type = in_array($type, ['text','number','date','email'], true) ? $type : 'text'; ?>
                                    <input type="<?php echo $input_type; ?>" class="form-control" name="dynamic_form_data[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($value); ?>" <?php echo $required ? 'required' : ''; ?>>
                                <?php endif; ?>
                                <div class="invalid-feedback">This field is required.</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>

                <div class="step-pane is-hidden" data-step="3">
                <hr>
                <h6><i class="fas fa-upload"></i> Required Documents</h6>
                <div class="row g-3">
                    <?php if (empty($required_documents)): ?>
                        <div class="col-12 text-muted">No mandatory documents configured for this service.</div>
                    <?php else: ?>
                        <?php foreach ($required_documents as $docName): ?>
                            <?php $doc = trim((string)$docName); if ($doc === '') continue; ?>
                            <div class="col-md-6">
                                <label class="form-label"><?php echo htmlspecialchars($doc); ?> *</label>
                                <input type="hidden" name="required_doc_names[]" value="<?php echo htmlspecialchars($doc); ?>">
                                <input type="file" class="form-control" name="required_docs[]" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">Allowed: PDF, JPG, JPEG, PNG (max 5MB)</small>
                                <div class="invalid-feedback">Please upload this required document.</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary is-hidden" id="prevStepBtn">Previous</button>
                    <button type="button" class="btn btn-outline-primary" id="nextStepBtn">Next</button>
                    <button type="submit" class="btn btn-submit px-4 is-hidden" id="submitBtn"><i class="fas fa-arrow-right"></i> Continue to Payment</button>
                </div>
                <div class="draft-status mt-2 small text-muted" id="draftStatusText"></div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var currentStep = 1;
            var panes = document.querySelectorAll('.step-pane');
            var prevBtn = document.getElementById('prevStepBtn');
            var nextBtn = document.getElementById('nextStepBtn');
            var submitBtn = document.getElementById('submitBtn');
            var badges = document.querySelectorAll('#stepperBar .badge');
            var csrfToken = <?php echo json_encode(csrf_token()); ?>;
            var serviceId = <?php echo (int)$service['id']; ?>;

            function showStep(step) {
                currentStep = step;
                panes.forEach(function (p) {
                    p.classList.toggle('is-hidden', parseInt(p.dataset.step, 10) !== step);
                });
                prevBtn.classList.toggle('is-hidden', !(step > 1));
                nextBtn.classList.toggle('is-hidden', !(step < 3));
                submitBtn.classList.toggle('is-hidden', step !== 3);
                badges.forEach(function (b, idx) {
                    b.classList.toggle('active', idx + 1 <= step);
                });
            }

            prevBtn.addEventListener('click', function () { if (currentStep > 1) showStep(currentStep - 1); });
            showStep(1);

            var form = document.getElementById('serviceApplicationForm');
            var saveTimer = null;
            var draftStatusEl = document.getElementById('draftStatusText');

            function setDraftStatus(text, kind) {
                if (!draftStatusEl) {
                    return;
                }
                draftStatusEl.textContent = text;
                draftStatusEl.classList.remove('text-muted', 'text-success', 'text-danger');
                draftStatusEl.classList.add(kind || 'text-muted');
            }

            function validateCurrentStep() {
                var pane = document.querySelector('.step-pane[data-step="' + currentStep + '"]');
                if (!pane) {
                    return true;
                }
                var requiredFields = pane.querySelectorAll('input[required], select[required], textarea[required]');
                var valid = true;
                requiredFields.forEach(function (field) {
                    var hasValue = false;
                    if (field.type === 'file') {
                        hasValue = field.files && field.files.length > 0;
                    } else {
                        hasValue = (field.value || '').trim() !== '';
                    }
                    field.classList.toggle('is-invalid', !hasValue);
                    if (!hasValue) {
                        valid = false;
                    }
                });
                return valid;
            }

            function collectPayload() {
                var payload = { csrf_token: csrfToken, service_id: serviceId, description: '', dynamic_form_data: {} };
                var desc = form.querySelector('textarea[name=\"description\"]');
                if (desc) payload.description = desc.value || '';
                form.querySelectorAll('[name^=\"dynamic_form_data[\"]').forEach(function (el) {
                    var key = el.name.replace('dynamic_form_data[', '').replace(']', '');
                    payload.dynamic_form_data[key] = el.value || '';
                });
                return payload;
            }
            function saveDraft() {
                var payload = collectPayload();
                try { localStorage.setItem('draft_service_' + serviceId, JSON.stringify(payload)); } catch (e) {}
                setDraftStatus('Saving draft...', 'text-muted');
                fetch('save_application_draft.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                    .then(function () { setDraftStatus('Draft saved.', 'text-success'); })
                    .catch(function () { setDraftStatus('Unable to save draft right now.', 'text-danger'); });
            }
            form.addEventListener('input', function () {
                clearTimeout(saveTimer);
                saveTimer = setTimeout(saveDraft, 1500);
            });

            nextBtn.addEventListener('click', function () {
                if (currentStep < 3) {
                    if (!validateCurrentStep()) {
                        return;
                    }
                    showStep(currentStep + 1);
                }
            });

            form.addEventListener('submit', function (e) {
                if (!validateCurrentStep()) {
                    e.preventDefault();
                    return;
                }
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                setDraftStatus('Submitting your application securely...', 'text-muted');
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
