
const log = msg => document.getElementById('log').textContent += msg + "\n";
(async function(){
    try {
        if ('serviceWorker' in navigator) {
            const regs = await navigator.serviceWorker.getRegistrations();
            for (const reg of regs) {
                await reg.unregister();
                log('Service worker unregistered');
            }
        }
        if ('caches' in window) {
            const keys = await caches.keys();
            for (const key of keys) {
                await caches.delete(key);
                log('Deleted cache: ' + key);
            }
        }
        localStorage.removeItem('chatzone_dark_mode');
        sessionStorage.clear();
        document.getElementById('status').textContent = 'Done. Click Back to Chat, then refresh once normally.';
    } catch (e) {
        document.getElementById('status').textContent = 'Cache clear finished with warning.';
        log(String(e));
    }
})();
