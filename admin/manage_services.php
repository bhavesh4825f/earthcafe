<?php
session_start();
include 'DataBaseConnection.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/panel_layout.php';

require_admin('../admin_login.php');

$message = '';
$messageType = '';

function normalizeDynamicSchemaFromInput($input): array
{
    $raw = $input;
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $allowed_types = ['text', 'number', 'date', 'email', 'textarea', 'select'];
    $normalized = [];
    $used_keys = [];

    foreach ($raw as $field) {
        if (!is_array($field)) {
            continue;
        }

        $label = trim((string)($field['label'] ?? ''));
        $key = trim((string)($field['key'] ?? ''));
        if ($key === '' && $label !== '') {
            $key = strtolower(preg_replace('/[^a-z0-9]+/', '_', $label));
            $key = trim($key, '_');
        }
        if ($key === '') {
            continue;
        }

        $key = strtolower(preg_replace('/[^a-z0-9_]+/', '', str_replace(' ', '_', $key)));
        if ($key === '' || in_array($key, $used_keys, true)) {
            continue;
        }
        $used_keys[] = $key;

        $type = strtolower(trim((string)($field['type'] ?? 'text')));
        if (!in_array($type, $allowed_types, true)) {
            $type = 'text';
        }

        $out = [
            'key' => $key,
            'label' => ($label !== '') ? $label : ucwords(str_replace('_', ' ', $key)),
            'type' => $type,
            'required' => !empty($field['required'])
        ];

        if ($type === 'select') {
            $options = $field['options'] ?? [];
            if (is_string($options)) {
                $options = explode(',', $options);
            }
            if (is_array($options)) {
                $options = array_values(array_filter(array_map(fn($v) => trim((string)$v), $options), fn($v) => $v !== ''));
                if (!empty($options)) {
                    $out['options'] = $options;
                }
            }
        }

        $normalized[] = $out;
    }

    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    // Delete related records first to avoid foreign key constraint
    $conn->begin_transaction();
    try {
        // Delete application drafts
        $stmt = $conn->prepare("DELETE FROM application_drafts WHERE service_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        // Delete service requests
        $stmt = $conn->prepare("DELETE FROM service_requests WHERE service_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();

        // Now delete the service
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $conn->commit();
            $message = "Service deleted successfully.";
            $messageType = "success";
            audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'delete_service', 'service', (string)$delete_id, '');
        } else {
            $conn->rollback();
            $message = "Error deleting service.";
            $messageType = "danger";
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error deleting service: " . $e->getMessage();
        $messageType = "danger";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail($_POST['csrf_token'] ?? null);
    $name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'Other Documents');
    $service_fee = (float)($_POST['service_fee'] ?? 0);
    $document_fee = (float)($_POST['document_fee'] ?? 0);
    $consultancy_fee = (float)($_POST['consultancy_fee'] ?? 0);
    $processing_time_days = max(1, (int)($_POST['processing_time_days'] ?? 7));
    $active = isset($_POST['active']) ? 1 : 0;
    $assigned_employee_id = (int)($_POST['assigned_employee_id'] ?? 0);
    $assigned_employee_id = $assigned_employee_id > 0 ? $assigned_employee_id : null;

    $documents_array = [];
    if (isset($_POST['documents'])) {
        if (is_array($_POST['documents'])) {
            $documents_array = array_values(array_filter($_POST['documents'], fn($v) => trim($v) !== ''));
        } else {
            $decoded = json_decode((string)$_POST['documents'], true);
            if (is_array($decoded)) {
                $documents_array = array_values(array_filter($decoded, fn($v) => trim((string)$v) !== ''));
            }
        }
    }
    $required_documents = json_encode($documents_array);

    $schema = normalizeDynamicSchemaFromInput($_POST['dynamic_form_schema'] ?? '[]');
    $dynamic_form_schema = json_encode($schema);

    if ($name === '') {
        $message = "Service name is required.";
        $messageType = "warning";
    } elseif ($service_fee < 0 || $document_fee < 0 || $consultancy_fee < 0) {
        $message = "Fee values cannot be negative.";
        $messageType = "warning";
    } else {
        $edit_id = (int)($_POST['edit_id'] ?? 0);
        if ($edit_id > 0) {
            $stmt = $conn->prepare("
                UPDATE services
                SET name = ?, description = ?, category = ?, service_fee = ?, document_fee = ?, consultancy_fee = ?,
                    required_documents = ?, dynamic_form_schema = ?, processing_time_days = ?, active = ?, assigned_employee_id = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sssdddssiiii",
                $name,
                $description,
                $category,
                $service_fee,
                $document_fee,
                $consultancy_fee,
                $required_documents,
                $dynamic_form_schema,
                $processing_time_days,
                $active,
                $assigned_employee_id,
                $edit_id
            );
            if ($stmt->execute()) {
                $message = "Service updated successfully.";
                $messageType = "success";
                audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'update_service', 'service', (string)$edit_id, $name);
            } else {
                $message = "Error updating service.";
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $check = $conn->prepare("SELECT id FROM services WHERE name = ?");
            $check->bind_param("s", $name);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $message = "A service with this name already exists.";
                $messageType = "danger";
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO services
                    (name, description, category, service_fee, document_fee, consultancy_fee, required_documents, dynamic_form_schema, processing_time_days, active, assigned_employee_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "sssdddssiii",
                    $name,
                    $description,
                    $category,
                    $service_fee,
                    $document_fee,
                    $consultancy_fee,
                    $required_documents,
                    $dynamic_form_schema,
                    $processing_time_days,
                    $active,
                    $assigned_employee_id
                );
                if ($stmt->execute()) {
                    $message = "Service created successfully.";
                    $messageType = "success";
                    $new_id = (int)$conn->insert_id;
                    audit_log($conn, 'admin', (int)$_SESSION['admin_id'], 'create_service', 'service', (string)$new_id, $name);
                } else {
                    $message = "Error creating service.";
                    $messageType = "danger";
                }
                $stmt->close();
            }
        }
    }
}

