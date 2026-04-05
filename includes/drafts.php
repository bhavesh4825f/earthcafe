<?php

if (!function_exists('ensure_application_drafts_table')) {
    function ensure_application_drafts_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS application_drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            service_id INT NOT NULL,
            payload LONGTEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_service (user_id, service_id)
        )");
    }
}

if (!function_exists('load_application_draft')) {
    function load_application_draft(mysqli $conn, int $userId, int $serviceId): ?array
    {
        ensure_application_drafts_table($conn);
        $stmt = $conn->prepare('SELECT payload FROM application_drafts WHERE user_id = ? AND service_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('ii', $userId, $serviceId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        $decoded = json_decode((string)$row['payload'], true);
        return is_array($decoded) ? $decoded : null;
    }
}

if (!function_exists('save_application_draft')) {
    function save_application_draft(mysqli $conn, int $userId, int $serviceId, array $payload): bool
    {
        ensure_application_drafts_table($conn);
        $json = json_encode($payload);
        $stmt = $conn->prepare("INSERT INTO application_drafts (user_id, service_id, payload) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP");
        if (!$stmt) return false;
        $stmt->bind_param('iis', $userId, $serviceId, $json);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('delete_application_draft')) {
    function delete_application_draft(mysqli $conn, int $userId, int $serviceId): void
    {
        $stmt = $conn->prepare('DELETE FROM application_drafts WHERE user_id = ? AND service_id = ?');
        if (!$stmt) return;
        $stmt->bind_param('ii', $userId, $serviceId);
        $stmt->execute();
        $stmt->close();
    }
}
