<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';
require_once __DIR__ . '/../includes/activity.php';
$user = cz_admin_require($pdo);
$userId = (int)$user['id'];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(160) NOT NULL,
        body TEXT NOT NULL,
        type ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_announcements_active (is_active, created_at)
    )");
} catch (Throwable $e) {}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = 'Invalid security token. Refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'create') {
                $title = trim((string)($_POST['title'] ?? ''));
                $body = trim((string)($_POST['body'] ?? ''));
                $type = (string)($_POST['type'] ?? 'info');
                if (!in_array($type, ['info','success','warning','danger'], true)) { $type = 'info'; }
                if ($title === '' || $body === '') {
                    $flash = 'Title and body are required.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO announcements (title, body, type, is_active, created_by) VALUES (?, ?, ?, 1, ?)');
                    $stmt->execute([$title, $body, $type, $userId]);
                    $flash = 'Announcement created.';
                    cz_activity_log($pdo, $userId, 'announcement_create', 'announcement', (int)$pdo->lastInsertId(), $title);
                }
            } elseif ($action === 'toggle') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('UPDATE announcements SET is_active = 1 - is_active WHERE id = ?');
                $stmt->execute([$id]);
                $flash = 'Announcement status updated.';
                cz_activity_log($pdo, $userId, 'announcement_toggle', 'announcement', $id, 'Toggled announcement visibility');
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
                $stmt->execute([$id]);
                $flash = 'Announcement deleted.';
                cz_activity_log($pdo, $userId, 'announcement_delete', 'announcement', $id, 'Deleted announcement');
            }
        } catch (Throwable $e) {
            $flash = 'Error: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->query("SELECT a.*, u.first_name, u.last_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by ORDER BY a.created_at DESC, a.id DESC");
$announcements = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__admin_announcements.css">
</head>

<body>
    <main class="wrap">
        <div class="top">
            <div>
                <h1>📢 Announcements</h1>
                <p class="muted">Post site-wide messages shown at the top of ChatZone.</p>
            </div>
            <div><a class="btn btn-light" href="admin_dashboard.php">Dashboard</a> <a class="btn"
                    href="chat.php">Chat</a></div>
        </div>
        <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
        <section class="card">
            <h2>Create announcement</h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                <input type="text" name="title" maxlength="160" placeholder="Title" required>
                <textarea name="body" placeholder="Announcement message" required></textarea>
                <select name="type">
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Danger</option>
                </select>
                <button class="btn" type="submit">Publish</button>
            </form>
        </section>
        <section class="card">
            <h2>All announcements</h2>
            <?php if (empty($announcements)): ?><p class="muted">No announcements yet.</p><?php endif; ?>
            <?php foreach ($announcements as $a): ?>
            <div class="ann">
                <div>
                    <b><?= e($a['title']) ?></b>
                    <span class="badge"><?= e($a['type']) ?></span>
                    <span
                        class="badge <?= (int)$a['is_active'] === 1 ? 'active' : 'off' ?>"><?= (int)$a['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                    <p><?= nl2br(e($a['body'])) ?></p>
                    <div class="muted">By
                        <?= e(trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?: 'Admin') ?> ·
                        <?= e($a['created_at']) ?></div>
                </div>
                <div class="actions">
                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input
                            type="hidden" name="action" value="toggle"><input type="hidden" name="id"
                            value="<?= (int)$a['id'] ?>"><button class="btn btn-light"
                            type="submit"><?= (int)$a['is_active'] === 1 ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" onsubmit="return confirm('Delete this announcement?')"><input type="hidden"
                            name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                            value="delete"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button
                            class="btn btn-danger" type="submit">Delete</button></form>
                </div>
            </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>

</html>