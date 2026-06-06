<?php

function friendship_id(PDO $pdo, int $userId, int $friendId): ?int {
    $a = min($userId, $friendId);
    $b = max($userId, $friendId);
    $stmt = $pdo->prepare('SELECT id FROM friendships WHERE user_one_id = ? AND user_two_id = ?');
    $stmt->execute([$a, $b]);
    $row = $stmt->fetch();
    return $row ? (int)$row['id'] : null;
}

function create_friendship(PDO $pdo, int $userId, int $friendId): int {
    $existing = friendship_id($pdo, $userId, $friendId);
    if ($existing) {
        return $existing;
    }
    $a = min($userId, $friendId);
    $b = max($userId, $friendId);
    $stmt = $pdo->prepare('INSERT INTO friendships (user_one_id, user_two_id) VALUES (?, ?)');
    $stmt->execute([$a, $b]);
    return (int)$pdo->lastInsertId();
}

function chat_list(PDO $pdo, int $userId): array {
    $sql = "
        SELECT
            f.id AS friendship_id,
            u.id AS friend_id,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_photo,
            (
                SELECT body FROM messages m
                WHERE m.friendship_id = f.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT created_at FROM messages m
                WHERE m.friendship_id = f.id
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_time
        FROM friendships f
        JOIN users u ON u.id = CASE
            WHEN f.user_one_id = :uid THEN f.user_two_id
            ELSE f.user_one_id
        END
        WHERE f.user_one_id = :uid OR f.user_two_id = :uid
        ORDER BY COALESCE(last_time, f.created_at) DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

function chat_messages(PDO $pdo, int $friendshipId): array {
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE friendship_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([$friendshipId]);
    return $stmt->fetchAll();
}
