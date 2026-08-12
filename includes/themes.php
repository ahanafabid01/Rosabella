<?php
/**
 * KARTLY - Homepage Theme Manager
 * 
 * This file provides theme loading and rendering functions for the homepage.
 * Each theme can customize the layout and presentation of sections.
 */

/**
 * Get the currently selected homepage theme
 * @return string Theme key (default_theme, compact_layout, showcase_layout)
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
        'compact_layout' => [
            'name' => 'Compact Layout',
            'hero_grid_layout' => 'minimal',
            'hero_height_mobile' => 'clamp(280px, 45vw, 360px)',
            'hero_height_tablet' => 'clamp(360px, 50vh, 480px)',
            'hero_height_desktop' => 'clamp(460px, 58vh, 580px)',
            'categories_columns_mobile' => 3,
            'categories_columns_tablet' => 4,
            'categories_columns_desktop' => 8,
            'products_columns_mobile' => 2,
            'products_columns_tablet' => 4,
            'products_columns_desktop' => 5,
            'product_card_style' => 'minimal',
            'show_category_sidebar' => false,
            'featured_section_title' => 'Top Products',
            'new_arrivals_title' => 'New',
            'compact_mode' => true,
        ],
        'showcase_layout' => [
            'name' => 'Showcase Layout',
            'hero_grid_layout' => 'full-width',
            'hero_height_mobile' => 'clamp(400px, 70vw, 600px)',
            'hero_height_tablet' => 'clamp(580px, 75vh, 720px)',
            'hero_height_desktop' => 'clamp(720px, 80vh, 880px)',
            'categories_columns_mobile' => 2,
            'categories_columns_tablet' => 3,
            'categories_columns_desktop' => 4,
            'products_columns_mobile' => 1,
            'products_columns_tablet' => 2,
            'products_columns_desktop' => 3,
            'product_card_style' => 'detailed',
            'show_category_sidebar' => true,
            'featured_section_title' => 'Handpicked Selection',
            'new_arrivals_title' => 'Latest Arrivals',
            'compact_mode' => false,
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
    if ($theme === 'compact_layout') {
        $cssVars['--hero-height-mobile'] = $config['hero_height_mobile'];
        $cssVars['--hero-height-tablet'] = $config['hero_height_tablet'];
        $cssVars['--hero-height-desktop'] = $config['hero_height_desktop'];
    } else if ($theme === 'showcase_layout') {
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
    
    return $css;
}
?>
