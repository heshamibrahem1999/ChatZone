<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = require_login($pdo);
$userId = (int) $user['id'];
update_user_presence($pdo, $userId);
require_csrf_or_redirect('chat.php');

$friendshipId = (int) ($_POST['friendship_id'] ?? 0);

if ($friendshipId <= 0) {
    redirect('chat.php');
}

$check = $pdo->prepare("\n    SELECT id\n    FROM friendships\n    WHERE id = ?\n      AND (user_one_id = ? OR user_two_id = ?)\n    LIMIT 1\n");
$check->execute([$friendshipId, $userId, $userId]);

if (!$check->fetch()) {
    redirect('chat.php');
}

$stmt = $pdo->prepare("\n    INSERT INTO chat_clears (user_id, friendship_id, cleared_at)\n    VALUES (?, ?, NOW())\n    ON DUPLICATE KEY UPDATE cleared_at = VALUES(cleared_at)\n");
$stmt->execute([$userId, $friendshipId]);

redirect('chat.php?friendship=' . $friendshipId);