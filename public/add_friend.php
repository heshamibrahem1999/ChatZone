<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/presence.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

update_user_presence($pdo, (int) $_SESSION['user_id']);

$userId = (int) $_SESSION['user_id'];
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_or_redirect('chat.php');
    $term = trim($_POST['email'] ?? '');
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$term, $user['id']]);
    $friend = $stmt->fetch();
    if ($friend) {
        create_friendship($pdo, (int)$user['id'], (int)$friend['id']);
    }
}
redirect('chat.php');