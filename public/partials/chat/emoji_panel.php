<button class="emoji-btn" type="button" id="emojiBtn" aria-label="Open emoji picker">😊</button>

<div class="emoji-panel" id="emojiPanel" aria-label="Emoji picker">
    <div class="emoji-panel-header">
        <strong>Emojis</strong>
        <button class="emoji-close" type="button" id="emojiClose" aria-label="Close emoji picker">×</button>
    </div>

    <div class="emoji-section-title">Smileys & reactions</div>
    <div class="emoji-grid">
        <?php
        $emojis = [
            '😀','😃','😄','😁','😆','😂','🤣','😊','😍','😘','😎','🥳',
            '😇','🙂','😉','😌','😋','😜','🤔','🙄','😴','😢','😭','😡',
            '👍','👎','👏','🙌','🙏','🤝','💪','👀','🔥','❤️','💙','💚',
            '🎉','✨','⭐','✅','❌','⚠️','📌','📷','🖼️','📎','💬','🚀',
            '🏆','🎮','⚽','🎾','☕','🍕','🌹','🌍','💻','📱','💰','🛒'
        ];
        foreach ($emojis as $emoji):
        ?>
            <button type="button" class="emoji-option" data-emoji="<?= e($emoji) ?>"><?= e($emoji) ?></button>
        <?php endforeach; ?>
    </div>
</div>
