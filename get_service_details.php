<?php
include 'DataBaseConnection.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Service ID required']);
    exit;
}

$service_id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT id, service_fee, document_fee, consultancy_fee, required_documents, dynamic_form_schema, processing_time_days FROM services WHERE id = ? AND active = 1");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Service not found']);
    exit;
}

$service = $result->fetch_assoc();
echo json_encode([
    'id' => $service['id'],
    'service_fee' => $service['service_fee'],
    'document_fee' => $service['document_fee'],
    'consultancy_fee' => $service['consultancy_fee'],
    'required_documents' => json_decode($service['required_documents'] ?? '[]', true) ?: [],
    'dynamic_form_schema' => json_decode($service['dynamic_form_schema'] ?? '[]', true) ?: [],
    'processing_time_days' => (int)($service['processing_time_days'] ?? 7)
]);

$stmt->close();
?>
