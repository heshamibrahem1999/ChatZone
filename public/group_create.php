<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$error = '';


$usersStmt = $pdo->prepare("\n    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.profile_photo\n    FROM friendships f\n    JOIN users u ON u.id = CASE\n        WHEN f.user_one_id = :uid THEN f.user_two_id\n        ELSE f.user_one_id\n    END\n    WHERE (f.user_one_id = :uid OR f.user_two_id = :uid)\n      AND u.id <> :uid\n      AND COALESCE(u.deleted_at, '') = ''\n      AND COALESCE(u.is_banned, 0) = 0\n    ORDER BY u.first_name, u.last_name, u.email\n");
$usersStmt->execute(['uid' => $userId]);
$allUsers = $usersStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $members = array_map('intval', $_POST['members'] ?? []);
    $members = array_values(array_unique(array_filter($members, fn($id) => $id > 0 && $id !== $userId)));

    
    $allowedIds = array_map(fn($u) => (int)$u['id'], $allUsers);
    $members = array_values(array_intersect($members, $allowedIds));

    if ($name === '') {
        $error = 'Group name is required.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO `groups` (name, created_by, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $userId]);
            $groupId = (int)$pdo->lastInsertId();

            $memberInsert = $pdo->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role, joined_at) VALUES (?, ?, ?, NOW())");
            $memberInsert->execute([$groupId, $userId, 'admin']);
            foreach ($members as $memberId) {
                $memberInsert->execute([$groupId, $memberId, 'member']);
            }

            $pdo->commit();
            header('Location: group.php?id=' . $groupId);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Failed to create group: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Group</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260531-groupcreatefix-1">
    <script src="assets/js/extracted/public__group_create-1.js"></script>
    <link rel="stylesheet" href="assets/css/extracted/public__group_create.css">
</head>

<body class="group-create-page">
    <script src="assets/js/extracted/public__group_create-2.js"></script>
    <div class="group-create-wrap">
        <div class="group-create-card">
            <h2>👥 Create Group</h2>
            <?php if ($error): ?><div class="error-box"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
                <label>Group name</label>
                <input type="text" name="name" required placeholder="My group">

                <h3>Add friends</h3>
                <?php if (!$allUsers): ?>
                <div class="empty-friends">No friends found yet. Add friends first, then create a group.</div>
                <?php else: ?>
                <div class="members-box">
                    <?php foreach ($allUsers as $u): ?>
                    <label class="member-row">
                        <input type="checkbox" name="members[]" value="<?= (int)$u['id'] ?>">
                        <span>
                            <span class="member-name"><?= e(trim($u['first_name'].' '.$u['last_name'])) ?></span><br>
                            <span class="member-email"><?= e($u['email']) ?></span>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="form-actions">
                    <button type="submit">Create Group</button>
                    <a href="chat.php">Back</a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>