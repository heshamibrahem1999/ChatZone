<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
$pollId = (int)($_POST['poll_id'] ?? 0);
$optionId = (int)($_POST['option_id'] ?? 0);

try {
    $stmt = $pdo->prepare("SELECT p.id, p.group_id, p.is_closed FROM group_polls p JOIN group_members gm ON gm.group_id = p.group_id AND gm.user_id = ? WHERE p.id = ? LIMIT 1");
    $stmt->execute([$userId, $pollId]);
    $poll = $stmt->fetch();
    if (!$poll) { echo json_encode(['success'=>false,'error'=>'Poll not found or access denied']); exit; }
    if ((int)$poll['is_closed'] === 1) { echo json_encode(['success'=>false,'error'=>'Poll is closed']); exit; }

    $opt = $pdo->prepare("SELECT id FROM group_poll_options WHERE id = ? AND poll_id = ? LIMIT 1");
    $opt->execute([$optionId, $pollId]);
    if (!$opt->fetch()) { echo json_encode(['success'=>false,'error'=>'Invalid option']); exit; }

    $pdo->prepare("INSERT INTO group_poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE option_id = VALUES(option_id), created_at = CURRENT_TIMESTAMP")->execute([$pollId, $optionId, $userId]);
    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
