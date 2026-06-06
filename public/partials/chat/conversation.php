<main class="conversation">
    <?php if ($selectedChat): ?>
        <?php require __DIR__ . '/conversation_header.php'; ?>
        <?php require __DIR__ . '/conversation_search.php'; ?>
        <?php require __DIR__ . '/conversation_messages.php'; ?>
        <?php require __DIR__ . '/conversation_notices.php'; ?>
        <?php require __DIR__ . '/conversation_composer.php'; ?>
    <?php else: ?>
        <?php require __DIR__ . '/conversation_empty.php'; ?>
    <?php endif; ?>
</main>
