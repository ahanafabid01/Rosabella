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
            'customers'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'users'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
            'coupons'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
            'website'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
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

        // Fetch unread count for sidebar badge
        try {
            $db = getDB();
            $sidebarUnread = (int)$db->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
        } catch (Throwable $e) { 
            $sidebarUnread = 0; 
        }

        // Fetch custom theme colors
        $adminSidebarBg = getSetting('admin_sidebar_bg') ?: '#f1f5f9';
        $adminContentBg = getSetting('admin_content_bg') ?: '#f8fafc';
        $adminPrimaryColor = getSetting('admin_primary_color') ?: '#0f766e';
        ?>
        <style id="rosabella-admin-theme-vars">
            :root {
                --admin-sidebar-bg: <?= htmlspecialchars($adminSidebarBg) ?>;
                --admin-content-bg: <?= htmlspecialchars($adminContentBg) ?>;
                --admin-theme-primary: <?= htmlspecialchars($adminPrimaryColor) ?>;
            }
            .admin-sidebar { background: var(--admin-sidebar-bg) !important; }
            .admin-content { background: var(--admin-content-bg) !important; }
        </style>
        <?php

        $navSections = [
            [
                'section' => 'OVERVIEW',
                'items' => [
                    [
                        'type'   => 'link',
                        'key'    => 'dashboard',
                        'href'   => $base . '/admin/dashboard',
                        'label'  => 'Dashboard',
                        'icon'   => 'dashboard',
                    ]
                ]
            ],
            [
                'section' => 'COMMERCE',
                'items' => [
                    [
                        'type'     => 'dropdown',
                        'key'      => 'customers_group',
                        'label'    => 'Customer',
                        'icon'     => 'customers',
                        'active_in'=> ['customers'],
                        'subitems' => [
                            ['href' => $base . '/admin/customers', 'label' => 'Customer List', 'key' => 'customers'],
                        ]
                    ],
                    [
                        'type'     => 'dropdown',
                        'key'      => 'products_group',
                        'label'    => 'Product',
                        'icon'     => 'products',
                        'active_in'=> ['products', 'categories', 'attributes', 'deals'],
                        'subitems' => [
                            ['href' => $base . '/admin/products', 'label' => 'Product List', 'key' => 'products'],
                            ['href' => $base . '/admin/categories', 'label' => 'Categories', 'key' => 'categories'],
                            ['href' => $base . '/admin/attributes.php', 'label' => 'Attributes', 'key' => 'attributes'],
                            ['href' => $base . '/admin/deals', 'label' => 'Deals & Offers', 'key' => 'deals'],
                        ]
                    ],
                    [
                        'type'     => 'dropdown',
                        'key'      => 'orders_group',
                        'label'    => 'Order',
                        'icon'     => 'orders',
                        'active_in'=> ['orders', 'order-create'],
                        'subitems' => [
                            ['href' => $base . '/admin/orders', 'label' => 'Order List', 'key' => 'orders'],
                            ['href' => $base . '/admin/order-create', 'label' => 'Order Create', 'key' => 'order-create'],
                        ]
                    ],
                ]
            ],
            [
                'section' => 'MARKETING',
                'items' => [
                    [
                        'type'     => 'dropdown',
                        'key'      => 'marketing_group',
                        'label'    => 'Marketing',
                        'icon'     => 'coupons',
                        'active_in'=> ['coupons', 'reviews'],
                        'subitems' => [
                            ['href' => $base . '/admin/coupons', 'label' => 'Coupons & Vouchers', 'key' => 'coupons'],
                            ['href' => $base . '/admin/reviews', 'label' => 'Customer Reviews', 'key' => 'reviews'],
                        ]
                    ],
                ]
            ],
            [
                'section' => 'MANAGEMENT',
                'items' => [
                    [
                        'type'     => 'dropdown',
                        'key'      => 'system_group',
                        'label'    => 'Staff & System',
                        'icon'     => 'users',
                        'active_in'=> ['users', 'notifications'],
                        'subitems' => [
                            ['href' => $base . '/admin/users', 'label' => 'Staffs', 'key' => 'users'],
                            ['href' => $base . '/admin/notifications', 'label' => 'Notifications', 'key' => 'notifications', 'badge' => $sidebarUnread],
                        ]
                    ],
                    [
                        'type'     => 'dropdown',
                        'key'      => 'settings_group',
                        'label'    => 'Website & Settings',
                        'icon'     => 'settings',
                        'active_in'=> ['website', 'hero', 'settings'],
                        'subitems' => [
                            ['href' => $base . '/admin/website-settings', 'label' => 'Storefront Settings', 'key' => 'website'],
                            ['href' => $base . '/admin/hero', 'label' => 'Hero Banners', 'key' => 'hero'],
                            ['href' => $base . '/admin/settings', 'label' => 'Admin Settings', 'key' => 'settings'],
                        ]
                    ],
                ]
            ]
        ];
        ?>
        <div class="admin-sidebar-backdrop" data-admin-sidebar-close></div>
        <aside class="admin-sidebar">
            <div class="admin-sidebar-brand">
                <?php
                $siteLogo = getSetting('site_logo');
                $siteName = getSetting('site_name') ?: 'Rosabella';
                ?>
                <div class="admin-brand-logo-wrap">
                    <?php if ($siteLogo): ?>
                        <img src="<?= BASE_URL . '/' . htmlspecialchars($siteLogo) ?>" alt="Logo" class="admin-brand-logo-img">
                    <?php else: ?>
                        <div class="admin-brand-logo-icon"><?= strtoupper(substr($siteName, 0, 1)) ?></div>
                    <?php endif; ?>
                </div>
                <div class="admin-brand-info">
                    <span class="admin-brand-name"><?= htmlspecialchars($siteName) ?></span>
                    <span class="admin-brand-badge">Admin Panel</span>
                </div>
            </div>
            
            <div class="admin-nav-divider" style="margin-bottom: 0.5rem;"></div>

            <nav class="admin-nav">
                <?php foreach ($navSections as $sec): ?>
                    <?php if (!empty($sec['section'])): ?>
                        <div class="admin-nav-section-title"><?= htmlspecialchars($sec['section']) ?></div>
                    <?php endif; ?>

                    <?php foreach ($sec['items'] as $item): ?>
                        <?php if ($item['type'] === 'link'): ?>
                            <a href="<?= htmlspecialchars($item['href']) ?>" class="admin-nav-link <?= $activePage === $item['key'] ? 'active' : '' ?>">
                                <?= adminIcon($item['icon']) ?>
                                <span class="admin-nav-label"><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php elseif ($item['type'] === 'dropdown'): ?>
                            <?php 
                            $isGroupActive = in_array($activePage, $item['active_in'], true);
                            $isOpen = $isGroupActive;
                            ?>
                            <div class="admin-nav-group <?= $isOpen ? 'open' : '' ?>">
                                <button type="button" class="admin-nav-group-btn <?= $isGroupActive ? 'active-group' : '' ?>" onclick="toggleAdminNavGroup(this)" aria-expanded="<?= $isOpen ? 'true' : 'false' ?>">
                                    <span class="admin-nav-group-left">
                                        <?= adminIcon($item['icon']) ?>
                                        <span class="admin-nav-label"><?= htmlspecialchars($item['label']) ?></span>
                                    </span>
                                    <svg class="admin-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"/>
                                    </svg>
                                </button>
                                <div class="admin-nav-submenu">
                                    <div class="admin-nav-submenu-inner">
                                        <?php foreach ($item['subitems'] as $sub): ?>
                                            <?php $isSubActive = ($activePage === $sub['key']); ?>
                                            <a href="<?= htmlspecialchars($sub['href']) ?>" class="admin-nav-sublink <?= $isSubActive ? 'active' : '' ?>">
                                                <span class="admin-nav-sub-dot"></span>
                                                <span class="admin-nav-sub-text"><?= htmlspecialchars($sub['label']) ?></span>
                                                <?php if (!empty($sub['badge']) && $sub['badge'] > 0): ?>
                                                    <span class="sidebar-notif-badge"><?= $sub['badge'] > 99 ? '99+' : $sub['badge'] ?></span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <div class="admin-nav-divider" style="margin-top: 1rem;"></div>

                <!-- Live Storefront Quick Link -->
                <a href="<?= BASE_URL ?>/" target="_blank" class="admin-nav-link admin-nav-storefront-link" title="Open Storefront in New Tab">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <span class="admin-nav-label">Live Storefront</span>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: auto; opacity: 0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>

                <a href="<?= BASE_URL ?>/logout" class="admin-nav-link admin-nav-muted" style="color: #f87171 !important;">
                    <?= adminIcon('logout') ?>
                    <span class="admin-nav-label">Logout</span>
                </a>
            </nav>
        </aside>
        <?php
    }
}

