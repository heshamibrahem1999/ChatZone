<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

require_once __DIR__ . '/../includes/blocking.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

function voice_json_fail(string $message, array $debug = []): void {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => $message, 'debug' => $debug], JSON_UNESCAPED_SLASHES);
    exit;
}
function voice_debug(string $stage, array $extra = []): array {
    $dir = __DIR__ . '/uploads/voices/';
    return array_merge([
        'stage' => $stage,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'post_keys' => array_keys($_POST ?? []),
        'files_keys' => array_keys($_FILES ?? []),
        'voice_file' => $_FILES['voice'] ?? null,
        'target_folder' => $dir,
        'folder_exists' => is_dir($dir),
        'folder_writable' => is_dir($dir) ? is_writable($dir) : null,
        'file_uploads' => ini_get('file_uploads'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ], $extra);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') voice_json_fail('POST only', voice_debug('wrong_method'));
if (empty($_SESSION['user_id'])) voice_json_fail('Unauthorized / session expired', voice_debug('no_session'));
require_csrf_or_json();

$userId = (int) $_SESSION['user_id'];
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }


$friendId = (int)($_POST['friend_id'] ?? 0);
$replyToId = (int)($_POST['reply_to_id'] ?? 0);
if ($friendId <= 0) voice_json_fail('Invalid friend id', voice_debug('invalid_friend'));

if (!isset($_FILES['voice'])) {
    voice_json_fail('No voice file reached PHP. This means JavaScript did not attach the Blob, or the request was blocked before PHP.', voice_debug('missing_file'));
}

$stmt = $pdo->prepare("SELECT id FROM friendships WHERE (user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?) LIMIT 1");
$stmt->execute([$userId, $friendId, $friendId, $userId]);
$friendship = $stmt->fetch();
if (!$friendship) voice_json_fail('Friendship not found', voice_debug('no_friendship'));
if (!users_can_message($pdo, $userId, $friendId)) voice_json_fail('You cannot send voice messages because this chat is blocked.', voice_debug('blocked_chat'));
$friendshipId = (int)$friendship['id'];

if ($replyToId > 0) {
    $replyCheck = $pdo->prepare("SELECT id FROM messages WHERE id = ? AND friendship_id = ? LIMIT 1");
    $replyCheck->execute([$replyToId, $friendshipId]);
    if (!$replyCheck->fetch()) voice_json_fail('Invalid reply message', voice_debug('bad_reply'));
} else {
    $replyToId = null;
}

$upload = safe_audio_upload($_FILES['voice'], __DIR__ . '/uploads/voices/', 'uploads/voices', 10 * 1024 * 1024);
if (!$upload['ok']) {
    voice_json_fail($upload['message'], voice_debug('upload_failed', ['upload' => $upload]));
}

$insert = $pdo->prepare("INSERT INTO messages (friendship_id, sender_id, body, message_type, file_path, reply_to_id, is_seen, seen_at, created_at) VALUES (?, ?, '', 'voice', ?, ?, 0, NULL, NOW())");
$ok = $insert->execute([$friendshipId, $userId, $upload['path'], $replyToId]);
if (!$ok) {
    safe_unlink_public_file(__DIR__, $upload['path']);
    voice_json_fail('Voice saved but database insert failed', voice_debug('db_failed', ['pdo_error' => $insert->errorInfo(), 'upload' => $upload]));
}

echo json_encode([
    'success' => true,
    'message_type' => 'voice',
    'file_path' => $upload['path'],
    'message_id' => (int)$pdo->lastInsertId(),
    'debug' => voice_debug('saved', ['upload' => $upload]),
], JSON_UNESCAPED_SLASHES);
