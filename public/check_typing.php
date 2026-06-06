<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$userId = (int) $_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
update_user_presence($pdo, $userId);

$friendshipId = (int) ($_GET['friendship_id'] ?? 0);

if ($friendshipId <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

$check = $pdo->prepare("
    SELECT id
    FROM friendships
    WHERE id = ?
      AND (user_one_id = ? OR user_two_id = ?)
    LIMIT 1
");
$check->execute([$friendshipId, $userId, $userId]);

if (!$check->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS typing_count
    FROM typing_status
    WHERE friendship_id = ?
      AND user_id <> ?
      AND is_typing = 1
      AND updated_at >= (NOW() - INTERVAL 5 SECOND)
");
$stmt->execute([$friendshipId, $userId]);
$row = $stmt->fetch();

echo json_encode([
    'success' => true,
    'is_typing' => ((int)$row['typing_count'] > 0)
]);