<?php

function cz_chat_boot_user(PDO $pdo): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit;
    }

    $authUser = require_login($pdo);
    $userId = (int)$authUser['id'];

    update_user_presence($pdo, $userId);

    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, language, is_admin, theme, background, text_color, text_background, text_size, profile_photo, about, notify_sound, notify_browser, last_active_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    $labels = language_labels($user['language']);
    $isArabic = ($user['language'] === 'Arabic');
    $langCode = $isArabic ? 'ar' : ($user['language'] === 'French' ? 'fr' : 'en');
    $dir = $isArabic ? 'rtl' : 'ltr';
    $fullName = trim($user['first_name'] . ' ' . $user['last_name']);

    return compact('userId', 'user', 'labels', 'isArabic', 'langCode', 'dir', 'fullName');
}
