<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

update_user_presence($pdo, (int) $_SESSION['user_id']);

$userId = (int) $_SESSION['user_id'];
$user = require_login($pdo);
require_csrf_or_redirect('chat.php');
$friendshipId = (int)($_POST['friendship_id'] ?? 0);
if ($friendshipId) {
    $check = $pdo->prepare('SELECT id FROM friendships WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
    $check->execute([$friendshipId, $user['id'], $user['id']]);
    if ($check->fetch()) {
        $stmt = $pdo->prepare('INSERT INTO chat_clears (user_id, friendship_id, cleared_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cleared_at = VALUES(cleared_at)');
        $stmt->execute([$user['id'], $friendshipId]);
    }
}
redirect('chat.php?friendship=' . $friendshipId);
