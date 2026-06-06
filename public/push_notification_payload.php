<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    if ((int)($user['notify_browser'] ?? 1) !== 1) {
        echo json_encode(['success' => false, 'message' => 'Browser notifications are disabled.']);
        exit;
    }

    $scope = $_GET['scope'] ?? '';
    $siteTitle = 'ChatZone';
    $defaultIcon = 'assets/img/icon-192.png';

    $formatBody = function (?string $body, ?string $type, ?string $filePath): string {
        $type = $type ?: 'text';
        if ($type === 'image') return '[Image]';
        if ($type === 'voice') return '[Voice message]';
        if ($type === 'file') return '[File]';
        $text = trim((string)$body);
        if ($text === '' && $filePath) return '[Attachment]';
        $text = preg_replace('/\s+/u', ' ', $text);
        if (function_exists('mb_strlen') && mb_strlen($text) > 120) {
            return mb_substr($text, 0, 117) . '...';
        }
        if (strlen($text) > 120) return substr($text, 0, 117) . '...';
        return $text !== '' ? $text : 'New message';
    };

    if ($scope === 'private') {
        $friendshipId = (int)($_GET['friendship_id'] ?? 0);
        if ($friendshipId <= 0) throw new RuntimeException('Missing friendship_id');

        $stmt = $pdo->prepare("SELECT f.id, u.id AS friend_id, u.first_name, u.last_name, u.profile_photo
            FROM friendships f
            JOIN users u ON u.id = CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END
            WHERE f.id = ? AND (f.user_one_id = ? OR f.user_two_id = ?)
            LIMIT 1");
        $stmt->execute([$userId, $friendshipId, $userId, $userId]);
        $chat = $stmt->fetch();
        if (!$chat) throw new RuntimeException('Chat not found');

        $muteStmt = $pdo->prepare("SELECT 1 FROM chat_mutes WHERE user_id = ? AND friendship_id = ? AND (muted_until IS NULL OR muted_until > NOW()) LIMIT 1");
        $muteStmt->execute([$userId, $friendshipId]);
        if ($muteStmt->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'Chat is muted.']);
            exit;
        }

        $msgStmt = $pdo->prepare("SELECT id, body, message_type, file_path, created_at
            FROM messages
            WHERE friendship_id = ? AND sender_id <> ? AND is_deleted = 0
            ORDER BY created_at DESC, id DESC
            LIMIT 1");
        $msgStmt->execute([$friendshipId, $userId]);
        $msg = $msgStmt->fetch();
        if (!$msg) throw new RuntimeException('No incoming message found');

        $name = trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? '')) ?: 'New message';
        $photo = $chat['profile_photo'] ?: 'default.png';
        echo json_encode([
            'success' => true,
            'id' => 'private-' . (int)$msg['id'],
            'title' => $name,
            'body' => $formatBody($msg['body'] ?? '', $msg['message_type'] ?? 'text', $msg['file_path'] ?? null),
            'icon' => 'uploads/profiles/' . $photo,
            'badge' => $defaultIcon,
            'url' => 'chat.php?friendship_id=' . $friendshipId,
            'scope' => 'private'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($scope === 'group') {
        $groupId = (int)($_GET['group_id'] ?? 0);
        if ($groupId <= 0) throw new RuntimeException('Missing group_id');

        $stmt = $pdo->prepare("SELECT g.id, g.name, g.avatar
            FROM `groups` g
            JOIN group_members gm ON gm.group_id = g.id AND gm.user_id = ?
            WHERE g.id = ?
            LIMIT 1");
        $stmt->execute([$userId, $groupId]);
        $group = $stmt->fetch();
        if (!$group) throw new RuntimeException('Group not found');

        $msgStmt = $pdo->prepare("SELECT gm.id, gm.body, gm.message_type, gm.file_path, gm.created_at, u.first_name, u.last_name
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            WHERE gm.group_id = ? AND gm.sender_id <> ? AND COALESCE(gm.is_deleted, 0) = 0
            ORDER BY gm.created_at DESC, gm.id DESC
            LIMIT 1");
        $msgStmt->execute([$groupId, $userId]);
        $msg = $msgStmt->fetch();
        if (!$msg) throw new RuntimeException('No incoming group message found');

        $sender = trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')) ?: 'Someone';
        $title = ($group['name'] ?: 'Group') . ' • ' . $sender;
        $avatar = $group['avatar'] ?: 'group-default.png';
        echo json_encode([
            'success' => true,
            'id' => 'group-' . (int)$msg['id'],
            'title' => $title,
            'body' => $formatBody($msg['body'] ?? '', $msg['message_type'] ?? 'text', $msg['file_path'] ?? null),
            'icon' => 'uploads/groups/' . $avatar,
            'badge' => $defaultIcon,
            'url' => 'group.php?id=' . $groupId,
            'scope' => 'group'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new RuntimeException('Invalid scope');
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
