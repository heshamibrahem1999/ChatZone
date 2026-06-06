<?php if ((int)($selectedChat['is_muted'] ?? 0) === 1): ?>
    <div class="muted-chat-notice">🔕 This chat is muted<?= empty($selectedChat['muted_until']) ? ' forever.' : ' until ' . e(date('M j, Y H:i', strtotime($selectedChat['muted_until']))) . '.' ?></div>
<?php endif; ?>

<?php if ($blockStatus['i_blocked_them']): ?>
    <div class="blocked-chat-notice">You blocked this user. Unblock them to send messages again.</div>
<?php elseif ($blockStatus['they_blocked_me']): ?>
    <div class="blocked-chat-notice">You cannot send messages to this user right now.</div>
<?php endif; ?>
