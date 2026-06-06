<?php


if (!function_exists('cz_admin_require')) {
    function cz_admin_require(PDO $pdo): array
    {
        $user = require_login($pdo);

        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin TINYINT(1) NOT NULL DEFAULT 0");
        } catch (Throwable $e) {}

        if ((int)($user['is_admin'] ?? 0) !== 1) {
            $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$user['id']]);
            $isAdmin = (int)($stmt->fetchColumn() ?: 0);
        } else {
            $isAdmin = 1;
        }

        if ($isAdmin !== 1) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }

        return $user;
    }
}
