
(function(){
  const btn = document.getElementById('enableBrowserNotifications');
  const status = document.getElementById('browserPermissionStatus');
  function render(){
    if (!status) return;
    if (!('Notification' in window)) { status.textContent = 'Browser notifications are not supported here.'; return; }
    status.textContent = 'Browser permission: ' + Notification.permission;
  }
  if (btn) btn.addEventListener('click', async function(){
    if (typeof window.czRequestNotificationPermission === 'function') await window.czRequestNotificationPermission();
    render();
  });
  render();
})();
