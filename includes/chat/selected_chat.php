<?php

function cz_chat_selected_friendship_id(array $chats): int
{
    $selectedFriendshipId = isset($_GET['friendship']) ? (int)$_GET['friendship'] : 0;

    if ($selectedFriendshipId === 0 && !empty($chats)) {
        $selectedFriendshipId = (int)$chats[0]['friendship_id'];
    }

    return $selectedFriendshipId;
}

function cz_chat_find_selected(array $chats, int $selectedFriendshipId): ?array
{
    foreach ($chats as $chat) {
        if ((int)$chat['friendship_id'] === $selectedFriendshipId) {
            return $chat;
        }
    }

    return null;
}

function cz_chat_block_status(?array $selectedChat): array
{
    if (!$selectedChat) {
        return ['i_blocked_them' => false, 'they_blocked_me' => false, 'is_blocked' => false];
    }

    $iBlockedThem = ((int)($selectedChat['i_blocked_them'] ?? 0) === 1);
    $theyBlockedMe = ((int)($selectedChat['they_blocked_me'] ?? 0) === 1);

    return [
        'i_blocked_them' => $iBlockedThem,
        'they_blocked_me' => $theyBlockedMe,
        'is_blocked' => $iBlockedThem || $theyBlockedMe,
    ];
}
