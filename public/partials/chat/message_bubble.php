<?php
$isMe = ((int)$message['sender_id'] === $userId);
$isDeleted = (int)($message['is_deleted'] ?? 0) === 1;
$reactionEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
$replyType = $message['reply_message_type'] ?? '';
$replyBody = $replyType === 'image' ? '[Image]' : ($replyType === 'voice' ? '[Voice message]' : trim((string)($message['reply_body'] ?? '')));
if (mb_strlen($replyBody) > 80) {
    $replyBody = mb_substr($replyBody, 0, 80) . '…';
}
if ($replyBody === '') {
    $replyBody = 'Deleted message';
}
$summary = [];
if (!$isDeleted && !empty($message['reactions_summary'])) {
    foreach (explode('|', $message['reactions_summary']) as $part) {
        $pos = strrpos($part, ':');
        if ($pos !== false) {
            $summary[] = [substr($part, 0, $pos), (int) substr($part, $pos + 1)];
        }
    }
}
?>
<div class="msg-row <?= $isMe ? 'me' : 'him' ?>" data-message-id="<?= (int)$message['id'] ?>">
    <?php if (!$isMe): ?>
        <img class="message-avatar" src="uploads/profiles/<?= e($selectedChat['profile_photo'] ?: 'default.png') ?>" alt="Friend">
    <?php endif; ?>

    <div class="msg-wrap">
        <?php if (!$isDeleted): ?>
            <button class="mobile-msg-menu-btn" type="button" title="Message actions" aria-label="Message actions">⋯</button>
        <?php endif; ?>
        <?php if ($isMe && !$isDeleted): ?>
            <button class="delete-msg-btn" data-message-id="<?= (int)$message['id'] ?>" title="Delete for everyone">×</button>
        <?php endif; ?>

        <?php if (!$isDeleted): ?>
            <div class="msg-actions">
                <button class="reply-btn" data-message-id="<?= (int)$message['id'] ?>" data-message-body="<?= e($message['body'] ?? '') ?>" data-message-type="<?= e($message['message_type'] ?? 'text') ?>" title="Reply">↩</button>
                <?php if ($isMe && (($message['message_type'] ?? 'text') === 'text')): ?>
                    <button class="edit-msg-btn" data-message-id="<?= (int)$message['id'] ?>" data-message-body="<?= e($message['body'] ?? '') ?>" title="Edit">✎</button>
                <?php endif; ?>
                <?php
                    $pinActive = ((int)($message['is_pinned'] ?? 0) === 1) ? 'active' : '';
                    $pinTitle = $pinActive ? 'Unpin message' : 'Pin message';
                    $starActive = ((int)($message['is_starred'] ?? 0) === 1) ? 'active' : '';
                    $starTitle = $starActive ? 'Unstar message' : 'Star message';
                ?>
                <button class="pin-msg-btn <?= e($pinActive) ?>" data-message-id="<?= (int)$message['id'] ?>" title="<?= e($pinTitle) ?>">📌</button>
                <button class="star-msg-btn <?= e($starActive) ?>" data-message-id="<?= (int)$message['id'] ?>" title="<?= e($starTitle) ?>">⭐</button>
                <button class="forward-msg-btn" data-source-type="private" data-message-id="<?= (int)$message['id'] ?>" title="Forward">➡</button>
                <button class="report-msg-btn" data-message-id="<?= (int)$message['id'] ?>" title="Report message">⚠️</button>
                <?php foreach ($reactionEmojis as $emoji): ?>
                    <button class="react-btn <?= (($message['my_reaction'] ?? '') === $emoji) ? 'active' : '' ?>" data-message-id="<?= (int)$message['id'] ?>" data-emoji="<?= e($emoji) ?>" title="React"><?= e($emoji) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="msg <?= $isMe ? 'me' : 'him' ?><?= ((int)($message['is_pinned'] ?? 0) === 1) ? ' pinned' : '' ?><?= ((int)($message['is_starred'] ?? 0) === 1) ? ' starred' : '' ?>">
            <?php if ((int)($message['is_pinned'] ?? 0) === 1): ?>
                <div class="pinned-badge">📌 Pinned</div>
            <?php endif; ?>
            <?php if ((int)($message['is_starred'] ?? 0) === 1): ?>
                <div class="starred-badge">⭐ Starred</div>
            <?php endif; ?>
            <?php if ((int)($message['is_forwarded'] ?? 0) === 1): ?>
                <div class="forwarded-badge">➡ Forwarded</div>
            <?php endif; ?>
            <?php if (!empty($message['reply_to_id'])): ?>
                <div class="reply-block">
                    <strong><?= e($message['reply_sender_name'] ?? 'Message') ?></strong>
                    <span><?= e($replyBody) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isDeleted): ?>
                <div class="deleted-message">This message was deleted</div>
            <?php elseif (($message['message_type'] ?? 'text') === 'voice' && !empty($message['file_path'])): ?>
                <audio class="voice-player" controls preload="metadata">
                    <source src="<?= e($message['file_path']) ?>" type="audio/webm">
                    Your browser does not support audio playback.
                </audio>
                <?php if (!empty($message['body'])): ?>
                    <div class="image-caption"><?= nl2br(e($message['body'])) ?></div>
                <?php endif; ?>
            <?php elseif (($message['message_type'] ?? 'text') === 'image' && !empty($message['file_path'])): ?>
                <a href="<?= e($message['file_path']) ?>" target="_blank">
                    <img class="chat-image" src="<?= e($message['file_path']) ?>" alt="Image message">
                </a>
                <?php if (!empty($message['body'])): ?>
                    <div class="image-caption"><?= nl2br(e($message['body'])) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div><?= nl2br(e($message['body'])) ?></div>
            <?php endif; ?>

            <div class="msg-time"><?= e($message['created_at']) ?><?= !empty($message['edited_at']) ? ' · edited' : '' ?></div>
        </div>

        <?php if (!empty($summary)): ?>
            <div class="reaction-summary">
                <?php foreach ($summary as [$emoji, $count]): ?>
                    <button class="reaction-chip <?= (($message['my_reaction'] ?? '') === $emoji) ? 'active' : '' ?>" data-message-id="<?= (int)$message['id'] ?>" data-emoji="<?= e($emoji) ?>"><?= e($emoji) ?> <?= (int)$count ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($isMe && (int)$message['id'] === $lastMyMessageId): ?>
            <?php
                $receiptClass = !empty($message['read_at']) || (int)($message['is_seen'] ?? 0) === 1 ? 'read' : (!empty($message['delivered_at']) ? 'delivered' : 'sent');
                $receiptText = $receiptClass === 'read' ? '✓✓ Read' : ($receiptClass === 'delivered' ? '✓✓ Delivered' : '✓ Sent');
            ?>
            <div class="seen-status receipt-status <?= e($receiptClass) ?>">
                <?= e($receiptText) ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isMe): ?>
        <img class="message-avatar" src="uploads/profiles/<?= e($user['profile_photo'] ?: 'default.png') ?>" alt="Me">
    <?php endif; ?>
</div>
