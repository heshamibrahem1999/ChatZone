
(function() {
    function ensureOverlay() {
        var overlay = document.getElementById('mobileOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'mobileOverlay';
            overlay.className = 'mobile-overlay';
            document.body.appendChild(overlay);
        }
        return overlay;
    }

    window.czOpenSidebar = function() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = ensureOverlay();
        if (!sidebar) {
            console.warn('ChatZone: sidebar not found');
            return false;
        }
        document.body.classList.add('mobile-sidebar-open');
        sidebar.classList.add('open');
        sidebar.style.transform = 'translateX(0)';
        sidebar.style.left = '0';
        sidebar.style.visibility = 'visible';
        sidebar.style.opacity = '1';
        sidebar.style.display = 'flex';
        overlay.classList.add('open');
        overlay.style.display = 'block';
        return false;
    };

    window.czCloseSidebar = function() {
        var sidebar = document.querySelector('.sidebar');
        var overlay = ensureOverlay();
        document.body.classList.remove('mobile-sidebar-open');
        if (sidebar) {
            sidebar.classList.remove('open');
            sidebar.style.transform = '';
            sidebar.style.left = '';
            sidebar.style.visibility = '';
            sidebar.style.opacity = '';
            sidebar.style.display = '';
        }
        overlay.classList.remove('open');
        overlay.style.display = '';
        return false;
    };

    document.addEventListener('click', function(event) {
        var openBtn = event.target.closest(
            '#openFriends, #openFriendsEmpty, .js-open-sidebar, .mobile-menu-btn');
        if (openBtn) {
            event.preventDefault();
            event.stopPropagation();
            window.czOpenSidebar();
            return false;
        }
        if (event.target && event.target.id === 'mobileOverlay') {
            window.czCloseSidebar();
        }
    }, true);
})();
