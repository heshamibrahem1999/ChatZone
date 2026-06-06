<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];


if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$status = strtolower((string)(cz_presence_request_value('status') ?: 'online'));
$reason = strtolower((string)(cz_presence_request_value('reason') ?: ''));



try {
    if ($status === 'offline') {
        mark_user_offline($pdo, $userId);
        echo json_encode(['success' => true, 'online' => false]);
        exit;
    }
    update_user_presence($pdo, $userId);
    cleanup_stale_presence($pdo);
    echo json_encode(['success' => true, 'online' => true]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Presence update failed']);
}