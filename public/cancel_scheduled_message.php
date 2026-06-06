<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

$user = require_login($pdo);
require_csrf_or_redirect('scheduled_messages.php');
$userId = (int)$user['id'];
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE scheduled_messages SET status = 'cancelled' WHERE id = ? AND sender_id = ? AND status = 'pending'");
    $stmt->execute([$id, $userId]);
}
header('Location: scheduled_messages.php');
exit;
