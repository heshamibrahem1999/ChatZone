
(function(){
    var dark = localStorage.getItem('chatzone_dark_mode') === '1' ||
               localStorage.getItem('cz_dark_mode') === '1' ||
               document.cookie.indexOf('cz_dark_mode=1') !== -1;
    if (dark) document.documentElement.classList.add('dark-mode');
    document.addEventListener('DOMContentLoaded', function(){
        if (dark) document.body.classList.add('dark-mode');
    });
})();
