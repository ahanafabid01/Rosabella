document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.admin-sidebar');
    const backdrop = document.querySelector('.admin-sidebar-backdrop');
    const toggleButtons = document.querySelectorAll('[data-admin-sidebar-toggle]');
    const closeTriggers = document.querySelectorAll('[data-admin-sidebar-close]');
    const navLinks = document.querySelectorAll('.admin-nav a');
    const mobileQuery = window.matchMedia('(max-width: 1024px)');

    if (!sidebar || !backdrop) {
        return;
    }

    const openSidebar = () => {
        sidebar.classList.add('open');
        backdrop.classList.add('active');
        document.body.classList.add('admin-sidebar-open');
    };

    const closeSidebar = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('active');
        document.body.classList.remove('admin-sidebar-open');
    };

    toggleButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    });

    closeTriggers.forEach((trigger) => {
        trigger.addEventListener('click', closeSidebar);
    });

    navLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) {
                closeSidebar();
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (!mobileQuery.matches) {
            closeSidebar();
        }
    });
});
