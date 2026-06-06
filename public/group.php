<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/groups/load_group_data.php';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($group['name']) ?></title>
    <link rel="stylesheet" href="assets/css/chat.css?v=20260602-refactor-v3">
    <link rel="stylesheet" href="assets/css/group-page.css?v=20260604-v16-typing">
</head>

<body>
    <script src="assets/js/extracted/public__group-1.js"></script>
    <?php require_once __DIR__ . '/partials/announcements.php'; ?>
    <div class="group-shell">
        <?php require __DIR__ . '/partials/group/header.php'; ?>
        <?php require __DIR__ . '/partials/group/timeline.php'; ?>
        <?php require __DIR__ . '/partials/group/composer.php'; ?>
    </div>
    <script type="application/json" id="group-mention-members">
    <?= json_encode($mentionMembers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
    <script type="application/json" id="chatzone-group-config">
    {
        "groupId": <?= json_encode((int)$groupId) ?>,
        "myDisplayName": <?= json_encode(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Someone') ?>,
        "csrfToken": <?= json_encode(csrf_token()) ?>,
        "notifyBrowser": <?= json_encode((int)($user['notify_browser'] ?? 1) === 1) ?>
    }
    </script>
    <script src="assets/js/group-config.js"></script>
    <script src="assets/js/ws/ws-defaults.js"></script>
    <script src="assets/js/ws/ws-client.js?v=20260605-v19-push"></script>
    <script src="assets/js/presence-heartbeat.js?v=20260604-v17-2-presence-timeout"></script>
    <script src="assets/js/forward-message.js?v=20260604-v18-forward"></script>
    <script src="assets/js/group-page.js?v=20260604-v17-2-presence-timeout"></script>
</body>

</html>