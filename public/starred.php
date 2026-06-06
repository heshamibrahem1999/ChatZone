<?php
require_once __DIR__ . '/../includes/chat/load_chat_data.php';


$pdo->exec("CREATE TABLE IF NOT EXISTS message_stars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_message_unique (user_id, message_id),
    KEY idx_user_id (user_id),
    KEY idx_message_id (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $pdo->prepare("
    SELECT
        ms.created_at AS starred_at,
        m.id AS message_id,
        m.friendship_id,
        m.sender_id,
        m.body,
        m.message_type,
        m.file_path,
        m.is_deleted,
        m.created_at AS message_created_at,
        f.user_one_id,
        f.user_two_id,
        other_user.id AS friend_id,
        other_user.first_name,
        other_user.last_name,
        other_user.profile_photo
    FROM message_stars ms
    JOIN messages m ON m.id = ms.message_id
    JOIN friendships f ON f.id = m.friendship_id
    JOIN users other_user ON other_user.id = CASE
        WHEN f.user_one_id = ? THEN f.user_two_id
        ELSE f.user_one_id
    END
    WHERE ms.user_id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
      AND m.created_at > COALESCE((
          SELECT cc.cleared_at
          FROM chat_clears cc
          WHERE cc.user_id = ? AND cc.friendship_id = m.friendship_id
          LIMIT 1
      ), '1970-01-01 00:00:00')
    ORDER BY ms.created_at DESC, m.id DESC
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId]);
$starredMessages = $stmt->fetchAll();

function starred_preview(array $message): string {
    if ((int)($message['is_deleted'] ?? 0) === 1) {
        return '[Deleted message]';
    }
    $type = $message['message_type'] ?? 'text';
    if ($type === 'image') {
        return '[Image] ' . trim((string)($message['body'] ?? ''));
    }
    if ($type === 'voice') {
        return '[Voice message] ' . trim((string)($message['body'] ?? ''));
    }
    $body = trim((string)($message['body'] ?? ''));
    return $body !== '' ? $body : '[Empty message]';
}

require_once __DIR__ . '/partials/chat/head.php';
?>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="app">
    <?php require_once __DIR__ . '/partials/chat/sidebar.php'; ?>

    <main class="chat-main starred-page-main">
        <header class="chat-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
                <div>
                    <div class="header-name">⭐ Starred Messages</div>
                    <div class="header-status"><?= count($starredMessages) ?> saved message<?= count($starredMessages) === 1 ? '' : 's' ?></div>
                </div>
            </div>
        </header>

        <section class="starred-page">
            <?php if (empty($starredMessages)): ?>
                <div class="starred-empty">
                    <div class="starred-empty-icon">⭐</div>
                    <h2>No starred messages yet</h2>
                    <p>Open any chat, hover over a message, and click ⭐ to save it here.</p>
                </div>
            <?php else: ?>
                <div class="starred-list">
                    <?php foreach ($starredMessages as $item): ?>
                        <?php
                            $friendName = trim($item['first_name'] . ' ' . $item['last_name']);
                            $preview = starred_preview($item);
                            if (mb_strlen($preview) > 220) {
                                $preview = mb_substr($preview, 0, 220) . '…';
                            }
                            $senderLabel = ((int)$item['sender_id'] === $userId) ? 'You' : $friendName;
                        ?>
                        <a class="starred-item" href="chat.php?friendship=<?= (int)$item['friendship_id'] ?>#message-<?= (int)$item['message_id'] ?>">
                            <img class="avatar" src="uploads/profiles/<?= e($item['profile_photo'] ?: 'default.png') ?>" alt="Friend">
                            <div class="starred-content">
                                <div class="starred-topline">
                                    <strong><?= e($friendName) ?></strong>
                                    <span><?= e($item['starred_at']) ?></span>
                                </div>
                                <div class="starred-sender"><?= e($senderLabel) ?> · <?= e($item['message_created_at']) ?></div>
                                <div class="starred-preview"><?= nl2br(e($preview)) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/partials/chat/scripts.php'; ?>
