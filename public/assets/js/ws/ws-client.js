
(function () {
  const WS_URL = window.ChatZoneWsUrl || 'ws://localhost:8081';
  const clientId = (Date.now().toString(36) + Math.random().toString(36).slice(2));
  let socket = null;
  let reconnectTimer = null;
  let connected = false;
  let reconnectDelay = 1000;

  function privateFriendshipId() {
    const msgList = document.getElementById('msgList');
    return msgList ? String(msgList.dataset.friendshipId || '') : '';
  }

  function groupId() {
    const box = document.getElementById('groupMessages');
    if (box && box.dataset.groupId) return String(box.dataset.groupId);
    return new URLSearchParams(location.search).get('id') || '';
  }

  function isPrivatePage() {
    return !!document.getElementById('msgList');
  }

  function isGroupPage() {
    return !!document.getElementById('groupMessages');
  }

  function safeJsonParse(value) {
    try { return JSON.parse(value); } catch (e) { return null; }
  }

  function setStatus(isConnected) {
    connected = !!isConnected;
    document.documentElement.dataset.czWs = connected ? 'connected' : 'offline';
  }

  function sendPacket(packet) {
    if (!socket || socket.readyState !== WebSocket.OPEN) return false;
    socket.send(JSON.stringify(Object.assign({ sender: clientId, at: Date.now() }, packet)));
    return true;
  }

  function refreshPrivate() {
    if (typeof window.czRefreshPrivateMessages === 'function') {
      window.czRefreshPrivateMessages(false);
    }
    if (typeof window.czRefreshPrivateSidebar === 'function') {
      window.czRefreshPrivateSidebar();
    }
  }

  function refreshGroup() {
    if (typeof window.czRefreshGroupMessages === 'function') {
      window.czRefreshGroupMessages(false);
    }
  }

  function onPacket(packet) {
    if (!packet || packet.sender === clientId) return;

    
    if (packet.type === 'typing' || packet.type === 'typing_stop') {
      if (packet.type === 'presence_changed') {
      if (typeof window.czRefreshPresenceStatus === 'function') window.czRefreshPresenceStatus();
      if (typeof window.czRefreshPrivateSidebar === 'function') window.czRefreshPrivateSidebar();
      if (typeof window.czRefreshGroupPresenceStatus === 'function') window.czRefreshGroupPresenceStatus();
      return;
    }

    if (packet.scope === 'private') {
        const current = privateFriendshipId();
        if (current && String(packet.friendshipId || '') === current && typeof window.czOnPrivateTyping === 'function') {
          window.czOnPrivateTyping(packet.type === 'typing', packet);
        }
      }
      if (packet.scope === 'group') {
        const currentGroupId = groupId();
        if (currentGroupId && String(packet.groupId || '') === currentGroupId && typeof window.czOnGroupTyping === 'function') {
          window.czOnGroupTyping(packet.type === 'typing', packet);
        }
      }
      return;
    }

    if (packet.type === 'presence_changed') {
      if (typeof window.czRefreshPresenceStatus === 'function') window.czRefreshPresenceStatus();
      if (typeof window.czRefreshPrivateSidebar === 'function') window.czRefreshPrivateSidebar();
      if (typeof window.czRefreshGroupPresenceStatus === 'function') window.czRefreshGroupPresenceStatus();
      return;
    }

    if (packet.scope === 'private') {
      if (typeof window.czMaybeNotifyFromPacket === 'function') window.czMaybeNotifyFromPacket(packet);
      const current = privateFriendshipId();
      if (current && String(packet.friendshipId || '') === current) refreshPrivate();
      else if (typeof window.czRefreshPrivateSidebar === 'function') window.czRefreshPrivateSidebar();
      return;
    }

    if (packet.scope === 'group') {
      if (typeof window.czMaybeNotifyFromPacket === 'function') window.czMaybeNotifyFromPacket(packet);
      const currentGroupId = groupId();
      if (currentGroupId && String(packet.groupId || '') === currentGroupId) refreshGroup();
      return;
    }

    if (packet.scope === 'sidebar') {
      if (typeof window.czRefreshPrivateSidebar === 'function') window.czRefreshPrivateSidebar();
    }
  }

  function connect() {
    if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) return;
    try {
      socket = new WebSocket(WS_URL);
    } catch (e) {
      console.warn('ChatZone WS create failed:', e);
      return scheduleReconnect();
    }

    socket.addEventListener('open', function () {
      setStatus(true);
      reconnectDelay = 1000;
      console.log('ChatZone WebSocket connected:', WS_URL);
      if (isPrivatePage()) {
        sendPacket({ type: 'join', scope: 'private', friendshipId: privateFriendshipId() });
      } else if (isGroupPage()) {
        sendPacket({ type: 'join', scope: 'group', groupId: groupId() });
      }
    });

    socket.addEventListener('message', function (event) {
      const packet = safeJsonParse(event.data);
      onPacket(packet);
    });

    socket.addEventListener('close', function () {
      setStatus(false);
      scheduleReconnect();
    });

    socket.addEventListener('error', function (err) {
      setStatus(false);
      console.warn('ChatZone WebSocket error:', err);
    });
  }

  function scheduleReconnect() {
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(function () {
      reconnectTimer = null;
      connect();
      reconnectDelay = Math.min(reconnectDelay * 1.5, 10000);
    }, reconnectDelay);
  }

  window.czWsNotifyPrivate = function (action) {
    const id = privateFriendshipId();
    if (!id) return false;
    return sendPacket({ type: 'notify', scope: 'private', friendshipId: id, action: action || 'update' });
  };

  window.czWsNotifyGroup = function (action) {
    const id = groupId();
    if (!id) return false;
    return sendPacket({ type: 'notify', scope: 'group', groupId: id, action: action || 'update' });
  };

  window.czWsConnected = function () { return connected; };

  window.czWsNotifyPresence = function (reason) {
    return sendPacket({ type: 'presence_changed', scope: 'sidebar', reason: reason || 'heartbeat' });
  };

  window.czWsSendPrivateTyping = function (isTyping, displayName) {
    const id = privateFriendshipId();
    if (!id) return false;
    return sendPacket({
      type: isTyping ? 'typing' : 'typing_stop',
      scope: 'private',
      friendshipId: id,
      displayName: displayName || ''
    });
  };

  window.czWsSendGroupTyping = function (isTyping, displayName) {
    const id = groupId();
    if (!id) return false;
    return sendPacket({
      type: isTyping ? 'typing' : 'typing_stop',
      scope: 'group',
      groupId: id,
      displayName: displayName || ''
    });
  };


  
  
  const originalFetch = window.fetch.bind(window);
  const privateEndpoints = [
    'ajax_send_message.php', 'upload_voice_message.php', 'react_message.php',
    'edit_message.php', 'delete_message.php', 'pin_message.php', 'star_message.php'
  ];
  const groupEndpoints = [
    'group_send.php', 'group_react_message.php', 'group_pin_message.php', 'group_star_message.php',
    'group_edit_message.php', 'group_delete_message.php', 'group_poll_vote.php',
    'group_poll_toggle.php'
  ];

  window.fetch = async function (input, init) {
    const response = await originalFetch(input, init);
    try {
      const url = typeof input === 'string' ? input : (input && input.url ? input.url : '');
      const clean = url.split('?')[0].split('/').pop();
      const method = (init && init.method ? init.method : 'GET').toUpperCase();
      if (method === 'POST') {
        if (privateEndpoints.includes(clean)) {
          const clone = response.clone();
          clone.json().then(function (data) {
            if (data && data.success) window.czWsNotifyPrivate(clean);
          }).catch(function () {});
        }
        if (groupEndpoints.includes(clean)) {
          const clone = response.clone();
          clone.json().then(function (data) {
            if (data && data.success) window.czWsNotifyGroup(clean);
          }).catch(function () {});
        }
      }
    } catch (e) {}
    return response;
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', connect);
  } else {
    connect();
  }
})();
