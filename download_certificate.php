<?php
session_start();
include 'DataBaseConnection.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['employee_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    exit("Please login first.");
}

$request_id = (int)($_GET['request_id'] ?? 0);

if ($request_id <= 0) {
    http_response_code(400);
    exit("Invalid request.");
}

$is_admin = isset($_SESSION['admin_id']);
$is_employee = isset($_SESSION['employee_id']);
$is_citizen = isset($_SESSION['user_id']);

if ($is_citizen && !$is_admin && !$is_employee) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("
        SELECT certificate_file, status
        FROM service_requests
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $request_id, $user_id);
} else {
    $stmt = $conn->prepare("
        SELECT certificate_file, status
        FROM service_requests
        WHERE id = ?
    ");
    $stmt->bind_param("i", $request_id);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['status'] !== 'completed' || empty($row['certificate_file'])) {
    http_response_code(403);
    exit("Certificate not available.");
}

$relative = str_replace(['..\\', '../'], '', (string)$row['certificate_file']);
$full_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
$uploads_root = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
$resolved = realpath($full_path);

if (!$resolved || !$uploads_root || strpos($resolved, $uploads_root) !== 0 || !is_file($resolved)) {
    http_response_code(404);
    exit("File not found.");
}

$mime = mime_content_type($resolved);
$filename = basename($resolved);

header('Content-Description: File Transfer');
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($resolved));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
readfile($resolved);
exit();
?>
