<?php

function cz_table_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch();
    } catch (Throwable $e) {
        return false;
    }
}

function cz_chat_ensure_read_receipt_columns(PDO $pdo): void
{
    try {
        if (!cz_table_column_exists($pdo, 'messages', 'delivered_at')) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN delivered_at DATETIME NULL AFTER seen_at");
        }
        if (!cz_table_column_exists($pdo, 'messages', 'read_at')) {
            $pdo->exec("ALTER TABLE messages ADD COLUMN read_at DATETIME NULL AFTER delivered_at");
        }
    } catch (Throwable $e) {}

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS group_message_reads (
            group_message_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (group_message_id, user_id),
            KEY idx_group_message_reads_user (user_id),
            KEY idx_group_message_reads_read_at (read_at)
        )");
    } catch (Throwable $e) {}
}


function cz_chat_ensure_tables(PDO $pdo): void
{
    ensure_user_blocks_table($pdo);
    cz_chat_ensure_read_receipt_columns($pdo);

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_archives (
        user_id INT UNSIGNED NOT NULL,
        friendship_id INT UNSIGNED NOT NULL,
        archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, friendship_id),
        KEY idx_chat_archives_friendship (friendship_id)
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_mutes (
        user_id INT UNSIGNED NOT NULL,
        friendship_id INT UNSIGNED NOT NULL,
        muted_until DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, friendship_id),
        KEY idx_chat_mutes_friendship (friendship_id),
        KEY idx_chat_mutes_until (muted_until)
    )");
}
