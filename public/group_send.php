<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$user = require_login($pdo);
$userId = (int)$user['id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$groupId = (int)($_POST['group_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$replyToId = (int)($_POST['reply_to_id'] ?? 0);

if ($groupId <= 0) { header('Location: chat.php'); exit; }

$check = $pdo->prepare("SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
$check->execute([$groupId, $userId]);
if (!$check->fetch()) { http_response_code(403); die('Access denied.'); }

$messageType = 'text';
$filePath = null;

function group_upload_dir_for_type($type) {
    $base = __DIR__ . '/uploads/group_media/';
    $sub = ($type === 'image') ? 'images/' : (($type === 'voice') ? 'voices/' : 'files/');
    $dir = $base . $sub;
    if (!is_dir($dir)) { mkdir($dir, 0775, true); }
    return [$dir, 'uploads/group_media/' . $sub];
}

if (!empty($_FILES['attachment']) && (int)$_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ((int)$_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        die('Upload failed. PHP error: ' . (int)$_FILES['attachment']['error']);
    }

    if ((int)$_FILES['attachment']['size'] > 20 * 1024 * 1024) {
        die('File too large. Max 20MB.');
    }

    $tmp = $_FILES['attachment']['tmp_name'];
    $original = $_FILES['attachment']['name'] ?? 'file';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $mime = mime_content_type($tmp) ?: ($_FILES['attachment']['type'] ?? 'application/octet-stream');

    $imageExt = ['jpg','jpeg','png','gif','webp'];
    $audioExt = ['webm','mp3','wav','ogg','m4a'];
    $fileExt  = ['pdf','doc','docx','zip','txt','xls','xlsx','ppt','pptx'];

    if (strpos($mime, 'image/') === 0 && in_array($ext, $imageExt, true)) {
        $messageType = 'image';
    } elseif ((strpos($mime, 'audio/') === 0 || strpos($mime, 'video/webm') === 0) && in_array($ext, $audioExt, true)) {
        $messageType = 'voice';
    } elseif (in_array($ext, $fileExt, true)) {
        $messageType = 'file';
    } else {
        die('File type not allowed: ' . htmlspecialchars($mime));
    }

    [$dir, $urlBase] = group_upload_dir_for_type($messageType);
    $filename = time() . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = $dir . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        die('Could not save uploaded file. Check folder permissions: public/uploads/group_media/');
    }

    $filePath = $urlBase . $filename;
}

if ($body === '' && $filePath === null) {
    header('Location: group.php?id=' . $groupId);
    exit;
}

$validReplyTo = null;
if ($replyToId > 0) {
    $replyCheck = $pdo->prepare("SELECT id FROM group_messages WHERE id = ? AND group_id = ? LIMIT 1");
    $replyCheck->execute([$replyToId, $groupId]);
    if ($replyCheck->fetch()) { $validReplyTo = $replyToId; }
}

$stmt = $pdo->prepare("INSERT INTO group_messages (group_id, sender_id, body, message_type, file_path, reply_to_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->execute([$groupId, $userId, $body, $messageType, $filePath, $validReplyTo]);
$messageId = (int)$pdo->lastInsertId();




try {
    

    $insertMention = $pdo->prepare("INSERT IGNORE INTO group_message_mentions (group_message_id, group_id, mentioned_user_id, mentioned_by) VALUES (?, ?, ?, ?)");

    
    if ($body !== '' && preg_match_all('/@\[[^\]]+\]\(user:(\d+)\)/u', $body, $idMatches)) {
        $ids = array_unique(array_map('intval', $idMatches[1]));
        $memberCheck = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND user_id = ? LIMIT 1");
        foreach ($ids as $mentionedId) {
            if ($mentionedId <= 0 || $mentionedId === $userId) { continue; }
            $memberCheck->execute([$groupId, $mentionedId]);
            if ($memberCheck->fetch()) {
                $insertMention->execute([$messageId, $groupId, $mentionedId, $userId]);
            }
        }
    }

    
    if ($body !== '' && preg_match_all('/(^|\s)@([\p{L}\p{N}._-]+)/u', $body, $matches)) {
        $tokens = array_unique(array_map('mb_strtolower', $matches[2]));
        $memberStmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.email FROM group_members gm JOIN users u ON u.id = gm.user_id WHERE gm.group_id = ?");
        $memberStmt->execute([$groupId]);
        $membersForMentions = $memberStmt->fetchAll();

        foreach ($membersForMentions as $member) {
            $mid = (int)$member['id'];
            if ($mid === $userId) { continue; }
            $first = mb_strtolower(trim($member['first_name'] ?? ''));
            $last = mb_strtolower(trim($member['last_name'] ?? ''));
            $fullCompact = preg_replace('/\s+/u', '', $first . $last);
            $emailLocal = mb_strtolower(strtok($member['email'] ?? '', '@'));
            $candidates = array_filter([$first, $fullCompact, $emailLocal]);

            foreach ($tokens as $token) {
                if (in_array($token, $candidates, true)) {
                    $insertMention->execute([$messageId, $groupId, $mid, $userId]);
                    break;
                }
            }
        }
    }
} catch (Throwable $e) {
    
}

header('Location: group.php?id=' . $groupId);
exit;