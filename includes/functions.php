<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/sessions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/settings.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/language.php';
require_once __DIR__ . '/core/chat_helpers.php';
require_once __DIR__ . '/core/rendering.php';
require_once __DIR__ . '/core/presence_text.php';
