<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/blocking.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

function forward_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function forward_ensure_columns(PDO $pdo): void {
    foreach ([
        "ALTER TABLE messages ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE messages ADD COLUMN forwarded_from_message_id INT NULL",
        "ALTER TABLE group_messages ADD COLUMN is_forwarded TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE group_messages ADD COLUMN forwarded_from_message_id INT NULL"
    ] as $sql) {
        try { $pdo->exec($sql); } catch (Throwable $e) {}
    }
}

try {
    if (!isset($_SESSION['user_id'])) forward_json(['success' => false, 'message' => 'Unauthorized'], 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') forward_json(['success' => false, 'message' => 'Invalid request'], 405);
    require_csrf_or_json();

    $userId = (int)$_SESSION['user_id'];
    update_user_presence($pdo, $userId);
    forward_ensure_columns($pdo);

    $sourceType = ($_POST['source_type'] ?? '') === 'group' ? 'group' : 'private';
    $sourceMessageId = (int)($_POST['source_message_id'] ?? 0);
    $destinationsRaw = $_POST['destinations'] ?? '[]';
    $destinations = json_decode($destinationsRaw, true);
    if (!is_array($destinations)) $destinations = [];

    if ($sourceMessageId <= 0) forward_json(['success' => false, 'message' => 'Invalid source message']);
    if (!$destinations) forward_json(['success' => false, 'message' => 'Choose at least one destination']);

    if ($sourceType === 'group') {
        $srcStmt = $pdo->prepare("\n            SELECT gm.id, gm.group_id, gm.body, gm.message_type, gm.file_path\n            FROM group_messages gm\n            JOIN group_members gmem ON gmem.group_id = gm.group_id AND gmem.user_id = ?\n            WHERE gm.id = ? AND COALESCE(gm.is_deleted, 0) = 0\n            LIMIT 1\n        ");
        $srcStmt->execute([$userId, $sourceMessageId]);
    } else {
        $srcStmt = $pdo->prepare("\n            SELECT m.id, m.friendship_id, m.body, m.message_type, m.file_path\n            FROM messages m\n            JOIN friendships f ON f.id = m.friendship_id\n            WHERE m.id = ? AND (f.user_one_id = ? OR f.user_two_id = ?) AND COALESCE(m.is_deleted, 0) = 0\n            LIMIT 1\n        ");
        $srcStmt->execute([$sourceMessageId, $userId, $userId]);
    }
    $source = $srcStmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) forward_json(['success' => false, 'message' => 'Source message not found or access denied']);

    $sent = 0;
    $privateTargets = [];
    $groupTargets = [];

    foreach ($destinations as $dest) {
        $destType = (($dest['type'] ?? '') === 'group') ? 'group' : 'private';
        $destId = (int)($dest['id'] ?? 0);
        if ($destId <= 0) continue;

        if ($destType === 'private') {
            $f = $pdo->prepare("\n                SELECT id, CASE WHEN user_one_id = ? THEN user_two_id ELSE user_one_id END AS friend_id\n                FROM friendships\n                WHERE ((user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?))\n                LIMIT 1\n            ");
            $f->execute([$userId, $userId, $destId, $destId, $userId]);
            $friendship = $f->fetch(PDO::FETCH_ASSOC);
            if (!$friendship) continue;
            if (!users_can_message($pdo, $userId, $destId)) continue;
            $ins = $pdo->prepare("\n                INSERT INTO messages (friendship_id, sender_id, body, message_type, file_path, is_forwarded, forwarded_from_message_id, is_seen, seen_at, created_at)\n                VALUES (?, ?, ?, ?, ?, 1, ?, 0, NULL, NOW())\n            ");
            $ins->execute([(int)$friendship['id'], $userId, $source['body'] ?? '', $source['message_type'] ?? 'text', $source['file_path'] ?? null, $sourceMessageId]);
            $sent++;
            $privateTargets[] = (int)$friendship['id'];
        } else {
            $g = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
            $g->execute([$destId, $userId]);
            if (!$g->fetchColumn()) continue;
            $ins = $pdo->prepare("\n                INSERT INTO group_messages (group_id, sender_id, body, message_type, file_path, is_forwarded, forwarded_from_message_id, created_at)\n                VALUES (?, ?, ?, ?, ?, 1, ?, NOW())\n            ");
            $ins->execute([$destId, $userId, $source['body'] ?? '', $source['message_type'] ?? 'text', $source['file_path'] ?? null, $sourceMessageId]);
            $sent++;
            $groupTargets[] = $destId;
        }
    }

    forward_json(['success' => true, 'sent' => $sent, 'private_targets' => array_values(array_unique($privateTargets)), 'group_targets' => array_values(array_unique($groupTargets))]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Throwable $rollbackError) {}
    }
    forward_json(['success' => false, 'message' => 'Forward failed: ' . $e->getMessage()], 500);
}