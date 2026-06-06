<div
    class="msg-list initial-bottom-pending"
    id="msgList"
    data-friendship-id="<?= (int)$selectedChat['friendship_id'] ?>"
    data-friend-id="<?= (int)$selectedChat['friend_id'] ?>"
    data-my-photo="<?= e($user['profile_photo'] ?: 'default.png') ?>"
    data-friend-photo="<?= e($selectedChat['profile_photo'] ?: 'default.png') ?>"
    data-i-blocked-them="<?= $blockStatus['i_blocked_them'] ? '1' : '0' ?>"
    data-they-blocked-me="<?= $blockStatus['they_blocked_me'] ? '1' : '0' ?>"
    data-page-limit="<?= (int)($messagePageLimit ?? 50) ?>"
    data-has-more="<?= (count($messages ?? []) >= (int)($messagePageLimit ?? 50)) ? '1' : '0' ?>"
>
    <div class="older-messages-loader" id="olderMessagesLoader" data-loading="0"><button type="button" id="loadOlderMessagesBtn">Load older messages</button><span class="older-loading-text" style="display:none;">Loading older messages...</span></div>
    <?php if (empty($messages)): ?>
        <div class="empty-state"><?= e($labels['empty_chat']) ?></div>
    <?php else: ?>
        <?php
        $lastMyMessageId = null;
        foreach ($messages as $m) {
            if ((int)$m['sender_id'] === $userId) {
                $lastMyMessageId = (int)$m['id'];
            }
        }
        ?>

        <?php foreach ($messages as $message): ?>
            <?php include __DIR__ . '/message_bubble.php'; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
