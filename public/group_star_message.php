<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    $messageId = (int)($_POST['message_id'] ?? 0);

    if ($messageId <= 0) {
        throw new Exception('Invalid message.');
    }

    
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_message_stars (
        group_message_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_message_id, user_id),
        INDEX idx_group_message_stars_user (user_id),
        INDEX idx_group_message_stars_message (group_message_id)
    )");

    
    $stmt = $pdo->prepare("SELECT gmsg.id
        FROM group_messages gmsg
        JOIN group_members gm ON gm.group_id = gmsg.group_id AND gm.user_id = ?
        WHERE gmsg.id = ?
        LIMIT 1");
    $stmt->execute([$userId, $messageId]);
    if (!$stmt->fetch()) {
        throw new Exception('Message not found or access denied.');
    }

    $check = $pdo->prepare("SELECT 1 FROM group_message_stars WHERE group_message_id = ? AND user_id = ? LIMIT 1");
    $check->execute([$messageId, $userId]);

    if ($check->fetch()) {
        $del = $pdo->prepare("DELETE FROM group_message_stars WHERE group_message_id = ? AND user_id = ?");
        $del->execute([$messageId, $userId]);
        echo json_encode(['success' => true, 'starred' => false]);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO group_message_stars (group_message_id, user_id) VALUES (?, ?)");
    $ins->execute([$messageId, $userId]);

    echo json_encode(['success' => true, 'starred' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}