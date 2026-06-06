<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_archives (
  user_id INT UNSIGNED NOT NULL,
  friendship_id INT UNSIGNED NOT NULL,
  archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, friendship_id),
  KEY idx_chat_archives_friendship (friendship_id)
)");

$stmt = $pdo->prepare("
    SELECT ca.friendship_id, ca.archived_at,
           u.id AS friend_id, u.first_name, u.last_name, u.email, u.profile_photo,
           (
             SELECT CASE
                WHEN m.is_deleted = 1 THEN '[Deleted message]'
                WHEN m.message_type = 'image' THEN '[Image]'
                WHEN m.message_type = 'voice' THEN '[Voice message]'
                ELSE m.body
             END
             FROM messages m
             WHERE m.friendship_id = ca.friendship_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 1
           ) AS last_message
    FROM chat_archives ca
    JOIN friendships f ON f.id = ca.friendship_id
    JOIN users u ON u.id = CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END
    WHERE ca.user_id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    ORDER BY ca.archived_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$archived = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archived Chats - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__archived_chats.css">
</head>
<body>
<div class="page-wrap">
    <div class="page-card">
        <div class="topbar">
            <h2>📦 Archived Chats</h2>
            <a class="btn" href="chat.php">← Back to Chat</a>
        </div>

        <?php if (empty($archived)): ?>
            <div class="empty">No archived chats yet.</div>
        <?php else: ?>
            <?php foreach ($archived as $chat): ?>
                <div class="archive-row">
                    <img src="uploads/profiles/<?= e($chat['profile_photo'] ?: 'default.png') ?>" alt="Friend">
                    <div class="meta">
                        <div class="name"><?= e(trim($chat['first_name'] . ' ' . $chat['last_name'])) ?></div>
                        <div class="last"><?= e($chat['last_message'] ?: 'No messages yet') ?></div>
                    </div>
                    <div class="actions">
                        <form method="post" action="archive_chat.php">
                            <input type="hidden" name="friendship_id" value="<?= (int)$chat['friendship_id'] ?>">
                            <input type="hidden" name="action" value="restore">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn primary" type="submit">Restore</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
