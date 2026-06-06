<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../includes/chat/load_chat_data.php';
require_once __DIR__ . '/partials/chat/head.php';
?>

<div class="mobile-overlay" id="mobileOverlay"></div>
<?php require_once __DIR__ . '/partials/announcements.php'; ?>

<div class="app">
    <?php require_once __DIR__ . '/partials/chat/sidebar.php'; ?>
    <?php require_once __DIR__ . '/partials/chat/conversation.php'; ?>
</div>

<?php require_once __DIR__ . '/partials/chat/scripts.php'; ?>
