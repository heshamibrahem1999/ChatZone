<?php
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$friendId = (int) ($_POST['friend_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$friendshipIdFromGet = (int) ($_GET['friendship'] ?? 0);

if ($friendId <= 0 || $body === '') {
    header("Location: chat.php" . ($friendshipIdFromGet ? "?friendship=" . $friendshipIdFromGet : ""));
    exit;
}


$stmt = $pdo->prepare("
    SELECT id
    FROM friendships
    WHERE (user_one_id = ? AND user_two_id = ?)
       OR (user_one_id = ? AND user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$userId, $friendId, $friendId, $userId]);
$friendship = $stmt->fetch();

if (!$friendship) {
    header("Location: chat.php");
    exit;
}

$friendshipId = (int) $friendship['id'];

$insert = $pdo->prepare("
    INSERT INTO messages (friendship_id, sender_id, body, created_at)
    VALUES (?, ?, ?, NOW())
");
$insert->execute([$friendshipId, $userId, $body]);

header("Location: chat.php?friendship=" . $friendshipId . "&scroll=bottom");
exit;