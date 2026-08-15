/**
 * Rosabella Admin — Notification Bell System
 * Polls /api/admin_notifications.php every 60s.
 * Supports filtering by type, mark-as-read, refresh, and outside-click close.
 */
(function () {
    'use strict';

    if (window.__notificationsJsInitialized) return;
    window.__notificationsJsInitialized = true;

    // ── Config ──────────────────────────────────────────────────────────────
    const POLL_INTERVAL_MS = 60_000; // 60 seconds
    const API_URL = (window.ROSABELLA_BASE_URL || '') + '/api/admin_notifications.php';

    // ── SVG Icons per type ───────────────────────────────────────────────────
    const ICONS = {
        order: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>`,
        stock: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
        review: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
        user: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`,
        alert: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    };

    // ── State ────────────────────────────────────────────────────────────────
    let allNotifications = [];
    let readIds = new Set(JSON.parse(localStorage.getItem('notif_read') || '[]'));
    let activeFilter = 'all';
    let pollTimer = null;
    let isOpen = false;

    // ── DOM Refs (lazy init after DOMContentLoaded) ──────────────────────────
    let bell, badge, panel, backdrop, list, loadingEl, markAllBtn, refreshBtn, closeMobileBtn, lastUpdatedEl, tabs;

    function initRefs() {
        bell           = document.getElementById('notifBellBtn');
        badge          = document.getElementById('notifBadge');
        panel          = document.getElementById('notifPanel');
        backdrop       = document.getElementById('notifBackdrop');
        list           = document.getElementById('notifList');
        loadingEl      = document.getElementById('notifLoading');
        markAllBtn     = document.getElementById('notifMarkAllBtn');
        refreshBtn     = document.getElementById('notifRefreshBtn');
        closeMobileBtn = document.getElementById('notifCloseMobileBtn');
        lastUpdatedEl  = document.getElementById('notifLastUpdated');
        tabs           = document.querySelectorAll('#notifTabs .notif-tab');
    }

    // ── Time-ago helper ──────────────────────────────────────────────────────
    function timeAgo(dateStr) {
        if (!dateStr) return '';
        const now  = new Date();
        const then = new Date(dateStr.replace(' ', 'T'));
        const diff = Math.floor((now - then) / 1000);
        if (diff <  60)  return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    // ── Persist read set ─────────────────────────────────────────────────────
    function saveRead() {
        try { localStorage.setItem('notif_read', JSON.stringify([...readIds])); } catch (_) {}
    }

    // ── Unread count (ignores server "all" count — uses local read set) ──────
    function countUnread() {
        return allNotifications.filter(n => !readIds.has(String(n.id))).length;
    }

    // ── Update badge ─────────────────────────────────────────────────────────
    function updateBadge() {
        if (!badge) return;
        const n = countUnread();
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : n;
            badge.hidden = false;
        } else {
            badge.hidden = true;
        }
    }

    // ── Render items ─────────────────────────────────────────────────────────
    function renderList() {
        if (!list) return;
        const filtered = activeFilter === 'all'
            ? allNotifications
            : allNotifications.filter(n => n.type === activeFilter);

        // Remove existing items (keep loading placeholder structure)
        while (list.firstChild) list.removeChild(list.firstChild);

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'notif-empty';
            empty.innerHTML = `
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                <p class="notif-empty-title">All clear!</p>
                <p class="notif-empty-sub">No notifications in this category</p>`;
            list.appendChild(empty);
            return;
        }

        filtered.forEach(n => {
            const isRead = readIds.has(String(n.id)) || Number(n.is_read) === 1;
            const a = document.createElement('a');
            a.href = n.url || '#';
            a.className = 'notif-item' + (isRead ? ' notif-read' : '');
            a.dataset.id = n.id;
            a.dataset.priority = n.priority || 'low';
            a.setAttribute('role', 'listitem');

            a.innerHTML = `
                <div class="notif-icon-bubble type-${n.type || 'alert'}">${ICONS[n.icon] || ICONS.alert}</div>
                <div class="notif-content">
                    <div class="notif-title">${escHtml(n.title)}</div>
                    <div class="notif-body">${escHtml(n.body)}</div>
                    <div class="notif-meta">
                        ${n.created_at || n.time ? `<span class="notif-time">${timeAgo(n.created_at || n.time)}</span>` : ''}
                        <span class="notif-priority-pill ${n.priority}">${n.priority}</span>
                    </div>
                </div>
                ${n.count > 1 ? `<span class="notif-count-chip">${n.count}</span>` : ''}`;

            a.addEventListener('click', () => {
                readIds.add(String(n.id));
                saveRead();
                updateBadge();
            });

            list.appendChild(a);
        });
    }

    // ── Fetch from API ───────────────────────────────────────────────────────
    async function fetchNotifications(showSpinner = false) {
        if (showSpinner && loadingEl && list) {
            list.innerHTML = '';
            const spinner = document.createElement('div');
            spinner.className = 'notif-loading';
            spinner.id = 'notifLoading';
            spinner.innerHTML = '<span class="notif-spinner"></span><span>Loading&hellip;</span>';
            list.appendChild(spinner);
        }

        if (refreshBtn) refreshBtn.classList.add('spinning');

        try {
            const res = await fetch(API_URL, { credentials: 'same-origin', cache: 'no-store' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            if (data.success && Array.isArray(data.notifications)) {
                allNotifications = data.notifications;
                updateBadge();
                if (isOpen) renderList();
                if (lastUpdatedEl) {
                    const t = new Date();
                    lastUpdatedEl.textContent = 'Updated ' + t.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }
            }
        } catch (err) {
            // Silently fail — don't break admin panel
        } finally {
            if (refreshBtn) refreshBtn.classList.remove('spinning');
        }
    }

    // ── Open / Close panel ───────────────────────────────────────────────────
    function openPanel() {
        if (!panel || !bell) return;
        isOpen = true;
        panel.classList.add('notif-panel--open');
        if (backdrop) backdrop.classList.add('active');
        bell.setAttribute('aria-expanded', 'true');
        renderList();
    }

    function closePanel() {
        if (!panel || !bell) return;
        isOpen = false;
        panel.classList.remove('notif-panel--open');
        if (backdrop) backdrop.classList.remove('active');
        bell.setAttribute('aria-expanded', 'false');
    }

    function togglePanel() {
        isOpen ? closePanel() : openPanel();
    }

    // ── Escape HTML ──────────────────────────────────────────────────────────
    function escHtml(str) {
        return (str || '').replace(/[&<>"']/g, c => ({
            '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
        })[c]);
    }

    function init() {
        initRefs();
        if (!bell || !panel) return;

        // Initial fetch
        fetchNotifications(true);

        // Start polling
        if (!pollTimer) {
            pollTimer = setInterval(() => fetchNotifications(false), POLL_INTERVAL_MS);
        }

        // Bell click / touch
        bell.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            togglePanel();
        });

        // Close on backdrop click
        if (backdrop) {
            backdrop.addEventListener('click', (e) => {
                e.preventDefault();
                closePanel();
            });
        }

        // Close button inside mobile header
        if (closeMobileBtn) {
            closeMobileBtn.addEventListener('click', (e) => {
                e.preventDefault();
                closePanel();
            });
        }

        // Tab filter
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                activeFilter = tab.dataset.filter;
                renderList();
            });
        });

        // Mark all read
        if (markAllBtn) {
            markAllBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                allNotifications.forEach(n => readIds.add(String(n.id)));
                saveRead();
                updateBadge();
                renderList();

                try {
                    const fd = new FormData();
                    fd.append('action', 'mark_all_read');
                    await fetch(API_URL, { method: 'POST', body: fd, credentials: 'same-origin' });
                } catch (_) {}
            });
        }

        // Refresh
        if (refreshBtn) {
            refreshBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fetchNotifications(true);
            });
        }

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (isOpen && !panel.contains(e.target) && !bell.contains(e.target)) {
                closePanel();
            }
        });

        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen) closePanel();
        });
    }

    // ── Bootstrap ────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

