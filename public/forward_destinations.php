<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];

    $friendsStmt = $pdo->prepare("\n        SELECT f.id AS friendship_id,\n               CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END AS friend_id,\n               u.first_name, u.last_name, u.profile_photo\n        FROM friendships f\n        JOIN users u ON u.id = CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END\n        WHERE f.user_one_id = ? OR f.user_two_id = ?\n        ORDER BY u.first_name, u.last_name\n    ");
    $friendsStmt->execute([$userId, $userId, $userId, $userId]);
    $friends = array_map(function($r) {
        return [
            'type' => 'private',
            'id' => (int)$r['friend_id'],
            'friendship_id' => (int)$r['friendship_id'],
            'name' => trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: ('User #' . (int)$r['friend_id']),
            'photo' => $r['profile_photo'] ?: 'default.png',
        ];
    }, $friendsStmt->fetchAll(PDO::FETCH_ASSOC));

    $groupsStmt = $pdo->prepare("\n        SELECT g.id, g.name, COALESCE(NULLIF(g.avatar, ''), 'group-default.png') AS photo\n        FROM `groups` g\n        JOIN group_members gm ON gm.group_id = g.id\n        WHERE gm.user_id = ?\n        ORDER BY g.name\n    ");
    $groupsStmt->execute([$userId]);
    $groups = array_map(function($r) {
        return [
            'type' => 'group',
            'id' => (int)$r['id'],
            'name' => $r['name'] ?: ('Group #' . (int)$r['id']),
            'photo' => $r['photo'] ?: 'group-default.png',
        ];
    }, $groupsStmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode(['success' => true, 'friends' => $friends, 'groups' => $groups], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
