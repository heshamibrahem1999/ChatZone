<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email_tokens.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$groupId = (int)($_GET['id'] ?? $_POST['group_id'] ?? 0);


try {
    $pdo->exec("ALTER TABLE `groups` ADD COLUMN invite_token VARCHAR(80) DEFAULT NULL");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE `groups` ADD COLUMN invite_enabled TINYINT(1) NOT NULL DEFAULT 1");
} catch (Throwable $e) {}
try {
    $pdo->exec("ALTER TABLE `groups` ADD UNIQUE KEY unique_group_invite_token (invite_token)");
} catch (Throwable $e) {}

$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
if (!$group) { http_response_code(403); die('Access denied or group not found.'); }
$isAdmin = (($group['role'] ?? '') === 'admin');
$message = '';
$error = '';

function cz_group_make_token(): string {
    return bin2hex(random_bytes(16));
}

if (empty($group['invite_token'])) {
    $token = cz_group_make_token();
    $up = $pdo->prepare("UPDATE `groups` SET invite_token = ?, invite_enabled = 1 WHERE id = ?");
    $up->execute([$token, $groupId]);
    $group['invite_token'] = $token;
    $group['invite_enabled'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!$isAdmin) {
        $error = 'Only group admins can change invite settings.';
    } else {
        try {
            if ($action === 'reset') {
                $token = cz_group_make_token();
                $pdo->prepare("UPDATE `groups` SET invite_token = ?, invite_enabled = 1 WHERE id = ?")->execute([$token, $groupId]);
                $group['invite_token'] = $token;
                $group['invite_enabled'] = 1;
                $message = 'Invite link reset successfully.';
            } elseif ($action === 'disable') {
                $pdo->prepare("UPDATE `groups` SET invite_enabled = 0 WHERE id = ?")->execute([$groupId]);
                $group['invite_enabled'] = 0;
                $message = 'Invite link disabled.';
            } elseif ($action === 'enable') {
                $pdo->prepare("UPDATE `groups` SET invite_enabled = 1 WHERE id = ?")->execute([$groupId]);
                $group['invite_enabled'] = 1;
                $message = 'Invite link enabled.';
            }
        } catch (Throwable $e) {
            $error = 'Invite action failed: ' . $e->getMessage();
        }
    }
}

$inviteLink = cz_base_url() . '/group_join.php?token=' . urlencode((string)$group['invite_token']);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Group Invite</title>
<link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
<link rel="stylesheet" href="assets/css/extracted/public__group_invite.css">
</head>
<body class="settings-page">
<div class="invite-card">
<h2>🔗 Invite to <?= e($group['name']) ?></h2>
<p><a href="group.php?id=<?= $groupId ?>">Back to group</a> · <a href="chat.php">Back to chat</a></p>
<?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<p>Status: <b><?= ((int)($group['invite_enabled'] ?? 1) === 1) ? 'Enabled' : 'Disabled' ?></b></p>
<p class="muted">Anyone logged in with this link can join this group.</p>
<div class="invite-link">
    <input id="inviteLink" value="<?= e($inviteLink) ?>" readonly>
    <button class="btn" type="button" onclick="navigator.clipboard.writeText(document.getElementById('inviteLink').value); this.textContent='Copied';">Copy</button>
</div>
<?php if ($isAdmin): ?>
<div class="actions">
    <form method="post"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="action" value="reset"><button class="btn secondary" onclick="return confirm('Reset invite link? Old link will stop working.')">Reset link</button></form>
    <?php if ((int)($group['invite_enabled'] ?? 1) === 1): ?>
    <form method="post"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="action" value="disable"><button class="btn danger">Disable link</button></form>
    <?php else: ?>
    <form method="post"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="action" value="enable"><button class="btn">Enable link</button></form>
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
</body>
</html>
