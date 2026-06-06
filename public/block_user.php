<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';
require_once __DIR__ . '/../includes/blocking.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function block_json_fail(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($_SESSION['user_id'])) block_json_fail('Unauthorized');
require_csrf_or_json();

$userId = (int) $_SESSION['user_id'];
$friendId = (int) ($_POST['friend_id'] ?? 0);
$action = (string) ($_POST['action'] ?? 'toggle');

if ($friendId <= 0 || $friendId === $userId) block_json_fail('Invalid user');

update_user_presence($pdo, $userId);
ensure_user_blocks_table($pdo);

$check = $pdo->prepare("SELECT id FROM friendships WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?) LIMIT 1");
$check->execute([$userId, $friendId, $friendId, $userId]);
if (!$check->fetch()) block_json_fail('You can only block existing chats');

$existsStmt = $pdo->prepare("SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ? LIMIT 1");
$existsStmt->execute([$userId, $friendId]);
$exists = (bool)$existsStmt->fetchColumn();

if ($action === 'unblock' || ($action === 'toggle' && $exists)) {
    $stmt = $pdo->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->execute([$userId, $friendId]);
    echo json_encode(['success' => true, 'blocked' => false, 'message' => 'User unblocked']);
    exit;
}

$stmt = $pdo->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id, created_at) VALUES (?, ?, NOW())");
$stmt->execute([$userId, $friendId]);

echo json_encode(['success' => true, 'blocked' => true, 'message' => 'User blocked']);
