<?php
function cz_table_exists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function cz_session_ip(): string {
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function cz_session_user_agent(): string {
    return substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255);
}

function cz_create_user_session(PDO $pdo, int $userId): ?string {
    if (!cz_table_exists($pdo, 'user_sessions')) return null;
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, token_hash, session_id, ip_address, user_agent, last_seen_at, created_at, is_active) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1)");
        $stmt->execute([$userId, $hash, session_id(), cz_session_ip(), cz_session_user_agent()]);
        $_SESSION['session_token'] = $token;
        return $token;
    } catch (Throwable $e) { return null; }
}

function cz_touch_user_session(PDO $pdo, int $userId): void {
    if (!cz_table_exists($pdo, 'user_sessions')) return;
    if (empty($_SESSION['session_token'])) {
        cz_create_user_session($pdo, $userId);
        return;
    }
    $hash = hash('sha256', $_SESSION['session_token']);
    try {
        $stmt = $pdo->prepare("SELECT id, is_active FROM user_sessions WHERE user_id = ? AND token_hash = ? LIMIT 1");
        $stmt->execute([$userId, $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['is_active'] !== 1) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            header('Location: login.php?session_revoked=1');
            exit;
        }
        $pdo->prepare("UPDATE user_sessions SET last_seen_at = NOW(), session_id = ?, ip_address = ?, user_agent = ? WHERE id = ?")
            ->execute([session_id(), cz_session_ip(), cz_session_user_agent(), (int)$row['id']]);
    } catch (Throwable $e) { return; }
}

function cz_revoke_current_session(PDO $pdo, int $userId): void {
    if (!cz_table_exists($pdo, 'user_sessions') || empty($_SESSION['session_token'])) return;
    $hash = hash('sha256', $_SESSION['session_token']);
    try {
        $pdo->prepare("UPDATE user_sessions SET is_active = 0, revoked_at = NOW() WHERE user_id = ? AND token_hash = ?")
            ->execute([$userId, $hash]);
    } catch (Throwable $e) { return; }
}
