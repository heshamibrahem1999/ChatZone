<form class="send-row <?= $blockStatus['is_blocked'] ? 'blocked' : '' ?>" id="sendForm" method="post" action="ajax_send_message.php" enctype="multipart/form-data" onsubmit="return window.ChatZoneSubmitMessage ? window.ChatZoneSubmitMessage(event) : true;" <?= $blockStatus['is_blocked'] ? 'style="display:none;"' : '' ?>>
    <input type="hidden" name="friend_id" id="friendId" value="<?= (int)$selectedChat['friend_id'] ?>">
    <input type="hidden" name="reply_to_id" id="replyToId" value="">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <div class="reply-preview" id="replyPreview">
        <div>
            <strong>Replying to</strong>
            <span id="replyPreviewText"></span>
        </div>
        <button type="button" id="cancelReply">×</button>
    </div>

    <?php require __DIR__ . '/emoji_panel.php'; ?>
    <button class="attach-btn" type="button" id="attachBtn">📎</button>
    <input type="file" name="image" id="imageInput" accept="image/*" hidden>
    <button class="voice-btn" type="button" id="voiceBtn" title="Record voice">🎙</button>
    <span class="voice-status" id="voiceStatus"></span>

    <input type="text" name="body" id="messageInput" placeholder="<?= e($labels['message_placeholder']) ?>" autocomplete="off">

    <button class="schedule-btn" type="button" id="scheduleBtn" title="Schedule message" onclick="event.preventDefault(); event.stopPropagation(); if (window.ChatZoneScheduleMessage) { window.ChatZoneScheduleMessage(); } else { alert('Schedule is still loading. Press Ctrl+F5 then try again.'); }"><img src="assets/img/schedule.png" width="35" height="35" alt="⏰" /></button>
    <button type="submit">➤</button>

    <div class="image-preview" id="imagePreview">
        <img id="previewImg" src="" alt="Preview">
        <span id="previewName"></span>
        <button type="button" id="removeImage">Remove</button>
    </div>
</form>
