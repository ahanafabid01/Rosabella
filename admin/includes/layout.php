<?php
/**
 * Rosabella - Shared Admin Layout Helpers
 */

if (!function_exists('adminIcon')) {
    function adminIcon(string $key): string
    {
        $icons = [
            'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
            'products' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            'categories' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>',
            'attributes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
            'deals' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L11 3.83a2 2 0 0 0-1.41-.59H4a2 2 0 0 0-2 2v5.59a2 2 0 0 0 .59 1.41L12.17 22a2 2 0 0 0 2.83 0l5.59-5.59a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
            'orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            'order-create' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/><path d="M12 8v6M9 11h6"/></svg>',
            'reviews'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><polygon points="12 7 13.2 9.4 16 9.8 14 11.8 14.5 14.5 12 13.2 9.5 14.5 10 11.8 8 9.8 10.8 9.4 12 7"/></svg>',
            'notifications' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            'users'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'coupons'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
            'settings'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'hero'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'back' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        ];

        return $icons[$key] ?? '';
    }
}

if (!function_exists('resolveAdminImageSrc')) {
    function resolveAdminImageSrc(?string $imagePath): string
    {
        $imagePath = trim((string)$imagePath);
        if ($imagePath === '') {
            return '';
        }
        if (preg_match('/^(https?:)?\/\//i', $imagePath) || strpos($imagePath, 'data:') === 0 || strpos($imagePath, '../') === 0 || strpos($imagePath, '/') === 0) {
            return $imagePath;
        }
        return '../' . $imagePath;
    }
}

if (!function_exists('renderAdminSidebar')) {
    function renderAdminSidebar(string $activePage): void
    {
        $base = BASE_URL;
        $items = [
            'dashboard'     => ['href' => $base . '/admin/dashboard',       'label' => 'Dashboard'],
            'products'      => ['href' => $base . '/admin/products',         'label' => 'Products'],
            'categories'    => ['href' => $base . '/admin/categories',       'label' => 'Categories'],
            'attributes'    => ['href' => $base . '/admin/attributes.php',   'label' => 'Attributes'],
            'deals'         => ['href' => $base . '/admin/deals',            'label' => 'Deals'],
            'orders'        => ['href' => $base . '/admin/orders',           'label' => 'Orders'],
            'order-create'  => ['href' => $base . '/admin/order-create',     'label' => 'Create Order'],
            'reviews'       => ['href' => $base . '/admin/reviews',          'label' => 'Reviews'],
            'notifications' => ['href' => $base . '/admin/notifications',    'label' => 'Notifications'],
            'users'         => ['href' => $base . '/admin/users',            'label' => 'Users'],
            'coupons'       => ['href' => $base . '/admin/coupons',          'label' => 'Coupons'],
            'hero'          => ['href' => $base . '/admin/hero',             'label' => 'Hero Banners'],
            'settings'      => ['href' => $base . '/admin/settings',         'label' => 'Settings'],
        ];
        ?>
        <div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>
        <aside class="admin-sidebar">
            <div class="admin-logo-centered">
                <?php
                $siteLogo = getSetting('site_logo');
                $siteName = getSetting('site_name') ?: 'Rosabella';
                ?>
                <?php if ($siteLogo): ?>
                    <img src="<?= BASE_URL . '/' . htmlspecialchars($siteLogo) ?>" alt="Logo" style="max-height: 40px; border-radius: 4px;">
                <?php else: ?>
                    <span class="logo-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></span>
                <?php endif; ?>
                <div class="logo-text">Admin Panel</div>
            </div>
            <div class="admin-nav-divider" style="margin-bottom: 1.5rem;"></div>
            <nav class="admin-nav">
                <?php
                // Fetch unread count for sidebar badge
                try {
                    $db = getDB();
                    $sidebarUnread = (int)$db->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
                } catch (Throwable $e) { $sidebarUnread = 0; }
                ?>
                <?php foreach ($items as $key => $item): ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activePage === $key ? 'active' : '' ?>">
                        <?= adminIcon($key) ?>
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <?php if ($key === 'notifications' && $sidebarUnread > 0): ?>
                            <span class="sidebar-notif-badge"><?= $sidebarUnread > 99 ? '99+' : $sidebarUnread ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <div class="admin-nav-divider"></div>
                <a href="<?= BASE_URL ?>/logout" class="admin-nav-muted">
                    <?= adminIcon('logout') ?>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        <?php
    }
}

