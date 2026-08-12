<?php
/**
 * KARTLY - Header Include
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/themes.php';

// Get cart and wishlist counts
$cartCount = getCartCount();
$wishlistCount = getWishlistCount();

// Get categories for navigation
$db = getDB();
$stmt = $db->query("
    SELECT *
    FROM categories 
    WHERE status = 'active'
    ORDER BY sort_order, name
");
$allCats = $stmt->fetchAll();
$categories = [];
$categoryChildren = [];
foreach ($allCats as $cat) {
    if (!$cat['parent_id']) {
        $categories[] = $cat;
    } else {
        $categoryChildren[$cat['parent_id']][] = $cat;
    }
}
$currencySymbol = getSetting('currency_symbol') ?: 'Tk';
$freeShippingThreshold = floatval(getSetting('free_shipping_threshold') ?: 5000);
$siteLogo = getSetting('site_logo') ?: '';
$siteIcon = getSetting('site_icon') ?: '';
$siteName = getSetting('site_name') ?: 'KARTLY';
$isClothingBrandTheme = getHomepageTheme() === 'clothing_brand';

// Typography settings from DB (font family only)
$typoTags = ['body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'small', 'label', 'button', 'input', 'nav', 'logo_text'];
$typoFonts = [];
$uniqueFonts = [];

foreach ($typoTags as $tag) {
    $font = getSetting('typo_font_' . $tag);
    if ($font) {
        $typoFonts[$tag] = $font;
        $uniqueFonts[$font] = true;
    }
}

// Color Palette settings from DB
$colorVars = [
    'primary', 'primary_hover', 'secondary', 'secondary_hover',
    'success', 'danger', 'warning', 'info',
    'text', 'text_light', 'text_muted',
    'bg', 'bg_secondary', 'bg_tertiary',
    'border', 'border_light',
    'topbar_bg', 'navbar_bg', 'footer_bg', 'logo_text'
];
$customColors = [];
foreach ($colorVars as $k) {
    $val = getSetting('color_' . $k);
    if ($val) $customColors[$k] = $val;
}

$logoStyle = "";
$logoStyle .= !empty($typoFonts['logo_text']) ? "font-family:'".htmlspecialchars($typoFonts['logo_text'])."', sans-serif; " : "font-weight: 800; font-size: 1.5rem; ";
$logoStyle .= !empty($customColors['logo_text']) ? "color:".htmlspecialchars($customColors['logo_text'])."; " : "color: var(--color-primary); ";
$logoStyle .= "line-height: 1; letter-spacing: -0.02em;";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Security: CSRF token for AJAX requests -->
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken()) ?>">
    <meta name="description" content="<?= htmlspecialchars(getSetting('site_tagline') ?? 'Your Premium E-Commerce Destination') ?>">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= htmlspecialchars($siteName) ?></title>
    
    <!-- Favicon -->
    <?php if ($siteIcon): ?>
        <link rel="icon" href="<?= BASE_URL ?>/<?= htmlspecialchars($siteIcon) ?>" type="image/x-icon">
        <link rel="shortcut icon" href="<?= BASE_URL ?>/<?= htmlspecialchars($siteIcon) ?>">
        <link rel="apple-touch-icon" href="<?= BASE_URL ?>/<?= htmlspecialchars($siteIcon) ?>">
    <?php else: ?>
        <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.ico" type="image/x-icon">
    <?php endif; ?>
    
    <!-- Google Fonts from Settings -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php foreach (array_keys($uniqueFonts) as $fontName): ?>
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode(trim($fontName)) ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php endforeach; ?>

    <?php 
    // PRELOAD CRITICAL HERO IMAGE (LCP) FOR HOMEPAGE
    $isHome = ($_SERVER['REQUEST_URI'] === '/' || $_SERVER['REQUEST_URI'] === '/index.php' || strpos($_SERVER['REQUEST_URI'], BASE_URL . '/') !== false);
    if ($isHome) {
        $stmtHero = $db->query("SELECT image_path FROM hero_slides WHERE position = 'main' AND status = 'active' ORDER BY sort_order ASC LIMIT 1");
        $lcpHero = $stmtHero->fetch(PDO::FETCH_ASSOC);
        if ($lcpHero && !empty($lcpHero['image_path'])) {
            $lcpUrl = BASE_URL . '/' . htmlspecialchars($lcpHero['image_path']);
            echo '<link rel="preload" as="image" href="'.$lcpUrl.'" fetchpriority="high">';
        }
    }
    ?>

    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
    
    <!-- Main Stylesheet (synchronous to prevent CLS) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

    <!-- Critical CSS — inlined to eliminate render-blocking -->
    <style>
        /* Critical above-fold CSS: Design tokens, reset, header, hero skeleton */
        :root{--color-primary:#0f766e;--color-primary-hover:#0b5b55;--color-primary-light:rgba(15,118,110,.14);--color-secondary:#f8f9fa;--color-secondary-hover:#e9ecef;--color-success:#198754;--color-danger:#c0392b;--color-warning:#f39c12;--color-text:#102133;--color-text-light:#475569;--color-text-muted:#64748b;--color-bg:#fff;--color-bg-secondary:#f6f8fb;--color-bg-tertiary:#edf1f5;--color-border:#d8dee6;--color-border-light:#e8edf3;--font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;--font-size-xs:.75rem;--font-size-sm:.875rem;--font-size-base:1rem;--font-size-lg:1.125rem;--font-size-xl:1.25rem;--font-size-2xl:1.5rem;--radius-sm:.25rem;--radius-md:.5rem;--radius-lg:.75rem;--radius-xl:1rem;--radius-full:9999px;--shadow-sm:0 1px 2px rgba(16,33,51,.06);--shadow-md:0 6px 18px rgba(16,33,51,.08);--transition-fast:150ms ease;--transition-base:250ms ease;--container-max:1440px;--container-padding:1.5rem}
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
        body{font-family:var(--font-family);font-size:var(--font-size-base);line-height:1.6;color:var(--color-text);background-color:var(--color-bg);min-height:100vh;display:flex;flex-direction:column}
        .container{width:100%;max-width:var(--container-max);margin-left:auto;margin-right:auto;padding-left:var(--container-padding);padding-right:var(--container-padding)}
        img{max-width:100%;height:auto;display:block}
        a{color:inherit;text-decoration:none}
        .header{position:sticky;top:0;z-index:100;background:var(--color-bg);box-shadow:var(--shadow-sm)}
        .header-main{padding:1rem 0;border-bottom:1px solid var(--color-border-light)}
        .header-content{display:flex;align-items:center;justify-content:space-between;gap:2rem}
        .logo{font-size:1.75rem;font-weight:800;color:var(--color-primary);letter-spacing:-.5px;display:flex;align-items:center;gap:.5rem}
        .header-search{flex:1;max-width:600px;position:relative}
        .header-actions{display:flex;align-items:center;gap:1rem}
        @media (max-width:991px){.header-search,.header-nav{display:none}}
        /* CLS fix: Reserve space for hero grid so images don't cause layout shift */
        .hero-bento-grid{display:grid;grid-template-columns:1fr;min-height:350px}
        @media(min-width:992px){.hero-bento-grid{grid-template-columns:1.5fr 1fr;min-height:420px}}
        .hero-main-banner,.hero-side-banners{width:100%}
        .hero-main-banner{background:#0f172a}
        .hero-main-banner{min-height:350px}
        @media(min-width:768px){.hero-main-banner{min-height:420px}}
        .hero-slider,.hero-slide,.hero-side-slider,.hero-side-banner{width:100%;height:100%}
        .hero-slide img{width:100%;height:100%;object-fit:cover;display:block}
        /* CLS fix: Category cards have fixed size */
        .category-card-img-wrap{aspect-ratio:1/1;overflow:hidden}
        .category-card-img-wrap img{width:100%;height:100%;object-fit:cover}
        /* CLS fix: Product card images */
        .product-image{aspect-ratio:1/1;overflow:hidden;background:#f8f9fa}
        .product-image img{width:100%;height:100%;object-fit:cover}
    </style>

    <!-- Dynamic Typography & Colors CSS from Admin Settings -->
    <style>
        <?php if (!empty($customColors)): ?>
        :root {
            <?php foreach ($customColors as $key => $hex): ?>
            --color-<?= str_replace('_', '-', preg_replace('/[^a-zA-Z0-9_-]/', '', $key)) ?>: <?= htmlspecialchars($hex) ?>;
            <?php endforeach; ?>
        }
        <?php endif; ?>

        <?php foreach ($typoFonts as $tag => $fontName): ?>
        <?= htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '', $tag), ENT_QUOTES) ?> { font-family: '<?= htmlspecialchars(trim($fontName)) ?>', sans-serif !important; }
        <?php endforeach; ?>
    </style>
    

    <?php if (isset($additionalStyles)): ?>
        <style><?= $additionalStyles ?></style>
    <?php endif; ?>

    <!-- Theme CSS (if themes system is loaded) -->
    <?php if (function_exists('getThemeCSS')): ?>
        <style><?= getThemeCSS() ?></style>
    <?php endif; ?>

    <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
</head>
<body class="<?= $isClothingBrandTheme ? 'theme-clothing-brand' : '' ?>">
    <?php if ($isClothingBrandTheme): ?>
    <div class="clothing-brand-topbar" role="status">
        <div class="container clothing-brand-topbar-content">
            <span>Complimentary shipping on orders over <?= htmlspecialchars($currencySymbol) ?><?= number_format($freeShippingThreshold, 0) ?></span>
            <div class="clothing-brand-topbar-links">
                <a href="<?= BASE_URL ?>/track-order">Track order</a>
                <a href="<?= BASE_URL ?>/size-guide">Size guide</a>
                <a href="<?= BASE_URL ?>/contact">Contact</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Header -->
    <header class="header <?= $isClothingBrandTheme ? 'clothing-brand-header' : '' ?>">

        <!-- ===== MAIN BAR: Logo | Search | Actions ===== -->
        <div class="header-main">
            <div class="header-main-content container">

                <!-- Mobile Menu Button -->
                <button class="mobile-menu-btn" aria-label="Open menu">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>

                <!-- Logo -->
                <a href="<?= BASE_URL ?>/" class="logo">
                    <?php if ($siteLogo): ?>
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" width="140" height="36" style="height:36px;width:auto;max-width:140px;object-fit:contain;display:block;">
                    <?php else: ?>
                        <span style="<?= $logoStyle ?>"><?= htmlspecialchars($siteName) ?></span>
                    <?php endif; ?>
                </a>

                <!-- Search Bar (center, desktop) -->
                <form action="<?= BASE_URL ?>/search" method="GET" class="header-search-form">
                    <svg class="header-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="search" name="search" placeholder="Search for products..." class="header-search-input search-input-live">
                    <button type="submit" class="header-search-btn" aria-label="Search">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                        </svg>
                    </button>
                </form>

                <!-- Right Actions -->
                <div class="header-actions">


                    <!-- Login / Register -->
                    <?php if (isLoggedIn()): ?>
                        <a href="<?= BASE_URL ?><?= isAdmin() ? '/admin' : '/account' ?>" class="header-login-link" aria-label="My Account">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span><?= isAdmin() ? 'Admin Panel' : 'My Account' ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login" class="header-login-link" aria-label="Login">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Login</span>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist -->
                    <a href="<?= BASE_URL ?>/wishlist" class="header-icon-btn header-action-btn" aria-label="Wishlist">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        <span class="header-badge wishlist-badge <?= $wishlistCount > 0 ? '' : 'hidden' ?>"><?= $wishlistCount ?></span>
                    </a>

                    <!-- Cart -->
                    <a href="<?= BASE_URL ?>/cart" class="header-cart-btn header-action-btn" aria-label="Shopping cart">
                        <div class="header-cart-icon-wrap">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            <span class="header-badge cart-badge <?= $cartCount > 0 ? '' : 'hidden' ?>"><?= $cartCount ?></span>
                        </div>
                        <span class="header-cart-label">Cart</span>
                    </a>

                </div>
            </div>

            <!-- Mobile Search (always visible on mobile) -->
            <div class="mobile-search">
                <form action="<?= BASE_URL ?>/search" method="GET" class="mobile-search-form">
                    <svg class="mobile-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="search" name="search" placeholder="Search for products..." class="search-input-live">
                    <button type="submit" aria-label="Search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <!-- ===== CATEGORY NAV BAR ===== -->
        <nav class="header-nav-bar">
            <div class="container">
                <ul class="header-nav-list">
                    <li>
                        <a href="<?= BASE_URL ?>/shop" class="header-nav-link">
                            All Products
                        </a>
                    </li>
                    <?php foreach ($categories as $category): ?>
                        <?php $hasChildren = !empty($categoryChildren[$category['id']]); ?>
                        <li class="<?= $hasChildren ? 'has-dropdown' : '' ?>">
                            <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="header-nav-link">
                                <?= htmlspecialchars($category['name']) ?>
                                <?php if ($hasChildren): ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                                <?php endif; ?>
                            </a>
                            <?php if ($hasChildren): ?>
                                <ul class="nav-dropdown">
                                    <?php foreach ($categoryChildren[$category['id']] as $child): ?>
                                        <li>
                                            <a href="<?= BASE_URL ?>/category/<?= urlencode($child['slug']) ?>">
                                                <?= htmlspecialchars($child['name']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>

    </header>

    <!-- Mobile Navigation Drawer -->
    <div class="mobile-nav-overlay"></div>
    <nav class="mobile-nav">
        <div class="mobile-nav-header">
            <a href="<?= BASE_URL ?>/" class="logo">
                <?php if ($siteLogo): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($siteLogo) ?>" alt="<?= htmlspecialchars($siteName) ?>" style="height:32px;width:auto;object-fit:contain;display:block;">
                <?php else: ?>
                    <span style="<?= $logoStyle ?>"><?= htmlspecialchars($siteName) ?></span>
                <?php endif; ?>
            </a>
            <button class="mobile-nav-close" aria-label="Close menu" style="margin-left:auto; width:36px; height:36px; min-width:36px; padding:0; border-radius:50%; background:var(--color-primary); display:flex; align-items:center; justify-content:center; transition:background 0.2s; border:none; cursor:pointer; flex-shrink:0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;">
            <ul class="mobile-nav-list" style="flex: none; overflow-y: visible;">
                <li class="mobile-nav-item">
                    <a href="<?= BASE_URL ?>/shop" class="mobile-nav-link main-cat">
                        All Products
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <?php $hasChildren = !empty($categoryChildren[$category['id']]); ?>
                    <li class="mobile-nav-item">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="mobile-nav-link main-cat" style="flex: 1;">
                                <?= htmlspecialchars($category['name']) ?>
                            </a>
                            <?php if ($hasChildren): ?>
                            <button class="mobile-toggle-btn" aria-label="Toggle Submenu" onclick="const container = this.parentElement.nextElementSibling; this.classList.toggle('open'); container.classList.toggle('open');">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasChildren): ?>
                        <div class="mobile-subnav-container">
                            <div class="mobile-subnav">
                                <div class="mobile-subnav-inner">
                                    <?php foreach ($categoryChildren[$category['id']] as $child): ?>
                                        <a href="<?= BASE_URL ?>/category/<?= urlencode($child['slug']) ?>" class="mobile-nav-link sub-cat">
                                            <?= htmlspecialchars($child['name']) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <!-- Mobile Nav Footer: Login / Account (inside ul for scrolling) -->
                <?php if (isLoggedIn()): ?>
                    <li class="mobile-nav-item" style="border-top: 1px solid var(--color-border-light); margin-top: 1rem; padding-top: 1rem;">
                        <a href="<?= BASE_URL ?><?= isAdmin() ? '/admin' : '/account' ?>" class="mobile-nav-account-btn" style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1.25rem;font-weight:600;color:var(--color-primary);background:var(--color-bg-secondary);margin:0 1.25rem 0.5rem;border-radius:var(--radius-md);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <?= isAdmin() ? 'Admin Panel' : 'My Account' ?>
                        </a>
                    </li>
                    <li class="mobile-nav-item">
                        <a href="<?= BASE_URL ?>/logout" class="mobile-nav-logout-btn" style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1.25rem;font-weight:600;color:var(--color-danger);margin:0 1.25rem 1rem;border-radius:var(--radius-md);background:var(--color-bg-tertiary);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="mobile-nav-item" style="border-top: 1px solid var(--color-border-light); margin-top: 1rem; padding-top: 1rem;">
                        <a href="<?= BASE_URL ?>/login" class="mobile-nav-login-btn" style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1.25rem;font-weight:600;color:white;background:var(--color-primary);margin:0 1.25rem 0.5rem;border-radius:var(--radius-md);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                            </svg>
                            Login
                        </a>
                    </li>
                    <li class="mobile-nav-item">
                        <a href="<?= BASE_URL ?>/register" class="mobile-nav-register-btn" style="display:flex;align-items:center;gap:0.5rem;padding:0.75rem 1.25rem;font-weight:600;color:var(--color-text);border:1px solid var(--color-border);margin:0 1.25rem 1rem;border-radius:var(--radius-md);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                            </svg>
                            Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <!-- Mini Cart Sidebar -->
    <?php
    $miniCartSessionId = session_id();
    if (isLoggedIn()) {
        $mcStmt = $db->prepare("
            SELECT c.*, p.name, p.slug, p.price, p.sale_price, p.main_image 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? OR c.session_id = ?
            ORDER BY c.created_at DESC
        ");
        $mcStmt->execute([$_SESSION['user_id'], $miniCartSessionId]);
    } else {
        $mcStmt = $db->prepare("
            SELECT c.*, p.name, p.slug, p.price, p.sale_price, p.main_image 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.session_id = ?
            ORDER BY c.created_at DESC
        ");
        $mcStmt->execute([$miniCartSessionId]);
    }
    $miniCartItems = $mcStmt->fetchAll();
    $miniCartSubtotal = 0;
    ?>
    <div class="mini-cart-overlay"></div>
    <div class="mini-cart-sidebar">
        <div class="mini-cart-header">
            <h3>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem; vertical-align: middle;">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Cart (<?= $cartCount ?>)
            </h3>
            <button class="mini-cart-close" aria-label="Close cart">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="mini-cart-body">
            <?php if (empty($miniCartItems)): ?>
                <div class="mini-cart-empty" style="text-align: center; padding: 3rem 1rem;">
                    <p style="color: var(--color-text-light);">Your cart is empty.</p>
                </div>
            <?php else: ?>
                <div class="mini-cart-items">
                    <?php foreach ($miniCartItems as $item): 
                        $price = $item['sale_price'] ?: $item['price'];
                        $miniCartSubtotal += $price * $item['quantity'];
                    ?>
                        <div class="mini-cart-item">
                            <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim($item['main_image'], '/')) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                            <div class="mini-cart-item-info">
                                <a href="<?= BASE_URL ?>/product/<?= $item['slug'] ?>" class="mini-cart-item-title"><?= htmlspecialchars($item['name']) ?></a>
                                <div class="mini-cart-item-price">
                                    <?= $currencySymbol ?> <?= number_format($price, 2) ?> <span>&times; <?= $item['quantity'] ?></span>
                                </div>
                            </div>
                            <button class="btn btn-ghost cart-remove" data-cart-id="<?= $item['id'] ?>" aria-label="Remove item" style="padding: 0.25rem; color: var(--color-danger);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mini-cart-footer" style="border-top: 1px solid var(--color-border); padding-top: 1.25rem; margin-top: 1.25rem;">
                    <div class="mini-cart-subtotal">
                        <span>Sub Total:</span>
                        <span class="subtotal-amount"><?= $currencySymbol ?> <?= number_format($miniCartSubtotal, 2) ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/cart" class="btn btn-primary mini-cart-btn" style="width: 100%;">View Cart</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<style>
#live-search-dropdown {
    display: none;
    position: fixed;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 12px 32px rgba(0,0,0,0.12);
    z-index: 99999;
    max-height: 420px;
    overflow-y: auto;
    min-width: 300px;
}
#live-search-dropdown a.lsd-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 18px;
    text-decoration: none;
    color: #111827;
    font-size: 0.95rem;
    border-bottom: 1px solid #f3f4f6;
    transition: background 0.12s;
}
#live-search-dropdown a.lsd-item:last-child { border-bottom: none; }
#live-search-dropdown a.lsd-item:hover { background: #f8f9fa; }
#live-search-dropdown .lsd-item .lsd-icon { color: #9ca3af; flex-shrink: 0; }
#live-search-dropdown .lsd-item .lsd-text { flex: 1; }
#live-search-dropdown .lsd-item .lsd-arrow { color: #d1d5db; flex-shrink: 0; }
#live-search-dropdown .lsd-empty {
    padding: 20px;
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}
</style>

