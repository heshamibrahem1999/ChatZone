<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$groupId = (int)($_GET['id'] ?? $_POST['group_id'] ?? 0);

$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
if (!$group) { http_response_code(403); die('Access denied.'); }
$isAdmin = ($group['role'] === 'admin');
$error = '';
$success = '';

function redirect_group_members($groupId) {
    header('Location: group_members.php?id=' . (int)$groupId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $memberId = (int)($_POST['user_id'] ?? 0);

    try {
        if ($action === 'leave') {
            
            $adminStmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND role = 'admin' AND user_id <> ?");
            $adminStmt->execute([$groupId, $userId]);
            $otherAdmins = (int)$adminStmt->fetchColumn();

            if ($isAdmin && $otherAdmins < 1) {
                $error = 'You must promote another admin before leaving this group.';
            } else {
                $del = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                $del->execute([$groupId, $userId]);
                header('Location: chat.php');
                exit;
            }
        } elseif ($isAdmin) {
            if ($action === 'rename') {
                $newName = trim($_POST['name'] ?? '');
                if ($newName === '') {
                    $error = 'Group name cannot be empty.';
                } else {
                    $up = $pdo->prepare("UPDATE `groups` SET name = ? WHERE id = ?");
                    $up->execute([$newName, $groupId]);
                    redirect_group_members($groupId);
                }
            } elseif ($action === 'add' && $memberId > 0) {
                $ins = $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())");
                $ins->execute([$groupId, $memberId]);
                redirect_group_members($groupId);
            } elseif ($action === 'remove' && $memberId > 0 && $memberId !== $userId) {
                $roleStmt = $pdo->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
                $roleStmt->execute([$groupId, $memberId]);
                $targetRole = $roleStmt->fetchColumn();

                if ($targetRole === 'admin') {
                    $adminStmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND role = 'admin'");
                    $adminStmt->execute([$groupId]);
                    if ((int)$adminStmt->fetchColumn() <= 1) {
                        $error = 'Cannot remove the last admin.';
                    } else {
                        $del = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                        $del->execute([$groupId, $memberId]);
                        redirect_group_members($groupId);
                    }
                } else {
                    $del = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                    $del->execute([$groupId, $memberId]);
                    redirect_group_members($groupId);
                }
            } elseif ($action === 'promote' && $memberId > 0) {
                $up = $pdo->prepare("UPDATE group_members SET role = 'admin' WHERE group_id = ? AND user_id = ?");
                $up->execute([$groupId, $memberId]);
                redirect_group_members($groupId);
            } elseif ($action === 'demote' && $memberId > 0 && $memberId !== $userId) {
                $adminStmt = $pdo->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ? AND role = 'admin'");
                $adminStmt->execute([$groupId]);
                if ((int)$adminStmt->fetchColumn() <= 1) {
                    $error = 'Cannot demote the last admin.';
                } else {
                    $up = $pdo->prepare("UPDATE group_members SET role = 'member' WHERE group_id = ? AND user_id = ?");
                    $up->execute([$groupId, $memberId]);
                    redirect_group_members($groupId);
                }
            }
        } else {
            $error = 'Only group admins can manage members.';
        }
    } catch (Throwable $e) {
        $error = 'Group action failed: ' . $e->getMessage();
    }
}


$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
$isAdmin = ($group && $group['role'] === 'admin');

$membersStmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email, gm.role FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY FIELD(gm.role,'admin','member'), u.first_name, u.last_name");
$membersStmt->execute([$groupId]);
$members = $membersStmt->fetchAll();

$usersStmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
    FROM friendships f
    JOIN users u ON u.id = CASE
        WHEN f.user_one_id = :uid THEN f.user_two_id
        ELSE f.user_one_id
    END
    WHERE (f.user_one_id = :uid OR f.user_two_id = :uid)
      AND u.id NOT IN (SELECT user_id FROM group_members WHERE group_id = :gid)
    ORDER BY u.first_name, u.last_name
");
$usersStmt->execute(['uid' => $userId, 'gid' => $groupId]);
$available = $usersStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Group Members</title>
<link rel="stylesheet" href="assets/css/chat.css?v=20260601-groupmembers-1">
<link rel="stylesheet" href="assets/css/extracted/public__group_members.css">
<script src="assets/js/extracted/public__group_members-1.js"></script>
</head>
<body class="group-members-page">
<div class="gm-shell">
    <div class="gm-header">
        <div class="gm-title">
            <div class="gm-avatar">👥</div>
            <div>
                <h1><?= e($group['name']) ?> Members</h1>
                <div class="gm-sub"><?= count($members) ?> member(s) · <?= $isAdmin ? 'You are admin' : 'Member view' ?></div>
            </div>
        </div>
        <div class="gm-top-actions">
            <a class="gm-link" href="group.php?id=<?= $groupId ?>">← Back to group</a>
            <a class="gm-link" href="chat.php">Chat</a>
        </div>
    </div>

    <?php if ($error): ?><div class="gm-alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="gm-alert success"><?= e($success) ?></div><?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="gm-card">
        <h3>Rename group</h3>
        <form method="post" class="gm-grid">
            <input type="hidden" name="group_id" value="<?= $groupId ?>">
            <input type="hidden" name="action" value="rename">
            <input class="gm-input" type="text" name="name" value="<?= e($group['name']) ?>" required>
            <button class="gm-btn" type="submit">Save</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="gm-card">
        <h3>Members</h3>
        <?php foreach ($members as $m): ?>
            <?php $fullName = trim($m['first_name'].' '.$m['last_name']); $initial = strtoupper(substr($fullName ?: $m['email'], 0, 1)); ?>
            <div class="gm-member">
                <div class="gm-member-info">
                    <div class="gm-person"><?= e($initial) ?></div>
                    <div style="min-width:0;">
                        <div class="gm-name"><?= e($fullName ?: $m['email']) ?></div>
                        <div class="gm-email"><?= e($m['email']) ?></div>
                        <span class="gm-role"><?= e($m['role']) ?></span>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <div class="gm-actions">
                    <?php if ($m['role'] !== 'admin'): ?>
                        <form method="post"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="promote"><button class="gm-btn secondary">Make admin</button></form>
                    <?php elseif ((int)$m['id'] !== $userId): ?>
                        <form method="post"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="demote"><button class="gm-btn secondary">Remove admin</button></form>
                    <?php endif; ?>
                    <?php if ((int)$m['id'] !== $userId): ?>
                        <form method="post" onsubmit="return confirm('Remove this member from the group?');"><input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="remove"><button class="gm-btn danger">Remove</button></form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($isAdmin): ?>
    <div class="gm-card">
        <h3>Add member</h3>
        <?php if ($available): ?>
        <form method="post" class="gm-grid">
            <input type="hidden" name="group_id" value="<?= $groupId ?>"><input type="hidden" name="action" value="add">
            <select class="gm-select" name="user_id" required>
                <?php foreach ($available as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e(trim($u['first_name'].' '.$u['last_name']).' — '.$u['email']) ?></option><?php endforeach; ?>
            </select>
            <button class="gm-btn" type="submit">Add</button>
        </form>
        <?php else: ?><p class="gm-muted">No friends available to add.</p><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="gm-card">
        <h3>Leave group</h3>
        <p class="gm-muted">If you are the only admin, promote another admin first.</p>
        <form method="post" onsubmit="return confirm('Leave this group?');">
            <input type="hidden" name="group_id" value="<?= $groupId ?>">
            <input type="hidden" name="action" value="leave">
            <button class="gm-btn danger">Leave Group</button>
        </form>
    </div>
</div>
</body>
</html>
