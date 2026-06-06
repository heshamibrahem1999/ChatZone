<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    $messageId = (int)($_POST['message_id'] ?? 0);

    if ($messageId <= 0) {
        throw new Exception('Invalid message.');
    }

    
    try {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN pinned_by INT UNSIGNED DEFAULT NULL");
    } catch (Throwable $e) {}
    try {
        $pdo->exec("ALTER TABLE group_messages ADD COLUMN pinned_at DATETIME DEFAULT NULL");
    } catch (Throwable $e) {}

    $stmt = $pdo->prepare("SELECT gm.id, gm.group_id, gm.sender_id, gm.is_pinned, mb.role
        FROM group_messages gm
        JOIN group_members mb ON mb.group_id = gm.group_id AND mb.user_id = ?
        WHERE gm.id = ?
        LIMIT 1");
    $stmt->execute([$userId, $messageId]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$msg) {
        throw new Exception('Message not found or access denied.');
    }

    $isAdmin = (($msg['role'] ?? '') === 'admin');
    $isOwner = ((int)$msg['sender_id'] === $userId);
    if (!$isAdmin && !$isOwner) {
        throw new Exception('Only group admins or the message sender can pin/unpin this message.');
    }

    $newValue = ((int)$msg['is_pinned'] === 1) ? 0 : 1;
    if ($newValue === 1) {
        $upd = $pdo->prepare("UPDATE group_messages SET is_pinned = 1, pinned_by = ?, pinned_at = NOW() WHERE id = ?");
        $upd->execute([$userId, $messageId]);
    } else {
        $upd = $pdo->prepare("UPDATE group_messages SET is_pinned = 0, pinned_by = NULL, pinned_at = NULL WHERE id = ?");
        $upd->execute([$messageId]);
    }

    echo json_encode(['success' => true, 'pinned' => $newValue === 1]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}