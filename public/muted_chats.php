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

$stmt = $pdo->prepare("
    SELECT cm.friendship_id, cm.muted_until, cm.updated_at,
           u.id AS friend_id, u.first_name, u.last_name, u.email, u.profile_photo,
           (
             SELECT CASE
                WHEN m.is_deleted = 1 THEN '[Deleted message]'
                WHEN m.message_type = 'image' THEN '[Image]'
                WHEN m.message_type = 'voice' THEN '[Voice message]'
                ELSE m.body
             END
             FROM messages m
             WHERE m.friendship_id = cm.friendship_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 1
           ) AS last_message
    FROM chat_mutes cm
    JOIN friendships f ON f.id = cm.friendship_id
    JOIN users u ON u.id = CASE WHEN f.user_one_id = ? THEN f.user_two_id ELSE f.user_one_id END
    WHERE cm.user_id = ?
      AND (cm.muted_until IS NULL OR cm.muted_until > NOW())
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    ORDER BY cm.updated_at DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$muted = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Muted Chats - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__muted_chats.css">
</head>
<body>
<div class="page-wrap">
    <div class="page-card">
        <div class="topbar">
            <h2>🔕 Muted Chats</h2>
            <a class="btn" href="chat.php">← Back to Chat</a>
        </div>

        <?php if (empty($muted)): ?>
            <div class="empty">No muted chats yet.</div>
        <?php else: ?>
            <?php foreach ($muted as $chat): ?>
                <div class="mute-row">
                    <img src="uploads/profiles/<?= e($chat['profile_photo'] ?: 'default.png') ?>" alt="Friend">
                    <div class="meta">
                        <div class="name"><?= e(trim($chat['first_name'] . ' ' . $chat['last_name'])) ?></div>
                        <div class="last"><?= e($chat['last_message'] ?: 'No messages yet') ?></div>
                        <div class="until">Muted <?= empty($chat['muted_until']) ? 'forever' : 'until ' . e(date('M j, Y H:i', strtotime($chat['muted_until']))) ?></div>
                    </div>
                    <div class="actions">
                        <a class="btn" href="chat.php?friendship=<?= (int)$chat['friendship_id'] ?>">Open</a>
                        <form method="post" action="mute_chat.php">
                            <input type="hidden" name="friendship_id" value="<?= (int)$chat['friendship_id'] ?>">
                            <input type="hidden" name="duration" value="unmute">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn primary" type="submit">Unmute</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
