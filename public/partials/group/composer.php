  <div class="reply-preview" id="groupReplyPreview"><span></span><button type="button" id="cancelGroupReply">×</button></div>
  <div class="group-send-wrap">
    <div id="mentionPicker" class="mention-picker"></div>
    <div id="groupAttachmentPreview" class="group-attachment-preview" aria-live="polite">
      <img id="groupAttachmentPreviewImg" alt="Selected image preview">
      <div class="preview-info">
        <div id="groupAttachmentPreviewTitle" class="preview-title">Selected attachment</div>
        <div id="groupAttachmentPreviewSub" class="preview-sub">Ready to send</div>
      </div>
      <button id="groupAttachmentPreviewRemove" class="preview-remove" type="button" title="Remove attachment">×</button>
    </div>
    <form class="group-send" method="post" action="group_send.php" enctype="multipart/form-data">
      <input type="hidden" name="group_id" value="<?= $groupId ?>">
      <input type="hidden" name="reply_to_id" id="groupReplyToId" value="">
      <input id="groupMessageInput" type="text" name="body" placeholder="Type a group message or attach media..." autocomplete="off">
      <label class="file-label">📎<input id="groupAttachmentInput" type="file" name="attachment" accept="image/*,audio/*,.pdf,.doc,.docx,.zip,.txt" style="display:none"></label>
      <button type="submit">➤</button>
    </form>
  </div>
