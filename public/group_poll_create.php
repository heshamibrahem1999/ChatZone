<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$groupId = (int)($_GET['id'] ?? $_POST['group_id'] ?? 0);

$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
if (!$group) { http_response_code(403); die('Access denied or group not found.'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $rawOptions = $_POST['options'] ?? [];
    $options = [];
    foreach ($rawOptions as $opt) {
        $opt = trim((string)$opt);
        if ($opt !== '' && !in_array($opt, $options, true)) { $options[] = $opt; }
    }
    if ($question === '') {
        $error = 'Question is required.';
    } elseif (count($options) < 2) {
        $error = 'Add at least 2 options.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO group_polls (group_id, created_by, question) VALUES (?, ?, ?)")->execute([$groupId, $userId, $question]);
            $pollId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO group_poll_options (poll_id, option_text) VALUES (?, ?)");
            foreach ($options as $opt) { $ins->execute([$pollId, $opt]); }
            $pdo->commit();
            header('Location: group.php?id=' . $groupId . '#poll-' . $pollId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = 'Failed to create poll: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title>Create Poll</title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260530-cachefix-1">
    <link rel="stylesheet" href="assets/css/extracted/public__group_poll_create.css">
</head>

<body>
    <div class="wrap">
        <h2>📊 Create poll in <?= e($group['name']) ?></h2>
        <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="group_id" value="<?= $groupId ?>">
            <div class="field"><label>Question</label><input name="question" maxlength="255" required
                    placeholder="What should we choose?"></div>
            <div id="options">
                <div class="field"><label>Option 1</label><input name="options[]" maxlength="255" required></div>
                <div class="field"><label>Option 2</label><input name="options[]" maxlength="255" required></div>
                <div class="field"><label>Option 3</label><input name="options[]" maxlength="255"></div>
                <div class="field"><label>Option 4</label><input name="options[]" maxlength="255"></div>
            </div>
            <button class="btn" type="submit">Create Poll</button>
            <a class="btn btn-secondary" href="group.php?id=<?= $groupId ?>">Cancel</a>
        </form>
    </div>
</body>

</html>