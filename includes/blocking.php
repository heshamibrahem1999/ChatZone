<?php


function ensure_user_blocks_table(PDO $pdo): void
{
    
    return;
}

function get_block_status(PDO $pdo, int $userId, int $friendId): array
{
    $empty = [
        'i_blocked_them' => false,
        'they_blocked_me' => false,
        'is_blocked' => false,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT blocker_id, blocked_id
            FROM user_blocks
            WHERE (blocker_id = ? AND blocked_id = ?)
               OR (blocker_id = ? AND blocked_id = ?)
            LIMIT 2
        ");
        $stmt->execute([$userId, $friendId, $friendId, $userId]);
    } catch (Throwable $e) {
        
        return $empty;
    }

    $iBlockedThem = false;
    $theyBlockedMe = false;

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['blocker_id'] === $userId && (int)$row['blocked_id'] === $friendId) {
            $iBlockedThem = true;
        }
        if ((int)$row['blocker_id'] === $friendId && (int)$row['blocked_id'] === $userId) {
            $theyBlockedMe = true;
        }
    }

    return [
        'i_blocked_them' => $iBlockedThem,
        'they_blocked_me' => $theyBlockedMe,
        'is_blocked' => $iBlockedThem || $theyBlockedMe,
    ];
}

function users_can_message(PDO $pdo, int $senderId, int $receiverId): bool
{
    $status = get_block_status($pdo, $senderId, $receiverId);
    return !$status['is_blocked'];
}
