<?php
require_once __DIR__ . '/../includes/chat/load_chat_data.php';

$type = $_GET['type'] ?? 'all';
$allowedTypes = ['all', 'image', 'voice', 'file'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$whereType = "m.message_type IN ('image', 'voice', 'file')";
$params = [$userId]; 

if ($type !== 'all') {
    $whereType = "m.message_type = ?";
}

if ($type !== 'all') {
    $params[] = $type; 
}

$params[] = $userId; 
$params[] = $userId; 
$params[] = $userId; 

$stmt = $pdo->prepare("
    SELECT
        m.id AS message_id,
        m.friendship_id,
        m.sender_id,
        m.body,
        m.message_type,
        m.file_path,
        m.created_at,
        f.user_one_id,
        f.user_two_id,
        other_user.id AS friend_id,
        other_user.first_name,
        other_user.last_name,
        other_user.profile_photo
    FROM messages m
    JOIN friendships f ON f.id = m.friendship_id
    JOIN users other_user ON other_user.id = CASE
        WHEN f.user_one_id = ? THEN f.user_two_id
        ELSE f.user_one_id
    END
    WHERE $whereType
      AND m.file_path IS NOT NULL
      AND m.file_path <> ''
      AND m.is_deleted = 0
      AND (f.user_one_id = ? OR f.user_two_id = ?)
      AND m.created_at > COALESCE((
          SELECT cc.cleared_at
          FROM chat_clears cc
          WHERE cc.user_id = ? AND cc.friendship_id = m.friendship_id
          LIMIT 1
      ), '1970-01-01 00:00:00')
    ORDER BY m.created_at DESC, m.id DESC
");
$stmt->execute($params);
$mediaItems = $stmt->fetchAll();

function media_label(array $item): string {
    $type = $item['message_type'] ?? '';
    if ($type === 'image') return 'Image';
    if ($type === 'voice') return 'Voice';
    return 'File';
}

function media_caption(array $item): string {
    $body = trim((string)($item['body'] ?? ''));
    if ($body !== '') return $body;
    return media_label($item);
}

require_once __DIR__ . '/partials/chat/head.php';
?>

<div class="mobile-overlay" id="mobileOverlay"></div>

<div class="app">
    <?php require_once __DIR__ . '/partials/chat/sidebar.php'; ?>

    <main class="chat-main media-gallery-main">
        <header class="chat-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn">☰</button>
                <div>
                    <div class="header-name">🖼️ Media Gallery</div>
                    <div class="header-status"><?= count($mediaItems) ?> item<?= count($mediaItems) === 1 ? '' : 's' ?></div>
                </div>
            </div>
        </header>

        <section class="media-gallery-page">
            <div class="media-tabs">
                <a class="media-tab <?= $type === 'all' ? 'active' : '' ?>" href="media_gallery.php?type=all">All</a>
                <a class="media-tab <?= $type === 'image' ? 'active' : '' ?>" href="media_gallery.php?type=image">Images</a>
                <a class="media-tab <?= $type === 'voice' ? 'active' : '' ?>" href="media_gallery.php?type=voice">Voice</a>
                <a class="media-tab <?= $type === 'file' ? 'active' : '' ?>" href="media_gallery.php?type=file">Files</a>
            </div>

            <?php if (empty($mediaItems)): ?>
                <div class="media-empty">
                    <div class="media-empty-icon">🖼️</div>
                    <h2>No media yet</h2>
                    <p>Images, voice messages, and files you send or receive will appear here.</p>
                </div>
            <?php else: ?>
                <div class="media-grid">
                    <?php foreach ($mediaItems as $item): ?>
                        <?php
                            $friendName = trim($item['first_name'] . ' ' . $item['last_name']);
                            $senderLabel = ((int)$item['sender_id'] === $userId) ? 'You' : $friendName;
                            $path = (string)$item['file_path'];
                            $caption = media_caption($item);
                            if (mb_strlen($caption) > 90) {
                                $caption = mb_substr($caption, 0, 90) . '…';
                            }
                        ?>
                        <article class="media-card">
                            <a class="media-preview" href="<?= e($path) ?>" target="_blank" rel="noopener">
                                <?php if ($item['message_type'] === 'image'): ?>
                                    <img src="<?= e($path) ?>" alt="Image">
                                <?php elseif ($item['message_type'] === 'voice'): ?>
                                    <div class="media-voice-icon">🎙️</div>
                                    <audio controls preload="metadata" src="<?= e($path) ?>"></audio>
                                <?php else: ?>
                                    <div class="media-file-icon">📎</div>
                                <?php endif; ?>
                            </a>
                            <div class="media-info">
                                <div class="media-title"><?= e($caption) ?></div>
                                <div class="media-meta"><?= e(media_label($item)) ?> · <?= e($senderLabel) ?> · <?= e($friendName) ?></div>
                                <div class="media-date"><?= e($item['created_at']) ?></div>
                                <a class="media-open-chat" href="chat.php?friendship=<?= (int)$item['friendship_id'] ?>#message-<?= (int)$item['message_id'] ?>">Open in chat</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/partials/chat/scripts.php'; ?>
