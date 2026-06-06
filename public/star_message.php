<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function star_response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!isset($_SESSION['user_id'])) {
        star_response(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        star_response(['success' => false, 'message' => 'Invalid request'], 405);
    }

    require_csrf_or_json();

    $userId = (int) $_SESSION['user_id'];
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $friendshipId = (int) ($_POST['friendship_id'] ?? 0);

    if ($messageId <= 0) {
        star_response(['success' => false, 'message' => 'Invalid message id']);
    }

    
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_stars (
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (message_id, user_id),
        INDEX idx_message_stars_user (user_id),
        INDEX idx_message_stars_message (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    
    
    if ($friendshipId > 0) {
        $check = $pdo->prepare("\n            SELECT m.id\n            FROM messages m\n            INNER JOIN friendships f ON f.id = m.friendship_id\n            WHERE m.id = ?\n              AND m.friendship_id = ?\n              AND (f.user_one_id = ? OR f.user_two_id = ?)\n            LIMIT 1\n        ");
        $check->execute([$messageId, $friendshipId, $userId, $userId]);
    } else {
        $check = $pdo->prepare("\n            SELECT m.id\n            FROM messages m\n            INNER JOIN friendships f ON f.id = m.friendship_id\n            WHERE m.id = ?\n              AND (f.user_one_id = ? OR f.user_two_id = ?)\n            LIMIT 1\n        ");
        $check->execute([$messageId, $userId, $userId]);
    }

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        star_response(['success' => false, 'message' => 'Access denied or message not found']);
    }

    $exists = $pdo->prepare("SELECT 1 FROM message_stars WHERE message_id = ? AND user_id = ? LIMIT 1");
    $exists->execute([$messageId, $userId]);

    if ($exists->fetchColumn()) {
        $delete = $pdo->prepare("DELETE FROM message_stars WHERE message_id = ? AND user_id = ?");
        $delete->execute([$messageId, $userId]);
        star_response(['success' => true, 'starred' => false, 'message_id' => $messageId]);
    }

    $insert = $pdo->prepare("INSERT INTO message_stars (message_id, user_id) VALUES (?, ?)");
    $insert->execute([$messageId, $userId]);

    star_response(['success' => true, 'starred' => true, 'message_id' => $messageId]);
} catch (Throwable $e) {
    star_response([
        'success' => false,
        'message' => 'Star error: ' . $e->getMessage()
    ], 500);
}