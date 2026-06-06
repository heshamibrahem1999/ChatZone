
(function () {
    function applyDarkMode(enabled) {
        document.body.classList.toggle('dark-mode', enabled);

        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.innerHTML = enabled
                ? '<img src="assets/img/sun.png" alt="☀️" />'
                : '<img src="assets/img/moon.png" alt="🌙" />';

            btn.title = enabled ? 'Switch to light mode' : 'Switch to dark mode';
            btn.setAttribute('aria-label', btn.title);
        }
    }

    function initThemeToggle() {
        var saved = localStorage.getItem('chatzone_dark_mode');
        var enabled = saved === '1';

        applyDarkMode(enabled);

        var btn = document.getElementById('themeToggle');
        if (!btn || btn.dataset.themeBound === '1') return;

        btn.dataset.themeBound = '1';
        btn.addEventListener('click', function () {
            enabled = !document.body.classList.contains('dark-mode');
            localStorage.setItem('chatzone_dark_mode', enabled ? '1' : '0');
            applyDarkMode(enabled);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeToggle);
    } else {
        initThemeToggle();
    }
})();