if (!function_exists('renderAdminTopbar')) {
    function renderAdminTopbar(string $pageTitle): void
    {
        $userName = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
        $initials = strtoupper(substr($userName, 0, 2));
        ?>
        <div class="admin-topbar">
            <div class="admin-topbar-left">
                <!-- Hamburger menu button visible only on mobile -->
                <button class="admin-menu-btn-mobile" type="button" data-admin-sidebar-toggle aria-label="Open admin menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="admin-topbar-breadcrumb">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span class="admin-topbar-separator">></span>
                    <span class="admin-topbar-title"><?= htmlspecialchars($pageTitle) ?></span>
                </div>
            </div>
            <div class="admin-topbar-right">
                <!-- ===== Notification Bell ===== -->
                <div class="notif-wrapper" id="notifWrapper">
                    <button class="admin-topbar-icon notif-bell-btn" id="notifBellBtn"
                            aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-badge" id="notifBadge" hidden>0</span>
                    </button>

                    <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications">
                        <div class="notif-panel-header">
                            <span class="notif-panel-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                                Notifications
                            </span>
                            <div class="notif-header-actions">
                                <button class="notif-mark-all-btn" id="notifMarkAllBtn" title="Mark all as read">&#10003; All read</button>
                                <button class="notif-refresh-btn" id="notifRefreshBtn" title="Refresh">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="notif-tabs" role="tablist" id="notifTabs">
                            <button class="notif-tab active" data-filter="all" role="tab">All</button>
                            <button class="notif-tab" data-filter="order" role="tab">Orders</button>
                            <button class="notif-tab" data-filter="stock" role="tab">Stock</button>
                            <button class="notif-tab" data-filter="review" role="tab">Reviews</button>
                            <button class="notif-tab" data-filter="user" role="tab">Users</button>
                        </div>
                        <div class="notif-list" id="notifList" role="list">
                            <div class="notif-loading" id="notifLoading">
                                <span class="notif-spinner"></span>
                                <span>Loading&hellip;</span>
                            </div>
                        </div>
                        <div class="notif-panel-footer">
                            <span class="notif-last-updated" id="notifLastUpdated"></span>
                            <a href="<?= BASE_URL ?>/admin/orders" class="notif-footer-link">View all orders &rarr;</a>
                        </div>
                    </div>
                </div>
                <!-- ===== End Notification Bell ===== -->
                <div class="admin-topbar-profile-pill">
                    <div class="admin-topbar-avatar"><?= $initials ?></div>
                    <span class="admin-topbar-name"><?= $userName ?></span>
                </div>
                <a href="<?= BASE_URL ?>/logout" class="admin-topbar-icon admin-topbar-logout" aria-label="Log Out">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="logout-text">Log Out</span>
                </a>
            </div>
        </div>
        <?php
    }
}

// Inject notification JS once per page (guard against multiple topbar calls)
if (!function_exists('renderNotificationScript')) {
    function renderNotificationScript(): void {
        static $injected = false;
        if ($injected) return;
        $injected = true;
        $base = htmlspecialchars(BASE_URL, ENT_QUOTES);
        echo "<script>window.ROSABELLA_BASE_URL = '{$base}';</script>\n";
        echo '<script src="' . $base . '/admin/js/notifications.js" defer></script>' . "\n";
    }
}
renderNotificationScript();


