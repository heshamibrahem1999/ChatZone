<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';
require_once __DIR__ . '/../includes/blocking.php';
require_once __DIR__ . '/../includes/chat/message_data.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
update_user_presence($pdo, $userId);

$friendshipId = (int) ($_GET['friendship_id'] ?? 0);

if ($friendshipId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid friendship']);
    exit;
}

$check = $pdo->prepare("
    SELECT id, CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END AS friend_id
    FROM friendships
    WHERE id = ?
      AND (user_one_id = ? OR user_two_id = ?)
    LIMIT 1
");
$check->execute([$userId, $friendshipId, $userId, $userId]);
$friendshipRow = $check->fetch();

if (!$friendshipRow) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}
$blockStatus = get_block_status($pdo, $userId, (int)$friendshipRow['friend_id']);

$markSeen = $pdo->prepare("
    UPDATE messages
    SET is_seen = 1,
        seen_at = COALESCE(seen_at, NOW()),
        delivered_at = COALESCE(delivered_at, NOW()),
        read_at = COALESCE(read_at, NOW())
    WHERE friendship_id = ?
      AND sender_id <> ?
      AND (is_seen = 0 OR seen_at IS NULL OR delivered_at IS NULL OR read_at IS NULL)
");
$markSeen->execute([$friendshipId, $userId]);

$limit = (int)($_GET['limit'] ?? 50);
$limit = max(1, min(200, $limit));
$beforeId = (int)($_GET['before_id'] ?? 0);
$beforeId = max(0, $beforeId);

$messages = cz_chat_load_messages($pdo, $userId, $friendshipId, $limit, $beforeId);
$hasMore = count($messages) === $limit;

echo json_encode([
    'success' => true,
    'messages' => $messages,
    'my_id' => $userId,
    'block_status' => $blockStatus,
    'has_more' => $hasMore,
    'limit' => $limit,
]);