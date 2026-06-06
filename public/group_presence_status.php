<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }
$userId = (int)$_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$groupId = (int)($_GET['id'] ?? 0);
if ($groupId <= 0) { echo json_encode(['success'=>false,'message'=>'Missing group']); exit; }
update_user_presence($pdo, $userId);
cleanup_stale_presence($pdo);
$presenceSql = presence_case_sql('u');

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total_members,
           COALESCE(SUM($presenceSql), 0) AS online_members
    FROM group_members gm
    JOIN users u ON u.id = gm.user_id
    WHERE gm.group_id = ?
");
$stmt->execute([$groupId]);
$row = $stmt->fetch();
if (!$row) { echo json_encode(['success'=>false,'message'=>'Group not found']); exit; }
$total = (int)$row['total_members'];
$online = (int)$row['online_members'];
echo json_encode([
    'success' => true,
    'total_members' => $total,
    'online_members' => $online,
    'status_text' => $online . ' online · ' . $total . ' members'
]);