if (!function_exists('renderAdminTopbar')) {
    function renderAdminTopbar(string $pageTitle): void
    {
        $userName   = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
        $userAvatar = $_SESSION['user_avatar'] ?? null;

        if ($userAvatar === null && isset($_SESSION['user_id'])) {
            try {
                $db = getDB();
                $avStmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
                $avStmt->execute([(int)$_SESSION['user_id']]);
                $userAvatar = $avStmt->fetchColumn() ?: '';
                $_SESSION['user_avatar'] = $userAvatar;
            } catch (Throwable $e) {
                $userAvatar = '';
            }
        }

        $avatarSrc = resolveAdminImageSrc($userAvatar);
        $initials  = strtoupper(substr(trim($_SESSION['user_name'] ?? 'AD'), 0, 2));
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
                            type="button"
                            aria-label="Notifications" aria-expanded="false" aria-haspopup="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="notif-badge" id="notifBadge" hidden>0</span>
                    </button>

                    <div class="notif-backdrop" id="notifBackdrop" data-notif-close></div>

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
                                <button class="notif-close-mobile-btn" id="notifCloseMobileBtn" type="button" aria-label="Close notifications">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
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
                            <a href="<?= BASE_URL ?>/admin/notifications" class="notif-footer-link">Notification Centre &rarr;</a>
                        </div>
                    </div>
                </div>
                <!-- ===== End Notification Bell ===== -->
                <a href="<?= BASE_URL ?>/admin/settings?tab=profile" class="admin-topbar-profile-pill" style="text-decoration: none; cursor: pointer;" title="Admin Profile & Settings">
                    <?php if (!empty($userAvatar)): ?>
                        <img src="<?= htmlspecialchars($avatarSrc) ?>" alt="<?= $userName ?>" class="admin-topbar-avatar">
                    <?php else: ?>
                        <div class="admin-topbar-avatar"><?= $initials ?></div>
                    <?php endif; ?>
                    <span class="admin-topbar-name"><?= $userName ?></span>
                </a>
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

// Inject admin layout & notification scripts once per page
if (!function_exists('renderAdminScripts')) {
    function renderAdminScripts(): void {
        static $injected = false;
        if ($injected) return;
        $injected = true;
        $base = htmlspecialchars(BASE_URL, ENT_QUOTES);
        echo "<script>window.ROSABELLA_BASE_URL = '{$base}';</script>\n";
        echo '<script src="' . $base . '/admin/js/admin.js" defer></script>' . "\n";
        echo '<script src="' . $base . '/admin/js/notifications.js" defer></script>' . "\n";
    }
}
renderAdminScripts();



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

