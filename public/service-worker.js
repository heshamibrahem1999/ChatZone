




self.addEventListener('install', event => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil((async () => {
    try {
      const keys = await caches.keys();
      await Promise.all(keys.map(key => caches.delete(key)));
    } catch (e) {}
    await self.clients.claim();
  })());
});




self.addEventListener('notificationclick', event => {
  event.notification.close();
  const targetUrl = (event.notification.data && event.notification.data.url) || 'chat.php';
  event.waitUntil((async () => {
    const allClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
    const target = new URL(targetUrl, self.location.origin);
    for (const client of allClients) {
      const url = new URL(client.url);
      if (url.origin === target.origin) {
        await client.focus();
        return client.navigate(target.href);
      }
    }
    return clients.openWindow(target.href);
  })());
});
