
(function(){
    function readJson(id, fallback){
        var el=document.getElementById(id);
        if(!el) return fallback;
        try{return JSON.parse(el.textContent||'');}catch(e){return fallback;}
    }
    window.GROUP_MENTION_MEMBERS = readJson('group-mention-members', []);
    window.ChatZoneGroupConfig = readJson('chatzone-group-config', {});
})();
