<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/sessions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$message = $_SESSION['sessions_message'] ?? '';
$error = $_SESSION['sessions_error'] ?? '';
unset($_SESSION['sessions_message'], $_SESSION['sessions_error']);
$currentHash = !empty($_SESSION['session_token']) ? hash('sha256', $_SESSION['session_token']) : '';
$rows = [];
if (cz_table_exists($pdo, 'user_sessions')) {
    $stmt = $pdo->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY is_active DESC, last_seen_at DESC, created_at DESC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $error = 'Import sql/2026_05_30_session_management.sql first.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sessions - ChatZone</title>
<link rel="stylesheet" href="assets/css/chat.css?v=20260530-sessions">
<link rel="stylesheet" href="assets/css/extracted/public__sessions.css">
</head>
<body class="<?= ($_COOKIE['cz_dark_mode'] ?? '') === '1' ? 'dark-mode' : '' ?>">
<div class="sessions-wrap">
  <div class="sessions-card">
    <h2>Active Sessions</h2>
    <p class="muted">See where your account is logged in and log out other devices.</p>
    <p><a href="chat.php">← Back to chat</a></p>
  </div>
  <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice err"><?= e($error) ?></div><?php endif; ?>
  <div class="sessions-card">
    <?php if (!$rows): ?>
      <p class="muted">No sessions found yet. Logout and login again after importing the SQL.</p>
    <?php endif; ?>
    <?php foreach ($rows as $row): $isCurrent = $currentHash && hash_equals($currentHash, (string)$row['token_hash']); ?>
      <div class="session-row">
        <div>
          <div><b><?= $isCurrent ? 'Current device' : 'Other device' ?></b> <?= (int)$row['is_active'] === 1 ? '<span class="badge">Active</span>' : '<span class="badge off">Logged out</span>' ?></div>
          <div class="muted">IP: <?= e($row['ip_address'] ?: 'unknown') ?></div>
          <div class="muted">Browser: <?= e($row['user_agent'] ?: 'unknown') ?></div>
          <div class="muted">Created: <?= e($row['created_at'] ?: '-') ?> · Last seen: <?= e($row['last_seen_at'] ?: '-') ?></div>
        </div>
        <div>
          <?php if ((int)$row['is_active'] === 1 && !$isCurrent): ?>
            <form method="post" action="revoke_session.php" onsubmit="return confirm('Log out this device?');">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <input type="hidden" name="session_id" value="<?= (int)$row['id'] ?>">
              <button class="btn danger" type="submit">Log out</button>
            </form>
          <?php elseif ($isCurrent): ?>
            <span class="muted">This session</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
