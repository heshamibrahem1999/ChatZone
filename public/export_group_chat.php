<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$groupId = (int)($_GET['id'] ?? 0);

if ($groupId <= 0) {
    http_response_code(400);
    die('Missing group id.');
}

$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
if (!$group) {
    http_response_code(403);
    die('Access denied or group not found.');
}

$msgStmt = $pdo->prepare("SELECT gm.*, u.first_name, u.last_name, u.email
    FROM group_messages gm
    JOIN users u ON u.id = gm.sender_id
    WHERE gm.group_id = ?
    ORDER BY gm.created_at ASC, gm.id ASC");
$msgStmt->execute([$groupId]);
$messages = $msgStmt->fetchAll();

$lines = [];
$lines[] = 'ChatZone Group Export';
$lines[] = 'Exported at: ' . date('Y-m-d H:i:s');
$lines[] = 'Group: ' . ($group['name'] ?? ('Group ' . $groupId));
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
    } elseif ($type === 'file') {
        $body = '[File] ' . ($m['file_path'] ?? '');
    } else {
        $body = (string)($m['body'] ?? '');
    }
    $edited = !empty($m['edited_at']) ? ' (edited)' : '';
    $lines[] = '[' . ($m['created_at'] ?? '') . '] ' . $sender . $edited . ':';
    $lines[] = $body;
    $lines[] = '';
}

$content = implode("\r\n", $lines);
$safeGroup = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string)($group['name'] ?? 'group')));
$filename = 'chatzone-group-' . trim($safeGroup, '-') . '-' . date('Ymd-His') . '.txt';
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
echo $content;
exit;