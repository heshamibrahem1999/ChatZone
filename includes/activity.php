<?php
function cz_activity_ensure_table(PDO $pdo): void {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            target_type VARCHAR(80) DEFAULT NULL,
            target_id INT UNSIGNED DEFAULT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_activity_user (user_id),
            INDEX idx_activity_action (action),
            INDEX idx_activity_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    } catch (Throwable $e) {}
}

function cz_activity_log(PDO $pdo, ?int $userId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $details = null): void {
    try {
        cz_activity_ensure_table($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, target_type, target_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$userId, $action, $targetType, $targetId, $details, $ip, $ua]);
    } catch (Throwable $e) {}
}

function cz_require_admin(PDO $pdo, array $user): void {
    $uid = (int)$user['id'];
    try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    if ((int)($stmt->fetchColumn() ?: 0) !== 1) {
        http_response_code(403);
        echo 'Access denied. Your user id is ' . $uid . '. Set users.is_admin = 1 for this id.';
        exit;
    }
}
