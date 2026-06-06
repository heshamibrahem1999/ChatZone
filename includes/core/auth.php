<?php

function current_user(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function require_login(PDO $pdo): array {
    $user = current_user($pdo);
    if (!$user) {
        redirect('login.php');
    }

    cz_touch_user_session($pdo, (int)$user['id']);

    if ((int)($user['is_banned'] ?? 0) === 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirect('login.php?banned=1');
    }

    cz_enforce_maintenance($pdo, $user);

    return $user;
}

function cz_is_admin_user(array $user): bool {
    return (int)($user['is_admin'] ?? 0) === 1;
}
