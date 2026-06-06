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
$body = trim((string) ($_POST['body'] ?? ''));

if ($messageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid message']);
    exit;
}

if ($body === '') {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

if (mb_strlen($body) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long']);
    exit;
}

$stmt = $pdo->prepare("\n    SELECT id, sender_id, message_type, is_deleted\n    FROM messages\n    WHERE id = ?\n    LIMIT 1\n");
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

if ((int)$message['sender_id'] !== $userId) {
    echo json_encode(['success' => false, 'message' => 'You can only edit your own messages']);
    exit;
}

if ((int)($message['is_deleted'] ?? 0) === 1) {
    echo json_encode(['success' => false, 'message' => 'Deleted messages cannot be edited']);
    exit;
}

if (($message['message_type'] ?? 'text') !== 'text') {
    echo json_encode(['success' => false, 'message' => 'Only text messages can be edited']);
    exit;
}

$update = $pdo->prepare("UPDATE messages SET body = ?, edited_at = NOW() WHERE id = ? AND sender_id = ?");
$ok = $update->execute([$body, $messageId, $userId]);

echo json_encode(['success' => $ok]);