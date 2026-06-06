
(function () {
    function isTouchChatLayout() {
        return window.matchMedia('(max-width: 850px)').matches ||
               window.matchMedia('(hover: none) and (pointer: coarse)').matches;
    }

    document.addEventListener('click', function (event) {
        if (!isTouchChatLayout()) return;

        var msgList = document.getElementById('msgList');
        if (!msgList) return;

        var mobileMenuBtn = event.target.closest('.mobile-msg-menu-btn');
        if (mobileMenuBtn) {
            var btnWrap = mobileMenuBtn.closest('.msg-wrap');
            if (btnWrap && msgList.contains(btnWrap)) {
                event.preventDefault();
                event.stopPropagation();
                msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (other) {
                    if (other !== btnWrap) other.classList.remove('show-actions');
                });
                btnWrap.classList.toggle('show-actions');
            }
            return;
        }

        var interactive = event.target.closest('button, a, input, select, textarea, audio, video, .reaction-chip, .msg-actions, .delete-msg-btn');
        if (interactive) return;

        var wrap = event.target.closest('.msg-wrap');
        if (!wrap || !msgList.contains(wrap)) return;

        event.preventDefault();
        event.stopPropagation();

        msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (other) {
            if (other !== wrap) other.classList.remove('show-actions');
        });

        wrap.classList.toggle('show-actions');
    }, true);
})();
