<?php
session_start();
include 'DataBaseConnection.php';

$doc_id = (int)($_GET['doc_id'] ?? 0);
$mode = strtolower(trim((string)($_GET['mode'] ?? 'download')));
if ($mode !== 'view') {
    $mode = 'download';
}
if ($doc_id <= 0) {
    http_response_code(400);
    exit('Invalid document.');
}

$is_admin = isset($_SESSION['admin_id']);
$is_employee = isset($_SESSION['employee_id']);
$is_citizen = isset($_SESSION['user_id']);

if (!$is_admin && !$is_employee && !$is_citizen) {
    http_response_code(401);
    exit('Please login first.');
}

$stmt = $conn->prepare("
    SELECT
        ad.id,
        ad.request_id,
        ad.user_id,
        ad.document_name,
        ad.file_path,
        sr.user_id AS request_owner_id
    FROM application_documents ad
    INNER JOIN service_requests sr ON sr.id = ad.request_id
    WHERE ad.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

if ($is_citizen && !$is_admin && !$is_employee) {
    $logged_user = (int)$_SESSION['user_id'];
    if ($logged_user !== (int)$doc['request_owner_id']) {
        http_response_code(403);
        exit('Access denied.');
    }
}

$relative = str_replace(['..\\', '../'], '', (string)$doc['file_path']);
$full_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
$uploads_root = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
$resolved = realpath($full_path);

if (!$resolved || !$uploads_root || strpos($resolved, $uploads_root) !== 0 || !is_file($resolved)) {
    http_response_code(404);
    exit('File not found.');
}

$safe_name = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$doc['document_name']);
$ext = pathinfo($resolved, PATHINFO_EXTENSION);
$download_name = $safe_name !== '' ? $safe_name : ('document_' . $doc_id);
if ($ext !== '' && stripos($download_name, '.' . $ext) === false) {
    $download_name .= '.' . $ext;
}

$mime = mime_content_type($resolved);
header('Content-Description: File Transfer');
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
$disposition = $mode === 'view' ? 'inline' : 'attachment';
header('Content-Disposition: ' . $disposition . '; filename="' . $download_name . '"');
header('Content-Length: ' . filesize($resolved));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
readfile($resolved);
exit();
?>
