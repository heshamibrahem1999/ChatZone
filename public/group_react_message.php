<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

try {
    $user = require_login($pdo);
    $userId = (int)$user['id'];
    $messageId = (int)($_POST['message_id'] ?? 0);
    $emoji = trim($_POST['emoji'] ?? '');
    $allowed = ['👍','❤️','😂','😮','😢','🙏','🔥'];

    if ($messageId <= 0 || !in_array($emoji, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid reaction.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT gm.group_id FROM group_messages gm JOIN group_members gmem ON gmem.group_id = gm.group_id AND gmem.user_id = ? WHERE gm.id = ? LIMIT 1");
    $stmt->execute([$userId, $messageId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Access denied.']);
        exit;
    }

    $create = $pdo->exec("CREATE TABLE IF NOT EXISTS group_message_reactions (
      id INT UNSIGNED NOT NULL AUTO_INCREMENT,
      group_message_id INT UNSIGNED NOT NULL,
      user_id INT NOT NULL,
      emoji VARCHAR(16) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_group_msg_user (group_message_id, user_id),
      KEY idx_group_reactions_msg (group_message_id),
      KEY idx_group_reactions_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $existing = $pdo->prepare("SELECT emoji FROM group_message_reactions WHERE group_message_id = ? AND user_id = ? LIMIT 1");
    $existing->execute([$messageId, $userId]);
    $old = $existing->fetchColumn();

    if ($old === $emoji) {
        $del = $pdo->prepare("DELETE FROM group_message_reactions WHERE group_message_id = ? AND user_id = ?");
        $del->execute([$messageId, $userId]);
        echo json_encode(['success' => true, 'action' => 'removed']);
    } else {
        $up = $pdo->prepare("INSERT INTO group_message_reactions (group_message_id, user_id, emoji, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), created_at = NOW()");
        $up->execute([$messageId, $userId, $emoji]);
        echo json_encode(['success' => true, 'action' => 'saved', 'emoji' => $emoji]);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}