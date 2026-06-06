<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_csrf_or_json();

function ensure_reports_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reporter_id INT NOT NULL,
        reported_user_id INT DEFAULT NULL,
        message_id INT DEFAULT NULL,
        friendship_id INT DEFAULT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_reporter (reporter_id),
        INDEX idx_reported_user (reported_user_id),
        INDEX idx_message (message_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

$userId = (int)$_SESSION['user_id'];
$messageId = (int)($_POST['message_id'] ?? 0);
$reportedUserId = (int)($_POST['reported_user_id'] ?? 0);
$friendshipId = (int)($_POST['friendship_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($reason === '' || mb_strlen($reason) < 3) {
    echo json_encode(['success' => false, 'message' => 'Please write a short reason.']);
    exit;
}
if (mb_strlen($reason) > 1000) {
    $reason = mb_substr($reason, 0, 1000);
}

ensure_reports_table($pdo);

try {
    if ($messageId > 0) {
        $stmt = $pdo->prepare("SELECT m.id, m.friendship_id, m.sender_id, f.user_one_id, f.user_two_id
            FROM messages m
            JOIN friendships f ON f.id = m.friendship_id
            WHERE m.id = ? AND (f.user_one_id = ? OR f.user_two_id = ?)
            LIMIT 1");
        $stmt->execute([$messageId, $userId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Message not found or access denied.']);
            exit;
        }
        $friendshipId = (int)$row['friendship_id'];
        $reportedUserId = (int)$row['sender_id'];
        if ($reportedUserId === $userId) {
            $reportedUserId = ((int)$row['user_one_id'] === $userId) ? (int)$row['user_two_id'] : (int)$row['user_one_id'];
        }
    } elseif ($reportedUserId > 0) {
        if ($reportedUserId === $userId) {
            echo json_encode(['success' => false, 'message' => 'You cannot report yourself.']);
            exit;
        }
        $a = min($userId, $reportedUserId);
        $b = max($userId, $reportedUserId);
        $stmt = $pdo->prepare('SELECT id FROM friendships WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
        $stmt->execute([$a, $b]);
        $row = $stmt->fetch();
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'You can only report users you have a chat with.']);
            exit;
        }
        $friendshipId = (int)$row['id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Nothing to report.']);
        exit;
    }

    $dup = $pdo->prepare("SELECT id FROM reports
        WHERE reporter_id = ?
          AND COALESCE(message_id, 0) = COALESCE(?, 0)
          AND COALESCE(reported_user_id, 0) = COALESCE(?, 0)
          AND status = 'open'
        LIMIT 1");
    $dup->execute([$userId, $messageId ?: null, $reportedUserId ?: null]);
    if ($dup->fetch()) {
        echo json_encode(['success' => true, 'message' => 'Already reported.']);
        exit;
    }

    $ins = $pdo->prepare('INSERT INTO reports (reporter_id, reported_user_id, message_id, friendship_id, reason) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$userId, $reportedUserId ?: null, $messageId ?: null, $friendshipId ?: null, $reason]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Report error: ' . $e->getMessage()]);
}
