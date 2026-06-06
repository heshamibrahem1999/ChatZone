<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->beginTransaction();

    $due = $pdo->prepare("SELECT sm.*, f.user_one_id, f.user_two_id
        FROM scheduled_messages sm
        JOIN friendships f ON f.id = sm.friendship_id
        WHERE sm.status = 'pending'
          AND sm.scheduled_at <= NOW()
          AND sm.sender_id = ?
        ORDER BY sm.scheduled_at ASC
        LIMIT 20
        FOR UPDATE");
    $due->execute([$userId]);
    $rows = $due->fetchAll();

    $insertMsg = $pdo->prepare("INSERT INTO messages (friendship_id, sender_id, body, message_type, file_path, reply_to_id, is_seen, seen_at, created_at)
        VALUES (?, ?, ?, 'text', NULL, NULL, 0, NULL, NOW())");
    $markSent = $pdo->prepare("UPDATE scheduled_messages SET status = 'sent', sent_message_id = ?, sent_at = NOW() WHERE id = ?");

    $count = 0;
    foreach ($rows as $row) {
        $insertMsg->execute([(int)$row['friendship_id'], (int)$row['sender_id'], $row['body']]);
        $messageId = (int)$pdo->lastInsertId();
        $markSent->execute([$messageId, (int)$row['id']]);
        $count++;
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'processed' => $count]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}