if (!function_exists('renderAdminPagination')) {
    function renderAdminPagination(int $currentPage, int $totalItems, int $perPage, string $baseUrl, array $queryParams = []): void
    {
        $totalPages = max(1, (int)ceil($totalItems / $perPage));
        $startItem = $totalItems > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
        $endItem = min($totalItems, $currentPage * $perPage);

        $buildUrl = function($p, $pp = null) use ($baseUrl, $queryParams, $perPage) {
            $params = array_merge($queryParams, [
                'page' => $p,
                'per_page' => $pp ?? $perPage
            ]);
            return htmlspecialchars($baseUrl . '?' . http_build_query($params));
        };

        ?>
        <style>
            .admin-pagination-bar {
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 1rem;
                padding: 1.25rem 1.5rem;
                background: #ffffff;
                border-top: 1px solid #f1f5f9;
                border-bottom-left-radius: 12px;
                border-bottom-right-radius: 12px;
            }
            .admin-pagination-info-wrap {
                display: flex;
                align-items: center;
                gap: 16px;
                font-size: 0.85rem;
                color: #64748b;
                flex-wrap: wrap;
            }
            .admin-pagination-controls {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .admin-pg-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 14px;
                border-radius: 8px;
                background: #f1f5f9;
                color: #334155;
                font-weight: 600;
                font-size: 0.85rem;
                text-decoration: none;
                transition: all 0.15s ease;
                border: 1px solid transparent;
            }
            .admin-pg-btn:hover {
                background: #e2e8f0;
                color: #0f172a;
            }
            .admin-pg-btn.active {
                background: var(--color-primary, #0f766e);
                color: #ffffff;
                font-weight: 700;
                box-shadow: 0 2px 6px rgba(15, 118, 110, 0.25);
            }
            .admin-pg-btn.disabled {
                background: #f8fafc;
                color: #cbd5e1;
                cursor: not-allowed;
            }
            @media (max-width: 768px) {
                .admin-pagination-bar {
                    flex-direction: column !important;
                    align-items: flex-start !important;
                    padding: 1rem !important;
                    gap: 0.85rem !important;
                }
                .admin-pagination-info-wrap {
                    width: 100% !important;
                    gap: 10px !important;
                }
                .admin-pagination-controls {
                    width: 100% !important;
                    overflow-x: auto;
                    padding-bottom: 2px;
                }
            }
        </style>

        <div class="admin-pagination-bar">
            <!-- Left Side: Entries Selector & Showing X to Y of Z entries -->
            <div class="admin-pagination-info-wrap">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span>Show</span>
                    <select onchange="location.href = this.value;" style="padding: 4px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; font-size: 0.85rem; font-weight: 600; cursor: pointer; outline: none;">
                        <?php foreach ([10, 15, 25, 50, 100] as $opt): ?>
                            <option value="<?= $buildUrl(1, $opt) ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>entries</span>
                </div>
                <div>
                    Showing <strong style="color: #0f172a;"><?= $startItem ?></strong> to <strong style="color: #0f172a;"><?= $endItem ?></strong> of <strong style="color: #0f172a;"><?= $totalItems ?></strong> entries
                </div>
            </div>

            <!-- Right Side: Individual Floating Rounded Buttons -->
            <div class="admin-pagination-controls">
                <!-- Previous Button -->
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $buildUrl($currentPage - 1) ?>" class="admin-pg-btn">Previous</a>
                <?php else: ?>
                    <span class="admin-pg-btn disabled">Previous</span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);

                if ($startPage > 1) {
                    echo '<a href="' . $buildUrl(1) . '" class="admin-pg-btn">1</a>';
                    if ($startPage > 2) {
                        echo '<span style="padding: 0 4px; color: #94a3b8; font-size: 0.85rem;">...</span>';
                    }
                }

                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i === $currentPage) {
                        echo '<span class="admin-pg-btn active">' . $i . '</span>';
                    } else {
                        echo '<a href="' . $buildUrl($i) . '" class="admin-pg-btn">' . $i . '</a>';
                    }
                }

                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span style="padding: 0 4px; color: #94a3b8; font-size: 0.85rem;">...</span>';
                    }
                    echo '<a href="' . $buildUrl($totalPages) . '" class="admin-pg-btn">' . $totalPages . '</a>';
                }
                ?>

                <!-- Next Button -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= $buildUrl($currentPage + 1) ?>" class="admin-pg-btn">Next</a>
                <?php else: ?>
                    <span class="admin-pg-btn disabled">Next</span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

