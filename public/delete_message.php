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

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, sender_id, file_path, is_deleted FROM messages WHERE id = ? LIMIT 1");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

if ((int)$message['sender_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'You can only delete your own messages']);
    exit;
}

if ((int)($message['is_deleted'] ?? 0) === 1) {
    echo json_encode(['success' => true]);
    exit;
}

$delete = $pdo->prepare("\n    UPDATE messages\n    SET body = '',\n        message_type = 'text',\n        file_path = NULL,\n        is_deleted = 1,\n        deleted_at = NOW()\n    WHERE id = ? AND sender_id = ?\n");
$ok = $delete->execute([$messageId, $userId]);

if ($ok && !empty($message['file_path'])) {
    safe_unlink_public_file(__DIR__, $message['file_path']);
}

echo json_encode(['success' => $ok]);