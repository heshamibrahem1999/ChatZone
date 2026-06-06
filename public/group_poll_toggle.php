<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$pollId = (int)($_POST['poll_id'] ?? 0);
try {
    $stmt = $pdo->prepare("SELECT p.id, p.group_id, p.is_closed, gm.role FROM group_polls p JOIN group_members gm ON gm.group_id = p.group_id AND gm.user_id = ? WHERE p.id = ? LIMIT 1");
    $stmt->execute([$userId, $pollId]);
    $poll = $stmt->fetch();
    if (!$poll) { echo json_encode(['success'=>false,'error'=>'Poll not found']); exit; }
    if (($poll['role'] ?? '') !== 'admin') { echo json_encode(['success'=>false,'error'=>'Only group admins can close/reopen polls']); exit; }
    $new = ((int)$poll['is_closed'] === 1) ? 0 : 1;
    $pdo->prepare("UPDATE group_polls SET is_closed = ? WHERE id = ?")->execute([$new, $pollId]);
    echo json_encode(['success'=>true,'is_closed'=>$new]);
} catch (Throwable $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }