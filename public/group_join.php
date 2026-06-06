<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
$token = trim($_GET['token'] ?? '');
$error = '';
$group = null;

if ($token === '') {
    $error = 'Invalid invite link.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `groups` WHERE invite_token = ? AND invite_enabled = 1 LIMIT 1");
        $stmt->execute([$token]);
        $group = $stmt->fetch();
        if (!$group) {
            $error = 'Invite link is invalid or disabled.';
        }
    } catch (Throwable $e) {
        $error = 'Invite system is not ready. Import the invite SQL first.';
    }
}

if ($group && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, 'member', NOW())")
            ->execute([(int)$group['id'], $userId]);
        header('Location: group.php?id=' . (int)$group['id']);
        exit;
    } catch (Throwable $e) {
        $error = 'Could not join group: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Join Group</title>
<link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
<link rel="stylesheet" href="assets/css/extracted/public__group_join.css">
</head>
<body class="settings-page">
<div class="join-card">
<?php if ($error): ?>
    <h2>Invite Error</h2>
    <div class="error"><?= e($error) ?></div>
    <p><a href="chat.php">Back to chat</a></p>
<?php else: ?>
    <h2>Join group</h2>
    <p>You are joining:</p>
    <h3>👥 <?= e($group['name']) ?></h3>
    <form method="post">
        <button class="btn">Join Group</button>
        <a style="margin-left:10px;" href="chat.php">Cancel</a>
    </form>
<?php endif; ?>
</div>
</body>
</html>
