  <div class="group-head">
    <div class="group-title-wrap">
      <div class="group-avatar">👥</div>
      <div class="group-title">
        <h2><?= e($group['name']) ?></h2>
        <div class="members" id="groupMembersText" data-members-list="<?= e(implode(', ', array_map(fn($m)=>trim($m['first_name'].' '.$m['last_name']), $members))) ?>"><?= count($members) ?> members: <?= e(implode(', ', array_map(fn($m)=>trim($m['first_name'].' '.$m['last_name']), $members))) ?></div>
        <div class="group-presence-status" id="groupPresenceStatus">Checking online members...</div>
        <div class="group-typing-status" id="groupTypingStatus" aria-live="polite"></div>
      </div>
    </div>
    <div class="group-head-actions">
      <button class="group-icon-btn group-search-btn" type="button" title="Search" onclick="document.getElementById('groupMessageInput')?.focus();"><img src="assets/img/search.png" width="35" height="35" alt="🔍" /></button>
      <button class="group-icon-btn" id="groupMoreBtn" type="button" title="More">⋮</button>
      <div class="group-more-menu" id="groupMoreMenu">
        <a href="group_poll_create.php?id=<?= $groupId ?>"><img src="assets/img/poll.png" width="35" height="35" alt="📊" /> Create Poll</a>
        <a href="group_media_gallery.php?id=<?= $groupId ?>"><img src="assets/img/gallery.png" width="35" height="35" alt="🖼️" /> Media Gallery</a>
        <a href="group_invite.php?id=<?= $groupId ?>"><img src="assets/img/invite_link.png" width="35" height="35" alt="🔗" /> Invite Link</a>
        <a href="export_group_chat.php?id=<?= $groupId ?>"><img src="assets/img/export.png" width="35" height="35" alt="⬇️" /> Export Group</a>
        <a href="group_members.php?id=<?= $groupId ?>"><img src="assets/img/setting.png" width="35" height="35" alt="⚙️" /> Manage Members</a>
        <a href="chat.php">← Back to Chats</a>
      </div>
    </div>
  </div>
