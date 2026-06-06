<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];

$pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  friendship_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  status ENUM('pending','sent','cancelled') NOT NULL DEFAULT 'pending',
  sent_message_id INT UNSIGNED DEFAULT NULL,
  sent_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_sched_due (status, scheduled_at),
  INDEX idx_sched_sender (sender_id, status),
  INDEX idx_sched_friendship (friendship_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $pdo->prepare("SELECT sm.*, CONCAT(u.first_name, ' ', u.last_name) AS friend_name
    FROM scheduled_messages sm
    JOIN friendships f ON f.id = sm.friendship_id
    JOIN users u ON u.id = CASE WHEN f.user_one_id = sm.sender_id THEN f.user_two_id ELSE f.user_one_id END
    WHERE sm.sender_id = ?
    ORDER BY FIELD(sm.status, 'pending','sent','cancelled'), sm.scheduled_at DESC");
$stmt->execute([$userId]);
$items = $stmt->fetchAll();
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Scheduled Messages</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__scheduled_messages.css">
</head>

<body>
    <div class="page-wrap">
        <div class="topbar">
            <h1>⏰ Scheduled Messages</h1><a href="chat.php">← Back to chat</a>
        </div>
        <?php if (!$items): ?>
        <div class="card">No scheduled messages yet.</div>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
        <div class="card">
            <div><strong>To:</strong> <?= e($item['friend_name'] ?: 'User') ?> <span
                    class="badge <?= e($item['status']) ?>"><?= e($item['status']) ?></span></div>
            <div class="msg-text"><?= e($item['body']) ?></div>
            <div class="muted">Scheduled:
                <?= e($item['scheduled_at']) ?><?= $item['sent_at'] ? ' · Sent: ' . e($item['sent_at']) : '' ?></div>
            <?php if ($item['status'] === 'pending'): ?>
            <form method="post" action="cancel_scheduled_message.php" style="margin-top:10px"
                onsubmit="return confirm('Cancel this scheduled message?')">
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="cancel-btn" type="submit">Cancel</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</body>

</html>