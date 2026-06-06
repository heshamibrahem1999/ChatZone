<?php
require_once __DIR__ . '/../includes/chat/load_chat_data.php';

$groupId = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'all';
$allowedTypes = ['all', 'image', 'voice', 'file'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$groupStmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$groupStmt->execute([$groupId, $userId]);
$group = $groupStmt->fetch();
if (!$group) {
    http_response_code(403);
    die('Access denied or group not found.');
}

$whereType = "gm.message_type IN ('image', 'voice', 'file')";
$params = [':group_id' => $groupId];

if ($type !== 'all') {
    $whereType = "gm.message_type = :type";
    $params[':type'] = $type;
}

$stmt = $pdo->prepare("
    SELECT
        gm.id AS message_id,
        gm.group_id,
        gm.sender_id,
        gm.body,
        gm.message_type,
        gm.file_path,
        gm.created_at,
        u.first_name,
        u.last_name,
        u.profile_photo
    FROM group_messages gm
    JOIN users u ON u.id = gm.sender_id
    WHERE gm.group_id = :group_id
      AND $whereType
      AND gm.file_path IS NOT NULL
      AND gm.file_path <> ''
      AND COALESCE(gm.is_deleted, 0) = 0
    ORDER BY gm.created_at DESC, gm.id DESC
");
$stmt->execute($params);
$mediaItems = $stmt->fetchAll();

function group_media_label(array $item): string {
    $type = $item['message_type'] ?? '';
    if ($type === 'image') return 'Image';
    if ($type === 'voice') return 'Voice';
    return 'File';
}

function group_media_caption(array $item): string {
    $body = trim((string)($item['body'] ?? ''));
    if ($body !== '') return $body;
    return group_media_label($item);
}

function group_media_tab_url(int $groupId, string $type): string {
    return 'group_media_gallery.php?id=' . $groupId . '&type=' . urlencode($type);
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
                    <div class="header-name">🖼️ <?= e($group['name']) ?> Media</div>
                    <div class="header-status"><?= count($mediaItems) ?> item<?= count($mediaItems) === 1 ? '' : 's' ?>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <a class="media-open-chat" href="group.php?id=<?= (int)$groupId ?>">Back to group</a>
            </div>
        </header>

        <section class="media-gallery-page">
            <div class="media-tabs">
                <a class="media-tab <?= $type === 'all' ? 'active' : '' ?>"
                    href="<?= e(group_media_tab_url($groupId, 'all')) ?>">All</a>
                <a class="media-tab <?= $type === 'image' ? 'active' : '' ?>"
                    href="<?= e(group_media_tab_url($groupId, 'image')) ?>">Images</a>
                <a class="media-tab <?= $type === 'voice' ? 'active' : '' ?>"
                    href="<?= e(group_media_tab_url($groupId, 'voice')) ?>">Voice</a>
                <a class="media-tab <?= $type === 'file' ? 'active' : '' ?>"
                    href="<?= e(group_media_tab_url($groupId, 'file')) ?>">Files</a>
            </div>

            <?php if (empty($mediaItems)): ?>
            <div class="media-empty">
                <div class="media-empty-icon">🖼️</div>
                <h2>No group media yet</h2>
                <p>Images, voice messages, and files shared in this group will appear here.</p>
            </div>
            <?php else: ?>
            <div class="media-grid">
                <?php foreach ($mediaItems as $item): ?>
                <?php
                            $senderName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
                            $senderLabel = ((int)$item['sender_id'] === $userId) ? 'You' : $senderName;
                            $path = (string)$item['file_path'];
                            $caption = group_media_caption($item);
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
                        <div class="media-meta"><?= e(group_media_label($item)) ?> · <?= e($senderLabel) ?></div>
                        <div class="media-date"><?= e($item['created_at']) ?></div>
                        <a class="media-open-chat"
                            href="group.php?id=<?= (int)$groupId ?>#group-message-<?= (int)$item['message_id'] ?>">Open
                            in group</a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php require_once __DIR__ . '/partials/chat/scripts.php'; ?>