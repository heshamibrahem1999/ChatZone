<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/chat/load_chat_data.php';


if (isset($_GET['friendship_id'])) {
    $selectedFriendshipId = (int) $_GET['friendship_id'];
}

require __DIR__ . '/partials/chat/sidebar.php';
