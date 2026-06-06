<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
update_user_presence($pdo, $userId);
cleanup_stale_presence($pdo);

$friendshipId = (int) ($_GET['friendship_id'] ?? 0);

if ($friendshipId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing friendship']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.id AS friend_id,
        u.last_active_at,
        " . presence_case_sql('u') . " AS is_online,
        me.language AS my_language
    FROM friendships f
    JOIN users me ON me.id = ?
    JOIN users u
        ON u.id = CASE
            WHEN f.user_one_id = ? THEN f.user_two_id
            ELSE f.user_one_id
        END
    WHERE f.id = ?
      AND (f.user_one_id = ? OR f.user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$userId, $userId, $friendshipId, $userId, $userId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Chat not found']);
    exit;
}

$language = $row['my_language'] ?: 'English';

if ((int)$row['is_online'] === 1) {
    $statusText = $language === 'Arabic' ? 'متصل الآن' : ($language === 'French' ? 'En ligne' : 'Online');
} else {
    $statusText = last_seen_text($row['last_active_at'] ?? null, $language);
}

echo json_encode([
    'success' => true,
    'friend_id' => (int)$row['friend_id'],
    'is_online' => ((int)$row['is_online'] === 1),
    'last_active_at' => $row['last_active_at'],
    'status_text' => $statusText,
    'presence' => ((int)$row['is_online'] === 1) ? 'online' : 'offline'
]);