<?php
/**
 * Rosabella - Homepage Theme Manager
 * 
 * This file provides theme loading and rendering functions for the homepage.
 * Each theme can customize the layout and presentation of sections.
 */

/**
 * Get the currently selected homepage theme
 * @return string Theme key (default_theme, clothing_brand)
 */
function getHomepageTheme() {
    if (!empty($_GET['theme_preview'])) {
        $allowed = ['default_theme', 'clothing_brand'];
        if (in_array($_GET['theme_preview'], $allowed)) {
            return $_GET['theme_preview'];
        }
    }
    return getSetting('homepage_theme') ?: 'default_theme';
}

/**
 * Get theme configuration
 * @param string $theme Theme key
 * @return array Theme configuration with metadata and settings
 */
function getThemeConfig($theme = null) {
    $theme = $theme ?: getHomepageTheme();
    
    $configs = [
        'default_theme' => [
            'name' => 'Default Theme',
            'hero_grid_layout' => 'bento',      // 'bento', 'full-width', 'minimal'
            'hero_height_mobile' => 'clamp(320px, 55vw, 480px)',
            'hero_height_tablet' => 'clamp(480px, 60vh, 600px)',
            'hero_height_desktop' => 'clamp(620px, 68vh, 720px)',
            'categories_columns_mobile' => 2,
            'categories_columns_tablet' => 3,
            'categories_columns_desktop' => 6,
            'products_columns_mobile' => 2,
            'products_columns_tablet' => 3,
            'products_columns_desktop' => 4,
            'product_card_style' => 'standard',  // 'standard', 'minimal', 'detailed'
            'show_category_sidebar' => false,
            'featured_section_title' => 'Featured Products',
            'new_arrivals_title' => 'New Arrivals',
            'compact_mode' => false,
        ],
        'clothing_brand' => [
            'name' => 'Clothing Brand',
            'hero_grid_layout' => 'full-width',
            'hero_height_mobile' => 'clamp(380px, 65vw, 520px)',
            'hero_height_tablet' => 'clamp(520px, 70vh, 680px)',
            'hero_height_desktop' => 'clamp(680px, 75vh, 800px)',
            'categories_columns_mobile' => 2,
            'categories_columns_tablet' => 3,
            'categories_columns_desktop' => 4,
            'products_columns_mobile' => 2,
            'products_columns_tablet' => 3,
            'products_columns_desktop' => 4,
            'product_card_style' => 'detailed',
            'show_category_sidebar' => false,
            'featured_section_title' => 'Featured Collections',
            'new_arrivals_title' => 'New Arrival',
            'compact_mode' => false,
            'promotional_sections' => true,  // Show promotional banners
            'category_showcase' => true,     // Prominent category display
            'hero_overlay_gradient' => true, // Dark overlay on hero
        ],
    ];
    
    return $configs[$theme] ?? $configs['default_theme'];
}

/**
 * Get CSS class names for hero section based on theme
 * @param string $theme Theme key
 * @return array Array of CSS variable overrides for hero
 */
function getThemeHeroCSS($theme = null) {
    $theme = $theme ?: getHomepageTheme();
    $config = getThemeConfig($theme);
    
    $cssVars = [];
    
    // Set CSS variables for responsive heights
    if ($theme === 'clothing_brand') {
        $cssVars['--hero-height-mobile'] = $config['hero_height_mobile'];
        $cssVars['--hero-height-tablet'] = $config['hero_height_tablet'];
        $cssVars['--hero-height-desktop'] = $config['hero_height_desktop'];
    }
    
    return $cssVars;
}

/**
 * Render hero section based on selected theme
 * @param array $heroMain Main banner slides
 * @param array $heroSideTop Top side banner slides
 * @param array $heroSideBottom Bottom side banner slides
 */
function renderHeroSection($heroMain, $heroSideTop, $heroSideBottom) {
    $theme = getHomepageTheme();
    $config = getThemeConfig($theme);
    
    if (empty($heroMain) && empty($heroSideTop) && empty($heroSideBottom)) {
        return; // No hero data to display
    }
    
    $gridClass = $config['hero_grid_layout'] === 'full-width' ? 'hero-bento-grid--full' : '';
    $shouldShowSide = (!empty($heroSideTop) || !empty($heroSideBottom)) && $config['hero_grid_layout'] !== 'full-width';
    ?>
    <section class="section" style="padding-top: 1.5rem; padding-bottom: 2rem;">
        <div class="container">
            <?php if ($config['hero_grid_layout'] === 'full-width'): ?>
                <!-- Full Width Hero (Showcase Layout) -->
                <div class="hero-main-banner" style="height: auto; min-height: 520px; border-radius: var(--radius-xl); overflow: hidden;">
                    <div class="hero-slider"></div>
                </div>
            <?php else: ?>
                <!-- Bento or Minimal Grid -->
                <div class="hero-bento-grid <?= $shouldShowSide ? '' : 'hero-bento-grid--full' ?>">
                    <?php if (!empty($heroMain)): ?>
                    <div class="hero-main-banner">
                        <div class="hero-slider"></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($shouldShowSide): ?>
                    <div class="hero-side-banners">
                        <?php if (!empty($heroSideTop)): ?>
                        <div class="hero-side-slider" data-side="top">
                            <!-- Will be populated by JS -->
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($heroSideBottom)): ?>
                        <div class="hero-side-slider" data-side="bottom">
                            <!-- Will be populated by JS -->
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php
}

