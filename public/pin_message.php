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

$stmt = $pdo->prepare("
    SELECT m.id, m.friendship_id, m.is_deleted, m.is_pinned
    FROM messages m
    INNER JOIN friendships f ON f.id = m.friendship_id
    WHERE m.id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$messageId, $userId, $userId]);
$message = $stmt->fetch();

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
    exit;
}

if ((int)($message['is_deleted'] ?? 0) === 1) {
    echo json_encode(['success' => false, 'message' => 'Cannot pin a deleted message']);
    exit;
}

$newPinned = ((int)($message['is_pinned'] ?? 0) === 1) ? 0 : 1;

$update = $pdo->prepare("
    UPDATE messages
    SET is_pinned = ?,
        pinned_at = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
    WHERE id = ?
");
$ok = $update->execute([$newPinned, $newPinned, $messageId]);

echo json_encode([
    'success' => (bool) $ok,
    'is_pinned' => $newPinned,
]);
