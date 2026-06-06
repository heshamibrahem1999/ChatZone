<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin/admin_common.php';
require_once __DIR__ . '/../includes/activity.php';

$user = cz_admin_require($pdo);
$userId = (int)$user['id'];


try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_banned TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS banned_at DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(128) DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_locked_until DATETIME DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_lock_reason VARCHAR(255) DEFAULT NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS login_locked_by INT UNSIGNED DEFAULT NULL"); } catch (Throwable $e) {}

$stmtAdmin = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
$stmtAdmin->execute([$userId]);
$isAdmin = (int)($stmtAdmin->fetchColumn() ?: 0);

if ($isAdmin !== 1) {
    http_response_code(403);
    echo 'Access denied. Your user id is ' . $userId . '. Set users.is_admin = 1 for this id.';
    exit;
}

$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('admin_users.php');
    $targetId = (int)($_POST['user_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if ($targetId > 0) {
        if ($action === 'make_admin') {
            $stmt = $pdo->prepare('UPDATE users SET is_admin = 1 WHERE id = ?');
            $stmt->execute([$targetId]);
            $notice = 'User promoted to admin.';
            cz_activity_log($pdo, $userId, 'admin_make_admin', 'user', $targetId, 'Promoted user to admin');
        } elseif ($action === 'remove_admin') {
            if ($targetId === $userId) {
                $notice = 'You cannot remove admin from yourself.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET is_admin = 0 WHERE id = ?');
                $stmt->execute([$targetId]);
                $notice = 'Admin removed.';
                cz_activity_log($pdo, $userId, 'admin_remove_admin', 'user', $targetId, 'Removed admin role');
            }
        } elseif ($action === 'ban') {
            if ($targetId === $userId) {
                $notice = 'You cannot ban yourself.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET is_banned = 1, banned_at = NOW(), is_online = 0 WHERE id = ?');
                $stmt->execute([$targetId]);
                $notice = 'User banned.';
                cz_activity_log($pdo, $userId, 'admin_ban_user', 'user', $targetId, 'Banned user');
            }
        } elseif ($action === 'unban') {
            $stmt = $pdo->prepare('UPDATE users SET is_banned = 0, banned_at = NULL WHERE id = ?');
            $stmt->execute([$targetId]);
            $notice = 'User unbanned.';
            cz_activity_log($pdo, $userId, 'admin_unban_user', 'user', $targetId, 'Unbanned user');
        } elseif ($action === 'verify_email') {
            $stmt = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE id = ?');
            $stmt->execute([$targetId]);
            $notice = 'User email verified manually.';
            cz_activity_log($pdo, $userId, 'admin_verify_email', 'user', $targetId, 'Manually verified user email');
        } elseif ($action === 'lock_1h') {
            if ($targetId === $userId) {
                $notice = 'You cannot lock yourself.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET login_locked_until = DATE_ADD(NOW(), INTERVAL 1 HOUR), login_lock_reason = 'Locked by admin', login_locked_by = ? WHERE id = ?");
                $stmt->execute([$userId, $targetId]);
                $notice = 'User locked for 1 hour.';
                cz_activity_log($pdo, $userId, 'admin_lock_user', 'user', $targetId, 'Locked user for 1 hour');
            }
        } elseif ($action === 'lock_24h') {
            if ($targetId === $userId) {
                $notice = 'You cannot lock yourself.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET login_locked_until = DATE_ADD(NOW(), INTERVAL 24 HOUR), login_lock_reason = 'Locked by admin', login_locked_by = ? WHERE id = ?");
                $stmt->execute([$userId, $targetId]);
                $notice = 'User locked for 24 hours.';
                cz_activity_log($pdo, $userId, 'admin_lock_user', 'user', $targetId, 'Locked user for 24 hours');
            }
        } elseif ($action === 'unlock_login') {
            $stmt = $pdo->prepare('UPDATE users SET login_locked_until = NULL, login_lock_reason = NULL, login_locked_by = NULL WHERE id = ?');
            $stmt->execute([$targetId]);
            $pdo->prepare('DELETE FROM login_attempts WHERE user_id = ? OR email = (SELECT email FROM users WHERE id = ? LIMIT 1)')->execute([$targetId, $targetId]);
            $notice = 'User login unlocked and failed attempts cleared.';
            cz_activity_log($pdo, $userId, 'admin_unlock_login', 'user', $targetId, 'Unlocked user login');
        }
    }
}

