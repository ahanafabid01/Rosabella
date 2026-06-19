<?php
/**
 * KARTLY - Header Include
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(getSetting('site_tagline') ?? 'Your Premium E-Commerce Destination') ?>">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>KARTLY</title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?= BASE_URL ?>/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    
    <!-- Additional page-specific styles -->
    <?php if (isset($additionalStyles)): ?>
        <style><?= $additionalStyles ?></style>
    <?php endif; ?>
    
    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
    </script>
</head>
<body>
    <!-- Header -->
    <header class="header">

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
                    <div class="logo-icon">K</div>
                    <span>KARTLY</span>
                </a>

                <!-- Search Bar (center, desktop) -->
                <form action="<?= BASE_URL ?>/search" method="GET" class="header-search-form">
                    <svg class="header-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="search" name="search" placeholder="Search for products..." class="header-search-input">
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
                        <a href="<?= BASE_URL ?>/account" class="header-login-link" aria-label="My Account">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>My Account</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login" class="header-login-link" aria-label="Login or Register">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Login / Register</span>
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
                    <input type="search" name="search" placeholder="Search for products...">
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
                <div class="logo-icon">K</div>
                <span>KARTLY</span>
            </a>
            <button class="btn btn-ghost btn-icon mobile-nav-close" aria-label="Close menu">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <ul class="mobile-nav-list">
            <li>
                <a href="<?= BASE_URL ?>/shop" class="mobile-nav-link">
                    All Products
                </a>
            </li>
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="<?= BASE_URL ?>/category/<?= urlencode($category['slug']) ?>" class="mobile-nav-link">
                        <?= htmlspecialchars($category['name']) ?>
                        <?php if ($category['child_count'] > 0): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
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
            <?php endif; ?>
        </div>
        <div class="mini-cart-footer">
            <div class="mini-cart-subtotal">
                <span>Sub Total:</span>
                <span class="subtotal-amount"><?= $currencySymbol ?> <?= number_format($miniCartSubtotal, 2) ?></span>
            </div>
            <a href="<?= BASE_URL ?>/cart" class="btn btn-primary mini-cart-btn" style="width: 100%;">View Cart</a>
        </div>
    </div>