<div id="live-search-dropdown"></div>

<script>
(function() {
    var BASE = '<?= BASE_URL ?>';
    var dropdown = document.getElementById('live-search-dropdown');
    var activeInput = null;
    var debounceTimer = null;

    function positionDropdown(input) {
        var rect = input.closest('form').getBoundingClientRect();
        dropdown.style.top    = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.left   = rect.left + 'px';
        dropdown.style.width  = rect.width + 'px';
    }

    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function boldMatch(text, query) {
        var re = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        return text.replace(re, '<span style="font-weight:400;color:#6b7280;">$1</span>');
    }

    function showDropdown(keywords, query) {
        if (keywords.length === 0) {
            dropdown.innerHTML = '<div class="lsd-empty">No suggestions found for "' + query + '"</div>';
        } else {
            var html = '';
            keywords.forEach(function(kw) {
                var bold = boldMatch(kw, query);
                var encoded = encodeURIComponent(kw);
                html += '<a href="' + BASE + '/search?search=' + encoded + '" class="lsd-item">' +
                    '<svg class="lsd-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>' +
                    '<span class="lsd-text" style="font-weight:700;">' + bold + '</span>' +
                    '<svg class="lsd-arrow" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17l9.2-9.2M17 17V7H7"/></svg>' +
                '</a>';
            });
            dropdown.innerHTML = html;
        }
        dropdown.style.display = 'block';
    }

    function hideDropdown() {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
    }

    function handleInput(e) {
        var query = e.target.value.trim();
        activeInput = e.target;
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            hideDropdown();
            return;
        }

        positionDropdown(e.target);

        debounceTimer = setTimeout(function() {
            fetch(BASE + '/api/search.php?q=' + encodeURIComponent(query))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) { hideDropdown(); return; }

                    var keywords = [];
                    (data.categories || []).forEach(function(c) { keywords.push(c.name); });
                    (data.products || []).forEach(function(p) {
                        keywords.push(p.name);
                        if (p.brand) keywords.push(p.brand + ' ' + p.name);
                    });

                    // Deduplicate
                    keywords = keywords.filter(function(v, i, a) { return a.indexOf(v) === i; });
                    keywords = keywords.slice(0, 8);

                    showDropdown(keywords, query);
                    positionDropdown(e.target);
                })
                .catch(function() { hideDropdown(); });
        }, 250);
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.search-input-live').forEach(function(input) {
            input.setAttribute('autocomplete', 'off');
            input.addEventListener('input', handleInput);
            input.addEventListener('focus', function(e) {
                if (e.target.value.trim().length >= 2 && dropdown.innerHTML !== '') {
                    positionDropdown(e.target);
                    dropdown.style.display = 'block';
                }
            });
        });

        // Reposition on scroll/resize
        window.addEventListener('scroll', function() {
            if (activeInput && dropdown.style.display === 'block') {
                positionDropdown(activeInput);
            }
        }, true);
        window.addEventListener('resize', function() {
            if (activeInput && dropdown.style.display === 'block') {
                positionDropdown(activeInput);
            }
        });

        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !e.target.closest('.search-input-live')) {
                hideDropdown();
                activeInput = null;
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideDropdown();
                activeInput = null;
            }
        });
    });
})();
</script>

<main id="main-content">
