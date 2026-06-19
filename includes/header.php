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
$stmt = $db->query("SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order, name");
$categories = $stmt->fetchAll();
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
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Additional page-specific styles -->
    <?php if (isset($additionalStyles)): ?>
        <style><?= $additionalStyles ?></style>
    <?php endif; ?>
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
                <a href="index.php" class="logo">
                    <div class="logo-icon">K</div>
                    <span>KARTLY</span>
                </a>

                <!-- Search Bar (center, desktop) -->
                <form action="products.php" method="GET" class="header-search-form">
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
                        <a href="account.php" class="header-login-link" aria-label="My Account">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>My Account</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="header-login-link" aria-label="Login or Register">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                            <span>Login / Register</span>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist -->
                    <a href="wishlist.php" class="header-icon-btn header-action-btn" aria-label="Wishlist">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                        </svg>
                        <span class="header-badge wishlist-badge <?= $wishlistCount > 0 ? '' : 'hidden' ?>"><?= $wishlistCount ?></span>
                    </a>

                    <!-- Cart -->
                    <a href="cart.php" class="header-cart-btn header-action-btn" aria-label="Shopping cart">
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
                <form action="products.php" method="GET" class="mobile-search-form">
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
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <a href="products.php?category=<?= urlencode($category['slug']) ?>" class="header-nav-link">
                                <?= htmlspecialchars($category['name']) ?>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </a>
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
            <a href="index.php" class="logo">
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
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="products.php?category=<?= urlencode($category['slug']) ?>" class="mobile-nav-link">
                        <?= htmlspecialchars($category['name']) ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Toast Container -->
    <div class="toast-container"></div>
