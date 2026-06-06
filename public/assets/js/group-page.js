// Group chat behavior: live updates, typing, and message actions.


const gm=document.getElementById('groupMessages');
function czSetGroupBottomInstant(){
  const box=document.getElementById('groupMessages');
  if(!box) return;
  const oldBehavior=box.style.scrollBehavior;
  box.style.scrollBehavior='auto';
  box.scrollTop=box.scrollHeight;
  requestAnimationFrame(function(){
    box.scrollTop=box.scrollHeight;
    box.classList.remove('initial-bottom-pending');
    box.dataset.initialBottomReady='1';
    box.style.scrollBehavior=oldBehavior;
  });
  setTimeout(function(){
    const oldBehavior2=box.style.scrollBehavior;
    box.style.scrollBehavior='auto';
    box.scrollTop=box.scrollHeight;
    box.classList.remove('initial-bottom-pending');
    box.style.scrollBehavior=oldBehavior2;
  },80);
}
if(gm) czSetGroupBottomInstant();
window.addEventListener('load', czSetGroupBottomInstant, {once:true});
const moreBtn=document.getElementById('groupMoreBtn');
const moreMenu=document.getElementById('groupMoreMenu');
if(moreBtn&&moreMenu){moreBtn.addEventListener('click',e=>{e.stopPropagation();moreMenu.classList.toggle('open');});document.addEventListener('click',()=>moreMenu.classList.remove('open'));}


(function(){
  const input = document.getElementById('groupAttachmentInput');
  const preview = document.getElementById('groupAttachmentPreview');
  const img = document.getElementById('groupAttachmentPreviewImg');
  const title = document.getElementById('groupAttachmentPreviewTitle');
  const sub = document.getElementById('groupAttachmentPreviewSub');
  const remove = document.getElementById('groupAttachmentPreviewRemove');
  let objectUrl = null;

  function clearPreview(){
    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    if (input) input.value = '';
    if (preview) preview.classList.remove('active');
    if (img) { img.removeAttribute('src'); img.style.display = 'none'; }
    if (title) title.textContent = 'Selected attachment';
    if (sub) sub.textContent = 'Ready to send';
  }

  if (img) img.style.display = 'none';
  if (remove) remove.addEventListener('click', clearPreview);

  if (input && preview) {
    input.addEventListener('change', function(){
      const file = input.files && input.files[0];
      if (!file) { clearPreview(); return; }
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }

      preview.classList.add('active');
      if (title) title.textContent = file.name || 'Selected attachment';
      if (sub) sub.textContent = file.type ? file.type : 'Attachment ready';

      if (file.type && file.type.startsWith('image/')) {
        objectUrl = URL.createObjectURL(file);
        if (img) { img.src = objectUrl; img.style.display = 'block'; }
      } else {
        if (img) { img.removeAttribute('src'); img.style.display = 'none'; }
      }
    });
  }
})();

