
(function () {
  const privateCfg = window.ChatZoneConfig || {};
  const groupCfg = window.ChatZoneGroupConfig || {};
  const notifyEnabled = privateCfg.notifyBrowser !== false && groupCfg.notifyBrowser !== false;
  const notifiedIds = new Set();

  function canUseNotifications() {
    return notifyEnabled && 'Notification' in window;
  }

  function shouldNotify(packet) {
    if (!canUseNotifications()) return false;
    if (!packet || packet.senderIsSelf) return false;
    if (packet.action === 'read_receipts' || packet.type === 'typing' || packet.type === 'typing_stop' || packet.type === 'presence_changed') return false;
    
    return document.hidden || !document.hasFocus();
  }

  async function ensureServiceWorker() {
    
    
    return null;
  }

  async function requestPermission() {
    if (!canUseNotifications()) return 'unsupported';
    if (Notification.permission === 'granted') return 'granted';
    if (Notification.permission === 'denied') return 'denied';
    try { return await Notification.requestPermission(); }
    catch (err) { return 'default'; }
  }

  async function fetchPayload(packet) {
    const qs = new URLSearchParams();
    qs.set('scope', packet.scope || '');
    if (packet.scope === 'private') qs.set('friendship_id', packet.friendshipId || '');
    if (packet.scope === 'group') qs.set('group_id', packet.groupId || '');
    qs.set('_', Date.now().toString());
    const res = await fetch('push_notification_payload.php?' + qs.toString(), { cache: 'no-store', credentials: 'same-origin' });
    return await res.json();
  }

  async function showPayload(payload) {
    if (!payload || !payload.success) return;
    if (payload.id && notifiedIds.has(payload.id)) return;
    if (payload.id) {
      notifiedIds.add(payload.id);
      setTimeout(function () { notifiedIds.delete(payload.id); }, 60000);
    }

    const options = {
      body: payload.body || 'New message',
      icon: payload.icon || 'assets/img/icon-192.png',
      badge: payload.badge || 'assets/img/icon-192.png',
      tag: payload.id || ('chatzone-' + Date.now()),
      data: { url: payload.url || 'chat.php' },
      renotify: true
    };

    const notification = new Notification(payload.title || 'ChatZone', options);
    notification.onclick = function () {
      window.focus();
      if (payload.url) location.href = payload.url;
      notification.close();
    };
  }

  window.czRequestNotificationPermission = async function () {
    return requestPermission();
  };

  window.czMaybeNotifyFromPacket = async function (packet) {
    if (!shouldNotify(packet)) return;
    const permission = await requestPermission();
    if (permission !== 'granted') return;
    try {
      const payload = await fetchPayload(packet);
      await showPayload(payload);
    } catch (err) {
      console.warn('ChatZone notification failed:', err);
    }
  };

})();