$q = trim($_GET['q'] ?? '');
$sql = "SELECT id, first_name, last_name, email, language, profile_photo, created_at, last_active_at, is_online,
               COALESCE(is_admin,0) AS is_admin, COALESCE(is_banned,0) AS is_banned, banned_at, email_verified_at, verification_token,
               login_locked_until, login_lock_reason
        FROM users";
$params = [];
if ($q !== '') {
    $sql .= " WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Users - ChatZone</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__admin_users.css">
</head>
<body>
<main class="admin-users-page">
    <div class="admin-top">
        <div>
            <h1>👥 User Management</h1>
            <p>Manage admins and banned users.</p>
        </div>
        <div>
            <a class="admin-btn" href="reports.php">⚠️ Reports</a>
            <a class="admin-btn" href="chat.php">← Back to chat</a>
        </div>
    </div>

    <?php if ($notice): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?>

    <form class="admin-search" method="get">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search users by name or email">
        <button type="submit">Search</button>
        <a class="admin-btn" href="admin_users.php">Reset</a>
    </form>

    <div class="admin-card">
        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th class="hide-sm">Status</th>
                    <th class="hide-sm">Last active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td>
                            <img class="avatar-mini" src="uploads/profiles/<?= e($row['profile_photo'] ?: 'default.png') ?>" alt="">
                            <b><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></b><br>
                            <small><?= e($row['email']) ?></small><br>
                            <?php if ((int)$row['is_admin'] === 1): ?><span class="badge blue">Admin</span><?php endif; ?>
                            <?php if (empty($row['email_verified_at']) && !empty($row['verification_token'])): ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="verify_email">Verify Email</button>
                                </form>
                            <?php endif; ?>

                            <?php if ((int)$row['is_banned'] === 1): ?><span class="badge red">Banned</span><?php endif; ?>
                            <?php if (!empty($row['login_locked_until']) && strtotime($row['login_locked_until']) > time()): ?><span class="badge red">Locked until <?= e($row['login_locked_until']) ?></span><?php endif; ?>
                            <?php if (!empty($row['email_verified_at'])): ?><span class="badge green">Verified</span><?php elseif (!empty($row['verification_token'])): ?><span class="badge red">Unverified</span><?php endif; ?>
                        </td>
                        <td class="hide-sm">
                            <?php if ((int)$row['is_online'] === 1): ?>
                                <span class="badge green">Online</span>
                            <?php else: ?>
                                <span class="badge">Offline</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-sm"><small><?= e($row['last_active_at'] ?? '—') ?></small></td>
                        <td>
                            <?php if ((int)$row['is_admin'] === 1): ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="remove_admin">Remove Admin</button>
                                </form>
                            <?php else: ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="make_admin">Make Admin</button>
                                </form>
                            <?php endif; ?>

                            <?php if (empty($row['email_verified_at']) && !empty($row['verification_token'])): ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="verify_email">Verify Email</button>
                                </form>
                            <?php endif; ?>

                            
                            <?php if (!empty($row['login_locked_until']) && strtotime($row['login_locked_until']) > time()): ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="unlock_login">Unlock Login</button>
                                </form>
                            <?php else: ?>
                                <form class="action-form" method="post" onsubmit="return confirm('Lock this user login for 1 hour?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="lock_1h">Lock 1h</button>
                                </form>
                                <form class="action-form" method="post" onsubmit="return confirm('Lock this user login for 24 hours?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="lock_24h">Lock 24h</button>
                                </form>
                            <?php endif; ?>

                            <?php if ((int)$row['is_banned'] === 1): ?>
                                <form class="action-form" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button name="action" value="unban">Unban</button>
                                </form>
                            <?php else: ?>
                                <form class="action-form" method="post" onsubmit="return confirm('Ban this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
                                    <button class="danger" name="action" value="ban">Ban</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
