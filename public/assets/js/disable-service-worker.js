
(function(){
  if (!('serviceWorker' in navigator)) return;
  try {
    if (sessionStorage.getItem('cz_sw_cleanup_done') === '1') return;
    sessionStorage.setItem('cz_sw_cleanup_done', '1');
  } catch (e) {}
  window.addEventListener('load', function(){
    setTimeout(function(){
      navigator.serviceWorker.getRegistrations().then(function(regs){
        regs.forEach(function(reg){ reg.unregister().catch(function(){}); });
      }).catch(function(){});
    }, 2500);
  });
})();
