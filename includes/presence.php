<?php
const CZ_PRESENCE_ONLINE_SECONDS = 8;

function ensure_presence_table(PDO $pdo): void
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_presence_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            last_seen_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_session (user_id, session_id),
            KEY idx_user_last_seen (user_id, last_seen_at),
            KEY idx_last_seen (last_seen_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        
    }
}

function cz_presence_request_value(string $key): string
{
    if (isset($_POST[$key])) return (string)$_POST[$key];
    if (isset($_GET[$key])) return (string)$_GET[$key];
    static $raw = null;
    if ($raw === null) {
        $raw = (string)file_get_contents('php://input');
    }
    if ($raw !== '') {
        $parsed = [];
        parse_str($raw, $parsed);
        if (isset($parsed[$key])) return (string)$parsed[$key];
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json[$key])) return (string)$json[$key];
    }
    return '';
}

function current_presence_session_id(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sid = session_id();
    if ($sid === '') {
        $sid = substr(hash('sha256', (string)microtime(true) . random_int(1, PHP_INT_MAX)), 0, 64);
    }

    
    
    $tabId = cz_presence_request_value('presence_tab_id');
    $tabId = preg_replace('/[^a-zA-Z0-9_-]/', '', $tabId);
    if ($tabId !== '') {
        return substr($sid . ':' . $tabId, 0, 128);
    }
    return substr($sid, 0, 128);
}

function update_user_presence(PDO $pdo, int $userId): void
{
    
    
    $sessionId = current_presence_session_id();

    try {
        $stmt = $pdo->prepare("INSERT INTO user_presence_sessions (user_id, session_id, last_seen_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_seen_at = VALUES(last_seen_at)");
        $stmt->execute([$userId, $sessionId]);
    } catch (Throwable $e) {
        
    }

    $stmt = $pdo->prepare("UPDATE users SET is_online = 1, last_active_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

function mark_user_offline(PDO $pdo, int $userId): void
{
    
    $sessionId = current_presence_session_id();

    try {
        $stmt = $pdo->prepare("DELETE FROM user_presence_sessions WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$userId, $sessionId]);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_presence_sessions WHERE user_id = ? AND last_seen_at >= (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$userId, CZ_PRESENCE_ONLINE_SECONDS]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }
    } catch (Throwable $e) {
        
    }

    $stmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
    $stmt->execute([$userId]);
}

function cleanup_stale_presence(PDO $pdo): void
{
    

    try {
        $stmt = $pdo->prepare("DELETE FROM user_presence_sessions WHERE last_seen_at < (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([CZ_PRESENCE_ONLINE_SECONDS]);

        $pdo->exec("UPDATE users u
            SET u.is_online = CASE
                WHEN EXISTS (
                    SELECT 1 FROM user_presence_sessions ups
                    WHERE ups.user_id = u.id
                      AND ups.last_seen_at >= (NOW() - INTERVAL " . CZ_PRESENCE_ONLINE_SECONDS . " SECOND)
                ) THEN 1 ELSE 0 END");
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("UPDATE users SET is_online = 0 WHERE is_online = 1 AND (last_active_at IS NULL OR last_active_at < (NOW() - INTERVAL ? SECOND))");
        $stmt->execute([CZ_PRESENCE_ONLINE_SECONDS]);
    }
}

function presence_case_sql(string $alias = 'u'): string
{
    $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'u';
    return "CASE WHEN EXISTS (
        SELECT 1 FROM user_presence_sessions ups_presence
        WHERE ups_presence.user_id = {$alias}.id
          AND ups_presence.last_seen_at >= (NOW() - INTERVAL " . CZ_PRESENCE_ONLINE_SECONDS . " SECOND)
    ) THEN 1 ELSE 0 END";
}
