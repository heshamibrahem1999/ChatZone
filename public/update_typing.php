<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

require_csrf_or_json();

$userId = (int) $_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
update_user_presence($pdo, $userId);

$friendshipId = (int) ($_POST['friendship_id'] ?? 0);
$isTyping = (int) ($_POST['is_typing'] ?? 0);

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
    INSERT INTO typing_status (friendship_id, user_id, is_typing, updated_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        is_typing = VALUES(is_typing),
        updated_at = NOW()
");
$stmt->execute([$friendshipId, $userId, $isTyping]);

echo json_encode(['success' => true]);