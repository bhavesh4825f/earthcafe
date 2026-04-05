<?php
declare(strict_types=1);

function ensure_audit_table(mysqli $conn): void
{
    static $created = false;
    if ($created) {
        return;
    }
    $conn->query("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            actor_type VARCHAR(30) NOT NULL,
            actor_id INT NOT NULL DEFAULT 0,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(60) NOT NULL,
            entity_id VARCHAR(80) NOT NULL,
            details TEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_audit_actor (actor_type, actor_id),
            INDEX idx_audit_entity (entity_type, entity_id),
            INDEX idx_audit_created (created_at)
        )
    ");
    $created = true;
}

function audit_log(
    mysqli $conn,
    string $actorType,
    int $actorId,
    string $action,
    string $entityType,
    string $entityId,
    string $details = ''
): void {
    ensure_audit_table($conn);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (actor_type, actor_id, action, entity_type, entity_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("sisssss", $actorType, $actorId, $action, $entityType, $entityId, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

