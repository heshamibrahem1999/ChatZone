<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../presence.php';
$user = require_login($pdo);
$userId = (int)$user['id'];
update_user_presence($pdo, $userId);
$groupId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT g.*, gm.role FROM `groups` g JOIN group_members gm ON gm.group_id = g.id WHERE g.id = ? AND gm.user_id = ? LIMIT 1");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();
if (!$group) { http_response_code(403); die('Access denied or group not found.'); }

function render_group_message_body_with_mentions(string $body): string {
    $safe = e($body);
    
    $safe = preg_replace('/@\[([^\]]+)\]\(user:(\d+)\)/u', '<span class="mention-highlight">@$1</span>', $safe);
    
    $safe = preg_replace('/(^|\s)@([\p{L}\p{N}._-]+)/u', '$1<span class="mention-highlight">@$2</span>', $safe);
    return nl2br($safe);
}




try {
    
    $markGroupRead = $pdo->prepare("
        INSERT INTO group_message_reads (group_message_id, user_id, read_at)
        SELECT gm.id, ?, NOW()
        FROM group_messages gm
        WHERE gm.group_id = ?
          AND gm.sender_id <> ?
          AND NOT EXISTS (
              SELECT 1 FROM group_message_reads gmr
              WHERE gmr.group_message_id = gm.id AND gmr.user_id = ?
          )
    ");
    $markGroupRead->execute([$userId, $groupId, $userId, $userId]);
} catch (Throwable $e) {}

$msgStmt = $pdo->prepare("
SELECT gm.*, u.first_name, u.last_name, u.profile_photo,
       rp.body AS reply_body, rp.message_type AS reply_type, ru.first_name AS reply_first_name, ru.last_name AS reply_last_name,
       gr.reactions AS reactions,
       CASE WHEN gms.user_id IS NULL THEN 0 ELSE 1 END AS is_starred,
       CASE WHEN gmm.mentioned_user_id IS NULL THEN 0 ELSE 1 END AS mentioned_me,
       COALESCE(gread.read_count, 0) AS read_count
FROM group_messages gm
JOIN users u ON u.id = gm.sender_id
LEFT JOIN group_messages rp ON rp.id = gm.reply_to_id
LEFT JOIN users ru ON ru.id = rp.sender_id
LEFT JOIN group_message_stars gms ON gms.group_message_id = gm.id AND gms.user_id = {$userId}
LEFT JOIN group_message_mentions gmm ON gmm.group_message_id = gm.id AND gmm.mentioned_user_id = {$userId}
LEFT JOIN (
    SELECT group_message_id, COUNT(*) AS read_count
    FROM group_message_reads
    GROUP BY group_message_id
) gread ON gread.group_message_id = gm.id
LEFT JOIN (
    SELECT grouped.group_message_id, GROUP_CONCAT(CONCAT(grouped.emoji, ' ', grouped.cnt) SEPARATOR ' ') AS reactions
    FROM (
        SELECT group_message_id, emoji, COUNT(*) AS cnt
        FROM group_message_reactions
        GROUP BY group_message_id, emoji
    ) grouped
    GROUP BY grouped.group_message_id
) gr ON gr.group_message_id = gm.id
WHERE gm.group_id = ?
ORDER BY gm.created_at ASC, gm.id ASC
");
$msgStmt->execute([$groupId]);
$messages = $msgStmt->fetchAll();

$membersStmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, gm.role FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ? ORDER BY gm.role, u.first_name");
$membersStmt->execute([$groupId]);
$members = $membersStmt->fetchAll();
$mentionMembers = array_map(function($m) {
    return [
        'id' => (int)$m['id'],
        'name' => trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: ('User #' . (int)$m['id']),
        'role' => $m['role'] ?? 'member'
    ];
}, $members);



$polls = [];
try {
    $pollStmt = $pdo->prepare("SELECT p.*, u.first_name, u.last_name FROM group_polls p JOIN users u ON u.id = p.created_by WHERE p.group_id = ? ORDER BY p.created_at DESC, p.id DESC LIMIT 20");
    $pollStmt->execute([$groupId]);
    $polls = $pollStmt->fetchAll();
    if ($polls) {
        $pollIds = array_map(fn($x) => (int)$x['id'], $polls);
        $placeholders = implode(',', array_fill(0, count($pollIds), '?'));
        $optStmt = $pdo->prepare("SELECT o.*, COUNT(v.user_id) AS votes FROM group_poll_options o LEFT JOIN group_poll_votes v ON v.option_id = o.id WHERE o.poll_id IN ($placeholders) GROUP BY o.id ORDER BY o.id ASC");
        $optStmt->execute($pollIds);
        $optionsByPoll = [];
        foreach ($optStmt->fetchAll() as $opt) { $optionsByPoll[(int)$opt['poll_id']][] = $opt; }
        $myVoteStmt = $pdo->prepare("SELECT poll_id, option_id FROM group_poll_votes WHERE user_id = ? AND poll_id IN ($placeholders)");
        $myVoteStmt->execute(array_merge([$userId], $pollIds));
        $myVotes = [];
        foreach ($myVoteStmt->fetchAll() as $v) { $myVotes[(int)$v['poll_id']] = (int)$v['option_id']; }
        foreach ($polls as &$poll) {
            $poll['options'] = $optionsByPoll[(int)$poll['id']] ?? [];
            $poll['my_vote'] = $myVotes[(int)$poll['id']] ?? 0;
            $poll['total_votes'] = array_sum(array_map(fn($o) => (int)$o['votes'], $poll['options']));
        }
        unset($poll);
    }
} catch (Throwable $e) {
    $polls = [];
}


$timeline = [];
foreach ($messages as $m) {
    $timeline[] = [
        'kind' => 'message',
        'time' => $m['created_at'] ?? '',
        'id' => (int)($m['id'] ?? 0),
        'data' => $m
    ];
}
foreach ($polls as $p) {
    $timeline[] = [
        'kind' => 'poll',
        'time' => $p['created_at'] ?? '',
        'id' => (int)($p['id'] ?? 0),
        'data' => $p
    ];
}
usort($timeline, function($a, $b) {
    $ta = strtotime($a['time'] ?: '1970-01-01 00:00:00');
    $tb = strtotime($b['time'] ?: '1970-01-01 00:00:00');
    if ($ta === $tb) {
        return ($a['id'] <=> $b['id']);
    }
    return $ta <=> $tb;
});
