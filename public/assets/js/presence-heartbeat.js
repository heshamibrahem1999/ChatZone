
(function () {
  var busy = false;
  var lastSent = 0;
  var TAB_KEY = 'cz_presence_tab_id';
  var tabId = '';

  try {
    tabId = sessionStorage.getItem(TAB_KEY) || '';
    if (!tabId) {
      tabId = 'tab_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
      sessionStorage.setItem(TAB_KEY, tabId);
    }
  } catch (e) {
    tabId = 'tab_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
  }

  function makeBody(status, reason) {
    var body = new URLSearchParams();
    body.set('status', status || 'online');
    body.set('reason', reason || 'heartbeat');
    body.set('presence_tab_id', tabId);
    body.set('_', String(Date.now()));
    return body;
  }

  async function pingPresence(reason) {
    if (busy) return;
    var now = Date.now();
    if (reason !== 'force' && now - lastSent < 8000) return;
    busy = true;
    lastSent = now;
    try {
      var res = await fetch('update_presence.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        keepalive: true,
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-ChatZone-Presence': reason || 'heartbeat' },
        body: makeBody('online', reason || 'heartbeat')
      });
      try { await res.clone().json(); } catch (e) {}
      if (typeof window.czWsNotifyPresence === 'function') window.czWsNotifyPresence(reason || 'heartbeat');
    } catch (e) {
      console.warn('Presence heartbeat failed:', e);
    } finally {
      busy = false;
    }
  }

  function markOffline(reason) {
    try {
      var body = makeBody('offline', reason || 'pagehide');
      var url = 'update_presence.php?status=offline&presence_tab_id=' + encodeURIComponent(tabId) + '&reason=' + encodeURIComponent(reason || 'pagehide') + '&_=' + Date.now();
      if (navigator.sendBeacon) {
        navigator.sendBeacon(url, body);
      } else {
        fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          cache: 'no-store',
          keepalive: true,
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body
        }).catch(function () {});
      }
      if (typeof window.czWsNotifyPresence === 'function') window.czWsNotifyPresence('offline');
    } catch (e) {}
  }

  window.czPingPresence = function () { pingPresence('force'); };
  window.czMarkOffline = markOffline;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { pingPresence('load'); });
  } else {
    pingPresence('load');
  }

  setInterval(function () {
    if (!document.hidden) pingPresence('heartbeat');
  }, 10000);

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) pingPresence('force');
  });
  window.addEventListener('focus', function () { pingPresence('force'); });
  window.addEventListener('pageshow', function () { pingPresence('force'); });

  window.addEventListener('pagehide', function () { markOffline('pagehide'); });
  window.addEventListener('beforeunload', function () { markOffline('beforeunload'); });
})();
