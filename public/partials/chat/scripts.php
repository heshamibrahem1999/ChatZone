<script type="application/json" id="chatzone-config-data">
{
    "emptyChat": <?= json_encode($labels['empty_chat'] ?? 'No messages yet.') ?>,
    "typingText": <?= json_encode($labels['typing'] ?? 'typing...') ?>,
    "seenText": <?= json_encode($labels['seen'] ?? 'Seen') ?>,
    "sentText": <?= json_encode($labels['sent'] ?? 'Sent') ?>,
    "deleteMessageConfirm": <?= json_encode($labels['delete_message_confirm'] ?? 'Delete this message?') ?>,
    "failedDeleteMessage": <?= json_encode($labels['failed_delete_message'] ?? 'Failed to delete message') ?>,
    "failedSendMessage": <?= json_encode($labels['failed_send_message'] ?? 'Failed to send message') ?>,
    "editMessagePrompt": <?= json_encode($labels['edit_message_prompt'] ?? 'Edit message:') ?>,
    "csrfToken": <?= json_encode(csrf_token()) ?>,
    "onlineText": <?= json_encode($user['language'] === 'Arabic' ? 'متصل الآن' : ($user['language'] === 'French' ? 'En ligne' : 'Online')) ?>,
    "notifySound": <?= json_encode((int)($user['notify_sound'] ?? 1) === 1) ?>,
    "notifyBrowser": <?= json_encode((int)($user['notify_browser'] ?? 1) === 1) ?>,
    "myDisplayName": <?= json_encode(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
}
</script>
<script src="<?= e(cz_asset('assets/js/chat/chat-config.js')) ?>"></script>
<script src="assets/js/extracted/public__partials__chat__scripts-1.js"></script>

<script src="assets/js/emoji-picker.js"></script>
<script src="assets/js/extracted/public__partials__chat__scripts-2.js"></script>
<script src="<?= e(cz_asset('assets/js/ws/ws-client.js')) ?>"></script>
<script src="<?= e(cz_asset('assets/js/presence-heartbeat.js')) ?>"></script>
<script src="assets/js/extracted/public__partials__chat__scripts-3.js"></script>

<script src="<?= e(cz_asset('assets/js/chat/chat-core.js')) ?>"></script>
<script src="<?= e(cz_asset('assets/js/forward-message.js')) ?>"></script>
<script src="<?= e(cz_asset('assets/js/chat/chat-theme.js')) ?>"></script>
<script src="<?= e(cz_asset('assets/js/chat/chat-mobile-actions.js')) ?>"></script>

</body>

</html>