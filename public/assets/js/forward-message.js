// Forward message modal and destination selection behavior.
(function(){
  if (window.ChatZoneForwardMessageLoaded) return;
  window.ChatZoneForwardMessageLoaded = true;

  let modal = null;
  let currentSource = null;
  let destinationsCache = null;

  function csrfToken(){
    return (window.ChatZoneConfig && window.ChatZoneConfig.csrfToken) || (window.ChatZoneGroupConfig && window.ChatZoneGroupConfig.csrfToken) || '';
  }

  function escapeHtml(text){
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function ensureStyles(){
    if (document.getElementById('czForwardStyles')) return;
    const style = document.createElement('style');
    style.id = 'czForwardStyles';
    style.textContent = `
      .cz-forward-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:999999;display:none;align-items:center;justify-content:center;padding:18px;}
      .cz-forward-backdrop.open{display:flex;}
      .cz-forward-modal{width:min(520px,96vw);max-height:82vh;background:#fff;color:#111827;border-radius:18px;box-shadow:0 24px 80px rgba(0,0,0,.32);overflow:hidden;display:flex;flex-direction:column;}
      body.dark-mode .cz-forward-modal{background:#111b21;color:#e9edef;border:1px solid rgba(255,255,255,.08);}
      .cz-forward-head{display:flex;align-items:center;justify-content:space-between;padding:15px 18px;border-bottom:1px solid rgba(148,163,184,.28);font-weight:800;}
      .cz-forward-close{border:0;background:transparent;color:inherit;font-size:24px;line-height:1;cursor:pointer;}
      .cz-forward-body{padding:10px 12px;overflow:auto;}
      .cz-forward-section-title{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin:12px 6px 6px;font-weight:800;}
      body.dark-mode .cz-forward-section-title{color:#9aa6ad;}
      .cz-forward-row{display:flex;align-items:center;gap:11px;padding:10px 8px;border-radius:12px;cursor:pointer;}
      .cz-forward-row:hover{background:rgba(15,23,42,.06);}
      body.dark-mode .cz-forward-row:hover{background:rgba(255,255,255,.07);}
      .cz-forward-row input{width:18px;height:18px;}
      .cz-forward-avatar{width:36px;height:36px;border-radius:50%;object-fit:cover;background:#e5e7eb;}
      .cz-forward-name{font-weight:700;}
      .cz-forward-foot{display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:12px 16px;border-top:1px solid rgba(148,163,184,.28);}
      .cz-forward-cancel,.cz-forward-send{border:0;border-radius:999px;padding:10px 16px;font-weight:800;cursor:pointer;}
      .cz-forward-cancel{background:#e5e7eb;color:#111827;}
      .cz-forward-send{background:#16a34a;color:#fff;}
      .forwarded-badge,.group-forwarded{display:inline-block;margin-bottom:6px;font-size:12px;border-radius:999px;padding:3px 8px;font-weight:800;background:#16a34a22;color:#16a34a;}
      body.dark-mode .forwarded-badge,body.dark-mode .group-forwarded{color:#86efac;background:#16a34a22;}
    `;
    document.head.appendChild(style);
  }

  function ensureModal(){
    ensureStyles();
    if (modal) return modal;
    modal = document.createElement('div');
    modal.className = 'cz-forward-backdrop';
    modal.innerHTML = `
      <div class="cz-forward-modal" role="dialog" aria-modal="true" aria-label="Forward message">
        <div class="cz-forward-head"><span>Forward message</span><button type="button" class="cz-forward-close" aria-label="Close">×</button></div>
        <div class="cz-forward-body"><div class="cz-forward-loading">Loading...</div></div>
        <div class="cz-forward-foot"><button type="button" class="cz-forward-cancel">Cancel</button><button type="button" class="cz-forward-send">Forward</button></div>
      </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    modal.querySelector('.cz-forward-close').addEventListener('click', closeModal);
    modal.querySelector('.cz-forward-cancel').addEventListener('click', closeModal);
    modal.querySelector('.cz-forward-send').addEventListener('click', sendForward);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });
    return modal;
  }

  function closeModal(){ if (modal) modal.classList.remove('open'); currentSource = null; }

  async function loadDestinations(){
    if (destinationsCache) return destinationsCache;
    const res = await fetch('forward_destinations.php?_=' + Date.now(), {cache:'no-store', credentials:'same-origin'});
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Could not load destinations');
    destinationsCache = data;
    return data;
  }

  function renderDestinations(data){
    const body = ensureModal().querySelector('.cz-forward-body');
    let html = '';
    const friends = data.friends || [];
    const groups = data.groups || [];
    function row(item){
      const imgBase = item.type === 'group' ? 'uploads/groups/' : 'uploads/profiles/';
      return `<label class="cz-forward-row">
        <input type="checkbox" data-type="${escapeHtml(item.type)}" data-id="${Number(item.id)}">
        <img class="cz-forward-avatar" src="${imgBase}${escapeHtml(item.photo || (item.type === 'group' ? 'group-default.png' : 'default.png'))}" alt="">
        <span class="cz-forward-name">${escapeHtml(item.name)}</span>
      </label>`;
    }
    if (friends.length) html += '<div class="cz-forward-section-title">Chats</div>' + friends.map(row).join('');
    if (groups.length) html += '<div class="cz-forward-section-title">Groups</div>' + groups.map(row).join('');
    if (!html) html = '<div class="cz-forward-loading">No destinations found.</div>';
    body.innerHTML = html;
  }

  async function openForward(sourceType, messageId){
    currentSource = {sourceType, messageId};
    const m = ensureModal();
    m.querySelector('.cz-forward-body').innerHTML = '<div class="cz-forward-loading">Loading...</div>';
    m.classList.add('open');
    try { renderDestinations(await loadDestinations()); }
    catch (err) { m.querySelector('.cz-forward-body').innerHTML = '<div class="cz-forward-loading">' + escapeHtml(err.message) + '</div>'; }
  }

  async function sendForward(){
    if (!currentSource) return;
    const checks = Array.from(modal.querySelectorAll('.cz-forward-body input[type="checkbox"]:checked'));
    const destinations = checks.map(c => ({type:c.dataset.type, id:Number(c.dataset.id)}));
    if (!destinations.length) { alert('Choose at least one chat or group.'); return; }
    const btn = modal.querySelector('.cz-forward-send');
    btn.disabled = true;
    btn.textContent = 'Forwarding...';
    try {
      const fd = new FormData();
      fd.append('source_type', currentSource.sourceType);
      fd.append('source_message_id', currentSource.messageId);
      fd.append('destinations', JSON.stringify(destinations));
      fd.append('csrf_token', csrfToken());
      const res = await fetch('forward_message.php', {method:'POST', body:fd, credentials:'same-origin', cache:'no-store'});
      const data = await res.json();
      if (!data.success) { alert(data.message || 'Forward failed'); return; }
      closeModal();
      if (typeof window.czRefreshPrivateMessages === 'function') await window.czRefreshPrivateMessages();
      if (typeof window.czRefreshPrivateSidebar === 'function') await window.czRefreshPrivateSidebar();
      if (typeof window.czRefreshGroupMessages === 'function') await window.czRefreshGroupMessages(true);
      if (typeof window.czWsNotifyPrivate === 'function') window.czWsNotifyPrivate('forward_message');
      if (typeof window.czWsNotifyGroup === 'function') window.czWsNotifyGroup('forward_message');
      alert('Forwarded to ' + Number(data.sent || 0) + ' destination(s).');
    } catch (err) {
      alert('Forward failed: ' + err.message);
    } finally {
      btn.disabled = false;
      btn.textContent = 'Forward';
    }
  }

  document.addEventListener('click', function(e){
    const btn = e.target.closest('.forward-msg-btn,.group-forward-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    openForward(btn.dataset.sourceType || 'private', btn.dataset.messageId || '0');
  }, true);
})();
