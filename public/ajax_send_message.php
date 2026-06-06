<?php
// Saves a private message and returns the new message data quickly.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

require_once __DIR__ . '/../includes/blocking.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

function voice_debug_payload(string $stage, array $extra = []): array
{
    $voiceDir = __DIR__ . '/uploads/voices/';
    return array_merge([
        'stage' => $stage,
        'files_keys' => array_keys($_FILES ?? []),
        'post_keys' => array_keys($_POST ?? []),
        'voice_file' => $_FILES['voice'] ?? null,
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'file_uploads' => ini_get('file_uploads'),
        'voice_dir' => $voiceDir,
        'voice_dir_exists' => is_dir($voiceDir),
        'voice_dir_writable' => is_dir($voiceDir) ? is_writable($voiceDir) : null,
    ], $extra);
}

function json_fail(string $message, array $debug = []): void
{
    echo json_encode(['success' => false, 'message' => $message, 'debug' => $debug]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    json_fail('Unauthorized');
}

require_csrf_or_json();

$userId = (int) $_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}


$friendId = (int) ($_POST['friend_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
$replyToId = (int) ($_POST['reply_to_id'] ?? 0);

if ($friendId <= 0) {
    json_fail('Invalid friend', voice_debug_payload('invalid_friend'));
}

$stmt = $pdo->prepare("
    SELECT id
    FROM friendships
    WHERE (user_one_id = ? AND user_two_id = ?)
       OR (user_one_id = ? AND user_two_id = ?)
    LIMIT 1
");
$stmt->execute([$userId, $friendId, $friendId, $userId]);
$friendship = $stmt->fetch();

if (!$friendship) {
    json_fail('Friendship not found', voice_debug_payload('friendship_not_found'));
}

if (!users_can_message($pdo, $userId, $friendId)) {
    json_fail('You cannot send messages because this chat is blocked.');
}

$friendshipId = (int) $friendship['id'];
$messageType = 'text';
$filePath = null;
$uploadDebug = [];

$hasImage = isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$hasVoice = isset($_FILES['voice']) && ($_FILES['voice']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if (($body === '') && !$hasImage && !$hasVoice && !empty($_POST['voice_expected'])) {
    json_fail('Browser did not send the voice file to PHP. Check HTTPS/microphone permission and browser console.', voice_debug_payload('voice_expected_but_missing'));
}

if ($hasImage && $hasVoice) {
    json_fail('Send either image or voice, not both', voice_debug_payload('image_and_voice'));
}

if ($hasImage) {
    $upload = safe_image_upload(
        $_FILES['image'],
        __DIR__ . '/uploads/messages/',
        'uploads/messages',
        5 * 1024 * 1024
    );

    if (!$upload['ok']) {
        json_fail($upload['message'], voice_debug_payload('image_upload_failed', ['upload' => $upload]));
    }

    $messageType = 'image';
    $filePath = $upload['path'];
}

if ($hasVoice) {
    $upload = safe_audio_upload(
        $_FILES['voice'],
        __DIR__ . '/uploads/voices/',
        'uploads/voices',
        10 * 1024 * 1024
    );
    $uploadDebug = $upload['debug'] ?? [];

    if (!$upload['ok']) {
        json_fail($upload['message'], voice_debug_payload('voice_upload_failed', ['upload' => $upload]));
    }

    $messageType = 'voice';
    $filePath = $upload['path'];
}

if ($messageType === 'text' && $body === '') {
    json_fail('Message is empty', voice_debug_payload('empty_message'));
}

if (mb_strlen($body) > 1000) {
    json_fail('Message is too long');
}

if ($replyToId > 0) {
    $replyCheck = $pdo->prepare("
        SELECT id
        FROM messages
        WHERE id = ? AND friendship_id = ?
        LIMIT 1
    " );
    $replyCheck->execute([$replyToId, $friendshipId]);
    if (!$replyCheck->fetch()) {
        json_fail('Invalid reply message');
    }
} else {
    $replyToId = null;
}

$insert = $pdo->prepare("
    INSERT INTO messages (friendship_id, sender_id, body, message_type, file_path, reply_to_id, is_seen, seen_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NOW())
");
$ok = $insert->execute([$friendshipId, $userId, $body, $messageType, $filePath, $replyToId]);

if (!$ok) {
    if ($messageType === 'voice' && $filePath) {
        safe_unlink_public_file(__DIR__, $filePath);
    }
    json_fail('Database insert failed', voice_debug_payload('db_insert_failed', ['pdo_error' => $insert->errorInfo()]));
}

echo json_encode([
    'success' => true,
    'friendship_id' => $friendshipId,
    'message_type' => $messageType,
    'file_path' => $filePath,
    'debug' => $messageType === 'voice' ? voice_debug_payload('voice_saved', ['upload' => $uploadDebug]) : null,
]);
