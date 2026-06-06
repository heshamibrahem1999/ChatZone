<div class="chat-header">
    <div class="chat-header-left">
        <button class="mobile-menu-btn js-open-sidebar" type="button" id="openFriends"
            onclick="window.czOpenSidebar && window.czOpenSidebar(); return false;">☰</button>

        <div class="avatar-wrap">
            <img class="avatar" src="uploads/profiles/<?= e($selectedChat['profile_photo'] ?: 'default.png') ?>"
                alt="Friend">
            <span id="chatHeaderStatusDot"
                class="status-dot <?= ((int)$selectedChat['is_online'] === 1) ? 'online' : 'offline' ?>"
                data-presence="<?= ((int)$selectedChat['is_online'] === 1) ? 'online' : 'offline' ?>"></span>
        </div>

        <div class="friend-info">
            <div class="friend-title">
                <?= e(trim($selectedChat['first_name'] . ' ' . $selectedChat['last_name'])) ?>
            </div>
            <?php
                $selectedPresenceText = ((int)($selectedChat['is_online'] ?? 0) === 1)
                    ? (($user['language'] === 'Arabic') ? 'متصل الآن' : (($user['language'] === 'French') ? 'En ligne' : 'Online'))
                    : last_seen_text($selectedChat['last_active_at'] ?? null, $user['language']);
            ?>
            <div class="status-text" id="typingStatus" data-default-status="<?= e($selectedPresenceText) ?>">
                <?= e($selectedPresenceText) ?>
            </div>

            <?php if (!empty(trim($selectedChat['about'] ?? ''))): ?>
            <div class="friend-about" title="<?= e($selectedChat['about']) ?>">
                <?= e($selectedChat['about']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="chat-header-actions compact-actions">
        <button class="header-icon-btn" type="button" id="openMessageSearch" title="Search messages"
            aria-label="Search messages"><img src="assets/img/search.png" width="35" height="35" alt="🔍" /></button>

        <div class="chat-more-wrap">
            <button class="header-icon-btn chat-more-btn" type="button" id="chatMoreBtn" title="More options"
                aria-label="More options">⋮</button>

            <div class="chat-more-menu" id="chatMoreMenu" aria-hidden="true">
                <form class="more-menu-form mute-form" method="post" action="mute_chat.php">
                    <input type="hidden" name="friendship_id" value="<?= (int)$selectedChat['friendship_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <?php if ((int)($selectedChat['is_muted'] ?? 0) === 1): ?>
                    <input type="hidden" name="duration" value="unmute">
                    <button class="more-menu-item" type="submit"><img src="assets/img/notification.png" width="35"
                            height="35" alt="🔔" /> Unmute chat</button>
                    <?php else: ?>
                    <label class="more-menu-label">Mute duration</label>
                    <div class="more-menu-row">
                        <select name="duration" title="Mute duration" class="mute-select">
                            <option value="8h">8 hours</option>
                            <option value="1w">1 week</option>
                            <option value="forever">Forever</option>
                        </select>
                        <button class="more-menu-item inline" type="submit"><img src="assets/img/mute.png" width="35"
                                height="35" alt="🔕 Mute" /></button>
                    </div>
                    <?php endif; ?>
                </form>

                <a class="more-menu-item"
                    href="export_chat.php?friendship_id=<?= (int)$selectedChat['friendship_id'] ?>"><img
                        src="assets/img/export.png" width="35" height="35" alt="⬇️" /> Export chat</a>

                <form class="more-menu-form" method="post" action="archive_chat.php"
                    onsubmit="return confirm('Archive this chat? You can restore it from Archived Chats.')">
                    <input type="hidden" name="friendship_id" value="<?= (int)$selectedChat['friendship_id'] ?>">
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="more-menu-item" type="submit"><img src="assets/img/archive.png" width="35"
                            height="35" alt="📦" /> Archive chat</button>
                </form>

                <form class="more-menu-form" method="post" action="clear_chat.php"
                    onsubmit="return confirm('Clear this chat history for you only? The other user will still keep their messages.')">
                    <input type="hidden" name="friendship_id" value="<?= (int)$selectedChat['friendship_id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="more-menu-item" type="submit"><img src="assets/img/clear.png" width="35" height="35"
                            alt="🧹" /> Clear chat</button>
                </form>

                <button class="more-menu-item" type="button" id="reportUserBtn"
                    data-friend-id="<?= (int)$selectedChat['friend_id'] ?>"><img src="assets/img/report.png" width="35"
                        height="35" alt="⚠️" /> Report user</button>
                <button class="more-menu-item block-user-btn <?= $blockStatus['i_blocked_them'] ? 'active' : '' ?>"
                    type="button" id="blockUserBtn" data-friend-id="<?= (int)$selectedChat['friend_id'] ?>"
                    data-blocked="<?= $blockStatus['i_blocked_them'] ? '1' : '0' ?>">
                    <?= $blockStatus['i_blocked_them'] ? '<img src="assets/img/unblock.png" width="35" height="35" alt="✅" /> Unblock user' : '<img src="assets/img/block.png" width="35" height="35" alt="🚫" /> Block user' ?>
                </button>
            </div>
        </div>
    </div>
</div>