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
            
            /* Hero Section with Dark Overlay */
            .hero-main-banner {
                position: relative;
                overflow: hidden;
            }
            
            .hero-main-banner::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(135deg, rgba(0,0,0,0.4) 0%, rgba(0,0,0,0.2) 100%);
                z-index: 2;
                pointer-events: none;
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
                background: linear-gradient(90deg, rgba(16, 14, 12, 0.6), rgba(16, 14, 12, 0.08) 70%);
                content: "";
                inset: 0;
                position: absolute;
            }

            .clothing-brand-hero-copy {
                bottom: clamp(2rem, 7vw, 6.5rem);
                color: #fffefd;
                left: max(1.5rem, calc((100vw - 1360px) / 2 + 1.5rem));
                max-width: min(580px, 80vw);
                position: absolute;
                z-index: 4;
            }

            .clothing-brand-hero-copy p {
                color: inherit;
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.16em;
                margin: 0 0 1rem;
                text-transform: uppercase;
            }

            .clothing-brand-hero-copy h1 {
                color: inherit !important;
                font-size: clamp(2.5rem, 5.5vw, 5.5rem) !important;
                font-weight: 600 !important;
                letter-spacing: -0.07em !important;
                line-height: 0.98;
                margin: 0 0 1.75rem;
            }

            .clothing-brand-hero-copy a {
                border-bottom: 1px solid currentColor;
                color: inherit;
                display: inline-flex;
                font-size: 0.72rem;
                font-weight: 800;
                gap: 0.75rem;
                letter-spacing: 0.12em;
                padding-bottom: 0.45rem;
                text-transform: uppercase;
            }

            .theme-clothing-brand .clothing-brand-categories {
                background: #fffefd;
            }

            .theme-clothing-brand .category-showcase-grid {
                gap: 1px;
                margin: 3rem 0 0;
            }

            .theme-clothing-brand .category-showcase-card {
                border-radius: 0;
                height: clamp(300px, 29vw, 460px);
            }

            .theme-clothing-brand .category-showcase-card:hover { transform: none; }
            .theme-clothing-brand .category-showcase-card img { transition: transform 500ms ease; }
            .theme-clothing-brand .category-showcase-card:hover img { transform: scale(1.045); }
            .theme-clothing-brand .category-showcase-label {
                font-size: clamp(1.1rem, 1.8vw, 1.6rem);
                font-weight: 600;
                letter-spacing: -0.03em;
                padding: 2rem;
                text-align: left;
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
            .theme-clothing-brand .clothing-brand-featured .products-grid {
                gap: clamp(1.25rem, 2.5vw, 2.5rem);
            }

            .theme-clothing-brand .clothing-brand-new-arrivals .product-image,
            .theme-clothing-brand .clothing-brand-featured .product-image {
                aspect-ratio: 1 / 1;
                background: #fffefd;
            }

            .theme-clothing-brand .product-card .product-image img {
                object-fit: contain;
                padding: 0.25rem;
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
            
            /* Product Grid - 4 Column on Desktop */
            .products-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
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
            
            /* Responsive Adjustments */
            @media (max-width: 768px) {
                .clothing-brand-hero .hero-bento-grid {
                    height: auto;
                }

                .clothing-brand-hero .hero-main-banner {
                    height: clamp(380px, 65vw, 520px);
                }

                .promo-banner-content {
                    grid-template-columns: 1fr;
                    min-height: 400px;
                }
                
                .promo-banner-text h2 {
                    font-size: 1.75rem;
                }
                
                .category-showcase-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
                }
                
                .category-showcase-card {
                    height: 200px;
                }
                
                .products-grid {
                    grid-template-columns: repeat(2, 1fr);
                    gap: 1rem;
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
            }
            
            @media (max-width: 480px) {
                .category-showcase-grid {
                    grid-template-columns: 1fr;
                }
                
                .promo-banner-text {
                    padding: 1.5rem;
                }
                
                .promo-banner-text h2 {
                    font-size: 1.5rem;
                }
                
                .products-grid {
                    grid-template-columns: 1fr;
                }

                .theme-clothing-brand .catalog-page .products-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .theme-clothing-brand .product-detail-page .add-to-cart-form > div:last-child { grid-template-columns: 1fr; }
                .theme-clothing-brand .auth-card { padding: 1.5rem 0 !important; box-shadow: none; }
            }
        ';
    }
    
    return $css;
}
?>
