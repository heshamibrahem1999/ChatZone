
(function(){
    var configTag = document.getElementById('chatzone-config-data');
    if (configTag) {
        try { window.ChatZoneConfig = JSON.parse(configTag.textContent || '{}'); } catch (e) { window.ChatZoneConfig = window.ChatZoneConfig || {}; }
    }
    var body = document.body;
    if (!body || !body.dataset) return;
    var map = { userTheme:'--user-theme', userBg:'--user-bg', userText:'--user-text', userBubble:'--user-bubble', userSize:'--user-size' };
    Object.keys(map).forEach(function(key){ if (body.dataset[key]) document.documentElement.style.setProperty(map[key], body.dataset[key]); });
})();
