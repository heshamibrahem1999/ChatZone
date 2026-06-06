<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    $messageId = (int)($_POST['message_id'] ?? 0);
    $body = trim((string)($_POST['body'] ?? ''));

    if ($messageId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid message.']); exit;
    }
    if ($body === '') {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty.']); exit;
    }
    if (mb_strlen($body) > 1000) {
        echo json_encode(['success' => false, 'error' => 'Message is too long.']); exit;
    }

    $stmt = $pdo->prepare("SELECT gm.id, gm.group_id, gm.sender_id, gm.message_type, COALESCE(gm.is_deleted,0) AS is_deleted
        FROM group_messages gm
        JOIN group_members gmem ON gmem.group_id = gm.group_id AND gmem.user_id = ?
        WHERE gm.id = ? LIMIT 1");
    $stmt->execute([$userId, $messageId]);
    $msg = $stmt->fetch();

    if (!$msg) { echo json_encode(['success' => false, 'error' => 'Message not found or access denied.']); exit; }
    if ((int)$msg['sender_id'] !== $userId) { echo json_encode(['success' => false, 'error' => 'You can only edit your own messages.']); exit; }
    if ((int)$msg['is_deleted'] === 1) { echo json_encode(['success' => false, 'error' => 'Deleted messages cannot be edited.']); exit; }
    if (($msg['message_type'] ?? 'text') !== 'text') { echo json_encode(['success' => false, 'error' => 'Only text messages can be edited.']); exit; }

    
    try { $pdo->exec("ALTER TABLE group_messages ADD COLUMN IF NOT EXISTS edited_at DATETIME NULL"); } catch (Throwable $ignore) {}

    $up = $pdo->prepare("UPDATE group_messages SET body = ?, edited_at = NOW() WHERE id = ? AND sender_id = ?");
    $up->execute([$body, $messageId, $userId]);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
