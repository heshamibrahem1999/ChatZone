<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_csrf_or_json();

$userId = (int) $_SESSION['user_id'];
update_user_presence($pdo, $userId);

$messageId = (int) ($_POST['message_id'] ?? 0);
$emoji = trim($_POST['emoji'] ?? '');
$allowed = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

if ($messageId <= 0 || !in_array($emoji, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid reaction']);
    exit;
}

$check = $pdo->prepare("
    SELECT m.id
    FROM messages m
    JOIN friendships f ON f.id = m.friendship_id
    WHERE m.id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    LIMIT 1
");
$check->execute([$messageId, $userId, $userId]);

if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$current = $pdo->prepare("SELECT emoji FROM message_reactions WHERE message_id = ? AND user_id = ? LIMIT 1");
$current->execute([$messageId, $userId]);
$row = $current->fetch();

if ($row && $row['emoji'] === $emoji) {
    $delete = $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ? AND user_id = ?");
    $ok = $delete->execute([$messageId, $userId]);
} else {
    $upsert = $pdo->prepare("
        INSERT INTO message_reactions (message_id, user_id, emoji)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = CURRENT_TIMESTAMP
    ");
    $ok = $upsert->execute([$messageId, $userId, $emoji]);
}

echo json_encode(['success' => (bool) $ok]);