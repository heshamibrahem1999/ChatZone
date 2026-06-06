<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';

$user = require_login($pdo);
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('privacy.php');
}

require_csrf_or_redirect('privacy.php');
$password = $_POST['password'] ?? '';

if (!password_verify($password, $user['password_hash'] ?? '')) {
    $_SESSION['privacy_error'] = 'Wrong password. Account was not deleted.';
    redirect('privacy.php');
}

$deletedEmail = 'deleted-user-' . $userId . '-' . time() . '@deleted.local';
$randomPass = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE users SET first_name = 'Deleted', last_name = 'User', email = ?, password_hash = ?, profile_photo = 'default.png', about = NULL, is_banned = 1, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$deletedEmail, $randomPass, $userId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['privacy_error'] = 'Could not delete account: ' . $e->getMessage();
    redirect('privacy.php');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
redirect('login.php?account_deleted=1');
