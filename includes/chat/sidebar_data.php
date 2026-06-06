<?php

function cz_chat_load_sidebar_chats(PDO $pdo, int $userId): array
{
    cleanup_stale_presence($pdo);
    $friendsStmt = $pdo->prepare("
        SELECT
            f.id AS friendship_id,
            u.id AS friend_id,
            u.first_name,
            u.last_name,
            u.email,
            u.profile_photo,
            u.about,
            CASE WHEN ub_me.blocked_id IS NULL THEN 0 ELSE 1 END AS i_blocked_them,
            CASE WHEN ub_them.blocker_id IS NULL THEN 0 ELSE 1 END AS they_blocked_me,
            " . presence_case_sql('u') . " AS is_online,
            u.last_active_at,
            cm.muted_until,
            CASE WHEN cm.user_id IS NOT NULL AND (cm.muted_until IS NULL OR cm.muted_until > NOW()) THEN 1 ELSE 0 END AS is_muted,
            (
                SELECT CASE
                    WHEN m.is_deleted = 1 THEN '[Deleted message]'
                    WHEN m.message_type = 'image' THEN '[Image]'
                    WHEN m.message_type = 'voice' THEN '[Voice message]'
                    ELSE m.body
                END
                FROM messages m
                WHERE m.friendship_id = f.id
                  AND m.created_at > COALESCE((
                      SELECT cc.cleared_at FROM chat_clears cc
                      WHERE cc.user_id = ? AND cc.friendship_id = f.id
                      LIMIT 1
                  ), '1970-01-01 00:00:00')
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT m.created_at
                FROM messages m
                WHERE m.friendship_id = f.id
                  AND m.created_at > COALESCE((
                      SELECT cc.cleared_at FROM chat_clears cc
                      WHERE cc.user_id = ? AND cc.friendship_id = f.id
                      LIMIT 1
                  ), '1970-01-01 00:00:00')
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT 1
            ) AS last_time,
            (
                SELECT COUNT(*)
                FROM messages m
                WHERE m.friendship_id = f.id
                  AND m.created_at > COALESCE((
                      SELECT cc.cleared_at FROM chat_clears cc
                      WHERE cc.user_id = ? AND cc.friendship_id = f.id
                      LIMIT 1
                  ), '1970-01-01 00:00:00')
                  AND m.sender_id <> ?
                  AND m.is_seen = 0
            ) AS unread_count
        FROM friendships f
        JOIN users u
            ON u.id = CASE
                WHEN f.user_one_id = ? THEN f.user_two_id
                ELSE f.user_one_id
            END
        LEFT JOIN user_blocks ub_me
            ON ub_me.blocker_id = ? AND ub_me.blocked_id = u.id
        LEFT JOIN user_blocks ub_them
            ON ub_them.blocker_id = u.id AND ub_them.blocked_id = ?
        LEFT JOIN chat_mutes cm
            ON cm.user_id = ? AND cm.friendship_id = f.id AND (cm.muted_until IS NULL OR cm.muted_until > NOW())
        WHERE (f.user_one_id = ? OR f.user_two_id = ?)
          AND NOT EXISTS (
              SELECT 1 FROM chat_archives ca
              WHERE ca.user_id = ? AND ca.friendship_id = f.id
          )
        ORDER BY COALESCE(last_time, f.created_at) DESC
    ");

    $friendsStmt->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]);
    return $friendsStmt->fetchAll();
}