document.querySelectorAll('.group-msg').forEach(row=>{row.addEventListener('click',e=>{if(e.target.closest('button,a,input,audio')) return; document.querySelectorAll('.group-msg.show-actions').forEach(x=>{if(x!==row)x.classList.remove('show-actions')}); row.classList.toggle('show-actions');});});
const replyInput=document.getElementById('groupReplyToId');
const replyPreview=document.getElementById('groupReplyPreview');
const replyText=replyPreview ? replyPreview.querySelector('span') : null;
document.querySelectorAll('.group-reply-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    replyInput.value=btn.dataset.messageId;
    replyText.textContent='Replying to: '+(btn.dataset.preview || 'message');
    replyPreview.classList.add('active');
  });
});
document.getElementById('cancelGroupReply')?.addEventListener('click',()=>{
  replyInput.value=''; replyPreview.classList.remove('active');
});
document.querySelectorAll('.group-react-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const fd=new FormData();
    fd.append('message_id',btn.dataset.messageId);
    fd.append('emoji',btn.dataset.emoji);
    const res=await fetch('group_react_message.php',{method:'POST',body:fd});
    const data=await res.json();
    if(!data.success){ alert('Reaction failed: '+(data.error || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});


document.querySelectorAll('.group-pin-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const fd = new FormData();
    fd.append('message_id', btn.dataset.messageId);
    const res = await fetch('group_pin_message.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Pin failed: '+(data.error || data.message || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});


document.querySelectorAll('.group-star-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const fd = new FormData();
    fd.append('message_id', btn.dataset.messageId);
    const res = await fetch('group_star_message.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Star failed: '+(data.error || data.message || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});

document.querySelectorAll('.group-edit-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const current = btn.dataset.body || '';
    const body = prompt('Edit message:', current);
    if(body === null) return;
    const fd = new FormData();
    fd.append('message_id', btn.dataset.messageId);
    fd.append('body', body);
    const res = await fetch('group_edit_message.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Edit failed: '+(data.error || data.message || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});
document.querySelectorAll('.group-delete-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    if(!confirm('Delete this group message for everyone?')) return;
    const fd = new FormData();
    fd.append('message_id', btn.dataset.messageId);
    const res = await fetch('group_delete_message.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Delete failed: '+(data.error || data.message || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});




document.querySelectorAll('.poll-option').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const fd = new FormData();
    fd.append('poll_id', btn.dataset.pollId);
    fd.append('option_id', btn.dataset.optionId);
    const res = await fetch('group_poll_vote.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Vote failed: '+(data.error || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});
document.querySelectorAll('.poll-toggle-btn').forEach(btn=>{
  btn.addEventListener('click',async()=>{
    const fd = new FormData();
    fd.append('poll_id', btn.dataset.pollId);
    const res = await fetch('group_poll_toggle.php',{method:'POST',body:fd});
    const data = await res.json();
    if(!data.success){ alert('Poll update failed: '+(data.error || 'Unknown error')); return; }
    if (typeof window.czRefreshGroupMessages === 'function') { await window.czRefreshGroupMessages(true); }
  });
});


const groupInput = document.getElementById('groupMessageInput');
const mentionPicker = document.getElementById('mentionPicker');
let mentionStartIndex = -1;

function getMentionQuery(value, caret) {
  const before = value.slice(0, caret);
  const at = before.lastIndexOf('@');
  if (at === -1) return null;
  if (at > 0 && !/\s/.test(before[at - 1])) return null;
  const q = before.slice(at + 1);
  
  if (/\s/.test(q)) return null;
  return {start: at, query: q.toLowerCase()};
}

function hideMentionPicker(){
  if (mentionPicker) mentionPicker.classList.remove('active');
  mentionStartIndex = -1;
}

function renderMentionPicker(items){
  if (!mentionPicker) return;
  mentionPicker.innerHTML = '';
  if (!items.length) { hideMentionPicker(); return; }
  items.slice(0, 8).forEach(member => {
    const div = document.createElement('div');
    div.className = 'mention-option';
    div.innerHTML = `<strong>${member.name}</strong><br><small>${member.role || 'member'}</small>`;
    div.addEventListener('mousedown', (e) => {
      e.preventDefault();
      insertMention(member);
    });
    mentionPicker.appendChild(div);
  });
  mentionPicker.classList.add('active');
}

function insertMention(member){
  if (!groupInput || mentionStartIndex < 0) return;
  const caret = groupInput.selectionStart;
  const before = groupInput.value.slice(0, mentionStartIndex);
  const after = groupInput.value.slice(caret);
  const token = `@[${member.name}](user:${member.id}) `;
  groupInput.value = before + token + after;
  const pos = (before + token).length;
  groupInput.focus();
  groupInput.setSelectionRange(pos, pos);
  hideMentionPicker();
}

if (groupInput && mentionPicker) {
  groupInput.addEventListener('input', () => {
    const info = getMentionQuery(groupInput.value, groupInput.selectionStart);
    if (!info) { hideMentionPicker(); return; }
    mentionStartIndex = info.start;
    const items = (window.GROUP_MENTION_MEMBERS || []).filter(m => (m.name || '').toLowerCase().includes(info.query));
    renderMentionPicker(items);
  });
  groupInput.addEventListener('keydown', e => {
    if (e.key === 'Escape') hideMentionPicker();
  });
  document.addEventListener('click', e => {
    if (!mentionPicker.contains(e.target) && e.target !== groupInput) hideMentionPicker();
  });
}



(function(){
  const messagesBox = document.getElementById('groupMessages');
  if (!messagesBox) return;

  const groupId = messagesBox.dataset.groupId || new URLSearchParams(location.search).get('id');
  let liveBusy = false;
  let lastHTML = messagesBox.innerHTML;

  function isNearBottom(el) {
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 140;
  }

  function anyAudioPlaying() {
    return Array.from(document.querySelectorAll('audio')).some(a => !a.paused && !a.ended);
  }

  function scrollGroupBottom() {
    czSetGroupBottomInstant();
  }

  async function refreshGroupMessages(forceBottom = false) {
    const box = document.getElementById('groupMessages');
    if (!box || !groupId || liveBusy || anyAudioPlaying()) return;
    liveBusy = true;
    const shouldBottom = forceBottom || isNearBottom(box);
    try {
      const url = 'group.php?id=' + encodeURIComponent(groupId) + '&_live=' + Date.now();
      const res = await fetch(url, {cache: 'no-store', headers: {'X-Requested-With':'fetch'}});
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const fresh = doc.getElementById('groupMessages');
      if (fresh && fresh.innerHTML !== lastHTML) {
        box.innerHTML = fresh.innerHTML;
        lastHTML = fresh.innerHTML;
        if (shouldBottom) scrollGroupBottom();
      }
    } catch (err) {
      console.warn('Group live refresh failed', err);
    } finally {
      liveBusy = false;
    }
  }

  window.czRefreshGroupMessages = refreshGroupMessages;

  
  document.addEventListener('click', function(e){
    const mobileBtn = e.target.closest('.mobile-group-menu-btn');
    if (mobileBtn && messagesBox.contains(mobileBtn)) {
      e.preventDefault();
      e.stopPropagation();
      const row = mobileBtn.closest('.group-msg');
      if (!row) return;
      document.querySelectorAll('.group-msg.show-actions').forEach(x => { if (x !== row) x.classList.remove('show-actions'); });
      row.classList.toggle('show-actions');
      return;
    }

    const row = e.target.closest('.group-msg');
    if (!row || !messagesBox.contains(row)) {
      document.querySelectorAll('.group-msg.show-actions').forEach(x => x.classList.remove('show-actions'));
      return;
    }
    if (e.target.closest('button,a,input,audio,label')) return;
    document.querySelectorAll('.group-msg.show-actions').forEach(x => { if (x !== row)x.classList.remove('show-actions'); });
    row.classList.toggle('show-actions');
  }, true);

  
  document.addEventListener('click', async function(e){
    const btn = e.target.closest('.group-reply-btn,.group-react-btn,.group-pin-btn,.group-star-btn,.group-edit-btn,.group-delete-btn,.poll-option,.poll-toggle-btn');
    if (!btn || !messagesBox.contains(btn)) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    try {
      if (btn.classList.contains('group-reply-btn')) {
        const replyInput = document.getElementById('groupReplyToId');
        const replyPreview = document.getElementById('groupReplyPreview');
        const replyText = replyPreview ? replyPreview.querySelector('span') : null;
        if (replyInput) replyInput.value = btn.dataset.messageId || '';
        if (replyText) replyText.textContent = 'Replying to: ' + (btn.dataset.preview || 'message');
        if (replyPreview) replyPreview.classList.add('active');
        return;
      }

      let endpoint = '';
      const fd = new FormData();

      if (btn.classList.contains('group-react-btn')) {
        endpoint = 'group_react_message.php';
        fd.append('message_id', btn.dataset.messageId);
        fd.append('emoji', btn.dataset.emoji);
      } else if (btn.classList.contains('group-pin-btn')) {
        endpoint = 'group_pin_message.php';
        fd.append('message_id', btn.dataset.messageId);
      } else if (btn.classList.contains('group-star-btn')) {
        endpoint = 'group_star_message.php';
        fd.append('message_id', btn.dataset.messageId);
      } else if (btn.classList.contains('group-edit-btn')) {
        const body = prompt('Edit message:', btn.dataset.body || '');
        if (body === null) return;
        endpoint = 'group_edit_message.php';
        fd.append('message_id', btn.dataset.messageId);
        fd.append('body', body);
      } else if (btn.classList.contains('group-delete-btn')) {
        if (!confirm('Delete this group message for everyone?')) return;
        endpoint = 'group_delete_message.php';
        fd.append('message_id', btn.dataset.messageId);
      } else if (btn.classList.contains('poll-option')) {
        endpoint = 'group_poll_vote.php';
        fd.append('poll_id', btn.dataset.pollId);
        fd.append('option_id', btn.dataset.optionId);
      } else if (btn.classList.contains('poll-toggle-btn')) {
        endpoint = 'group_poll_toggle.php';
        fd.append('poll_id', btn.dataset.pollId);
      }

      if (!endpoint) return;
      const res = await fetch(endpoint, {method:'POST', body:fd});
      const data = await res.json();
      if (!data.success) {
        alert('Action failed: ' + (data.error || data.message || 'Unknown error'));
        return;
      }
      await refreshGroupMessages(true);
    } catch (err) {
      alert('Action failed: ' + err.message);
    }
  }, true);

  
  setTimeout(scrollGroupBottom, 120);
  setTimeout(scrollGroupBottom, 500);

  
  
  
})();




(function(){
  const input = document.getElementById('groupMessageInput');
  const status = document.getElementById('groupTypingStatus');
  const cfg = window.ChatZoneGroupConfig || {};
  if (!input || !status) return;

  const typingUsers = new Map();
  let debounceTimer = null;
  let stopTimer = null;
  let isCurrentlyTyping = false;

  function renderTyping(){
    const now = Date.now();
    for (const [name, expires] of typingUsers.entries()) {
      if (expires <= now) typingUsers.delete(name);
    }
    const names = Array.from(typingUsers.keys()).filter(Boolean);
    if (names.length === 0) {
      status.textContent = '';
    } else if (names.length === 1) {
      status.textContent = names[0] + ' is typing...';
    } else if (names.length === 2) {
      status.textContent = names[0] + ' and ' + names[1] + ' are typing...';
    } else {
      status.textContent = names.length + ' people are typing...';
    }
  }

  function sendTyping(isTyping){
    if (typeof window.czWsSendGroupTyping === 'function') {
      window.czWsSendGroupTyping(!!isTyping, cfg.myDisplayName || 'Someone');
    }
  }

  input.addEventListener('input', function(){
    clearTimeout(debounceTimer);
    clearTimeout(stopTimer);
    if (!isCurrentlyTyping) {
      isCurrentlyTyping = true;
      sendTyping(true);
    } else {
      debounceTimer = setTimeout(function(){ sendTyping(true); }, 500);
    }
    stopTimer = setTimeout(function(){
      isCurrentlyTyping = false;
      sendTyping(false);
    }, 2500);
  });

  input.addEventListener('blur', function(){
    clearTimeout(debounceTimer);
    clearTimeout(stopTimer);
    isCurrentlyTyping = false;
    sendTyping(false);
  });

  window.addEventListener('pagehide', function(){ sendTyping(false); });

  window.czOnGroupTyping = function(isTyping, packet){
    const name = (packet && packet.displayName ? String(packet.displayName).trim() : 'Someone');
    if (isTyping) {
      typingUsers.set(name, Date.now() + 3500);
    } else {
      typingUsers.delete(name);
    }
    renderTyping();
  };

  setInterval(renderTyping, 2500);
})();



(function(){
  const form = document.querySelector('form.group-send');
  const box = document.getElementById('groupMessages');
  if (!form || !box || form.dataset.wsSendBound === '1') return;
  form.dataset.wsSendBound = '1';

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const input = document.getElementById('groupMessageInput');
    const attachment = document.getElementById('groupAttachmentInput');

    try {
      const res = await fetch(form.getAttribute('action') || 'group_send.php', {
        method: 'POST',
        body: fd,
        cache: 'no-store',
        credentials: 'same-origin'
      });
      if (!res.ok) {
        const text = await res.text();
        alert('Group send failed: ' + text.slice(0, 300));
        return;
      }

      if (input) { input.value = ''; if (typeof window.czWsSendGroupTyping === 'function') window.czWsSendGroupTyping(false, (window.ChatZoneGroupConfig || {}).myDisplayName || 'Someone'); }
      if (attachment) attachment.value = '';
      const replyInput = document.getElementById('groupReplyToId');
      const replyPreview = document.getElementById('groupReplyPreview');
      if (replyInput) replyInput.value = '';
      if (replyPreview) replyPreview.classList.remove('active');
      const preview = document.getElementById('groupAttachmentPreview');
      if (preview) preview.classList.remove('active');

      if (typeof window.czWsNotifyGroup === 'function') {
        window.czWsNotifyGroup('group_send');
      }
      if (typeof window.czRefreshGroupMessages === 'function') {
        await window.czRefreshGroupMessages(true);
      }
    } catch (err) {
      console.error('Group AJAX send error:', err);
      alert('Group send failed. Check console.');
    }
  }, true);
})();


(function setupGroupPresence(){
  const status = document.getElementById('groupPresenceStatus');
  const groupId = (window.ChatZoneGroupConfig && window.ChatZoneGroupConfig.groupId) || new URLSearchParams(location.search).get('id');
  if (!status || !groupId) return;

  async function refreshGroupPresenceStatus(){
    try {
      const res = await fetch('group_presence_status.php?id=' + encodeURIComponent(groupId), { cache: 'no-store', credentials: 'same-origin' });
      const data = await res.json();
      if (!data || !data.success) return;
      status.textContent = data.status_text || '';
    } catch (err) {
      console.warn('Group presence refresh failed:', err);
    }
  }

  window.czRefreshGroupPresenceStatus = refreshGroupPresenceStatus;
  refreshGroupPresenceStatus();
  setInterval(refreshGroupPresenceStatus, 30000);
})();