/**
 * Get product grid columns based on theme and viewport
 * @param string $viewport 'mobile', 'tablet', or 'desktop'
 * @param string $theme Theme key
 * @return int Number of columns
 */
function getProductGridColumns($viewport = 'desktop', $theme = null) {
    $config = getThemeConfig($theme);
    
    $key = 'products_columns_' . $viewport;
    return $config[$key] ?? 4;
}

/**
 * Check if product card should show detailed info
 * @param string $theme Theme key
 * @return bool
 */
function shouldShowDetailedProductCards($theme = null) {
    $config = getThemeConfig($theme);
    return $config['product_card_style'] === 'detailed';
}

/**
 * Check if theme uses compact spacing
 * @param string $theme Theme key
 * @return bool
 */
function isCompactTheme($theme = null) {
    $config = getThemeConfig($theme);
    return $config['compact_mode'];
}

/**
 * Get section title for featured products
 * @param string $theme Theme key
 * @return string
 */
function getFeaturedSectionTitle($theme = null) {
    $config = getThemeConfig($theme);
    return $config['featured_section_title'];
}

/**
 * Get section title for new arrivals
 * @param string $theme Theme key
 * @return string
 */
function getNewArrivalsSectionTitle($theme = null) {
    $config = getThemeConfig($theme);
    return $config['new_arrivals_title'];
}

/**
 * Get dynamic CSS for theme
 * @param string $theme Theme key
 * @return string CSS styles
 */
