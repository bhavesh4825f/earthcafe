<?php
include 'DataBaseConnection.php';

header('Content-Type: application/json');

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0) > 0;
}

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cnt'] ?? 0) > 0;
}

function runQuery(mysqli $conn, string $sql, array &$results, string $label): void
{
    if ($conn->query($sql)) {
        $results[] = ['label' => $label, 'status' => 'ok'];
    } else {
        $results[] = ['label' => $label, 'status' => 'error', 'error' => $conn->error];
    }
}

$results = [];

if (tableExists($conn, 'users')) {
    if (!columnExists($conn, 'users', 'role')) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'citizen' AFTER password", $results, 'users.role');
    }
    if (!columnExists($conn, 'users', 'is_active')) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role", $results, 'users.is_active');
    }
    if (!columnExists($conn, 'users', 'terms_accepted_at')) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN terms_accepted_at DATETIME NULL AFTER is_active", $results, 'users.terms_accepted_at');
    }
    if (!columnExists($conn, 'users', 'privacy_accepted_at')) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN privacy_accepted_at DATETIME NULL AFTER terms_accepted_at", $results, 'users.privacy_accepted_at');
    }
    if (!columnExists($conn, 'users', 'consent_ip')) {
        runQuery($conn, "ALTER TABLE users ADD COLUMN consent_ip VARCHAR(45) NULL AFTER privacy_accepted_at", $results, 'users.consent_ip');
    }
}

if (tableExists($conn, 'employees')) {
    if (!columnExists($conn, 'employees', 'is_active')) {
        runQuery($conn, "ALTER TABLE employees ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER password", $results, 'employees.is_active');
    }
}

if (tableExists($conn, 'services')) {
    if (!columnExists($conn, 'services', 'dynamic_form_schema')) {
        runQuery($conn, "ALTER TABLE services ADD COLUMN dynamic_form_schema LONGTEXT NULL AFTER required_documents", $results, 'services.dynamic_form_schema');
    }
    if (!columnExists($conn, 'services', 'processing_time_days')) {
        runQuery($conn, "ALTER TABLE services ADD COLUMN processing_time_days INT NOT NULL DEFAULT 7 AFTER dynamic_form_schema", $results, 'services.processing_time_days');
    }
}

if (tableExists($conn, 'service_requests')) {
    if (!columnExists($conn, 'service_requests', 'dynamic_form_data')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN dynamic_form_data LONGTEXT NULL AFTER description", $results, 'service_requests.dynamic_form_data');
    }
    if (!columnExists($conn, 'service_requests', 'payment_status')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid' AFTER total_fee", $results, 'service_requests.payment_status');
    }
    if (!columnExists($conn, 'service_requests', 'payment_method')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN payment_method VARCHAR(50) NULL AFTER payment_status", $results, 'service_requests.payment_method');
    }
    if (!columnExists($conn, 'service_requests', 'payment_reference')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN payment_reference VARCHAR(120) NULL AFTER payment_method", $results, 'service_requests.payment_reference');
    }
    if (!columnExists($conn, 'service_requests', 'payment_date')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN payment_date DATETIME NULL AFTER payment_reference", $results, 'service_requests.payment_date');
    }
    if (!columnExists($conn, 'service_requests', 'additional_docs_requested')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN additional_docs_requested TINYINT(1) NOT NULL DEFAULT 0 AFTER employee_notes", $results, 'service_requests.additional_docs_requested');
    }
    if (!columnExists($conn, 'service_requests', 'certificate_file')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN certificate_file VARCHAR(255) NULL AFTER additional_docs_requested", $results, 'service_requests.certificate_file');
    }
    if (!columnExists($conn, 'service_requests', 'certificate_generated_at')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN certificate_generated_at DATETIME NULL AFTER certificate_file", $results, 'service_requests.certificate_generated_at');
    }
    if (!columnExists($conn, 'service_requests', 'approved_at')) {
        runQuery($conn, "ALTER TABLE service_requests ADD COLUMN approved_at DATETIME NULL AFTER certificate_generated_at", $results, 'service_requests.approved_at');
    }
    if (!columnExists($conn, 'services', 'assigned_employee_id')) {
        runQuery($conn, "ALTER TABLE services ADD COLUMN assigned_employee_id INT(11) NULL AFTER active", $results, 'services.assigned_employee_id');
        runQuery($conn, "ALTER TABLE services ADD CONSTRAINT fk_services_employee FOREIGN KEY (assigned_employee_id) REFERENCES employees(id) ON DELETE SET NULL", $results, 'services.assigned_employee_id_fk');
    }
}

if (tableExists($conn, 'contact_messages')) {
    if (!columnExists($conn, 'contact_messages', 'status')) {
        runQuery($conn, "ALTER TABLE contact_messages ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open' AFTER message", $results, 'contact_messages.status');
    }
    if (!columnExists($conn, 'contact_messages', 'admin_reply')) {
        runQuery($conn, "ALTER TABLE contact_messages ADD COLUMN admin_reply TEXT NULL AFTER status", $results, 'contact_messages.admin_reply');
    }
    if (!columnExists($conn, 'contact_messages', 'resolved_at')) {
        runQuery($conn, "ALTER TABLE contact_messages ADD COLUMN resolved_at DATETIME NULL AFTER admin_reply", $results, 'contact_messages.resolved_at');
    }
}

runQuery(
    $conn,
    "CREATE TABLE IF NOT EXISTS application_documents (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_id INT NOT NULL,
        user_id INT NOT NULL,
        document_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        uploaded_by VARCHAR(20) NOT NULL DEFAULT 'citizen',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_request_id (request_id),
        INDEX idx_user_id (user_id),
        CONSTRAINT fk_app_docs_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE,
        CONSTRAINT fk_app_docs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    $results,
    'application_documents'
);

runQuery(
    $conn,
    "CREATE TABLE IF NOT EXISTS application_remarks (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_id INT NOT NULL,
        remark_by_type VARCHAR(20) NOT NULL,
        remark_by_id INT NOT NULL,
        remark_type VARCHAR(30) NOT NULL DEFAULT 'note',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_remark_request (request_id),
        CONSTRAINT fk_remarks_request FOREIGN KEY (request_id) REFERENCES service_requests(id) ON DELETE CASCADE
    )",
    $results,
    'application_remarks'
);

runQuery(
    $conn,
    "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(120) NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT 'subscribed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    $results,
    'newsletter_subscribers'
);

runQuery(
    $conn,
    "CREATE TABLE IF NOT EXISTS newsletter_campaigns (
        id INT PRIMARY KEY AUTO_INCREMENT,
        subject VARCHAR(255) NOT NULL,
        body LONGTEXT NOT NULL,
        sent_by INT NOT NULL,
        sent_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    $results,
    'newsletter_campaigns'
);

echo json_encode([
    'status' => 'completed',
    'results' => $results
], JSON_PRETTY_PRINT);

$conn->close();
?>
