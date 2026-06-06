


(function () {
    if (window.ChatZoneSubmitMessage) return;
    window.ChatZoneSubmitMessage = async function (event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        var form = document.getElementById('sendForm');
        if (!form || form.dataset.sending === '1') return false;
        form.dataset.sending = '1';
        try {
            var fd = new FormData(form);
            var input = document.getElementById('messageInput');
            var imageInput = document.getElementById('imageInput');
            var hasText = input && input.value.trim().length > 0;
            var hasImage = imageInput && imageInput.files && imageInput.files.length > 0;
            if (!hasText && !hasImage) return false;
            var res = await fetch(form.getAttribute('action') || 'ajax_send_message.php', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                cache: 'no-store'
            });
            var raw = await res.text();
            var data;
            try { data = JSON.parse(raw); } catch (e) {
                console.error('Send non-JSON response:', raw);
                alert('Send failed: server returned invalid response.');
                return false;
            }
            if (data && data.success) {
                if (input) input.value = '';
                if (imageInput) imageInput.value = '';
                if (typeof window.czRefreshPrivateMessages === 'function') setTimeout(function(){ window.czRefreshPrivateMessages({refreshSidebar:false}); }, 20);
                if (typeof window.czRefreshPrivateSidebarSoon === 'function') window.czRefreshPrivateSidebarSoon(900);
                var msgList = document.getElementById('msgList');
                if (msgList) { var b = msgList.style.scrollBehavior; msgList.style.scrollBehavior = 'auto'; msgList.scrollTop = msgList.scrollHeight; msgList.style.scrollBehavior = b; }
                return false;
            }
            alert((data && data.message) || 'Failed to send message.');
        } catch (err) {
            console.error('Bootstrap send failed:', err);
            alert('Send request failed. Check console.');
        } finally {
            form.dataset.sending = '0';
        }
        return false;
    };
})();
