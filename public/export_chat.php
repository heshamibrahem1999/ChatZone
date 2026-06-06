<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$friendshipId = (int)($_GET['friendship_id'] ?? $_GET['friendship'] ?? 0);

if ($friendshipId <= 0) {
    http_response_code(400);
    die('Missing chat id.');
}

$stmt = $pdo->prepare("SELECT f.*, u.id AS friend_id, u.first_name, u.last_name, u.email
    FROM friendships f
    JOIN users u ON u.id = CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END
    WHERE f.id = ? AND (f.user_one_id = ? OR f.user_two_id = ?)
    LIMIT 1");
$stmt->execute([$userId, $friendshipId, $userId, $userId]);
$chat = $stmt->fetch();
if (!$chat) {
    http_response_code(403);
    die('Access denied.');
}

$clearStmt = $pdo->prepare("SELECT cleared_at FROM chat_clears WHERE user_id = ? AND friendship_id = ? LIMIT 1");
$clearStmt->execute([$userId, $friendshipId]);
$clearedAt = $clearStmt->fetchColumn() ?: '1970-01-01 00:00:00';

$msgStmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name, u.email
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.friendship_id = ? AND m.created_at > ?
    ORDER BY m.created_at ASC, m.id ASC");
$msgStmt->execute([$friendshipId, $clearedAt]);
$messages = $msgStmt->fetchAll();

$friendName = trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: ('User ' . (int)$chat['friend_id']);
$myName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Me';

$lines = [];
$lines[] = 'ChatZone Chat Export';
$lines[] = 'Exported at: ' . date('Y-m-d H:i:s');
$lines[] = 'Chat: ' . $myName . ' <-> ' . $friendName;
$lines[] = str_repeat('=', 60);
$lines[] = '';

foreach ($messages as $m) {
    $sender = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: ($m['email'] ?? 'User');
    $type = $m['message_type'] ?? 'text';
    if ((int)($m['is_deleted'] ?? 0) === 1) {
        $body = '[Deleted message]';
    } elseif ($type === 'image') {
        $body = '[Image] ' . ($m['file_path'] ?? '');
    } elseif ($type === 'voice') {
        $body = '[Voice message] ' . ($m['file_path'] ?? '');
    } else {
        $body = (string)($m['body'] ?? '');
    }
    $edited = !empty($m['edited_at']) ? ' (edited)' : '';
    $lines[] = '[' . ($m['created_at'] ?? '') . '] ' . $sender . $edited . ':';
    $lines[] = $body;
    $lines[] = '';
}

$content = implode("\r\n", $lines);
$filename = 'chatzone-chat-' . $friendshipId . '-' . date('Ymd-His') . '.txt';
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;
exit;
