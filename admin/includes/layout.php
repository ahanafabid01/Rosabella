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
            'deals' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L11 3.83a2 2 0 0 0-1.41-.59H4a2 2 0 0 0-2 2v5.59a2 2 0 0 0 .59 1.41L12.17 22a2 2 0 0 0 2.83 0l5.59-5.59a2 2 0 0 0 0-2.83z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>',
            'orders' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
            'reviews' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><polygon points="12 7 13.2 9.4 16 9.8 14 11.8 14.5 14.5 12 13.2 9.5 14.5 10 11.8 8 9.8 10.8 9.4 12 7"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'coupons' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            'hero' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
            'back' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
            'logout' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
        ];

        return $icons[$key] ?? '';
    }
}

if (!function_exists('renderAdminSidebar')) {
    function renderAdminSidebar(string $activePage): void
    {
        $base = BASE_URL;
        $items = [
            'dashboard'  => ['href' => $base . '/admin/dashboard', 'label' => 'Dashboard'],
            'products'   => ['href' => $base . '/admin/products',  'label' => 'Products'],
            'categories' => ['href' => $base . '/admin/categories','label' => 'Categories'],
            'deals'      => ['href' => $base . '/admin/deals',     'label' => 'Deals'],
            'orders'     => ['href' => $base . '/admin/orders',    'label' => 'Orders'],
            'reviews'    => ['href' => $base . '/admin/reviews',   'label' => 'Reviews'],
            'users'      => ['href' => $base . '/admin/users',     'label' => 'Users'],
            'coupons'    => ['href' => $base . '/admin/coupons',   'label' => 'Coupons'],
            'hero'       => ['href' => $base . '/admin/hero',      'label' => 'Hero Banners'],
            'settings'   => ['href' => $base . '/admin/settings',  'label' => 'Settings'],
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
                <?php foreach ($items as $key => $item): ?>
                    <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activePage === $key ? 'active' : '' ?>">
                        <?= adminIcon($key) ?>
                        <span><?= htmlspecialchars($item['label']) ?></span>
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
                <button class="admin-topbar-icon" aria-label="Notifications">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </button>
                <button class="admin-topbar-icon" aria-label="Messages">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                </button>
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
