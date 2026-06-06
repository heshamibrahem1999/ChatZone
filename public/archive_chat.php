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
$action = $_POST['action'] ?? 'archive';

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

$pdo->exec("CREATE TABLE IF NOT EXISTS chat_archives (
  user_id INT UNSIGNED NOT NULL,
  friendship_id INT UNSIGNED NOT NULL,
  archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, friendship_id),
  KEY idx_chat_archives_friendship (friendship_id)
)");

if ($action === 'restore') {
    $q = $pdo->prepare('DELETE FROM chat_archives WHERE user_id = ? AND friendship_id = ?');
    $q->execute([$userId, $friendshipId]);
    header('Location: chat.php?friendship=' . $friendshipId);
    exit;
}

$q = $pdo->prepare('INSERT INTO chat_archives (user_id, friendship_id, archived_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE archived_at = NOW()');
$q->execute([$userId, $friendshipId]);
header('Location: chat.php');
exit;
