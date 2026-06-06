<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/activity.php';
$user = cz_admin_require($pdo);

$stmt = $pdo->query("SELECT la.*, u.name AS user_name FROM login_attempts la LEFT JOIN users u ON u.id = la.user_id ORDER BY la.created_at DESC LIMIT 200");
$attempts = $stmt->fetchAll();
render_header('Login Attempts');
?>
<div class="chat-shell admin-page">
    <?php include __DIR__ . '/partials/chat/sidebar.php'; ?>
    <main class="chat-main">
        <div class="chat-header">
            <div>
                <h2>Login Attempts</h2>
                <p>Recent success/failure login events</p>
            </div>
        </div>
        <div class="settings-card" style="margin:16px; overflow:auto;">
            <table class="admin-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Email</th>
                        <th>User</th>
                        <th>IP</th>
                        <th>Status</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): ?>
                    <tr>
                        <td><?= e($a['created_at']) ?></td>
                        <td><?= e($a['email'] ?? '') ?></td>
                        <td><?= e($a['user_name'] ?? '-') ?></td>
                        <td><?= e($a['ip_address'] ?? '') ?></td>
                        <td><?= ((int)$a['success'] === 1) ? '✅ Success' : '❌ Failed' ?></td>
                        <td style="max-width:360px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            <?= e($a['user_agent'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php render_footer(); ?>