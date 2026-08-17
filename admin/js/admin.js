(function() {
    'use strict';
    if (window.__adminJsInitialized) return;
    window.__adminJsInitialized = true;

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
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        });

        closeTriggers.forEach((trigger) => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                closeSidebar();
            });
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

        // Auto-hide success alerts after 3 seconds, keep error alerts visible for clear guidance
        const successAlerts = document.querySelectorAll('.alert.alert-success');
        if (successAlerts.length > 0) {
            setTimeout(() => {
                successAlerts.forEach(alert => {
                    alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-6px)';
                    setTimeout(() => alert.remove(), 400);
                });
            }, 3000);
        }

        // Prevent form resubmission warning on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    });

    window.toggleAdminNavGroup = function(btn) {
        if (!btn) return;
        const group = btn.closest('.admin-nav-group');
        if (!group) return;
        const isOpen = group.classList.contains('open');
        group.classList.toggle('open', !isOpen);
        btn.setAttribute('aria-expanded', (!isOpen).toString());
    };
})();


