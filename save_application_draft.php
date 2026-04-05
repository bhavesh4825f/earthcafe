<?php
session_start();
header('Content-Type: application/json');
include 'DataBaseConnection.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/drafts.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit();
}

$csrf = (string)($data['csrf_token'] ?? '');
if (!hash_equals(csrf_token(), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid token']);
    exit();
}

$serviceId = (int)($data['service_id'] ?? 0);
if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid service']);
    exit();
}

$payload = [
    'description' => (string)($data['description'] ?? ''),
    'dynamic_form_data' => is_array($data['dynamic_form_data'] ?? null) ? $data['dynamic_form_data'] : []
];

$ok = save_application_draft($conn, (int)$_SESSION['user_id'], $serviceId, $payload);
echo json_encode(['ok' => $ok]);
