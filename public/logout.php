<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/presence.php';
require_once __DIR__ . '/../includes/sessions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    mark_user_offline($pdo, (int) $_SESSION['user_id']);
    cz_revoke_current_session($pdo, (int) $_SESSION['user_id']);
}

session_unset();
session_destroy();

header("Location: login.php");
exit;