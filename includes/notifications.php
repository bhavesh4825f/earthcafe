<?php

if (!function_exists('get_notifications_table_columns')) {
    function get_notifications_table_columns(mysqli $conn): array
    {
        $columns = [];
        $result = $conn->query("SHOW COLUMNS FROM notifications");
        if (!$result) {
            return $columns;
        }

        while ($row = $result->fetch_assoc()) {
            $columns[(string)$row['Field']] = $row;
        }
        $result->free();

        return $columns;
    }
}

if (!function_exists('get_notifications_index_names')) {
    function get_notifications_index_names(mysqli $conn): array
    {
        $indexes = [];
        $result = $conn->query("SHOW INDEX FROM notifications");
        if (!$result) {
            return $indexes;
        }

        while ($row = $result->fetch_assoc()) {
            $indexes[(string)$row['Key_name']] = true;
        }
        $result->free();

        return $indexes;
    }
}

if (!function_exists('drop_notifications_legacy_user_column')) {
    function drop_notifications_legacy_user_column(mysqli $conn): void
    {
        $result = $conn->query("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'notifications'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $constraintName = str_replace('`', '``', (string)$row['CONSTRAINT_NAME']);
                $conn->query("ALTER TABLE notifications DROP FOREIGN KEY `{$constraintName}`");
            }
            $result->free();
        }

        $conn->query("ALTER TABLE notifications DROP COLUMN user_id");
    }
}

if (!function_exists('ensure_notifications_table')) {
    function ensure_notifications_table(mysqli $conn): void
    {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_type VARCHAR(20) NOT NULL,
                recipient_id INT NOT NULL,
                title VARCHAR(190) NOT NULL,
                message TEXT NOT NULL,
                action_url VARCHAR(255) DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_recipient (recipient_type, recipient_id, is_read),
                INDEX idx_created (created_at)
            )");

            $columns = get_notifications_table_columns($conn);
            if ($columns === []) {
                return;
            }

            $hasLegacyUserId = isset($columns['user_id']);
            $hasLegacyStatus = isset($columns['status']);

            if (!isset($columns['recipient_type'])) {
                $conn->query("ALTER TABLE notifications ADD COLUMN recipient_type VARCHAR(20) NULL AFTER id");
            }
            if (!isset($columns['recipient_id'])) {
                $conn->query("ALTER TABLE notifications ADD COLUMN recipient_id INT NULL AFTER recipient_type");
            }
            if (!isset($columns['title'])) {
                $conn->query("ALTER TABLE notifications ADD COLUMN title VARCHAR(190) NULL AFTER recipient_id");
            }
            if (!isset($columns['action_url'])) {
                $conn->query("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(255) DEFAULT NULL AFTER message");
            }
            if (!isset($columns['is_read'])) {
                $conn->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER action_url");
            }

            $columns = get_notifications_table_columns($conn);

            if ($hasLegacyUserId && isset($columns['recipient_type'], $columns['recipient_id'])) {
                $conn->query("UPDATE notifications SET recipient_type = 'citizen' WHERE recipient_type IS NULL OR recipient_type = ''");
                $conn->query("UPDATE notifications SET recipient_id = user_id WHERE recipient_id IS NULL");
            }
            if ($hasLegacyUserId && isset($columns['user_id'])) {
                drop_notifications_legacy_user_column($conn);
            }
            if ($hasLegacyStatus && isset($columns['is_read'])) {
                $conn->query("UPDATE notifications SET is_read = CASE WHEN status = 'read' THEN 1 ELSE 0 END");
            }
            if (isset($columns['title'])) {
                $conn->query("UPDATE notifications SET title = 'Notification' WHERE title IS NULL OR title = ''");
            }
            if (isset($columns['recipient_type'])) {
                $conn->query("UPDATE notifications SET recipient_type = 'system' WHERE recipient_type IS NULL OR recipient_type = ''");
            }
            if (isset($columns['recipient_id'])) {
                $conn->query("UPDATE notifications SET recipient_id = 0 WHERE recipient_id IS NULL");
            }

            if (isset($columns['recipient_type'])) {
                $conn->query("ALTER TABLE notifications MODIFY recipient_type VARCHAR(20) NOT NULL");
            }
            if (isset($columns['recipient_id'])) {
                $conn->query("ALTER TABLE notifications MODIFY recipient_id INT NOT NULL");
            }
            if (isset($columns['title'])) {
                $conn->query("ALTER TABLE notifications MODIFY title VARCHAR(190) NOT NULL");
            }

            $indexes = get_notifications_index_names($conn);
            if (!isset($indexes['idx_recipient']) && isset($columns['recipient_type'], $columns['recipient_id'], $columns['is_read'])) {
                $conn->query("ALTER TABLE notifications ADD INDEX idx_recipient (recipient_type, recipient_id, is_read)");
            }
            if (!isset($indexes['idx_created']) && isset($columns['created_at'])) {
                $conn->query("ALTER TABLE notifications ADD INDEX idx_created (created_at)");
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Failed to ensure notifications table: ' . $e->getMessage());
        }
    }
}

if (!function_exists('create_notification')) {
    function create_notification(mysqli $conn, string $recipientType, int $recipientId, string $title, string $message, ?string $actionUrl = null): void
    {
        try {
            ensure_notifications_table($conn);
            $stmt = $conn->prepare("INSERT INTO notifications (recipient_type, recipient_id, title, message, action_url) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                return;
            }

            $stmt->bind_param('sisss', $recipientType, $recipientId, $title, $message, $actionUrl);
            $stmt->execute();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log('Failed to create notification: ' . $e->getMessage());
        }
    }
}

if (!function_exists('get_unread_notification_count')) {
    function get_unread_notification_count(mysqli $conn, string $recipientType, int $recipientId): int
    {
        try {
            ensure_notifications_table($conn);
            $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
            if (!$stmt) {
                return 0;
            }

            $stmt->bind_param('si', $recipientType, $recipientId);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
            $stmt->close();

            return $count;
        } catch (mysqli_sql_exception $e) {
            error_log('Failed to read notifications count: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('mark_notification_read')) {
    function mark_notification_read(mysqli $conn, int $id, string $recipientType, int $recipientId): void
    {
        try {
            ensure_notifications_table($conn);
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
            if (!$stmt) {
                return;
            }

            $stmt->bind_param('isi', $id, $recipientType, $recipientId);
            $stmt->execute();
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            error_log('Failed to mark notification read: ' . $e->getMessage());
        }
    }
}
