<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../presence.php';
require_once __DIR__ . '/../blocking.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/user_context.php';
require_once __DIR__ . '/sidebar_data.php';
require_once __DIR__ . '/selected_chat.php';
require_once __DIR__ . '/message_data.php';

$chatContext = cz_chat_boot_user($pdo);
extract($chatContext, EXTR_OVERWRITE);




$chats = cz_chat_load_sidebar_chats($pdo, $userId);
$selectedFriendshipId = cz_chat_selected_friendship_id($chats);
$selectedChat = cz_chat_find_selected($chats, $selectedFriendshipId);
$blockStatus = cz_chat_block_status($selectedChat);
$messagePageLimit = 50;
$messages = $selectedChat ? cz_chat_load_messages($pdo, $userId, (int)$selectedChat['friendship_id'], $messagePageLimit) : [];
