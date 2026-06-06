<?php

function cz_chat_ensure_forward_columns(PDO $pdo): void
{
    foreach ([
        "ALTER TABLE messages ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE messages ADD COLUMN forwarded_from_message_id INT NULL",
        "ALTER TABLE group_messages ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE group_messages ADD COLUMN forwarded_from_message_id INT NULL"
    ] as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

function cz_chat_load_messages(PDO $pdo, int $userId, int $friendshipId, int $limit = 50, int $beforeId = 0): array
{
    
    
    $limit = max(1, min(200, $limit));
    $beforeId = max(0, $beforeId);

    $whereBefore = $beforeId > 0 ? "AND m.id < :before_id" : "";

    $sql = "
        SELECT
            m.id, m.friendship_id, m.sender_id, m.body, m.message_type, m.file_path,
            m.reply_to_id, m.is_forwarded, m.forwarded_from_message_id, m.is_pinned, m.pinned_at, m.is_deleted, m.deleted_at, m.edited_at, m.is_seen, m.seen_at, m.delivered_at, m.read_at, m.created_at,
            r.body AS reply_body,
            r.message_type AS reply_message_type,
            r.sender_id AS reply_sender_id,
            CONCAT(ru.first_name, ' ', ru.last_name) AS reply_sender_name,
            GROUP_CONCAT(CONCAT(rc.emoji, ':', rc.total) ORDER BY rc.emoji SEPARATOR '|') AS reactions_summary,
            myr.emoji AS my_reaction,
            CASE WHEN ms.message_id IS NULL THEN 0 ELSE 1 END AS is_starred
        FROM (
            SELECT m.id
            FROM messages m
            WHERE m.friendship_id = :friendship_id_inner
              $whereBefore
              AND m.created_at > COALESCE((
                  SELECT cc.cleared_at
                  FROM chat_clears cc
                  WHERE cc.user_id = :clear_user_id AND cc.friendship_id = m.friendship_id
                  LIMIT 1
              ), '1970-01-01 00:00:00')
            ORDER BY m.created_at DESC, m.id DESC
            LIMIT :limit_rows
        ) latest
        JOIN messages m ON m.id = latest.id
        LEFT JOIN messages r ON r.id = m.reply_to_id
        LEFT JOIN users ru ON ru.id = r.sender_id
        LEFT JOIN (
            SELECT message_id, emoji, COUNT(*) AS total
            FROM message_reactions
            GROUP BY message_id, emoji
        ) rc ON rc.message_id = m.id
        LEFT JOIN message_reactions myr ON myr.message_id = m.id AND myr.user_id = :my_reaction_user_id
        LEFT JOIN message_stars ms ON ms.message_id = m.id AND ms.user_id = :star_user_id
        GROUP BY
            m.id, m.friendship_id, m.sender_id, m.body, m.message_type, m.file_path,
            m.reply_to_id, m.is_forwarded, m.forwarded_from_message_id, m.is_pinned, m.pinned_at, m.is_deleted, m.deleted_at, m.edited_at, m.is_seen, m.seen_at, m.delivered_at, m.read_at, m.created_at,
            r.body, r.message_type, r.sender_id, ru.first_name, ru.last_name, myr.emoji, ms.message_id
        ORDER BY m.created_at ASC, m.id ASC
    ";

    $msgStmt = $pdo->prepare($sql);
    $msgStmt->bindValue(':friendship_id_inner', $friendshipId, PDO::PARAM_INT);
    $msgStmt->bindValue(':clear_user_id', $userId, PDO::PARAM_INT);
    $msgStmt->bindValue(':my_reaction_user_id', $userId, PDO::PARAM_INT);
    $msgStmt->bindValue(':star_user_id', $userId, PDO::PARAM_INT);
    $msgStmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
    if ($beforeId > 0) {
        $msgStmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
    }
    $msgStmt->execute();
    return $msgStmt->fetchAll();
}
