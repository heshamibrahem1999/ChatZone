<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_csrf_or_redirect('chat.php');

$userId = (int) $_SESSION['user_id'];
$friendshipId = (int) ($_POST['friendship_id'] ?? 0);
$duration = $_POST['duration'] ?? '8h';

if ($friendshipId <= 0) {
    header('Location: chat.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM friendships WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
$stmt->execute([$friendshipId, $userId, $userId]);
if (!$stmt->fetch()) {
    header('Location: chat.php');
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_mutes (
  user_id INT UNSIGNED NOT NULL,
  friendship_id INT UNSIGNED NOT NULL,
  muted_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, friendship_id),
  KEY idx_chat_mutes_friendship (friendship_id),
  KEY idx_chat_mutes_until (muted_until)
)");

if ($duration === 'unmute') {
    $q = $pdo->prepare('DELETE FROM chat_mutes WHERE user_id = ? AND friendship_id = ?');
    $q->execute([$userId, $friendshipId]);
    header('Location: chat.php?friendship=' . $friendshipId);
    exit;
}

if ($duration === '1w') {
    $mutedUntilSql = 'DATE_ADD(NOW(), INTERVAL 1 WEEK)';
} elseif ($duration === 'forever') {
    $mutedUntilSql = 'NULL';
} else {
    $mutedUntilSql = 'DATE_ADD(NOW(), INTERVAL 8 HOUR)';
}

$sql = "INSERT INTO chat_mutes (user_id, friendship_id, muted_until, created_at, updated_at)
        VALUES (?, ?, $mutedUntilSql, NOW(), NOW())
        ON DUPLICATE KEY UPDATE muted_until = VALUES(muted_until), updated_at = NOW()";
$q = $pdo->prepare($sql);
$q->execute([$userId, $friendshipId]);

header('Location: chat.php?friendship=' . $friendshipId);
exit;