function getThemeCSS($theme = null) {
    $theme = $theme ?: getHomepageTheme();
    $config = getThemeConfig($theme);
    
    $css = '';
    
    if ($config['compact_mode']) {
        $css .= '
            /* Compact Mode Spacing */
            .section { padding: 2rem 0; }
            .products-grid { gap: 0.8rem; }
            .product-content { padding: 0.6rem 0.8rem 0.8rem; }
            .product-card { border-radius: 0.5rem; }
            .category-card { border-radius: 0.5rem; }
            .deals-grid { gap: 0.8rem; }
            .deal-card { height: 160px; }
        ';
    }
    
    if ($config['product_card_style'] === 'minimal') {
        $css .= '
            /* Minimal Product Cards */
            .product-name { font-size: 0.85rem; -webkit-line-clamp: 2; }
            .product-price { font-size: 0.9rem; }
            .product-actions { padding: 0.5rem; }
        ';
    } else if ($config['product_card_style'] === 'detailed') {
        $css .= '
            /* Detailed Product Cards */
            .product-card { border-radius: var(--radius-xl); }
            .product-name { font-size: 1rem; -webkit-line-clamp: 3; }
            .product-price { font-size: 1.1rem; margin-top: 0.5rem; }
        ';
    }
    
    // Clothing Brand Theme Specific Styles
    if ($theme === 'clothing_brand') {
        $css .= '
            /* Clothing Brand Theme - Fashion-Focused Layout */
            
            /* Hero Section */
            .hero-main-banner {
                position: relative;
                overflow: hidden;
            }


            .clothing-brand-hero .hero-bento-grid {
                height: clamp(680px, 75vh, 800px);
            }

            .clothing-brand-hero .hero-main-banner {
                height: 100%;
                border-radius: 0;
                box-shadow: none;
            }

            .clothing-brand-hero .hero-slide img {
                object-position: center;
            }

            .clothing-brand-section-header {
                align-items: center;
                flex-direction: column;
                margin-bottom: 0.5rem;
                text-align: center;
            }

            .clothing-brand-eyebrow {
                color: var(--color-primary);
                font-size: 0.75rem;
                font-weight: 800;
                letter-spacing: 0.16em;
                margin: 0 0 0.5rem;
                text-transform: uppercase;
            }

            /* Site-wide fashion brand system */
            .theme-clothing-brand {
                --color-primary: #1c1917;
                --color-primary-hover: #44403c;
                --color-primary-light: rgba(28, 25, 23, 0.08);
                --color-text: #1c1917;
                --color-text-light: #57534e;
                --color-text-muted: #78716c;
                --color-bg: #fffefd;
                --color-bg-secondary: #f7f5f2;
                --color-bg-tertiary: #ede9e3;
                --color-border: #ddd7cf;
                --color-border-light: #ebe7e1;
                background: #fffefd;
                letter-spacing: 0.01em;
            }

            .theme-clothing-brand .container {
                max-width: 1360px;
            }

            .theme-clothing-brand h1,
            .theme-clothing-brand h2,
            .theme-clothing-brand h3,
            .theme-clothing-brand .section-title {
                color: #1c1917;
                letter-spacing: -0.035em;
            }

            .theme-clothing-brand .section {
                padding-top: clamp(3.5rem, 7vw, 6.5rem);
                padding-bottom: clamp(3.5rem, 7vw, 6.5rem);
            }

            .clothing-brand-topbar {
                background: #1c1917;
                color: #fffefd;
                font-size: 0.7rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .clothing-brand-topbar-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 34px;
                gap: 1rem;
            }

            .clothing-brand-topbar-links {
                display: flex;
                align-items: center;
                gap: 1.25rem;
            }

            .clothing-brand-topbar a { color: inherit; opacity: 0.78; }
            .clothing-brand-topbar a:hover { opacity: 1; }

            .theme-clothing-brand .header {
                background: rgba(255, 254, 253, 0.96);
                box-shadow: none;
                border-bottom: 1px solid var(--color-border-light);
            }

            .theme-clothing-brand .header-main {
                border-bottom: 0;
                padding: 1.15rem 0;
            }

            .theme-clothing-brand .logo span {
                color: #1c1917 !important;
                font-size: 1.35rem !important;
                font-weight: 800 !important;
                letter-spacing: 0.12em !important;
            }

            .theme-clothing-brand .header-search-form,
            .theme-clothing-brand .mobile-search-form {
                background: #f7f5f2;
                border: 1px solid transparent;
                border-radius: 0;
            }

            .theme-clothing-brand .header-search-form:focus-within,
            .theme-clothing-brand .mobile-search-form:focus-within {
                background: #fffefd;
                border-color: #1c1917;
            }

            .theme-clothing-brand .header-login-link,
            .theme-clothing-brand .header-cart-btn,
            .theme-clothing-brand .header-icon-btn {
                border-radius: 0;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.07em;
                text-transform: uppercase;
            }

            .theme-clothing-brand .header-nav-bar {
                background: #fffefd;
                border-top: 1px solid var(--color-border-light);
            }

            .theme-clothing-brand .header-nav-list {
                justify-content: center;
            }

            .theme-clothing-brand .header-nav-link {
                color: #292524;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.1em;
                padding: 0.9rem 1.15rem;
                text-transform: uppercase;
            }

            .theme-clothing-brand .header-nav-list li:hover .header-nav-link,
            .theme-clothing-brand .header-nav-link:hover {
                background: transparent;
                color: #1c1917;
            }

            .theme-clothing-brand .header-nav-link::after {
                background: #1c1917;
                bottom: 0;
                content: "";
                height: 2px;
                left: 1.15rem;
                position: absolute;
                right: 1.15rem;
                transform: scaleX(0);
                transition: transform 180ms ease;
            }

            .theme-clothing-brand .header-nav-link:hover::after { transform: scaleX(1); }
            .theme-clothing-brand .nav-dropdown { border-color: var(--color-border); border-radius: 0; border-top: 2px solid #1c1917; }
            .theme-clothing-brand .nav-dropdown a:hover { background: #f7f5f2; color: #1c1917; }

            .theme-clothing-brand .btn,
            .theme-clothing-brand .promo-cta-btn {
                border-radius: 0;
                font-size: 0.74rem;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-transform: uppercase;
            }

            .theme-clothing-brand .btn-primary { background: #1c1917; border-color: #1c1917; }
            .theme-clothing-brand .btn-primary:hover { background: #44403c; border-color: #44403c; }
            .theme-clothing-brand .btn-outline { border-color: #1c1917; color: #1c1917; }
            .theme-clothing-brand .btn-outline:hover { background: #1c1917; color: #fffefd; }

            .theme-clothing-brand .product-card {
                background: transparent;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .theme-clothing-brand .product-image,
            .theme-clothing-brand .product-image img {
                border-radius: 0;
            }

            .theme-clothing-brand .product-content { padding: 1rem 0 0; }
            .theme-clothing-brand .product-name { font-size: 0.9rem; font-weight: 600; letter-spacing: 0.01em; }
            .theme-clothing-brand .product-price { font-size: 0.9rem; margin-top: 0.35rem; }
            .theme-clothing-brand .product-wishlist { border-radius: 0; }
            .theme-clothing-brand .badge { border-radius: 0; font-size: 0.66rem; letter-spacing: 0.06em; text-transform: uppercase; }

            .theme-clothing-brand input,
            .theme-clothing-brand select,
            .theme-clothing-brand textarea {
                border-radius: 0;
            }

            .theme-clothing-brand .page-header,
            .theme-clothing-brand .breadcrumb {
                background: #f7f5f2;
                border-color: var(--color-border-light);
            }

            .theme-clothing-brand .card,
            .theme-clothing-brand .filter-sidebar,
            .theme-clothing-brand .cart-summary,
            .theme-clothing-brand .checkout-summary {
                border-color: var(--color-border-light);
                border-radius: 0;
                box-shadow: none;
            }

            .theme-clothing-brand .site-trust-badges {
                background: #f7f5f2 !important;
                border-color: var(--color-border-light) !important;
            }

            .theme-clothing-brand .footer-trust-item {
                background: transparent;
                border: 0 !important;
                box-shadow: none !important;
                border-radius: 0;
                flex-direction: column;
                padding: 1rem;
            }

            .theme-clothing-brand .footer-trust-item span {
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .theme-clothing-brand .footer {
                background: #1c1917;
                border: 0;
                color: #f5f5f4;
            }

            .theme-clothing-brand .newsletter {
                background: #292524;
                border-bottom: 1px solid #57534e;
            }

            .theme-clothing-brand .newsletter-text h3,
            .theme-clothing-brand .footer-heading { color: #fffefd; }
            .theme-clothing-brand .newsletter-text p,
            .theme-clothing-brand .footer-description,
            .theme-clothing-brand .footer-contact-item,
            .theme-clothing-brand .footer-link,
            .theme-clothing-brand .footer-copyright { color: #d6d3d1; }
            .theme-clothing-brand .footer-link:hover { color: #fffefd; }
            .theme-clothing-brand .footer-heading { font-size: 0.72rem; letter-spacing: 0.1em; text-transform: uppercase; }
            .theme-clothing-brand .footer-bottom { border-color: #44403c; }
            .theme-clothing-brand .footer-social a { background: transparent; border: 1px solid #57534e; border-radius: 0; color: #fffefd; }
            .theme-clothing-brand .footer-social a:hover { background: #fffefd; color: #1c1917; }
            .theme-clothing-brand .payment-method { background: #44403c; border: 0; border-radius: 0; color: #fffefd; }

            /* Catalogue and filter experience */
            .theme-clothing-brand .catalog-page .sidebar-desktop > div {
                background: #fffefd !important;
                border: 1px solid var(--color-border-light) !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                padding: 1.5rem !important;
            }

            .theme-clothing-brand .catalog-page details {
                border-bottom: 1px solid var(--color-border-light);
                margin-bottom: 1.25rem !important;
                padding-bottom: 1.25rem;
            }

            .theme-clothing-brand .catalog-page details:last-child { border-bottom: 0; }
            .theme-clothing-brand .catalog-page summary { letter-spacing: 0.11em !important; }
            .theme-clothing-brand .catalog-page .form-input,
            .theme-clothing-brand .catalog-page .form-select {
                background: #fffefd !important;
                border-color: var(--color-border) !important;
                border-radius: 0 !important;
            }

            .theme-clothing-brand .catalog-page .products-grid-container > div:first-child {
                border-bottom: 1px solid var(--color-border-light);
                margin-bottom: 2rem !important;
                padding-bottom: 1.25rem;
            }

            .theme-clothing-brand .catalog-page .btn-sm {
                border-radius: 0;
                font-size: 0.67rem;
                padding: 0.55rem 0.7rem;
            }

            /* Clothing Brand Pagination (Minimalist Luxury Look) */
            .theme-clothing-brand .catalog-pagination-wrapper {
                border-top: 1px solid var(--color-border-light);
                margin-top: 3.5rem;
                padding-top: 2.5rem;
            }

            .theme-clothing-brand .catalog-pagination-info {
                font-size: 0.75rem;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--color-text-light);
            }

            .theme-clothing-brand .page-btn {
                border-radius: 0;
                border: 1px solid var(--color-border-light);
                background: #fffefd;
                color: #1c1917;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.06em;
                text-transform: uppercase;
            }

            .theme-clothing-brand .page-btn:hover {
                border-color: #1c1917;
                background: #f7f5f2;
                color: #1c1917;
            }

            .theme-clothing-brand .page-btn.active {
                background: #1c1917;
                border-color: #1c1917;
                color: #fffefd;
                box-shadow: none;
            }

            .theme-clothing-brand .page-btn.disabled {
                border-color: var(--color-border-light);
                background: transparent;
                opacity: 0.3;
            }

            @media (min-width: 1024px) {
                .theme-clothing-brand .products-layout.filters-hidden {
                    grid-template-columns: 1fr;
                }
            }

            /* Homepage: editorial collection landing page */
            .theme-clothing-brand .clothing-brand-hero {
                padding: 0 !important;
            }

            .theme-clothing-brand .clothing-brand-hero .container {
                max-width: none;
                padding: 0;
            }

            .theme-clothing-brand .clothing-brand-hero .hero-main-banner {
                border-radius: 0;
            }

            .theme-clothing-brand .clothing-brand-hero .hero-slide::after {
                display: none;
            }


            /* ═══════════════════════════════════════════════
               CLOTHING BRAND: EDITORIAL CATEGORY GRID
            ═══════════════════════════════════════════════ */
            .theme-clothing-brand .clothing-brand-categories {
                background: #f9f7f4;
                padding: 3rem 0 4rem;
            }

            /* Header row */
            .theme-clothing-brand .cb-cat-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .theme-clothing-brand .cb-cat-title {
                font-size: clamp(1.6rem, 3vw, 2.8rem);
                font-weight: 700;
                letter-spacing: -0.04em;
                line-height: 1;
                color: #1c1917;
                margin: 0.25rem 0 0;
            }

            .theme-clothing-brand .cb-cat-view-all {
                display: inline-flex;
                align-items: center;
                gap: 0.45rem;
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #1c1917;
                text-decoration: none;
                border-bottom: 1.5px solid #1c1917;
                padding-bottom: 2px;
                white-space: nowrap;
                flex-shrink: 0;
                transition: opacity 0.2s;
            }
            .theme-clothing-brand .cb-cat-view-all:hover { opacity: 0.5; }

            /* ── Outer mosaic: hero | 2×2 secondary ── */
            .theme-clothing-brand .cb-cat-grid {
                display: grid;
                grid-template-columns: 2fr 3fr;  /* hero narrower, secondary wider */
                gap: 6px;
                align-items: stretch;             /* KEY: hero stretches to match secondary */
            }

            /* ── Secondary 2×2 box ── */
            .theme-clothing-brand .cb-cat-secondary {
                display: grid;
                grid-template-columns: 1fr 1fr;
                grid-template-rows: 1fr 1fr;    /* strict 2 rows */
                gap: 6px;
            }

            /* ── Card base ── */
            .theme-clothing-brand .cb-cat-card {
                position: relative;
                display: block;
                overflow: hidden;
                text-decoration: none;
                background: #111;
                color: #fff;
            }

            /* Image fills card — absolutely positioned so it always covers */
            .theme-clothing-brand .cb-cat-card > img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
                transition: transform 700ms cubic-bezier(0.25, 0.46, 0.45, 0.94),
                            opacity 500ms ease;
            }

            .theme-clothing-brand .cb-cat-card:hover > img {
                transform: scale(1.07);
                opacity: 0.88;
            }

            /* Gradient overlay */
            .theme-clothing-brand .cb-cat-overlay {
                position: absolute;
                bottom: 0; left: 0; right: 0;
                background: linear-gradient(
                    0deg,
                    rgba(0,0,0,0.75) 0%,
                    rgba(0,0,0,0.3) 55%,
                    transparent 100%
                );
                padding: 2.5rem 1.5rem 1.4rem;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                pointer-events: none;
            }

            .theme-clothing-brand .cb-cat-name {
                font-size: clamp(0.85rem, 1.4vw, 1.25rem);
                font-weight: 700;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                line-height: 1;
            }

            .theme-clothing-brand .cb-cat-cta {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                font-size: 0.65rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                opacity: 0;
                transform: translateY(8px);
                transition: opacity 0.3s ease, transform 0.3s ease;
            }

            .theme-clothing-brand .cb-cat-card:hover .cb-cat-cta {
                opacity: 1;
                transform: translateY(0);
            }

            /* ── HERO: no aspect-ratio — let it stretch via CSS grid ── */
            .theme-clothing-brand .cb-cat-hero {
                /* height is 100% of grid row which = secondary panel height */
                min-height: 420px;
            }

            .theme-clothing-brand .cb-cat-hero .cb-cat-overlay {
                padding: 5rem 2rem 2rem;
            }

            .theme-clothing-brand .cb-cat-hero .cb-cat-name {
                font-size: clamp(1.1rem, 2vw, 1.9rem);
            }

            /* ── Small cards: aspect-ratio defines grid height ── */
            .theme-clothing-brand .cb-cat-small {
                aspect-ratio: 4 / 3;  /* drives secondary panel total height */
            }

            .theme-clothing-brand .cb-cat-small .cb-cat-name {
                font-size: clamp(0.75rem, 1vw, 1rem);
            }

            .theme-clothing-brand .cb-cat-small .cb-cat-overlay {
                padding: 2rem 1.1rem 1rem;
            }

            /* ── "View All" tile (4th slot) ── */
            .theme-clothing-brand .cb-cat-more-tile {
                aspect-ratio: 4 / 3;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                background: #1c1917;
                color: #fff;
                text-decoration: none;
                transition: background 0.3s ease;
            }
            .theme-clothing-brand .cb-cat-more-tile:hover { background: #2d2926; }

            .theme-clothing-brand .cb-cat-more-count {
                font-size: clamp(1.5rem, 2.5vw, 2.4rem);
                font-weight: 800;
                letter-spacing: -0.04em;
                line-height: 1;
            }

            .theme-clothing-brand .cb-cat-more-label {
                font-size: 0.65rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                text-align: center;
                opacity: 0.75;
            }

            .theme-clothing-brand .cb-cat-more-arrow {
                opacity: 0;
                transform: translateX(-4px);
                transition: opacity 0.3s, transform 0.3s;
            }

            .theme-clothing-brand .cb-cat-more-tile:hover .cb-cat-more-arrow {
                opacity: 1;
                transform: translateX(0);
            }

            .theme-clothing-brand .clothing-brand-new-arrivals {
                background: #f7f5f2;
            }

            .theme-clothing-brand .clothing-brand-new-arrivals .section-header,
            .theme-clothing-brand .clothing-brand-featured .section-header {
                margin-bottom: clamp(2rem, 4vw, 3.5rem) !important;
            }

            .theme-clothing-brand .clothing-brand-new-arrivals .section-title,
            .theme-clothing-brand .clothing-brand-featured .section-title {
                font-size: clamp(2.15rem, 4vw, 4rem);
                font-weight: 600;
            }

            .theme-clothing-brand .clothing-brand-new-arrivals .products-grid,
            .theme-clothing-brand .clothing-brand-featured .products-grid,
            .theme-clothing-brand .products-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: clamp(1.25rem, 2vw, 2rem);
            }

            .theme-clothing-brand .product-card {
                background: transparent;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                min-width: 0;
                width: 100%;
            }

            .theme-clothing-brand .product-image {
                aspect-ratio: 3 / 4;
                background: #f7f5f2;
                border-radius: 0;
                overflow: hidden;
                position: relative;
            }

            .theme-clothing-brand .product-card .product-image img {
                object-fit: cover;
                width: 100%;
                height: 100%;
                padding: 0;
                transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .theme-clothing-brand .product-card:hover .product-image img {
                transform: scale(1.04);
            }

            .theme-clothing-brand .clothing-brand-deals {
                background: #1c1917;
                color: #fffefd;
            }

            .theme-clothing-brand .clothing-brand-deals .section-title,
            .theme-clothing-brand .clothing-brand-deals .section-subtitle { color: #fffefd; }
            .theme-clothing-brand .clothing-brand-deals .btn-outline { border-color: #fffefd; color: #fffefd; }
            .theme-clothing-brand .clothing-brand-deals .btn-outline:hover { background: #fffefd; color: #1c1917; }
            .theme-clothing-brand .clothing-brand-deals .deals-grid { gap: 1px; }
            .theme-clothing-brand .clothing-brand-deals .deal-card {
                border-radius: 0;
                height: clamp(280px, 28vw, 420px);
                min-height: 0;
            }
            .theme-clothing-brand .clothing-brand-deals .deal-card::before {
                background: linear-gradient(180deg, rgba(0, 0, 0, 0.18) 0%, rgba(0, 0, 0, 0.78) 100%);
                content: "";
                inset: 0;
                pointer-events: none;
                position: absolute;
                z-index: 1;
            }
            .theme-clothing-brand .clothing-brand-deals .deal-badge,
            .theme-clothing-brand .clothing-brand-deals .deal-timer,
            .theme-clothing-brand .clothing-brand-deals .deal-content { z-index: 2; }
            .theme-clothing-brand .clothing-brand-deals .deal-content { padding: clamp(1rem, 2vw, 1.5rem); }
            .theme-clothing-brand .clothing-brand-deals .deal-subtitle {
                color: #fffefd;
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                margin-bottom: 0.35rem;
                opacity: 0.92;
                text-transform: uppercase;
            }
            .theme-clothing-brand .clothing-brand-deals .deal-title {
                color: #fffefd;
                font-size: clamp(1.25rem, 2vw, 1.8rem);
                font-weight: 600;
                letter-spacing: -0.04em;
                line-height: 1.08;
            }
            .theme-clothing-brand .clothing-brand-deals .deal-timer {
                background: rgba(28, 25, 23, 0.75);
                border: 1px solid rgba(255, 254, 253, 0.35);
                border-radius: 0;
                font-size: 0.72rem;
            }

            /* Product detail experience */
            .theme-clothing-brand .product-detail-page .product-detail-grid {
                align-items: start;
                gap: clamp(2rem, 5vw, 5rem);
            }

            .theme-clothing-brand .product-detail-page .product-gallery-main {
                background: #f7f5f2;
                border-radius: 0;
            }

            .theme-clothing-brand .product-detail-page .product-gallery-thumbs { gap: 0.65rem; }
            .theme-clothing-brand .product-detail-page .product-thumb-btn {
                border-radius: 0;
                border-color: var(--color-border);
            }
            .theme-clothing-brand .product-detail-page .product-thumb-btn.active { border-color: #1c1917; }
            .theme-clothing-brand .product-detail-page h1 { font-size: clamp(1.85rem, 3vw, 2.75rem) !important; font-weight: 600 !important; letter-spacing: -0.045em !important; }
            .theme-clothing-brand .product-detail-page .color-swatch-btn { border-radius: 0 !important; }
            .theme-clothing-brand .product-detail-page .size-btn,
            .theme-clothing-brand .product-detail-page .variant-btn,
            .theme-clothing-brand .product-detail-page .quantity-input {
                border-radius: 0 !important;
            }
            .theme-clothing-brand .product-detail-page .size-btn:hover,
            .theme-clothing-brand .product-detail-page .size-btn.active,
            .theme-clothing-brand .product-detail-page .variant-btn.active {
                background: #1c1917 !important;
                border-color: #1c1917 !important;
                color: #fffefd !important;
            }
            .theme-clothing-brand .product-detail-page .related-products-sidebar .product-card { border-top: 1px solid var(--color-border-light); }

            /* Authentication pages */
            .theme-clothing-brand .auth-breadcrumb { background: #f7f5f2; margin-bottom: 0 !important; }
            .theme-clothing-brand .auth-page {
                background: linear-gradient(115deg, #f7f5f2 0%, #fffefd 54%, #f1ede7 100%);
                min-height: 620px;
                padding: clamp(3rem, 8vw, 6rem) 0 !important;
            }
            .theme-clothing-brand .auth-card {
                background: #fffefd;
                border: 1px solid var(--color-border-light);
                box-shadow: 0 20px 50px rgba(28, 25, 23, 0.08);
                padding: clamp(1.75rem, 5vw, 3.25rem) !important;
            }
            .theme-clothing-brand .auth-card h1 {
                font-size: clamp(2rem, 5vw, 2.5rem) !important;
                font-weight: 600 !important;
                letter-spacing: -0.05em !important;
            }
            .theme-clothing-brand .auth-card .form-group { margin-bottom: 1.25rem; }
            .theme-clothing-brand .auth-card .form-label {
                font-size: 0.7rem !important;
                font-weight: 800 !important;
                letter-spacing: 0.09em;
                text-transform: uppercase;
            }
            .theme-clothing-brand .auth-card .form-input {
                background: #fffefd;
                border: 1px solid var(--color-border) !important;
                border-radius: 0 !important;
                min-height: 48px;
                padding: 0.75rem 0.9rem;
            }
            .theme-clothing-brand .auth-card .form-input:focus {
                border-color: #1c1917 !important;
                box-shadow: 0 0 0 3px rgba(28, 25, 23, 0.08);
            }
            .theme-clothing-brand .auth-card .btn { border-radius: 0 !important; min-height: 50px; }
            .theme-clothing-brand .auth-card a[style*="Forgotten"] { color: #1c1917 !important; text-decoration: underline !important; }

            /* Cart and checkout */
            .theme-clothing-brand .cart-page .cart-item,
            .theme-clothing-brand .checkout-page .sticky-summary {
                border-radius: 0 !important;
                border-color: var(--color-border-light) !important;
                box-shadow: none !important;
            }
            .theme-clothing-brand .cart-page .cart-header-desktop { background: #f7f5f2 !important; }
            .theme-clothing-brand .cart-page select,
            .theme-clothing-brand .checkout-page .form-input { border-radius: 0 !important; }
            .theme-clothing-brand .checkout-page .form-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .theme-clothing-brand .checkout-page .sticky-summary { background: #f7f5f2 !important; }
            
            /* Category Showcase - 4 Column Grid */
            .category-showcase-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 1.5rem;
                margin: 3rem 0;
            }
            
            .category-showcase-card {
                position: relative;
                height: 280px;
                border-radius: var(--radius-xl);
                overflow: hidden;
                cursor: pointer;
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            
            .category-showcase-card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-md);
            }
            
            .category-showcase-card img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .category-showcase-label {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.8) 100%);
                color: white;
                padding: 1.5rem;
                text-align: center;
                font-size: 1.25rem;
                font-weight: 700;
                letter-spacing: 0.05em;
            }
            
            /* Promotional Banner Section */
            .promo-banner-section {
                margin: 3rem 0;
                border-radius: var(--radius-xl);
                overflow: hidden;
                background: var(--color-bg-secondary);
            }
            
            .promo-banner-content {
                display: grid;
                grid-template-columns: 1fr 1fr;
                align-items: center;
                min-height: 350px;
            }
            
            .promo-banner-image {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .promo-banner-text {
                padding: 2rem 2.5rem;
                text-align: center;
            }
            
            .promo-banner-text h2 {
                font-size: 2.5rem;
                font-weight: 800;
                margin-bottom: 1rem;
                letter-spacing: -0.02em;
            }
            
            .promo-banner-text p {
                font-size: 1.1rem;
                color: var(--color-text-light);
                margin-bottom: 1.5rem;
            }
            
            .promo-cta-btn {
                display: inline-block;
                padding: 0.875rem 2rem;
                background: var(--color-primary);
                color: white;
                border-radius: var(--radius-lg);
                font-weight: 600;
                transition: all 0.3s ease;
                text-decoration: none;
            }
            
            .promo-cta-btn:hover {
                background: var(--color-primary-hover);
                transform: scale(1.05);
            }
            
            /* Product Grid - Desktop Layout */
            .products-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 1.5rem;
            }
            
            /* Featured Section Styling */
            .section-heading {
                font-size: 1.75rem;
                font-weight: 800;
                margin-bottom: 2rem;
                text-align: center;
                letter-spacing: -0.02em;
            }
            
            /* Responsive Adjustments: Tablet & Mobile (2 Products in a Row) */
            @media (max-width: 991px) {
                .products-grid,
                .theme-clothing-brand .products-grid {
                    grid-template-columns: repeat(3, 1fr);
                    gap: 1.25rem;
                }
            }

            @media (max-width: 768px) {
                .clothing-brand-hero .hero-bento-grid {
                    height: auto;
                }

                .clothing-brand-hero .hero-main-banner {
                    height: clamp(340px, 60vw, 480px);
                }

                .promo-banner-content {
                    grid-template-columns: 1fr;
                    min-height: 360px;
                }
                
                .promo-banner-text h2 {
                    font-size: 1.5rem;
                }
                
                /* Category Grid – Mobile: stack hero top, 2x2 below */
                .theme-clothing-brand .cb-cat-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                    margin-bottom: 1.25rem;
                }

                .theme-clothing-brand .cb-cat-grid {
                    grid-template-columns: 1fr;   /* stack hero above secondary */
                    gap: 4px;
                }

                .theme-clothing-brand .cb-cat-hero {
                    aspect-ratio: 16 / 9;         /* wide banner on mobile */
                    min-height: unset;
                }

                .theme-clothing-brand .cb-cat-secondary {
                    grid-template-columns: 1fr 1fr;
                    grid-template-rows: auto;
                    gap: 4px;
                }

                .theme-clothing-brand .cb-cat-small,
                .theme-clothing-brand .cb-cat-more-tile {
                    aspect-ratio: 4 / 3;
                }

                .theme-clothing-brand .cb-cat-name { font-size: 0.72rem; }

                .theme-clothing-brand .cb-cat-overlay,
                .theme-clothing-brand .cb-cat-hero .cb-cat-overlay {
                    padding: 1.5rem 0.875rem 0.875rem;
                }

                .theme-clothing-brand .cb-cat-cta { display: none; }

                /* Strict 2-column Product Grid on Mobile */
                .products-grid,
                .theme-clothing-brand .products-grid,
                .theme-clothing-brand .clothing-brand-new-arrivals .products-grid,
                .theme-clothing-brand .clothing-brand-featured .products-grid,
                .theme-clothing-brand .catalog-page .products-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                    gap: 0.75rem !important;
                }

                .clothing-brand-topbar-content { justify-content: center; min-height: 32px; }
                .clothing-brand-topbar-links { display: none; }
                .theme-clothing-brand .header-main { padding: 0.75rem 0; }
                .theme-clothing-brand .catalog-page .sidebar-desktop > div { padding: 1.25rem !important; }
                .theme-clothing-brand .product-detail-page .product-detail-grid { gap: 1.75rem; }
                .theme-clothing-brand .product-detail-page .add-to-cart-form > div:last-child { display: grid !important; grid-template-columns: 1fr 1fr; gap: 0.65rem !important; }
                .theme-clothing-brand .product-detail-page .add-to-cart-form .btn { min-height: 50px; padding-left: 0.65rem; padding-right: 0.65rem; }
                .theme-clothing-brand .auth-page { min-height: auto; }
                .theme-clothing-brand .auth-card { border-left: 0; border-right: 0; }

                /* Clothing Brand Mobile Product Card Polish */
                .theme-clothing-brand .product-card {
                    min-width: 0;
                    width: 100%;
                }
                .theme-clothing-brand .product-image {
                    aspect-ratio: 3 / 4 !important;
                    background: #f7f5f2;
                }
                .theme-clothing-brand .product-content {
                    padding: 0.5rem 0 0 !important;
                }
                .theme-clothing-brand .product-name {
                    font-size: 0.8rem !important;
                    letter-spacing: 0.02em !important;
                    text-transform: uppercase;
                    line-height: 1.3 !important;
                    margin-bottom: 0.2rem !important;
                    min-height: auto !important;
                }
                .theme-clothing-brand .product-price {
                    font-size: 0.85rem !important;
                    margin-top: 0.15rem !important;
                }
                .theme-clothing-brand .price-original {
                    font-size: 0.72rem !important;
                }
                .theme-clothing-brand .product-wishlist {
                    opacity: 1 !important;
                    transform: none !important;
                    width: 28px !important;
                    height: 28px !important;
                    top: 6px !important;
                    right: 6px !important;
                    background: rgba(255, 255, 255, 0.88) !important;
                    backdrop-filter: blur(4px) !important;
                }
                .theme-clothing-brand .product-wishlist svg {
                    width: 14px !important;
                    height: 14px !important;
                }
                .theme-clothing-brand .product-badges {
                    top: 6px !important;
                    left: 6px !important;
                    gap: 3px !important;
                }
                .theme-clothing-brand .badge {
                    font-size: 0.58rem !important;
                    padding: 2px 5px !important;
                    letter-spacing: 0.08em !important;
                }
            }
            
            @media (max-width: 480px) {
                .theme-clothing-brand .cb-cat-title {
                    font-size: 1.25rem;
                }

                .theme-clothing-brand .cb-cat-hero {
                    aspect-ratio: 16 / 7;  /* very short banner on tiny screens */
                }

                .theme-clothing-brand .cb-cat-small,
                .theme-clothing-brand .cb-cat-more-tile {
                    aspect-ratio: 3 / 2;
                }

                .promo-banner-text {
                    padding: 1.25rem;
                }
                
                .promo-banner-text h2 {
                    font-size: 1.35rem;
                }
                
                /* Strict 2 Columns on small phones */
                .products-grid,
                .theme-clothing-brand .products-grid,
                .theme-clothing-brand .clothing-brand-new-arrivals .products-grid,
                .theme-clothing-brand .clothing-brand-featured .products-grid,
                .theme-clothing-brand .catalog-page .products-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                    gap: 0.6rem !important;
                }

                .theme-clothing-brand .product-detail-page .add-to-cart-form > div:last-child { grid-template-columns: 1fr; }
                .theme-clothing-brand .auth-card { padding: 1.5rem 0 !important; box-shadow: none; }
            }
        ';
    }
    
    return $css;
}
?>
