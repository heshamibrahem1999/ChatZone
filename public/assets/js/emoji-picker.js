document.addEventListener('DOMContentLoaded', function () {
    const emojiBtn = document.getElementById('emojiBtn');
    const emojiPanel = document.getElementById('emojiPanel');
    const emojiClose = document.getElementById('emojiClose');
    const messageInput = document.getElementById('messageInput');

    if (!emojiBtn || !emojiPanel || !messageInput) return;

    emojiBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        emojiPanel.classList.toggle('open');
    });

    if (emojiClose) {
        emojiClose.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            emojiPanel.classList.remove('open');
        });
    }

    emojiPanel.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = e.target.closest('.emoji-option');
        if (!btn) return;

        const emoji = btn.dataset.emoji || btn.textContent.trim();

        messageInput.value += emoji;
        messageInput.dispatchEvent(new Event('input', { bubbles: true }));
        messageInput.focus();

        emojiPanel.classList.remove('open');
    });

    document.addEventListener('click', function () {
        emojiPanel.classList.remove('open');
    });
});