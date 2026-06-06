<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

$hasGroups = cz_admin_table_exists($pdo, 'groups');
$hasGroupMessages = cz_admin_table_exists($pdo, 'group_messages');
$hasReports = cz_admin_table_exists($pdo, 'reports');

$stats = [
    'users' => cz_admin_count($pdo, 'SELECT COUNT(*) FROM users'),
    'online' => cz_admin_count($pdo, 'SELECT COUNT(*) FROM users WHERE is_online = 1'),
    'banned' => cz_admin_count($pdo, 'SELECT COUNT(*) FROM users WHERE COALESCE(is_banned,0) = 1'),
    'private_messages' => cz_admin_count($pdo, 'SELECT COUNT(*) FROM messages'),
    'voice_messages' => cz_admin_count($pdo, "SELECT COUNT(*) FROM messages WHERE message_type = 'voice'"),
    'image_messages' => cz_admin_count($pdo, "SELECT COUNT(*) FROM messages WHERE message_type = 'image'"),
    'groups' => $hasGroups ? cz_admin_count($pdo, 'SELECT COUNT(*) FROM `groups`') : 0,
    'group_messages' => $hasGroupMessages ? cz_admin_count($pdo, 'SELECT COUNT(*) FROM group_messages') : 0,
    'open_reports' => $hasReports ? cz_admin_count($pdo, "SELECT COUNT(*) FROM reports WHERE status = 'open'") : 0,
];

$recentUsers = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, profile_photo, is_online, last_active_at, created_at, COALESCE(is_banned,0) AS is_banned FROM users ORDER BY id DESC LIMIT 8");
    $recentUsers = $stmt->fetchAll();
} catch (Throwable $e) {}

$recentMessages = [];
try {
    $stmt = $pdo->query("SELECT m.id, m.body, m.message_type, m.file_path, m.created_at, u.first_name, u.last_name
                         FROM messages m
                         LEFT JOIN users u ON u.id = m.sender_id
                         ORDER BY m.created_at DESC, m.id DESC LIMIT 8");
    $recentMessages = $stmt->fetchAll();
} catch (Throwable $e) {}

$recentReports = [];
if ($hasReports) {
    try {
        $stmt = $pdo->query("SELECT r.id, r.reason, r.status, r.created_at, u.first_name, u.last_name
                             FROM reports r
                             LEFT JOIN users u ON u.id = r.reporter_id
                             ORDER BY r.created_at DESC, r.id DESC LIMIT 6");
        $recentReports = $stmt->fetchAll();
    } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__admin_dashboard.css?v=20260606-mobilefix">
</head>
<body>
<main class="dash">
    <div class="dash-top">
        <div><h1>📊 Admin Dashboard</h1><p class="muted">Quick overview of ChatZone activity.</p></div>
        <div class="dash-actions">
            <a class="dash-btn" href="admin_users.php">👥 Users</a>
            <a class="dash-btn" href="reports.php">⚠️ Reports</a>
            <a class="dash-btn" href="admin_announcements.php">📢 Announcements</a>
            <a class="dash-btn" href="admin_backup.php">🧰 Backup</a>
            <a class="dash-btn" href="admin_activity.php">🧾 Logs</a>
            <a class="dash-btn" href="chat.php">← Chat</a>
        </div>
    </div>

    <section class="stats-grid">
        <div class="stat-card"><div>Total users</div><strong><?= (int)$stats['users'] ?></strong></div>
        <div class="stat-card"><div>Online now</div><strong><?= (int)$stats['online'] ?></strong></div>
        <div class="stat-card"><div>Private messages</div><strong><?= (int)$stats['private_messages'] ?></strong></div>
        <div class="stat-card"><div>Groups</div><strong><?= (int)$stats['groups'] ?></strong></div>
        <div class="stat-card"><div>Group messages</div><strong><?= (int)$stats['group_messages'] ?></strong></div>
        <div class="stat-card"><div>Voice messages</div><strong><?= (int)$stats['voice_messages'] ?></strong></div>
        <div class="stat-card"><div>Image messages</div><strong><?= (int)$stats['image_messages'] ?></strong></div>
        <div class="stat-card"><div>Open reports</div><strong><?= (int)$stats['open_reports'] ?></strong></div>
    </section>

    <section class="dash-grid">
        <div class="panel">
            <h2>Newest users</h2>
            <table class="simple-table">
                <thead><tr><th>User</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($recentUsers as $row): ?>
                    <tr>
                        <td><img class="avatar-mini" src="uploads/profiles/<?= e($row['profile_photo'] ?: 'default.png') ?>" alt=""><b><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></b><br><span class="muted"><?= e($row['email']) ?></span></td>
                        <td><?php if ((int)$row['is_banned'] === 1): ?><span class="badge red">Banned</span><?php elseif ((int)$row['is_online'] === 1): ?><span class="badge green">Online</span><?php else: ?><span class="badge">Offline</span><?php endif; ?></td>
                        <td><span class="muted"><?= e($row['created_at'] ?? '') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2>Recent private messages</h2>
            <table class="simple-table">
                <thead><tr><th>Sender</th><th>Message</th><th>Time</th></tr></thead>
                <tbody>
                <?php foreach ($recentMessages as $row): ?>
                    <tr>
                        <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'User') ?></td>
                        <td><span class="badge blue"><?= e($row['message_type']) ?></span><div class="message-preview"><?= e($row['body'] ?: $row['file_path'] ?: '') ?></div></td>
                        <td><span class="muted"><?= e($row['created_at'] ?? '') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel full">
            <h2>Latest reports</h2>
            <?php if (empty($recentReports)): ?>
                <p class="muted">No reports yet.</p>
            <?php else: ?>
                <table class="simple-table">
                    <thead><tr><th>ID</th><th>Reporter</th><th>Reason</th><th>Status</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentReports as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['id'] ?></td>
                            <td><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'User') ?></td>
                            <td><?= e(mb_strimwidth((string)$r['reason'], 0, 100, '...')) ?></td>
                            <td><span class="badge <?= $r['status'] === 'open' ? 'red' : 'green' ?>"><?= e($r['status']) ?></span></td>
                            <td><span class="muted"><?= e($r['created_at']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
