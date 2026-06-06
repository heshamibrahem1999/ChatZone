<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

function group_safe_delete_public_file(string $publicDir, ?string $path): void {
    if (!$path) return;
    $path = str_replace(['..', '\\'], ['', '/'], $path);
    $full = realpath($publicDir . '/' . ltrim($path, '/'));
    $root = realpath($publicDir);
    if ($full && $root && strpos($full, $root) === 0 && is_file($full)) {
        @unlink($full);
    }
}

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    $messageId = (int)($_POST['message_id'] ?? 0);

    if ($messageId <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid message.']); exit; }

    
    try { $pdo->exec("ALTER TABLE group_messages ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $ignore) {}
    try { $pdo->exec("ALTER TABLE group_messages ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL"); } catch (Throwable $ignore) {}

    $stmt = $pdo->prepare("SELECT gm.id, gm.group_id, gm.sender_id, gm.file_path, COALESCE(gm.is_deleted,0) AS is_deleted
        FROM group_messages gm
        JOIN group_members gmem ON gmem.group_id = gm.group_id AND gmem.user_id = ?
        WHERE gm.id = ? LIMIT 1");
    $stmt->execute([$userId, $messageId]);
    $msg = $stmt->fetch();

    if (!$msg) { echo json_encode(['success' => false, 'error' => 'Message not found or access denied.']); exit; }
    if ((int)$msg['sender_id'] !== $userId) { echo json_encode(['success' => false, 'error' => 'You can only delete your own messages.']); exit; }
    if ((int)$msg['is_deleted'] === 1) { echo json_encode(['success' => true]); exit; }

    $del = $pdo->prepare("UPDATE group_messages SET body = '', message_type = 'text', file_path = NULL, is_deleted = 1, deleted_at = NOW() WHERE id = ? AND sender_id = ?");
    $del->execute([$messageId, $userId]);

    group_safe_delete_public_file(__DIR__, $msg['file_path'] ?? null);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}