$employees = [];
$emp_stmt = $conn->prepare("SELECT id, name FROM employees WHERE is_active = 1 ORDER BY name");
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
while ($emp = $emp_result->fetch_assoc()) {
    $employees[] = $emp;
}
$emp_stmt->close();

$services = [];
$stmt = $conn->prepare("SELECT * FROM services ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Builder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .layout { display: flex; margin-top: 60px; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .sidebar {
            position: fixed; left: 0; top: 60px; width: 250px; height: calc(100vh - 60px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px; color: #fff; overflow-y: auto;
        }
        .sidebar a { display: block; color: rgba(255,255,255,0.82); text-decoration: none; padding: 10px 12px; border-radius: 6px; margin-bottom: 6px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.22); color: #fff; }
        .main { margin-left: 250px; padding: 24px; flex: 1; }
        .card-box { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px; }
        .document-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .document-tag { background: #ffe8a1; color: #333; border-radius: 20px; font-size: 12px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 5px; }
        .document-tag button { border: 0; background: transparent; font-weight: 700; line-height: 1; }
        .service-card { border: 1px solid #eceff4; border-radius: 10px; padding: 16px; margin-bottom: 14px; }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-inactive { background: #f8d7da; color: #842029; }
        .meta { font-size: 13px; color: #666; }
        .dynamic-field-row { border: 1px solid #dbe2ef; border-radius: 10px; padding: 12px; margin-bottom: 10px; background: #fff; }
        .dynamic-empty { border: 1px dashed #c7d2e6; border-radius: 10px; padding: 14px; text-align: center; color: #6c757d; background: #fcfdff; }
        .hint { font-size: 12px; color: #6c757d; }
    </style>
    <link rel="stylesheet" href="../css/panel-unified.css">
</head>
<body>
    <?php render_panel_header('admin', (string)($_SESSION['admin_name'] ?? 'Admin')); ?>

    <div class="layout">
        <?php render_panel_sidebar('admin', 'manage_services.php'); ?>

        <main class="main">
            <h2 style="color:#333; margin-bottom: 18px;"><i class="fas fa-cogs"></i> Dynamic Service Builder</h2>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card-box">
                <h5 style="margin-bottom: 16px;"><i class="fas fa-plus-circle"></i> Create / Edit Service</h5>
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <input type="hidden" id="edit_id" name="edit_id">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Service Name *</label>
                            <input class="form-control" type="text" id="service_name" name="service_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category *</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="Certificates">Certificates</option>
                                <option value="Scholarships">Scholarships</option>
                                <option value="Other Documents">Other Documents</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <input class="form-control" type="text" id="description" name="description">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Service Fee (INR)</label>
                            <input class="form-control" type="number" step="0.01" id="service_fee" name="service_fee" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Document Fee (INR)</label>
                            <input class="form-control" type="number" step="0.01" id="document_fee" name="document_fee" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Consultancy Fee (INR)</label>
                            <input class="form-control" type="number" step="0.01" id="consultancy_fee" name="consultancy_fee" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Processing Days</label>
                            <input class="form-control" type="number" min="1" id="processing_time_days" name="processing_time_days" value="7" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Required Documents</label>
                            <div class="input-group">
                                <input type="text" id="new_document" class="form-control" placeholder="Enter document name and click Add">
                                <button class="btn btn-outline-secondary" type="button" onclick="addDocument()">Add</button>
                            </div>
                            <div class="document-tags" id="document_tags"></div>
                            <input type="hidden" name="documents" id="documents_json">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Application Form Fields</label>
                            <div class="card border-0 bg-light p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">Create fields shown to citizens while applying for this service.</small>
                                    <button class="btn btn-sm btn-outline-primary" type="button" onclick="addDynamicField()">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>
                                </div>
                                <div id="dynamic_fields_container"></div>
                                <input type="hidden" id="dynamic_form_schema" name="dynamic_form_schema" value="[]">
                            </div>
                        </div>
                        <div class="col-12 form-check">
                            <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                            <label class="form-check-label" for="active">Active</label>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Assigned Employee</label>
                            <select class="form-select" id="assigned_employee_id" name="assigned_employee_id">
                                <option value="">-- Select Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Service</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-redo"></i> Reset</button>
                    </div>
                </form>
            </div>

            <div class="card-box">
                <h5 style="margin-bottom: 16px;"><i class="fas fa-list"></i> Existing Services</h5>
                <?php if (empty($services)): ?>
                    <div class="alert alert-info">No services created yet.</div>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <?php
                            $docs = json_decode($service['required_documents'] ?? '[]', true);
                            if (!is_array($docs)) { $docs = []; }
                            $schema = json_decode($service['dynamic_form_schema'] ?? '[]', true);
                            if (!is_array($schema)) { $schema = []; }
                        ?>
                        <div class="service-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 style="margin:0;"><?php echo htmlspecialchars($service['name']); ?></h6>
                                    <div class="meta"><?php echo htmlspecialchars($service['category']); ?> | <?php echo (int)($service['processing_time_days'] ?? 7); ?> days</div>
                                </div>
                                <span class="badge <?php echo (int)$service['active'] === 1 ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo (int)$service['active'] === 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>

                            <p class="meta mt-2 mb-2"><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>

                            <div class="row">
                                <div class="col-md-4 meta"><strong>Service:</strong> <?php echo number_format((float)$service['service_fee'], 2); ?></div>
                                <div class="col-md-4 meta"><strong>Document:</strong> <?php echo number_format((float)$service['document_fee'], 2); ?></div>
                                <div class="col-md-4 meta"><strong>Consultancy:</strong> <?php echo number_format((float)$service['consultancy_fee'], 2); ?></div>
                            </div>

                            <div class="mt-2">
                                <strong>Assigned Employee:</strong>
                                <?php
                                    $assigned_name = '';
                                    if (!empty($service['assigned_employee_id'])) {
                                        foreach ($employees as $emp) {
                                            if ((int)$emp['id'] === (int)$service['assigned_employee_id']) {
                                                $assigned_name = $emp['name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo htmlspecialchars($assigned_name ?: 'None');
                                ?>
                            </div>

                            <div class="document-tags mt-2">
                                <?php if (empty($docs)): ?>
                                    <span class="meta">No required documents configured.</span>
                                <?php else: ?>
                                    <?php foreach ($docs as $doc): ?>
                                        <span class="document-tag"><?php echo htmlspecialchars((string)$doc); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="mt-2">
                                <small class="text-muted">Application Form Fields (<?php echo count($schema); ?>)</small>
                                <div class="document-tags mt-1">
                                    <?php if (empty($schema)): ?>
                                        <span class="meta">No dynamic fields configured.</span>
                                    <?php else: ?>
                                        <?php foreach ($schema as $field): ?>
                                            <?php
                                                $field_label = trim((string)($field['label'] ?? $field['key'] ?? 'Field'));
                                                $field_type = trim((string)($field['type'] ?? 'text'));
                                                $required = !empty($field['required']) ? 'Required' : 'Optional';
                                            ?>
                                            <span class="document-tag">
                                                <?php echo htmlspecialchars($field_label); ?>
                                                <small class="text-muted">(<?php echo htmlspecialchars($field_type); ?>, <?php echo htmlspecialchars($required); ?>)</small>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-2">
                                <button class="btn btn-sm btn-warning" onclick='editService(<?php echo json_encode($service, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this service?')">
                                    <?php echo csrf_input(); ?>
                                    <input type="hidden" name="delete_service" value="1">
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$service['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDocuments = [];
        let dynamicFields = [];
        const serviceForm = document.querySelector('form');

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(tag) {
                const chars = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                };
                return chars[tag] || tag;
            });
        }

        function toFieldKey(label) {
            return String(label || '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        function addDocument() {
            const input = document.getElementById('new_document');
            const value = input.value.trim();
            if (!value) return;
            if (!currentDocuments.includes(value)) {
                currentDocuments.push(value);
                renderDocumentTags();
            }
            input.value = '';
        }

        function removeDocument(value) {
            currentDocuments = currentDocuments.filter(item => item !== value);
            renderDocumentTags();
        }

        function renderDocumentTags() {
            const container = document.getElementById('document_tags');
            container.innerHTML = currentDocuments.map(doc =>
                `<span class="document-tag">${escapeHtml(doc)} <button type="button" onclick="removeDocument(decodeURIComponent('${encodeURIComponent(doc)}'))">x</button></span>`
            ).join('');
            document.getElementById('documents_json').value = JSON.stringify(currentDocuments);
        }

        function normalizeDynamicField(field) {
            const normalized = {
                label: String(field?.label || '').trim(),
                key: String(field?.key || '').trim(),
                type: String(field?.type || 'text').toLowerCase(),
                required: Boolean(field?.required),
                options: []
            };
            if (!normalized.key && normalized.label) {
                normalized.key = toFieldKey(normalized.label);
            }
            const allowed = ['text', 'number', 'date', 'email', 'textarea', 'select'];
            if (!allowed.includes(normalized.type)) {
                normalized.type = 'text';
            }

            const rawOptions = field?.options;
            if (Array.isArray(rawOptions)) {
                normalized.options = rawOptions.map(v => String(v).trim()).filter(v => v !== '');
            } else if (typeof rawOptions === 'string') {
                normalized.options = rawOptions.split(',').map(v => v.trim()).filter(v => v !== '');
            }
            return normalized;
        }

        function syncDynamicSchema() {
            const finalSchema = [];
            const usedKeys = new Set();

            dynamicFields.forEach(field => {
                const normalized = normalizeDynamicField(field);
                normalized.key = toFieldKey(normalized.key || normalized.label);
                if (!normalized.key || usedKeys.has(normalized.key)) {
                    return;
                }
                usedKeys.add(normalized.key);

                const out = {
                    key: normalized.key,
                    label: normalized.label || normalized.key.replace(/_/g, ' '),
                    type: normalized.type,
                    required: normalized.required
                };
                if (normalized.type === 'select' && normalized.options.length > 0) {
                    out.options = normalized.options;
                }
                finalSchema.push(out);
            });

            document.getElementById('dynamic_form_schema').value = JSON.stringify(finalSchema);
        }

        function renderDynamicFields() {
            const container = document.getElementById('dynamic_fields_container');

            if (dynamicFields.length === 0) {
                container.innerHTML = '<div class="dynamic-empty">No fields yet. Click "Add Field" to build your citizen application form.</div>';
                syncDynamicSchema();
                return;
            }

            container.innerHTML = dynamicFields.map((field, index) => {
                const optionsText = (field.options || []).join(', ');
                const showOptions = field.type === 'select';
                return `
                    <div class="dynamic-field-row">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label mb-1">Field Label *</label>
                                <input type="text" class="form-control form-control-sm" value="${escapeHtml(field.label)}" onchange="updateDynamicField(${index}, 'label', this.value)">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Field Key *</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" value="${escapeHtml(field.key)}" onchange="updateDynamicField(${index}, 'key', this.value)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="autoGenerateKey(${index})">Auto</button>
                                </div>
                                <div class="hint">Used in database, e.g. aadhaar_number</div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label mb-1">Type</label>
                                <select class="form-select form-select-sm" onchange="updateDynamicField(${index}, 'type', this.value)">
                                    <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text</option>
                                    <option value="number" ${field.type === 'number' ? 'selected' : ''}>Number</option>
                                    <option value="date" ${field.type === 'date' ? 'selected' : ''}>Date</option>
                                    <option value="email" ${field.type === 'email' ? 'selected' : ''}>Email</option>
                                    <option value="textarea" ${field.type === 'textarea' ? 'selected' : ''}>Textarea</option>
                                    <option value="select" ${field.type === 'select' ? 'selected' : ''}>Dropdown</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" ${field.required ? 'checked' : ''} onchange="updateDynamicField(${index}, 'required', this.checked)">
                                    <label class="form-check-label">Required</label>
                                </div>
                            </div>
                            <div class="col-md-1 d-flex align-items-end justify-content-end">
                                <button class="btn btn-sm btn-outline-danger" type="button" onclick="removeDynamicField(${index})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="col-12 ${showOptions ? '' : 'd-none'}">
                                <label class="form-label mb-1">Dropdown Options</label>
                                <input type="text" class="form-control form-control-sm" placeholder="Option 1, Option 2, Option 3" value="${escapeHtml(optionsText)}" onchange="updateDynamicField(${index}, 'options', this.value)">
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            syncDynamicSchema();
        }

        function addDynamicField(field = null) {
            const next = normalizeDynamicField(field || {
                label: '',
                key: '',
                type: 'text',
                required: false,
                options: []
            });
            dynamicFields.push(next);
            renderDynamicFields();
        }

        function removeDynamicField(index) {
            dynamicFields = dynamicFields.filter((_, i) => i !== index);
            renderDynamicFields();
        }

        function updateDynamicField(index, prop, value) {
            if (!dynamicFields[index]) return;

            if (prop === 'label') {
                dynamicFields[index].label = String(value || '').trim();
                if (!dynamicFields[index].key) {
                    dynamicFields[index].key = toFieldKey(dynamicFields[index].label);
                }
            } else if (prop === 'key') {
                dynamicFields[index].key = toFieldKey(value);
            } else if (prop === 'type') {
                dynamicFields[index].type = String(value || 'text').toLowerCase();
                if (dynamicFields[index].type !== 'select') {
                    dynamicFields[index].options = [];
                }
            } else if (prop === 'required') {
                dynamicFields[index].required = Boolean(value);
            } else if (prop === 'options') {
                dynamicFields[index].options = String(value || '')
                    .split(',')
                    .map(v => v.trim())
                    .filter(v => v !== '');
            }

            renderDynamicFields();
        }

        function autoGenerateKey(index) {
            if (!dynamicFields[index]) return;
            dynamicFields[index].key = toFieldKey(dynamicFields[index].label);
            renderDynamicFields();
        }

        function editService(service) {
            document.getElementById('edit_id').value = service.id || '';
            document.getElementById('service_name').value = service.name || '';
            document.getElementById('description').value = service.description || '';
            document.getElementById('category').value = service.category || 'Other Documents';
            document.getElementById('service_fee').value = service.service_fee || 0;
            document.getElementById('document_fee').value = service.document_fee || 0;
            document.getElementById('consultancy_fee').value = service.consultancy_fee || 0;
            document.getElementById('processing_time_days').value = service.processing_time_days || 7;
            document.getElementById('active').checked = String(service.active) === '1';
            document.getElementById('assigned_employee_id').value = service.assigned_employee_id || '';

            try {
                currentDocuments = JSON.parse(service.required_documents || '[]');
                if (!Array.isArray(currentDocuments)) currentDocuments = [];
            } catch (e) {
                currentDocuments = [];
            }
            renderDocumentTags();

            try {
                const parsedSchema = JSON.parse(service.dynamic_form_schema || '[]');
                dynamicFields = Array.isArray(parsedSchema) ? parsedSchema.map(normalizeDynamicField) : [];
            } catch (e) {
                dynamicFields = [];
            }
            renderDynamicFields();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('edit_id').value = '';
            serviceForm.reset();
            document.getElementById('processing_time_days').value = 7;
            currentDocuments = [];
            dynamicFields = [];
            renderDocumentTags();
            renderDynamicFields();
            document.getElementById('active').checked = true;
            document.getElementById('assigned_employee_id').value = '';
        }

        document.getElementById('new_document').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addDocument();
            }
        });

        serviceForm.addEventListener('submit', function() {
            syncDynamicSchema();
        });

        renderDocumentTags();
        renderDynamicFields();
    </script>
    <?php render_panel_footer('admin'); ?>
    <script src="../js/panel-unified.js"></script>
</body>
</html>
