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
            'order-create' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/><path d="M12 8v6M9 11h6"/></svg>',
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
            'dashboard'    => ['href' => $base . '/admin/dashboard',   'label' => 'Dashboard'],
            'products'     => ['href' => $base . '/admin/products',    'label' => 'Products'],
            'categories'   => ['href' => $base . '/admin/categories',  'label' => 'Categories'],
            'deals'        => ['href' => $base . '/admin/deals',       'label' => 'Deals'],
            'orders'       => ['href' => $base . '/admin/orders',      'label' => 'Orders'],
            'order-create' => ['href' => $base . '/admin/order-create','label' => 'Create Order'],
            'reviews'      => ['href' => $base . '/admin/reviews',     'label' => 'Reviews'],
            'users'        => ['href' => $base . '/admin/users',       'label' => 'Users'],
            'coupons'      => ['href' => $base . '/admin/coupons',     'label' => 'Coupons'],
            'hero'         => ['href' => $base . '/admin/hero',        'label' => 'Hero Banners'],
            'settings'     => ['href' => $base . '/admin/settings',    'label' => 'Settings'],
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
                gap: 0.85rem;
                padding: 1rem 1.25rem;
                background: #ffffff;
                border-top: 1px solid #f1f5f9;
                border-bottom-left-radius: 12px;
                border-bottom-right-radius: 12px;
            }
            .admin-pagination-info-wrap {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 0.83rem;
                color: #64748b;
                flex-wrap: wrap;
            }
            .admin-pagination-sep {
                color: #cbd5e1;
            }
            .admin-pagination-controls {
                display: inline-flex;
                align-items: center;
                background: #f8fafc;
                padding: 4px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                gap: 3px;
            }
            @media (max-width: 768px) {
                .admin-pagination-bar {
                    flex-direction: column !important;
                    align-items: stretch !important;
                    padding: 0.85rem 1rem !important;
                    gap: 0.75rem !important;
                }
                .admin-pagination-info-wrap {
                    justify-content: space-between !important;
                    width: 100% !important;
                    font-size: 0.8rem !important;
                }
                .admin-pagination-sep {
                    display: none !important;
                }
                .admin-pagination-controls {
                    width: 100% !important;
                    justify-content: center !important;
                    box-sizing: border-box !important;
                }
                .admin-pagination-controls a,
                .admin-pagination-controls span {
                    flex: 1 !important;
                    text-align: center !important;
                    justify-content: center !important;
                    padding: 0.4rem 0.5rem !important;
                }
            }
        </style>

        <div class="admin-pagination-bar">
            <!-- Left Side: Entries Selector & Showing X to Y of Z entries -->
            <div class="admin-pagination-info-wrap">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span>Show</span>
                    <select onchange="location.href = this.value;" style="padding: 0.25rem 0.5rem; border: 1.5px solid #cbd5e1; border-radius: 6px; background: #fff; color: #334155; font-size: 0.82rem; font-weight: 600; cursor: pointer; outline: none;">
                        <?php foreach ([10, 15, 25, 50, 100] as $opt): ?>
                            <option value="<?= $buildUrl(1, $opt) ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span>entries</span>
                </div>
                <span class="admin-pagination-sep">|</span>
                <div>
                    Showing <strong style="color: #0f172a;"><?= $startItem ?></strong>-<?= $endItem ?> of <strong style="color: #0f172a;"><?= $totalItems ?></strong>
                </div>
            </div>

            <!-- Right Side: Professional Button Pill Group -->
            <div class="admin-pagination-controls">
                <!-- Previous Button -->
                <?php if ($currentPage > 1): ?>
                    <a href="<?= $buildUrl($currentPage - 1) ?>" style="padding: 0.4rem 0.85rem; border-radius: 6px; background: transparent; color: #475569; font-weight: 600; font-size: 0.84rem; text-decoration: none; transition: all 0.15s ease;">
                        Prev
                    </a>
                <?php else: ?>
                    <span style="padding: 0.4rem 0.85rem; border-radius: 6px; background: transparent; color: #cbd5e1; font-weight: 500; font-size: 0.84rem; cursor: not-allowed;">
                        Prev
                    </span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);

                if ($startPage > 1) {
                    echo '<a href="' . $buildUrl(1) . '" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: transparent; color: #475569; font-weight: 600; font-size: 0.84rem; text-decoration: none;">1</a>';
                    if ($startPage > 2) {
                        echo '<span style="padding: 0.4rem 0.3rem; color: #94a3b8; font-size: 0.84rem;">...</span>';
                    }
                }

                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i === $currentPage) {
                        echo '<span style="padding: 0.4rem 0.85rem; border-radius: 6px; background: #0f766e; color: #ffffff; font-weight: 700; font-size: 0.84rem; box-shadow: 0 2px 6px rgba(15, 118, 110, 0.25);">' . $i . '</span>';
                    } else {
                        echo '<a href="' . $buildUrl($i) . '" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: transparent; color: #475569; font-weight: 600; font-size: 0.84rem; text-decoration: none;">' . $i . '</a>';
                    }
                }

                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span style="padding: 0.4rem 0.3rem; color: #94a3b8; font-size: 0.84rem;">...</span>';
                    }
                    echo '<a href="' . $buildUrl($totalPages) . '" style="padding: 0.4rem 0.75rem; border-radius: 6px; background: transparent; color: #475569; font-weight: 600; font-size: 0.84rem; text-decoration: none;">' . $totalPages . '</a>';
                }
                ?>

                <!-- Next Button -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= $buildUrl($currentPage + 1) ?>" style="padding: 0.4rem 0.85rem; border-radius: 6px; background: transparent; color: #475569; font-weight: 600; font-size: 0.84rem; text-decoration: none; transition: all 0.15s ease;">
                        Next
                    </a>
                <?php else: ?>
                    <span style="padding: 0.4rem 0.85rem; border-radius: 6px; background: transparent; color: #cbd5e1; font-weight: 500; font-size: 0.84rem; cursor: not-allowed;">
                        Next
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

