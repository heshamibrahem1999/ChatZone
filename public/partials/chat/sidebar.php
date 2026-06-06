<aside class="sidebar">
    <div class="sidebar-top">
        <div class="me-box">
            <img class="avatar" src="uploads/profiles/<?= e($user['profile_photo'] ?: 'default.png') ?>" alt="Me">
            <div class="me-name"><?= e($fullName) ?></div>
        </div>

        <div class="top-actions">
            <button type="button" class="icon-btn theme-toggle" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode"><img src="assets/img/moon.png" alt="Dark mode" /></button>
            <a class="icon-btn" href="profile.php" title="<?= e($labels['edit_profile']) ?>"><img src="assets/img/setting.png" width="35" height="35" alt="⚙️" /></a>
            <a class="icon-btn" href="notification_settings.php" title="Notifications"><img src="assets/img/notification.png" width="35" height="35" alt="🔔" /></a>
            <a class="icon-btn" href="logout.php" title="<?= e($labels['logout']) ?>"><img src="assets/img/logout.png" width="35" height="35" alt="↪" /></a>
        </div>
    </div>

    <div class="search-box">
        <input type="text" id="chatSearch" placeholder="Search or start new chat">
    </div>

    <div class="sidebar-shortcuts">
        <a class="sidebar-shortcut" href="starred.php"><img src="assets/img/favorite.png" width="35" height="35" alt="⭐" /> Starred Messages</a>
        <a class="sidebar-shortcut" href="media_gallery.php"><img src="assets/img/gallery.png" width="35" height="35" alt="🖼️" /> Media Gallery</a>
        <a class="sidebar-shortcut" href="archived_chats.php"><img src="assets/img/archive.png" width="35" height="35" alt="📦" /> Archived Chats</a>
        <a class="sidebar-shortcut" href="muted_chats.php"><img src="assets/img/mute.png" width="35" height="35" alt="🔕" /> Muted Chats</a>
        <a class="sidebar-shortcut" href="scheduled_messages.php"><img src="assets/img/schedule.png" width="35" height="35" alt="⏰" /> Scheduled Messages</a>
        <a class="sidebar-shortcut" href="privacy.php"><img src="assets/img/privacy.png" width="35" height="35" alt="🔐" /> Privacy & Data</a>
        <a class="sidebar-shortcut" href="sessions.php"><img src="assets/img/sessions.png" width="35" height="35" alt="💻" /> Sessions</a>
        <a class="sidebar-shortcut" href="security_2fa.php"><img src="assets/img/two_factor.png" width="35" height="35" alt="🛡️" /> Two-Factor Login</a>
        <a class="sidebar-shortcut" href="group_create.php"><img src="assets/img/group.png" width="35" height="35" alt="👥" /> New Group</a>
        <a class="sidebar-shortcut" href="group_mentions.php"><img src="assets/img/mentions.png" width="35" height="35" alt="🏷️" /> Mentions</a>
                <?php if ((int)($user['is_admin'] ?? 0) === 1): ?>
                    <a class="sidebar-shortcut" href="admin_dashboard.php"><img src="assets/img/dashboard.png" width="35" height="35" alt="📊" /> Dashboard</a>
            <a class="sidebar-shortcut" href="admin_backup.php"><img src="assets/img/backup.png" width="35" height="35" alt="🧰" /> Backup</a>
            <a class="sidebar-shortcut" href="reports.php"><img src="assets/img/report.png" width="35" height="35" alt="⚠️" /> Reports</a>
            <a class="sidebar-shortcut" href="admin_users.php"><img src="assets/img/users.png" width="35" height="35" alt="👥" /> Users</a>
            <a class="sidebar-shortcut" href="admin_announcements.php"><img src="assets/img/announcement.png" width="35" height="35" alt="📢" /> Announcements</a>
            <a class="sidebar-shortcut" href="admin_login_attempts.php"><img src="assets/img/login_attempts.png" width="35" height="35" alt="🛡️" /> Login Attempts</a>
            <a class="sidebar-shortcut" href="admin_health.php"><img src="assets/img/health.png" width="35" height="35" alt="🩺" /> System Health</a>
            <a class="sidebar-shortcut" href="admin_error_logs.php"><img src="assets/img/error_logs.png" width="35" height="35" alt="🧯" /> Error Logs</a>
            <a class="sidebar-shortcut" href="admin_storage.php"><img src="assets/img/storage.png" width="35" height="35" alt="🗂️" /> Storage</a>
            <a class="sidebar-shortcut" href="admin_maintenance.php"><img src="assets/img/maintenance.png" width="35" height="35" alt="🛠️" /> Maintenance</a>
        <?php endif; ?>
    </div>

    <div class="add-friend">
        <form method="post" action="add_friend.php">
            <input type="email" name="email" placeholder="friend@example.com" required>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit">+</button>
        </form>
    </div>

    <div class="chat-list">
        <?php if (empty($chats)): ?>
            <div class="list-empty">No chats yet.</div>
        <?php else: ?>
            <div id="noSearchResults" class="list-empty" style="display:none;">No chats found.</div>

            <?php foreach ($chats as $chat): ?>
                <a class="chat-item <?= ((int)$chat['friendship_id'] === $selectedFriendshipId) ? 'active' : '' ?>"
                   href="chat.php?friendship=<?= (int)$chat['friendship_id'] ?>"
                   data-friendship-id="<?= (int)$chat['friendship_id'] ?>"
                   data-search="<?= e(strtolower(trim($chat['first_name'] . ' ' . $chat['last_name'] . ' ' . $chat['email']))) ?>">

                    <div class="avatar-wrap">
                        <img class="avatar" src="uploads/profiles/<?= e($chat['profile_photo'] ?: 'default.png') ?>" alt="Friend">
                        <span class="status-dot chat-list-status-dot <?= ((int)$chat['is_online'] === 1) ? 'online' : 'offline' ?>" data-presence="<?= ((int)$chat['is_online'] === 1) ? 'online' : 'offline' ?>" data-friendship-id="<?= (int)$chat['friendship_id'] ?>"></span>
                    </div>

                    <div class="chat-meta">
                        <div class="chat-row">
                            <div class="chat-name"><?= e(trim($chat['first_name'] . ' ' . $chat['last_name'])) ?></div>

                            <?php if ((int)$chat['unread_count'] > 0): ?>
                                <span class="unread-badge"><?= (int)$chat['unread_count'] ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="status-text">
                            <?= e(last_seen_text($chat['last_active_at'] ?? null, $user['language'])) ?>
                        </div>

                        <?php if (!empty(trim($chat['about'] ?? ''))): ?>
                            <div class="friend-about-list" title="<?= e($chat['about']) ?>">
                                <?= e($chat['about']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ((int)($chat['is_muted'] ?? 0) === 1): ?>
                            <div class="muted-list-badge"><img src="assets/img/mute.png" width="35" height="35" alt="🔕" /> Muted<?= empty($chat['muted_until']) ? ' forever' : ' until ' . e(date('M j, H:i', strtotime($chat['muted_until']))) ?></div>
                        <?php endif; ?>

                        <?php if ((int)($chat['i_blocked_them'] ?? 0) === 1): ?>
                            <div class="blocked-list-badge"><img src="assets/img/block.png" width="35" height="35" alt="🚫" /> Blocked by you</div>
                        <?php elseif ((int)($chat['they_blocked_me'] ?? 0) === 1): ?>
                            <div class="blocked-list-badge"><img src="assets/img/block.png" width="35" height="35" alt="🚫" /> Blocked</div>
                        <?php endif; ?>

                        <div class="last-msg"><?= e($chat['last_message'] ?? '') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php
    $groupStmt = $pdo->prepare("
        SELECT g.id, g.name, g.avatar,
               (SELECT gm.body FROM group_messages gm WHERE gm.group_id = g.id ORDER BY gm.created_at DESC, gm.id DESC LIMIT 1) AS last_message,
               (SELECT gm.created_at FROM group_messages gm WHERE gm.group_id = g.id ORDER BY gm.created_at DESC, gm.id DESC LIMIT 1) AS last_time
        FROM `groups` g
        JOIN group_members mem ON mem.group_id = g.id
        WHERE mem.user_id = ?
        ORDER BY COALESCE(last_time, g.created_at) DESC
    ");
    try { $groupStmt->execute([$userId]); $sidebarGroups = $groupStmt->fetchAll(); } catch (Throwable $e) { $sidebarGroups = []; }
    ?>
    <?php if (!empty($sidebarGroups)): ?>
        <div class="sidebar-section-title">Groups</div>
        <div class="chat-list group-list">
            <?php foreach ($sidebarGroups as $g): ?>
                <a class="chat-item" href="group.php?id=<?= (int)$g['id'] ?>" data-search="<?= e(strtolower($g['name'])) ?>">
                    <div class="avatar-wrap"><img class="avatar" src="uploads/profiles/groupdefault.png ?>" alt="Group"></div>
                    <div class="chat-meta">
                        <div class="chat-row"><div class="chat-name">👥 <?= e($g['name']) ?></div></div>
                        <div class="last-msg"><?= e($g['last_message'] ?? '') ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</aside>
