// Main chat behavior: sending, refreshing, receipts, and typing updates.
function ChatZoneInit() {
    const cfg = window.ChatZoneConfig || {};
    const msgList = document.getElementById('msgList');
    const sendForm = document.getElementById('sendForm');
    const messageInput = document.getElementById('messageInput');
    const friendIdInput = document.getElementById('friendId');
    const replyToInput = document.getElementById('replyToId');
    const replyPreview = document.getElementById('replyPreview');
    const replyPreviewText = document.getElementById('replyPreviewText');
    const cancelReply = document.getElementById('cancelReply');
    const imageInput = document.getElementById('imageInput');
    const attachBtn = document.getElementById('attachBtn');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const previewName = document.getElementById('previewName');
    const removeImage = document.getElementById('removeImage');
    const voiceBtn = document.getElementById('voiceBtn');
    const voiceStatus = document.getElementById('voiceStatus');
    const typingStatus = document.getElementById('typingStatus');
    const chatHeaderStatusDot = document.getElementById('chatHeaderStatusDot');
    const messageSearchPanel = document.getElementById('messageSearchPanel');
    const openMessageSearch = document.getElementById('openMessageSearch');
    const closeMessageSearch = document.getElementById('closeMessageSearch');
    const messageSearchInput = document.getElementById('messageSearchInput');
    const messageSearchResults = document.getElementById('messageSearchResults');
    const reactionEmojis = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    const blockUserBtn = document.getElementById('blockUserBtn');
    const reportUserBtn = document.getElementById('reportUserBtn');
    const scheduleBtn = document.getElementById('scheduleBtn');
    const chatMoreBtn = document.getElementById('chatMoreBtn');
    const chatMoreMenu = document.getElementById('chatMoreMenu');

    
    
    
    let mobileSidebarEventsBound = false;

    initMobileSidebar();
    initChatSearch();
    initMessageSearch();
    initChatMoreMenu();
    bindSidebarThemeToggle();

    function initChatMoreMenu() {
        if (!chatMoreBtn || !chatMoreMenu) return;

        chatMoreBtn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            chatMoreMenu.classList.toggle('open');
            chatMoreMenu.setAttribute('aria-hidden', chatMoreMenu.classList.contains('open') ? 'false' : 'true');
        });

        chatMoreMenu.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        document.addEventListener('click', function () {
            chatMoreMenu.classList.remove('open');
            chatMoreMenu.setAttribute('aria-hidden', 'true');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                chatMoreMenu.classList.remove('open');
                chatMoreMenu.setAttribute('aria-hidden', 'true');
            }
        });
    }

    if (!msgList) return;

    const friendshipId = msgList.dataset.friendshipId;

    if (reportUserBtn) {
        reportUserBtn.addEventListener('click', async function () {
            const friendId = reportUserBtn.dataset.friendId || '';
            const reason = prompt('Why are you reporting this user?');
            if (!friendId || reason === null) return;
            const trimmed = reason.trim();
            if (!trimmed) return alert('Please write a short reason.');
            try {
                const formData = new FormData();
                formData.append('reported_user_id', friendId);
                formData.append('reason', trimmed);
                formData.append('csrf_token', cfg.csrfToken || '');
                const res = await fetch('report.php', { method: 'POST', body: formData, cache: 'no-store' });
                const data = await res.json();
                if (data.success) alert('Report sent. Thank you.');
                else alert(data.message || 'Failed to send report');
            } catch (err) {
                console.error('Report user error:', err);
                alert('Report failed. Check console.');
            }
        });
    }

    if (blockUserBtn) {
        blockUserBtn.addEventListener('click', async function () {
            const friendId = blockUserBtn.dataset.friendId || '';
            const isBlocked = blockUserBtn.dataset.blocked === '1';
            const question = isBlocked ? 'Unblock this user?' : 'Block this user? They will not be able to message you, and you will not be able to message them until you unblock.';
            if (!friendId || !confirm(question)) return;
            try {
                const formData = new FormData();
                formData.append('friend_id', friendId);
                formData.append('action', isBlocked ? 'unblock' : 'block');
                formData.append('csrf_token', cfg.csrfToken || '');
                const res = await fetch('block_user.php', { method: 'POST', body: formData, cache: 'no-store' });
                const data = await res.json();
                if (data.success) {
                    
                    if (blockUserBtn) {
                        blockUserBtn.dataset.blocked = isBlocked ? '0' : '1';
                        blockUserBtn.textContent = isBlocked ? 'Block user' : 'Unblock user';
                    }
                    await fetchMessages({ refreshSidebar: true });
                } else {
                    alert(data.message || 'Failed to update block status');
                }
            } catch (err) {
                console.error('Block user error:', err);
                alert('Block request failed. Check console.');
            }
        });
    }

    const friendPhoto = msgList.dataset.friendPhoto || 'default.png';
    const myPhoto = msgList.dataset.myPhoto || 'default.png';
    let defaultStatusText = typingStatus ? typingStatus.textContent : '';
    let typingTimer = null;
    let wsTypingHideTimer = null;
    let lastTypingWsState = false;
    let lastHtml = msgList.innerHTML;
    let loadedMessages = [];
    let hasMoreOlderMessages = msgList.dataset.hasMore === '1';
    let olderMessagesBusy = false;
    const pageLimit = Math.max(1, Math.min(200, Number(msgList.dataset.pageLimit || 50)));

    
    setScrollToBottomInstant();
    window.addEventListener('load', setScrollToBottomInstant, { once: true });
    const olderLoader = document.getElementById('olderMessagesLoader');
    let olderMessagesObserver = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let recordedVoiceBlob = null;
    let recordingStartedAt = 0;
    let recordingMimeType = 'audio/webm';
    let recordingReadyPromise = null;
    let resolveRecordingReady = null;


    function ensureMobileOverlay() {
        let mobileOverlay = document.getElementById('mobileOverlay');
        if (!mobileOverlay) {
            mobileOverlay = document.createElement('div');
            mobileOverlay.id = 'mobileOverlay';
            mobileOverlay.className = 'mobile-overlay';
            document.body.appendChild(mobileOverlay);
        }
        return mobileOverlay;
    }

    function openMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mobileOverlay = ensureMobileOverlay();
        if (!sidebar) return;
        sidebar.classList.add('open');
        document.body.classList.add('mobile-sidebar-open');
        mobileOverlay.classList.add('open');
        mobileOverlay.style.display = 'block';
    }

    function closeMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mobileOverlay = ensureMobileOverlay();
        if (sidebar) sidebar.classList.remove('open');
        document.body.classList.remove('mobile-sidebar-open');
        mobileOverlay.classList.remove('open');
        mobileOverlay.style.display = '';
    }

    function initMobileSidebar() {
        
        
        if (mobileSidebarEventsBound) return;
        mobileSidebarEventsBound = true;

        document.addEventListener('click', function (event) {
            const openBtn = event.target.closest('#openFriends, #openFriendsEmpty, .js-open-sidebar, .mobile-menu-btn');
            if (openBtn) {
                event.preventDefault();
                openMobileSidebar();
                return;
            }

            if (event.target && event.target.id === 'mobileOverlay') {
                closeMobileSidebar();
            }
        });
    }

    function initChatSearch() {
        const chatSearch = document.getElementById('chatSearch');
        const noSearchResults = document.getElementById('noSearchResults');
        if (!chatSearch) return;
        chatSearch.addEventListener('input', function () {
            const value = chatSearch.value.toLowerCase().trim();
            const items = document.querySelectorAll('.chat-item');
            let visibleCount = 0;
            items.forEach(function (item) {
                const text = item.dataset.search || item.textContent.toLowerCase();
                if (text.includes(value)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            if (noSearchResults) noSearchResults.style.display = visibleCount === 0 ? 'block' : 'none';
        });
    }


    function bindSidebarThemeToggle() {
        const btn = document.getElementById('themeToggle');
        if (!btn || btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        const apply = function (enabled) {
            document.body.classList.toggle('dark-mode', enabled);
            btn.innerHTML = enabled ? 
                '<img src="assets/img/sun.png" alt="☀️" />'
                : '<img src="assets/img/moon.png" alt="🌙" />';
            btn.title = enabled ? 'Switch to light mode' : 'Switch to dark mode';
            btn.setAttribute('aria-label', btn.title);
        };
        apply(localStorage.getItem('chatzone_dark_mode') === '1');
        btn.addEventListener('click', function () {
            const enabled = !document.body.classList.contains('dark-mode');
            localStorage.setItem('chatzone_dark_mode', enabled ? '1' : '0');
            apply(enabled);
        });
    }

    let sidebarRefreshBusy = false;
    async function refreshSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar || sidebarRefreshBusy) return;
        sidebarRefreshBusy = true;
        try {
            const url = `fetch_sidebar.php?friendship_id=${encodeURIComponent(friendshipId || '')}&_=${Date.now()}`;
            const res = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
            const html = await res.text();
            if (!res.ok || !html.includes('class="sidebar"')) return;

            const currentSearch = document.getElementById('chatSearch') ? document.getElementById('chatSearch').value : '';
            const wasOpen = sidebar.classList.contains('open') || document.body.classList.contains('mobile-sidebar-open');
            sidebar.outerHTML = html;
            const newSidebar = document.querySelector('.sidebar');
            if (newSidebar && wasOpen) {
                newSidebar.classList.add('open');
                document.body.classList.add('mobile-sidebar-open');
                const mobileOverlay = ensureMobileOverlay();
                mobileOverlay.classList.add('open');
                mobileOverlay.style.display = 'block';
            }
            initChatSearch();
            initMobileSidebar();
            bindSidebarThemeToggle();
            if (currentSearch && document.getElementById('chatSearch')) {
                const input = document.getElementById('chatSearch');
                input.value = currentSearch;
                input.dispatchEvent(new Event('input'));
            }
            if (typeof refreshPresenceStatus === 'function') refreshPresenceStatus();
        } catch (err) {
            console.error('Sidebar refresh error:', err);
        } finally {
            sidebarRefreshBusy = false;
        }
    }

    let sidebarRefreshTimer = null;
    function refreshSidebarSoon(delay) {
        clearTimeout(sidebarRefreshTimer);
        sidebarRefreshTimer = setTimeout(function () {
            refreshSidebar();
        }, typeof delay === 'number' ? delay : 150);
    }

    function initMessageSearch() {
        if (!messageSearchPanel || !openMessageSearch || !messageSearchInput || !messageSearchResults) return;
        let searchTimer = null;

        function openPanel() {
            messageSearchPanel.classList.add('open');
            messageSearchInput.focus();
        }

        function closePanel() {
            messageSearchPanel.classList.remove('open');
            messageSearchInput.value = '';
            messageSearchResults.innerHTML = '';
            clearMessageHighlight();
        }

        openMessageSearch.addEventListener('click', openPanel);
        if (closeMessageSearch) closeMessageSearch.addEventListener('click', closePanel);

        messageSearchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runMessageSearch, 250);
        });

        messageSearchResults.addEventListener('click', function (e) {
            const result = e.target.closest('.message-search-result');
            if (!result || !msgList) return;
            jumpToMessage(result.dataset.messageId);
        });
    }

    function clearMessageHighlight() {
        document.querySelectorAll('.msg-row.search-hit').forEach(function (row) {
            row.classList.remove('search-hit');
        });
    }

    function jumpToMessage(messageId) {
        clearMessageHighlight();
        const row = msgList ? msgList.querySelector(`.msg-row[data-message-id="${CSS.escape(String(messageId))}"]`) : null;
        if (!row) return;
        row.classList.add('search-hit');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { row.classList.remove('search-hit'); }, 2200);
    }

    async function runMessageSearch() {
        if (!messageSearchInput || !messageSearchResults || !friendshipId) return;
        const q = messageSearchInput.value.trim();
        if (q.length < 2) {
            messageSearchResults.innerHTML = '<div class="message-search-empty">Type at least 2 characters.</div>';
            return;
        }
        messageSearchResults.innerHTML = '<div class="message-search-empty">Searching...</div>';
        try {
            const url = `search_messages.php?friendship_id=${encodeURIComponent(friendshipId)}&q=${encodeURIComponent(q)}`;
            const res = await fetch(url, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) {
                messageSearchResults.innerHTML = `<div class="message-search-empty">${escapeHtml(data.message || 'Search failed')}</div>`;
                return;
            }
            if (!data.results || data.results.length === 0) {
                messageSearchResults.innerHTML = '<div class="message-search-empty">No messages found.</div>';
                return;
            }
            messageSearchResults.innerHTML = data.results.map(function (item) {
                return `<button type="button" class="message-search-result" data-message-id="${Number(item.id)}">
                    <strong>${escapeHtml(item.sender_name || 'User')}</strong>
                    <span>${escapeHtml(shortText(item.body, item.message_type === 'image' ? '[Image]' : (item.message_type === 'voice' ? '[Voice message]' : 'Message')))}</span>
                    <small>${escapeHtml(item.created_at || '')}</small>
                </button>`;
            }).join('');
        } catch (err) {
            console.error('Message search error:', err);
            messageSearchResults.innerHTML = '<div class="message-search-empty">Search failed.</div>';
        }
    }

    function isNearBottom() {
        if (!msgList) return true;
        return (msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight) < 160;
    }

    function setScrollToBottomInstant() {
        if (!msgList) return;
        const oldBehavior = msgList.style.scrollBehavior;
        msgList.style.scrollBehavior = 'auto';
        msgList.scrollTop = msgList.scrollHeight;
        requestAnimationFrame(function () {
            msgList.scrollTop = msgList.scrollHeight;
            msgList.classList.remove('initial-bottom-pending');
            msgList.dataset.initialBottomReady = '1';
            msgList.style.scrollBehavior = oldBehavior;
        });
        setTimeout(function () {
            const oldBehavior2 = msgList.style.scrollBehavior;
            msgList.style.scrollBehavior = 'auto';
            msgList.scrollTop = msgList.scrollHeight;
            msgList.classList.remove('initial-bottom-pending');
            msgList.style.scrollBehavior = oldBehavior2;
        }, 80);
    }

    function scrollToBottom() {
        setScrollToBottomInstant();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function nl2br(text) { return escapeHtml(text).replace(/\n/g, '<br>'); }

    function shortText(text, fallback) {
        const clean = String(text || '').replace(/\s+/g, ' ').trim();
        if (!clean) return fallback || 'Message';
        return clean.length > 80 ? clean.slice(0, 80) + '…' : clean;
    }

    function hasSelectedImage() { return imageInput && imageInput.files && imageInput.files.length > 0; }

    function hasRecordedVoice() { return recordedVoiceBlob instanceof Blob && recordedVoiceBlob.size > 0; }

    function clearImagePreview() {
        if (!imageInput) return;
        imageInput.value = '';
        if (imagePreview) imagePreview.style.display = 'none';
        if (previewImg) previewImg.src = '';
        if (previewName) previewName.textContent = '';
    }

    function setReply(messageId, body, type) {
        if (replyToInput) replyToInput.value = messageId || '';
        if (replyPreviewText) replyPreviewText.textContent = shortText(body, type === 'image' ? '[Image]' : 'Message');
        if (replyPreview) replyPreview.style.display = messageId ? 'flex' : 'none';
        if (messageInput) messageInput.focus();
    }

    function clearReply() { setReply('', '', ''); }

    if (cancelReply) cancelReply.addEventListener('click', clearReply);

    function localDateTimeDefault(minutesFromNow) {
        const d = new Date(Date.now() + (minutesFromNow || 5) * 60000);
        d.setSeconds(0, 0);
        const pad = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    async function scheduleCurrentMessage() {
        const body = messageInput ? messageInput.value.trim() : '';
        const friendId = friendIdInput ? friendIdInput.value : '';
        if (!friendId) return;
        if (!body) return alert('Write the text message first, then click schedule.');
        if (hasSelectedImage() || hasRecordedVoice() || (mediaRecorder && mediaRecorder.state === 'recording')) {
            return alert('Scheduled messages support text only for now.');
        }
        const when = prompt('Send this message at?\nFormat: YYYY-MM-DD HH:MM', localDateTimeDefault(5));
        if (when === null) return;
        const scheduledAt = when.trim();
        if (!scheduledAt) return;
        try {
            const formData = new FormData();
            formData.append('friend_id', friendId);
            formData.append('body', body);
            formData.append('scheduled_at', scheduledAt);
            formData.append('csrf_token', cfg.csrfToken || '');
            const res = await fetch('schedule_message.php', { method: 'POST', body: formData, cache: 'no-store' });
            const data = await res.json();
            if (data.success) {
                if (messageInput) messageInput.value = '';
                alert('Message scheduled for ' + (data.scheduled_at || scheduledAt));
            } else {
                alert(data.message || 'Failed to schedule message');
            }
        } catch (err) {
            console.error('Schedule message error:', err);
            alert('Schedule request failed.');
        }
    }

    async function processScheduledMessages() {
        try {
            const res = await fetch('process_scheduled_messages.php', { cache: 'no-store' });
            const data = await res.json();
            if (data.success && Number(data.processed) > 0) {
                await fetchMessages();
            }
        } catch (err) {
            console.error('Process scheduled messages error:', err);
        }
    }

    window.ChatZoneScheduleMessage = scheduleCurrentMessage;
    if (scheduleBtn) {
        scheduleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            scheduleCurrentMessage();
        });
    }

    if (attachBtn && imageInput) attachBtn.addEventListener('click', function () { imageInput.click(); });

    if (imageInput) {
        imageInput.addEventListener('change', function () {
            const file = imageInput.files[0];
            if (!file) return clearImagePreview();
            if (!file.type.startsWith('image/')) {
                alert('Please choose an image file');
                return clearImagePreview();
            }
            if (file.size > 5 * 1024 * 1024) {
                alert('Image must be less than 5MB');
                return clearImagePreview();
            }
            const reader = new FileReader();
            reader.onload = function (e) {
                if (previewImg) previewImg.src = e.target.result;
                if (previewName) previewName.textContent = file.name;
                if (imagePreview) imagePreview.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        });
    }

    if (removeImage) removeImage.addEventListener('click', clearImagePreview);

    function clearVoiceRecording() {
        recordedVoiceBlob = null;
        recordedChunks = [];
        recordingReadyPromise = null;
        resolveRecordingReady = null;
        if (voiceStatus) voiceStatus.textContent = '';
        if (voiceBtn) {
            voiceBtn.classList.remove('recording', 'ready');
            voiceBtn.textContent = '🎙';
            voiceBtn.title = 'Record voice';
        }
    }

    function setVoiceStatus(text) {
        if (voiceStatus) voiceStatus.textContent = text || '';
    }

    async function startVoiceRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || typeof MediaRecorder === 'undefined') {
            alert('Voice recording is not supported in this browser.');
            return;
        }
        clearImagePreview();
        clearVoiceRecording();
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const supportedTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
            recordingMimeType = supportedTypes.find(function (type) {
                return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(type);
            }) || '';
            mediaRecorder = recordingMimeType ? new MediaRecorder(stream, { mimeType: recordingMimeType }) : new MediaRecorder(stream);
            recordedChunks = [];
            recordingStartedAt = Date.now();
            recordingReadyPromise = new Promise(function (resolve) { resolveRecordingReady = resolve; });
            mediaRecorder.ondataavailable = function (event) {
                if (event.data && event.data.size > 0) recordedChunks.push(event.data);
            };
            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(function (track) { track.stop(); });
                const duration = Math.max(1, Math.round((Date.now() - recordingStartedAt) / 1000));
                const finalMimeType = mediaRecorder.mimeType || recordingMimeType || (recordedChunks[0] && recordedChunks[0].type) || 'audio/webm';
                recordedVoiceBlob = new Blob(recordedChunks, { type: finalMimeType });
                if (recordedVoiceBlob.size <= 0) {
                    alert('Voice recording is empty. Please try again.');
                    if (resolveRecordingReady) resolveRecordingReady(false);
                    clearVoiceRecording();
                    return;
                }
                if (recordedVoiceBlob.size > 10 * 1024 * 1024) {
                    alert('Voice message must be less than 10MB');
                    if (resolveRecordingReady) resolveRecordingReady(false);
                    clearVoiceRecording();
                    return;
                }
                if (voiceBtn) {
                    voiceBtn.classList.remove('recording');
                    voiceBtn.classList.add('ready');
                    voiceBtn.textContent = '✅';
                    voiceBtn.title = 'Voice ready. Press send.';
                }
                setVoiceStatus('Voice ready (' + duration + 's)');
                if (resolveRecordingReady) resolveRecordingReady(true);
                recordingReadyPromise = null;
                resolveRecordingReady = null;
            };
            
            
            
            mediaRecorder.start(250);
            if (voiceBtn) {
                voiceBtn.classList.add('recording');
                voiceBtn.textContent = '■';
                voiceBtn.title = 'Stop recording';
            }
            setVoiceStatus('Recording... click stop');
        } catch (err) {
            console.error('Voice recording error:', err);
            alert('Could not access microphone.');
            clearVoiceRecording();
        }
    }

    function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }

    async function waitForVoiceReady(maxMs = 1200) {
        if (hasRecordedVoice()) return true;
        if (recordingReadyPromise && mediaRecorder && mediaRecorder.state === 'inactive') {
            setVoiceStatus('Preparing voice...');
            await Promise.race([recordingReadyPromise, wait(maxMs)]);
        }
        return hasRecordedVoice();
    }

    async function stopVoiceRecordingAndWait() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            try { mediaRecorder.requestData(); } catch (err) {}
            mediaRecorder.stop();
            setVoiceStatus('Preparing voice...');
            if (recordingReadyPromise) await Promise.race([recordingReadyPromise, wait(1200)]);
        }
        return hasRecordedVoice();
    }

    function stopVoiceRecording() {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            try { mediaRecorder.requestData(); } catch (err) {}
            mediaRecorder.stop();
            setVoiceStatus('Preparing voice...');
        }
    }

    if (voiceBtn) {
        voiceBtn.addEventListener('click', async function () {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopVoiceRecording();
                return;
            }

            if (hasRecordedVoice()) {
                
                document.addEventListener('click', function (e) {
        if (!msgList || msgList.contains(e.target)) return;
        msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (wrap) {
            wrap.classList.remove('show-actions');
        });
    });

    if (sendForm) {
                    if (typeof sendForm.requestSubmit === 'function') {
                        sendForm.requestSubmit();
                    } else {
                        sendForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    }
                } else {
                    alert('Send form not found. Voice cannot be sent.');
                }
                return;
            }

            await startVoiceRecording();
        });
    }

    function renderReplyBlock(message) {
        if (!message.reply_to_id) return '';
        const sender = message.reply_sender_name || 'Message';
        const replyText = message.reply_message_type === 'image' ? '[Image]' : (message.reply_message_type === 'voice' ? '[Voice message]' : shortText(message.reply_body, 'Deleted message'));
        return `<div class="reply-block"><strong>${escapeHtml(sender)}</strong><span>${escapeHtml(replyText)}</span></div>`;
    }

    function voiceMimeType(path) {
        const clean = String(path || '').split('?')[0].toLowerCase();
        if (clean.endsWith('.ogg')) return 'audio/ogg';
        if (clean.endsWith('.mp3')) return 'audio/mpeg';
        if (clean.endsWith('.m4a') || clean.endsWith('.mp4')) return 'audio/mp4';
        if (clean.endsWith('.wav')) return 'audio/wav';
        return 'audio/webm';
    }

    function fixVoicePlayerMetadata(root) {
        const players = (root || document).querySelectorAll('audio.voice-player, audio.group-audio');
        players.forEach(function (player) {
            if (player.dataset.voiceFixed === '1') return;
            player.dataset.voiceFixed = '1';
            player.addEventListener('loadedmetadata', function () {
                if (player.duration === Infinity || Number.isNaN(player.duration)) {
                    const oldTime = player.currentTime;
                    player.currentTime = 10000000;
                    player.addEventListener('timeupdate', function fixOnce() {
                        player.removeEventListener('timeupdate', fixOnce);
                        player.currentTime = oldTime || 0;
                    }, { once: true });
                }
            });
            player.load();
        });
    }

    function renderMessageBody(message) {
        if (Number(message.is_deleted) === 1) {
            return `<div class="deleted-message">This message was deleted</div>`;
        }
        const type = message.message_type || 'text';
        if (type === 'voice' && message.file_path) {
            const voicePath = escapeHtml(message.file_path);
            const voiceType = escapeHtml(voiceMimeType(message.file_path));
            let html = `<audio class="voice-player" controls preload="metadata" src="${voicePath}" type="${voiceType}"></audio>`;
            if (message.body) html += `<div class="image-caption">${nl2br(message.body)}</div>`;
            return html;
        }
        if (type === 'image' && message.file_path) {
            let html = `<a href="${escapeHtml(message.file_path)}" target="_blank"><img class="chat-image" src="${escapeHtml(message.file_path)}" alt="Image message"></a>`;
            if (message.body) html += `<div class="image-caption">${nl2br(message.body)}</div>`;
            return html;
        }
        return `<div>${nl2br(message.body)}</div>`;
    }


    const receiptRankCache = new Map();

    function receiptRank(message) {
        if (!message) return 0;
        if (Number(message.is_seen) === 1 || !!message.read_at || !!message.seen_at) return 2;
        if (!!message.delivered_at) return 1;
        return 0;
    }

    function mergeReceiptFields(oldMessage, newMessage) {
        if (!oldMessage) return newMessage;
        const oldRank = receiptRank(oldMessage);
        const newRank = receiptRank(newMessage);
        if (oldRank > newRank) {
            newMessage.is_seen = oldMessage.is_seen;
            newMessage.seen_at = oldMessage.seen_at;
            newMessage.delivered_at = oldMessage.delivered_at;
            newMessage.read_at = oldMessage.read_at;
        }
        return newMessage;
    }

    function rememberReceiptRank(message) {
        const id = Number(message && message.id ? message.id : 0);
        if (!id) return receiptRank(message);
        const rank = receiptRank(message);
        const cached = receiptRankCache.get(id) || 0;
        const finalRank = Math.max(rank, cached);
        receiptRankCache.set(id, finalRank);
        return finalRank;
    }

    function renderReceiptStatus(message) {
        const rank = rememberReceiptRank(message);
        if (rank >= 2) return '<div class="seen-status receipt-status read">✓✓ Read</div>';
        if (rank >= 1) return '<div class="seen-status receipt-status delivered">✓✓ Delivered</div>';
        return '<div class="seen-status receipt-status sent">✓ Sent</div>';
    }

    function renderReactions(message) {
        if (!message.reactions_summary) return '';
        const parts = String(message.reactions_summary).split('|').filter(Boolean);
        if (!parts.length) return '';
        const items = parts.map(function (part) {
            const idx = part.lastIndexOf(':');
            if (idx === -1) return '';
            const emoji = part.slice(0, idx);
            const count = part.slice(idx + 1);
            const active = message.my_reaction === emoji ? ' active' : '';
            return `<button class="reaction-chip${active}" data-message-id="${Number(message.id)}" data-emoji="${escapeHtml(emoji)}">${escapeHtml(emoji)} ${Number(count)}</button>`;
        }).join('');
        return `<div class="reaction-summary">${items}</div>`;
    }

    function renderActionBar(message, isMe) {
        if (Number(message.is_deleted) === 1) return '';
        const buttons = reactionEmojis.map(function (emoji) {
            const active = message.my_reaction === emoji ? ' active' : '';
            return `<button class="react-btn${active}" data-message-id="${Number(message.id)}" data-emoji="${escapeHtml(emoji)}" title="React">${escapeHtml(emoji)}</button>`;
        }).join('');
        const editButton = (isMe && (message.message_type || 'text') === 'text')
            ? `<button class="edit-msg-btn" data-message-id="${Number(message.id)}" data-message-body="${escapeHtml(message.body || '')}" title="Edit">✎</button>`
            : '';
        const pinActive = Number(message.is_pinned) === 1 ? ' active' : '';
        const pinTitle = Number(message.is_pinned) === 1 ? 'Unpin message' : 'Pin message';
        const pinButton = `<button class="pin-msg-btn${pinActive}" data-message-id="${Number(message.id)}" title="${pinTitle}">📌</button>`;
        const starActive = Number(message.is_starred) === 1 ? ' active' : '';
        const starTitle = Number(message.is_starred) === 1 ? 'Unstar message' : 'Star message';
        const starButton = `<button class="star-msg-btn${starActive}" data-message-id="${Number(message.id)}" title="${starTitle}">⭐</button>`;
        const forwardButton = `<button class="forward-msg-btn" data-source-type="private" data-message-id="${Number(message.id)}" title="Forward">➡</button>`;
        const reportButton = `<button class="report-msg-btn" data-message-id="${Number(message.id)}" title="Report message">⚠️</button>`;
        return `<div class="msg-actions"><button class="reply-btn" data-message-id="${Number(message.id)}" data-message-body="${escapeHtml(message.body || '')}" data-message-type="${escapeHtml(message.message_type || 'text')}" title="Reply">↩</button>${editButton}${pinButton}${starButton}${forwardButton}${reportButton}${buttons}</div>`;
    }

    function mergeMessages(existing, incoming, prepend) {
        const map = new Map();
        const ordered = prepend ? incoming.concat(existing) : incoming;
        ordered.forEach(function (message) {
            const id = Number(message.id);
            map.set(id, mergeReceiptFields(map.get(id), message));
        });
        return Array.from(map.values()).sort(function (a, b) {
            const ca = String(a.created_at || '');
            const cb = String(b.created_at || '');
            if (ca < cb) return -1;
            if (ca > cb) return 1;
            return Number(a.id) - Number(b.id);
        });
    }

    function renderMessages(messages, myId, options) {
        options = options || {};
        const preserveScrollTop = !!options.preserveScrollTop;
        const oldScrollHeight = msgList.scrollHeight;
        const oldScrollTop = msgList.scrollTop;

        if (!Array.isArray(messages) || messages.length === 0) {
            msgList.innerHTML = `<div class="older-messages-loader" id="olderMessagesLoader" data-loading="0"><button type="button" id="loadOlderMessagesBtn">Load older messages</button><span class="older-loading-text" style="display:none;">Loading older messages...</span></div><div class="empty-state">${escapeHtml(cfg.emptyChat || 'No messages yet.')}</div>`;
            lastHtml = msgList.innerHTML;
            return;
        }
        let html = '';
        if (hasMoreOlderMessages) {
            html += '<div class="older-messages-loader" id="olderMessagesLoader" data-loading="0"><button type="button" id="loadOlderMessagesBtn">Load older messages</button><span class="older-loading-text" style="display:none;">Loading older messages...</span></div>';
        }
        let lastMyMessageId = null;
        messages.forEach(function (message) {
            if (Number(message.sender_id) === Number(myId)) lastMyMessageId = Number(message.id);
        });
        messages.forEach(function (message) {
            const isMe = Number(message.sender_id) === Number(myId);
            const isLastMyMessage = isMe && Number(message.id) === lastMyMessageId;
            html += `<div class="msg-row ${isMe ? 'me' : 'him'}" data-message-id="${Number(message.id)}">`;
            if (!isMe) html += `<img class="message-avatar" src="uploads/profiles/${escapeHtml(friendPhoto)}" alt="Friend">`;
            html += `<div class="msg-wrap">`;
            if (Number(message.is_deleted) !== 1) html += `<button class="mobile-msg-menu-btn" type="button" title="Message actions" aria-label="Message actions">⋯</button>`;
            if (isMe && Number(message.is_deleted) !== 1) html += `<button class="delete-msg-btn" data-message-id="${Number(message.id)}" title="Delete for everyone">×</button>`;
            html += renderActionBar(message, isMe);
            const pinnedClass = Number(message.is_pinned) === 1 ? ' pinned' : '';
            const starredClass = Number(message.is_starred) === 1 ? ' starred' : '';
            const pinnedBadge = Number(message.is_pinned) === 1 ? '<div class="pinned-badge">📌 Pinned</div>' : '';
            const starBadge = Number(message.is_starred) === 1 ? '<div class="starred-badge">⭐ Starred</div>' : '';
            const forwardedBadge = Number(message.is_forwarded) === 1 ? '<div class="forwarded-badge">➡ Forwarded</div>' : '';
            html += `<div class="msg ${isMe ? 'me' : 'him'}${pinnedClass}${starredClass}">${pinnedBadge}${starBadge}${forwardedBadge}${renderReplyBlock(message)}${renderMessageBody(message)}<div class="msg-time">${escapeHtml(message.created_at)}${message.edited_at ? ' · edited' : ''}</div></div>`;
            if (Number(message.is_deleted) !== 1) html += renderReactions(message);
            if (isLastMyMessage) html += renderReceiptStatus(message);
            html += `</div>`;
            if (isMe) html += `<img class="message-avatar" src="uploads/profiles/${escapeHtml(myPhoto)}" alt="Me">`;
            html += `</div>`;
        });
        const wasNearBottom = msgList.scrollHeight - msgList.scrollTop - msgList.clientHeight < 120;
        msgList.innerHTML = html;
        if (preserveScrollTop) {
            const diff = msgList.scrollHeight - oldScrollHeight;
            msgList.scrollTop = oldScrollTop + diff;
        } else if (html !== lastHtml || wasNearBottom) {
            scrollToBottom();
        }
        lastHtml = html;
        fixVoicePlayerMetadata(msgList);
        watchOlderMessagesLoader();
    }

    function setOlderLoaderLoading(isLoading) {
        const loader = document.getElementById('olderMessagesLoader');
        if (!loader) return;
        loader.dataset.loading = isLoading ? '1' : '0';
        const btn = loader.querySelector('#loadOlderMessagesBtn');
        const text = loader.querySelector('.older-loading-text');
        if (btn) btn.style.display = isLoading ? 'none' : 'inline-flex';
        if (text) text.style.display = isLoading ? 'inline' : 'none';
    }

    function watchOlderMessagesLoader() {
        const loader = document.getElementById('olderMessagesLoader');
        if (!loader || !hasMoreOlderMessages) return;
        if (olderMessagesObserver) olderMessagesObserver.disconnect();
        if ('IntersectionObserver' in window) {
            olderMessagesObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting && msgList.scrollTop <= 160) loadOlderMessages();
                });
            }, { root: msgList, threshold: 0.01 });
            olderMessagesObserver.observe(loader);
        }
    }

    function isAtTopOfMessages() {
        if (!msgList) return false;
        return msgList.scrollTop <= 160;
    }

    async function updateTyping(isTyping) {
        if (!friendshipId || Number(friendshipId) <= 0) return;

        
        
        if (typeof window.czWsSendPrivateTyping === 'function') {
            window.czWsSendPrivateTyping(!!isTyping, cfg.myDisplayName || '');
        }

        try {
            const formData = new FormData();
            formData.append('friendship_id', friendshipId);
            formData.append('is_typing', isTyping ? '1' : '0');
            formData.append('csrf_token', cfg.csrfToken || '');
            
            fetch('update_typing.php', { method: 'POST', body: formData, credentials: 'same-origin', cache: 'no-store' }).catch(function () {});
        } catch (err) { console.error('Typing update error:', err); }
    }

    async function checkTyping() {
        if (!friendshipId || Number(friendshipId) <= 0 || !typingStatus) return;
        try {
            const res = await fetch(`check_typing.php?friendship_id=${encodeURIComponent(friendshipId)}`, { cache: 'no-store' });
            const data = await res.json();
            const isTypingNow = data.success && data.is_typing;
            if (!lastTypingWsState) {
                typingStatus.textContent = isTypingNow ? (cfg.typingText || 'typing...') : defaultStatusText;
                typingStatus.classList.toggle('is-typing', isTypingNow);
            }
        } catch (err) { console.error('Typing check error:', err); }
    }


    window.czOnPrivateTyping = function (isTyping) {
        if (!typingStatus) return;
        clearTimeout(wsTypingHideTimer);
        lastTypingWsState = !!isTyping;
        if (isTyping) {
            typingStatus.textContent = cfg.typingText || 'typing...';
            typingStatus.classList.add('is-typing');
            wsTypingHideTimer = setTimeout(function () {
                lastTypingWsState = false;
                typingStatus.textContent = defaultStatusText;
                typingStatus.classList.remove('is-typing');
            }, 3000);
        } else {
            typingStatus.textContent = defaultStatusText;
            typingStatus.classList.remove('is-typing');
        }
    };

    function setPresenceDot(dot, isOnline) {
        if (!dot) return;
        const online = !!isOnline;
        dot.classList.toggle('online', online);
        dot.classList.toggle('offline', !online);
        dot.dataset.presence = online ? 'online' : 'offline';
        dot.setAttribute('aria-label', online ? 'Online' : 'Offline');
        
        dot.style.backgroundColor = online ? '#25d366' : '#9e9e9e';
        dot.style.boxShadow = online ? '0 0 0 3px rgba(37, 211, 102, 0.22)' : 'none';
    }

    function applyPresenceData(data) {
        if (!data || !data.success) return;
        const isOnline = !!data.is_online;
        defaultStatusText = data.status_text || defaultStatusText;
        if (typingStatus && !typingStatus.classList.contains('is-typing')) {
            typingStatus.textContent = defaultStatusText;
            typingStatus.dataset.defaultStatus = defaultStatusText;
        }
        setPresenceDot(chatHeaderStatusDot, isOnline);
        document.querySelectorAll(`.chat-list-status-dot[data-friendship-id="${CSS.escape(String(friendshipId))}"]`).forEach(function (dot) {
            setPresenceDot(dot, isOnline);
        });
    }

    async function refreshPresenceStatus() {
        if (!friendshipId || Number(friendshipId) <= 0) return;
        try {
            const res = await fetch(`get_presence_status.php?friendship_id=${encodeURIComponent(friendshipId)}&_=${Date.now()}`, { cache: 'no-store', credentials: 'same-origin' });
            const data = await res.json();
            applyPresenceData(data);
        } catch (err) {
            console.error('Presence refresh error:', err);
        }
    }

    window.czRefreshPresenceStatus = refreshPresenceStatus;
    setTimeout(refreshPresenceStatus, 250);
    window.addEventListener('focus', refreshPresenceStatus);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshPresenceStatus();
    });

    function isVoicePlaying() {
        const players = msgList ? msgList.querySelectorAll('audio.voice-player') : [];
        for (const player of players) {
            if (!player.paused && !player.ended) return true;
        }
        return false;
    }

    let lastReadReceiptBroadcastAt = 0;

    function shouldBroadcastReadReceipt(messages, myId) {
        if (!Array.isArray(messages) || !messages.length) return false;
        const now = Date.now();
        if (now - lastReadReceiptBroadcastAt < 800) return false;

        
        
        return messages.some(function (message) {
            return Number(message.sender_id || 0) !== Number(myId || 0)
                && (Number(message.is_seen || 0) === 1 || !!message.read_at || !!message.seen_at);
        });
    }

    function broadcastReadReceiptIfNeeded(messages, myId) {
        if (!shouldBroadcastReadReceipt(messages, myId)) return;
        lastReadReceiptBroadcastAt = Date.now();
        if (typeof window.czWsNotifyPrivate === 'function') {
            window.czWsNotifyPrivate('read_receipts');
        }
    }

    async function fetchMessages(options) {
        if (!friendshipId || Number(friendshipId) <= 0) return;
        options = options || {};

        
        
        if (isVoicePlaying()) return;

        try {
            const currentCount = Math.max(pageLimit, Math.min(200, loadedMessages.length || pageLimit));
            const res = await fetch(`fetch_messages.php?friendship_id=${encodeURIComponent(friendshipId)}&limit=${encodeURIComponent(currentCount)}&_=${Date.now()}`, { cache: 'no-store' });
            const data = await res.json();
            if (data.success) {
                const shouldStickToBottom = isNearBottom();
                const freshMessages = Array.isArray(data.messages) ? data.messages : [];
                loadedMessages = mergeMessages(loadedMessages, freshMessages, false);
                hasMoreOlderMessages = !!data.has_more;
                renderMessages(loadedMessages, data.my_id, { preserveScrollTop: options.preserveScrollTop && !shouldStickToBottom });
                if (options.refreshSidebar) refreshSidebarSoon(150);
                broadcastReadReceiptIfNeeded(freshMessages, data.my_id);
                if (shouldStickToBottom) scrollToBottom();
            }
        } catch (err) { console.error('Fetch messages error:', err); }
    }

    async function loadOlderMessages() {
        if (!friendshipId || Number(friendshipId) <= 0 || olderMessagesBusy || !hasMoreOlderMessages) return;
        const firstRow = msgList.querySelector('.msg-row[data-message-id]');
        if (!firstRow) return;
        olderMessagesBusy = true;
        setOlderLoaderLoading(true);
        try {
            const beforeId = Number(firstRow.dataset.messageId || 0);
            const res = await fetch(`fetch_messages.php?friendship_id=${encodeURIComponent(friendshipId)}&before_id=${encodeURIComponent(beforeId)}&limit=${encodeURIComponent(pageLimit)}`, { cache: 'no-store' });
            const data = await res.json();
            if (data.success) {
                const older = Array.isArray(data.messages) ? data.messages : [];
                hasMoreOlderMessages = !!data.has_more;
                if (older.length) {
                    loadedMessages = mergeMessages(loadedMessages, older, true);
                    renderMessages(loadedMessages, data.my_id, { preserveScrollTop: true });
                }
            }
        } catch (err) {
            console.error('Load older messages error:', err);
        } finally {
            olderMessagesBusy = false;
            setOlderLoaderLoading(false);
            watchOlderMessagesLoader();
        }
    }

    msgList.addEventListener('scroll', function () {
        if (isAtTopOfMessages()) loadOlderMessages();
    }, { passive: true });

    
    document.addEventListener('scroll', function () {
        if (isAtTopOfMessages()) loadOlderMessages();
    }, { passive: true, capture: true });

    async function reactToMessage(messageId, emoji) {
        try {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('emoji', emoji);
            formData.append('csrf_token', cfg.csrfToken || '');
            const res = await fetch('react_message.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) await fetchMessages();
            else alert(data.message || 'Failed to react');
        } catch (err) { console.error('Reaction error:', err); }
    }

    msgList.addEventListener('click', async function (e) {
        if (e.target.closest('#loadOlderMessagesBtn')) {
            e.preventDefault();
            await loadOlderMessages();
            return;
        }
        const mobileMenuBtn = e.target.closest('.mobile-msg-menu-btn');
        if (mobileMenuBtn) {
            e.preventDefault();
            e.stopPropagation();
            const wrap = mobileMenuBtn.closest('.msg-wrap');
            if (wrap) {
                msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (other) {
                    if (other !== wrap) other.classList.remove('show-actions');
                });
                wrap.classList.toggle('show-actions');
            }
            return;
        }

        
        
        const clickedInteractive = e.target.closest('button, a, input, select, textarea, audio, video, .reaction-chip, .msg-actions');
        const clickedWrap = e.target.closest('.msg-wrap');
        const isTouchLayout = window.matchMedia('(max-width: 850px)').matches || window.matchMedia('(hover: none) and (pointer: coarse)').matches;

        if (isTouchLayout && clickedWrap && !clickedInteractive) {
            e.preventDefault();
            msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (wrap) {
                if (wrap !== clickedWrap) wrap.classList.remove('show-actions');
            });
            clickedWrap.classList.toggle('show-actions');
            return;
        }

        const reactBtn = e.target.closest('.react-btn, .reaction-chip');
        if (reactBtn) {
            e.preventDefault();
            await reactToMessage(reactBtn.dataset.messageId, reactBtn.dataset.emoji);
            return;
        }

        const replyBtn = e.target.closest('.reply-btn');
        if (replyBtn) {
            e.preventDefault();
            setReply(replyBtn.dataset.messageId, replyBtn.dataset.messageBody, replyBtn.dataset.messageType);
            return;
        }

        const pinBtn = e.target.closest('.pin-msg-btn');
        if (pinBtn) {
            e.preventDefault();
            try {
                const formData = new FormData();
                formData.append('message_id', pinBtn.dataset.messageId);
                formData.append('csrf_token', cfg.csrfToken || '');
                const res = await fetch('pin_message.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) await fetchMessages();
                else alert(data.message || 'Failed to pin message');
            } catch (err) { console.error('Pin message error:', err); }
            return;
        }

        const starBtn = e.target.closest('.star-msg-btn');
        if (starBtn) {
            e.preventDefault();
            try {
                const formData = new FormData();
                formData.append('message_id', starBtn.dataset.messageId);
                formData.append('csrf_token', cfg.csrfToken || '');
                formData.append('friendship_id', friendshipId || '');
                const res = await fetch('star_message.php', { method: 'POST', body: formData, cache: 'no-store' });
                const raw = await res.text();
                let data;
                try { data = JSON.parse(raw); } catch (parseErr) {
                    console.error('Star raw response:', raw);
                    alert('Star failed: invalid server response. Check Console.');
                    return;
                }
                console.log('STAR RESPONSE:', data);
                if (data.success) {
                    const row = starBtn.closest('.msg-row');
                    const bubble = row ? row.querySelector('.msg') : null;
                    starBtn.classList.toggle('active', !!data.starred);
                    if (bubble) bubble.classList.toggle('starred', !!data.starred);
                    await fetchMessages();
                }
                else alert(data.message || 'Failed to star message');
            } catch (err) { console.error('Star message error:', err); alert('Star error: ' + err.message); }
            return;
        }


        const reportMsgBtn = e.target.closest('.report-msg-btn');
        if (reportMsgBtn) {
            e.preventDefault();
            const reason = prompt('Why are you reporting this message?');
            if (reason === null) return;
            const trimmed = reason.trim();
            if (!trimmed) return alert('Please write a short reason.');
            try {
                const formData = new FormData();
                formData.append('message_id', reportMsgBtn.dataset.messageId);
                formData.append('friendship_id', friendshipId || '');
                formData.append('reason', trimmed);
                formData.append('csrf_token', cfg.csrfToken || '');
                const res = await fetch('report.php', { method: 'POST', body: formData, cache: 'no-store' });
                const data = await res.json();
                if (data.success) alert('Report sent. Thank you.');
                else alert(data.message || 'Failed to send report');
            } catch (err) {
                console.error('Report message error:', err);
                alert('Report failed. Check console.');
            }
            return;
        }

        const editBtn = e.target.closest('.edit-msg-btn');
        if (editBtn) {
            e.preventDefault();
            const currentBody = editBtn.dataset.messageBody || '';
            const newBody = prompt(cfg.editMessagePrompt || 'Edit message:', currentBody);
            if (newBody === null) return;
            const trimmed = newBody.trim();
            if (!trimmed || trimmed === currentBody.trim()) return;
            try {
                const formData = new FormData();
                formData.append('message_id', editBtn.dataset.messageId);
                formData.append('body', trimmed);
                formData.append('csrf_token', cfg.csrfToken || '');
                const res = await fetch('edit_message.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) await fetchMessages();
                else alert(data.message || 'Failed to edit message');
            } catch (err) { console.error('Edit message error:', err); }
            return;
        }

        const btn = e.target.closest('.delete-msg-btn');
        if (!btn) return;
        const messageId = btn.dataset.messageId;
        if (!messageId) return;
        if (!confirm(cfg.deleteMessageConfirm || 'Delete this message for everyone?')) return;
        try {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('csrf_token', cfg.csrfToken || '');
            const res = await fetch('delete_message.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                await fetchMessages();
                scrollToBottom();
            } else {
                alert(data.message || cfg.failedDeleteMessage || 'Failed to delete message');
            }
        } catch (err) { console.error('Delete message error:', err); }
    });

    document.addEventListener('click', function (e) {
        if (!msgList || msgList.contains(e.target)) return;
        msgList.querySelectorAll('.msg-wrap.show-actions').forEach(function (wrap) {
            wrap.classList.remove('show-actions');
        });
    });

    if (sendForm) {
        if (messageInput) {
            messageInput.addEventListener('input', function () {
                updateTyping(true);
                clearTimeout(typingTimer);
                typingTimer = setTimeout(function () { updateTyping(false); }, 1500);
            });
            messageInput.addEventListener('blur', function () {
                clearTimeout(typingTimer);
                updateTyping(false);
            });
        }
        let sendingMessage = false;
        async function submitChatMessage(e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            if (sendingMessage) return false;
            sendingMessage = true;
            try {
                const body = messageInput ? messageInput.value.trim() : '';
                const friendId = friendIdInput ? friendIdInput.value : '';
                await stopVoiceRecordingAndWait();
                if (!friendId) return false;
                if (voiceStatus && voiceStatus.textContent && !hasRecordedVoice() && !hasSelectedImage() && !body) {
                    await waitForVoiceReady();
                }
                if (!body && !hasSelectedImage() && !hasRecordedVoice()) {
                    if (voiceStatus && voiceStatus.textContent) {
                        alert('Voice is still preparing or empty. Please wait until it says Voice ready, then press Send.');
                    }
                    return false;
                }
                const formData = new FormData();
                formData.append('friend_id', friendId);
                formData.append('body', body);
                formData.append('reply_to_id', replyToInput ? replyToInput.value : '');
                formData.append('csrf_token', cfg.csrfToken || '');
                if (hasSelectedImage()) formData.append('image', imageInput.files[0]);
                if (hasRecordedVoice()) {
                    const ext = recordedVoiceBlob.type.includes('mp4') ? 'm4a' : (recordedVoiceBlob.type.includes('ogg') ? 'ogg' : 'webm');
                    formData.append('voice', recordedVoiceBlob, 'voice-message.' + ext);
                    formData.append('voice_expected', '1');
                    console.log('Sending voice blob:', { size: recordedVoiceBlob.size, type: recordedVoiceBlob.type, ext: ext });
                }
                const endpoint = hasRecordedVoice() ? 'upload_voice_message.php' : 'ajax_send_message.php';
                const endpointUrl = new URL(endpoint, window.location.href).toString();
                console.log('ChatZone send endpoint:', endpointUrl, 'hasVoice:', hasRecordedVoice());
                const sendStartedAt = performance.now();
                const res = await fetch(endpointUrl, { method: 'POST', body: formData, credentials: 'same-origin', cache: 'no-store' });
                console.log('ChatZone send fetch time ms:', Math.round(performance.now() - sendStartedAt));
                const rawText = await res.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('Non-JSON response from send endpoint:', rawText);
                    alert('Server returned a non-JSON error. Check PHP error log. First response text: ' + rawText.slice(0, 300));
                    return false;
                }
                if (data.success) {
                    updateTyping(false);
                    if (messageInput) messageInput.value = '';
                    clearReply();
                    clearImagePreview();
                    clearVoiceRecording();
                    
                    
                    if (messageInput) messageInput.focus();
                    setTimeout(function () {
                        fetchMessages({ refreshSidebar: false });
                        refreshSidebarSoon(900);
                        scrollToBottom();
                    }, 20);
                } else {
                    console.error('Send failed response:', data);
                    const details = data.debug ? '\n\nDebug: ' + JSON.stringify(data.debug, null, 2) : '';
                    alert((data.message || cfg.failedSendMessage || 'Failed to send message') + details);
                }
            } catch (err) {
                console.error('Send message error:', err);
                alert('Send request failed. Open browser console/network or check PHP error log.');
            } finally {
                sendingMessage = false;
            }
            return false;
        }
        window.ChatZoneSubmitMessage = submitChatMessage;
        sendForm.addEventListener('submit', submitChatMessage);
    }

    fetchMessages().then(function () { watchOlderMessagesLoader(); });
    scrollToBottom();
    refreshPresenceStatus();

    
    
    
    function refreshOnReturnToChat() {
        if (document.hidden) return;
        fetchMessages({ preserveScrollTop: true, refreshSidebar: true });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshOnReturnToChat();
    });
    window.addEventListener('focus', refreshOnReturnToChat);
    window.addEventListener('pageshow', refreshOnReturnToChat);
    window.addEventListener('pagehide', function () { updateTyping(false); });
    processScheduledMessages();
    
    
    
    
    
    window.czRefreshPrivateMessages = fetchMessages;
    window.czRefreshPrivateSidebar = refreshSidebar;
    window.czRefreshPrivateSidebarSoon = refreshSidebarSoon;

    
    setInterval(checkTyping, 7000);
    refreshPresenceStatus();
    setInterval(refreshPresenceStatus, 30000);

    
    setInterval(processScheduledMessages, 90000);
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ChatZoneInit);
} else {
    ChatZoneInit();
}
