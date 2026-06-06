<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];

function fetch_all_safe(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return ['_error' => $e->getMessage()];
    }
}

$data = [
    'exported_at' => date('c'),
    'user' => $user,
    'private_messages_sent' => fetch_all_safe($pdo, 'SELECT * FROM messages WHERE sender_id = ? ORDER BY created_at ASC, id ASC', [$userId]),
    'private_messages_received' => fetch_all_safe($pdo, 'SELECT * FROM messages WHERE receiver_id = ? ORDER BY created_at ASC, id ASC', [$userId]),
    'friendships' => fetch_all_safe($pdo, 'SELECT * FROM friendships WHERE user_id = ? OR friend_id = ? ORDER BY id ASC', [$userId, $userId]),
    'groups' => fetch_all_safe($pdo, 'SELECT g.* FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE gm.user_id = ? ORDER BY g.created_at ASC', [$userId]),
    'group_memberships' => fetch_all_safe($pdo, 'SELECT * FROM group_members WHERE user_id = ? ORDER BY joined_at ASC', [$userId]),
    'group_messages' => fetch_all_safe($pdo, 'SELECT * FROM group_messages WHERE sender_id = ? ORDER BY created_at ASC, id ASC', [$userId]),
    'message_stars' => fetch_all_safe($pdo, 'SELECT * FROM message_stars WHERE user_id = ?', [$userId]),
    'group_message_stars' => fetch_all_safe($pdo, 'SELECT * FROM group_message_stars WHERE user_id = ?', [$userId]),
    'archived_chats' => fetch_all_safe($pdo, 'SELECT * FROM archived_chats WHERE user_id = ?', [$userId]),
    'muted_chats' => fetch_all_safe($pdo, 'SELECT * FROM muted_chats WHERE user_id = ?', [$userId]),
    'reports_submitted' => fetch_all_safe($pdo, 'SELECT * FROM reports WHERE reporter_id = ? ORDER BY created_at ASC', [$userId]),
    'scheduled_messages' => fetch_all_safe($pdo, 'SELECT * FROM scheduled_messages WHERE sender_id = ? ORDER BY scheduled_at ASC', [$userId]),
];

$filename = 'chatzone-data-user-' . $userId . '-' . date('Ymd-His') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
