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
    $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, language=?, theme=?, background=?, text_color=?, text_background=?, text_size=? WHERE id=?');
    $stmt->execute([
        trim($_POST['first_name'] ?? $user['first_name']),
        trim($_POST['last_name'] ?? $user['last_name']),
        trim($_POST['email'] ?? $user['email']),
        $_POST['language'] ?? $user['language'],
        $_POST['theme'] ?? $user['theme'],
        $_POST['background'] ?? $user['background'],
        $_POST['text_color'] ?? $user['text_color'],
        $_POST['text_background'] ?? $user['text_background'],
        $_POST['text_size'] ?? $user['text_size'],
        $user['id']
    ]);
}
redirect('chat.php');