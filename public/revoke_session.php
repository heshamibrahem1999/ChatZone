<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/sessions.php';
$user = require_login($pdo);
require_csrf_or_redirect('sessions.php');
$sessionId = (int)($_POST['session_id'] ?? 0);
if ($sessionId <= 0 || !cz_table_exists($pdo, 'user_sessions')) {
    $_SESSION['sessions_error'] = 'Invalid session.';
    redirect('sessions.php');
}
$currentHash = !empty($_SESSION['session_token']) ? hash('sha256', $_SESSION['session_token']) : '';
$stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0, revoked_at = NOW() WHERE id = ? AND user_id = ? AND token_hash <> ?");
$stmt->execute([$sessionId, (int)$user['id'], $currentHash]);
$_SESSION['sessions_message'] = $stmt->rowCount() ? 'Session logged out.' : 'Session was not changed.';
redirect('sessions.php');
