<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
update_user_presence($pdo, $userId);

$friendshipId = (int) ($_GET['friendship_id'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));

if ($friendshipId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid friendship']);
    exit;
}

if (mb_strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}

$check = $pdo->prepare("SELECT id FROM friendships WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1");
$check->execute([$friendshipId, $userId, $userId]);

if (!$check->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$like = '%' . $q . '%';
$stmt = $pdo->prepare("
    SELECT
        m.id,
        m.sender_id,
        m.body,
        m.message_type,
        m.file_path,
        m.created_at,
        CONCAT(u.first_name, ' ', u.last_name) AS sender_name
    FROM messages m
    JOIN users u ON u.id = m.sender_id
    WHERE m.friendship_id = ?
      AND m.created_at > COALESCE((
          SELECT cc.cleared_at
          FROM chat_clears cc
          WHERE cc.user_id = ? AND cc.friendship_id = m.friendship_id
          LIMIT 1
      ), '1970-01-01 00:00:00')
      AND COALESCE(m.is_deleted, 0) = 0
      AND (
            m.body LIKE ?
            OR (m.message_type = 'image' AND ? LIKE '%image%')
      )
    ORDER BY m.created_at DESC, m.id DESC
    LIMIT 30
");
$stmt->execute([$friendshipId, $userId, $like, $q]);

$results = [];
foreach ($stmt->fetchAll() as $row) {
    $body = trim((string) $row['body']);
    if (($row['message_type'] ?? '') === 'image' && $body === '') {
        $body = '[Image]';
    }
    $results[] = [
        'id' => (int) $row['id'],
        'sender_id' => (int) $row['sender_id'],
        'sender_name' => $row['sender_name'],
        'body' => $body,
        'message_type' => $row['message_type'],
        'created_at' => $row['created_at'],
    ];
}

echo json_encode(['success' => true, 'results' => $results]);
