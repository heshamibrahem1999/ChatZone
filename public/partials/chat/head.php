<!DOCTYPE html>
<html lang="<?= e($langCode) ?>" dir="<?= e($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatZone</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#00a884">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ChatZone">
    <link rel="apple-touch-icon" href="assets/icons/icon-192.svg">
<link rel="stylesheet" href="<?= e(cz_asset('assets/css/chat.css')) ?>">
    <link rel="stylesheet" href="<?= e(cz_asset('assets/css/chat-user-theme.css')) ?>">
    <link rel="stylesheet" href="<?= e(cz_asset('assets/css/emoji-picker.css')) ?>">
</head>
<body data-user-theme="<?= e($user['theme'] ?: '#00a884') ?>" data-user-bg="<?= e($user['background'] ?: '#efeae2') ?>" data-user-text="<?= e($user['text_color'] ?: '#111111') ?>" data-user-bubble="<?= e($user['text_background'] ?: '#d9fdd3') ?>" data-user-size="<?= e($user['text_size'] ?: '16px') ?>">
