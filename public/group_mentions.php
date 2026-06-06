<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_message_mentions (
        group_message_id INT UNSIGNED NOT NULL,
        group_id INT UNSIGNED NOT NULL,
        mentioned_user_id INT UNSIGNED NOT NULL,
        mentioned_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_message_id, mentioned_user_id),
        INDEX idx_gmm_user (mentioned_user_id),
        INDEX idx_gmm_group (group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

$stmt = $pdo->prepare("\n    SELECT gmm.created_at AS mentioned_at, gm.id AS message_id, gm.body, gm.message_type, gm.file_path, gm.created_at AS message_created_at,\n           g.id AS group_id, g.name AS group_name,\n           u.first_name, u.last_name\n    FROM group_message_mentions gmm\n    JOIN group_messages gm ON gm.id = gmm.group_message_id\n    JOIN `groups` g ON g.id = gmm.group_id\n    JOIN users u ON u.id = gm.sender_id\n    JOIN group_members me ON me.group_id = g.id AND me.user_id = gmm.mentioned_user_id\n    WHERE gmm.mentioned_user_id = ?\n    ORDER BY gmm.created_at DESC\n    LIMIT 100\n");
$stmt->execute([$userId]);
$mentions = $stmt->fetchAll();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Mentions</title><link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
<link rel="stylesheet" href="assets/css/extracted/public__group_mentions.css"></head>
<body><div class="page">
<h2>🏷️ Mentions</h2>
<p><a href="chat.php">Back to chat</a></p>
<?php if (!$mentions): ?>
    <p>No group mentions yet.</p>
<?php endif; ?>
<?php foreach ($mentions as $m): ?>
    <div class="mention-card">
        <div><strong><?= e($m['group_name']) ?></strong></div>
        <div class="meta">From <?= e(trim($m['first_name'].' '.$m['last_name'])) ?> · <?= e($m['message_created_at']) ?></div>
        <div class="body">
            <?php
            $body = $m['body'] ?: '[' . ($m['message_type'] ?: 'media') . ']';
            $safe = nl2br(e($body));
            echo preg_replace('/@\[([^\]]+)\]\(user:(\d+)\)/u', '<span class="mention-highlight">@$1</span>', preg_replace('/(^|\s)@([\p{L}\p{N}._-]+)/u', '$1<span class="mention-highlight">@$2</span>', $safe));
            ?>
        </div>
        <a href="group.php?id=<?= (int)$m['group_id'] ?>#group-message-<?= (int)$m['message_id'] ?>">Open message</a>
    </div>
<?php endforeach; ?>
</div></body></html>
