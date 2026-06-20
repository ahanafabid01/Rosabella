<?php
/**
 * KARTLY - 404 Page Not Found
 */

function dispatchCleanUrlFallback(): void
{
    $requestPath = parse_url(
        $_SERVER['REDIRECT_URL']
            ?? $_SERVER['REDIRECT_SCRIPT_URL']
            ?? $_SERVER['REQUEST_URI']
            ?? '/',
        PHP_URL_PATH
    );
    $requestPath = '/' . trim(rawurldecode((string)$requestPath), '/');

    $basePath = defined('BASE_URL') ? BASE_URL : '';
    if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
        $requestPath = substr($requestPath, strlen($basePath));
    }

    $route = trim($requestPath, '/');
    if ($route === 'errors/404.php') {
        return;
    }

    $publicPages = [
        'shop' => __DIR__ . '/../public/products.php',
        'products' => __DIR__ . '/../public/products.php',
        'search' => __DIR__ . '/../public/products.php',
        'cart' => __DIR__ . '/../public/cart.php',
        'checkout' => __DIR__ . '/../public/checkout.php',
        'wishlist' => __DIR__ . '/../public/wishlist.php',
        'account' => __DIR__ . '/../public/account.php',
        'login' => __DIR__ . '/../public/login.php',
        'register' => __DIR__ . '/../public/register.php',
        'logout' => __DIR__ . '/../public/logout.php',
        'contact' => __DIR__ . '/../public/contact.php',
        'my-orders' => __DIR__ . '/../public/my-orders.php',
        'track-order' => __DIR__ . '/../public/track-order.php',
        'gift-cards' => __DIR__ . '/../public/gift-cards.php',
        'payment_callback' => __DIR__ . '/../public/payment_callback.php',
        'payment_ipn' => __DIR__ . '/../public/payment_ipn.php',
        'payment_result' => __DIR__ . '/../public/payment_result.php',
    ];

    $infoPages = [
        'about' => __DIR__ . '/../pages/about.php',
        'help' => __DIR__ . '/../pages/help.php',
        'shipping' => __DIR__ . '/../pages/shipping.php',
        'returns' => __DIR__ . '/../pages/returns.php',
        'terms' => __DIR__ . '/../pages/terms.php',
        'privacy' => __DIR__ . '/../pages/privacy.php',
        'cookies' => __DIR__ . '/../pages/cookies.php',
        'careers' => __DIR__ . '/../pages/careers.php',
        'press' => __DIR__ . '/../pages/press.php',
        'affiliate' => __DIR__ . '/../pages/affiliate.php',
        'accessibility' => __DIR__ . '/../pages/accessibility.php',
        'sustainability' => __DIR__ . '/../pages/sustainability.php',
        'size-guide' => __DIR__ . '/../pages/size-guide.php',
    ];

    $filters = [
        'sale' => 'sale',
        'new-arrivals' => 'new',
        'best-sellers' => 'bestseller',
    ];

    $targetFile = $publicPages[$route] ?? $infoPages[$route] ?? null;

    if ($targetFile === null && isset($filters[$route])) {
        $_GET['filter'] = $filters[$route];
        $targetFile = __DIR__ . '/../public/products.php';
    }

    if ($targetFile === null && preg_match('#^category/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['category'] = $match[1];
        $targetFile = __DIR__ . '/../public/products.php';
    }

    if ($targetFile === null && preg_match('#^product/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['slug'] = $match[1];
        $targetFile = __DIR__ . '/../public/product.php';
    }

    if ($targetFile === null && preg_match('#^track/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['order'] = $match[1];
        $targetFile = __DIR__ . '/../public/track-order.php';
    }

    if ($targetFile !== null && is_file($targetFile)) {
        http_response_code(200);
        require $targetFile;
        exit;
    }
}

dispatchCleanUrlFallback();

$pageTitle = 'Page Not Found';
require_once __DIR__ . '/../includes/header.php';
?>

    <section class="section" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
        <div class="container" style="text-align: center;">
            <div style="font-size: 8rem; font-weight: 700; color: var(--color-primary); line-height: 1; margin-bottom: 1rem;">404</div>
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 1rem;">Page Not Found</h1>
            <p style="color: var(--color-text-light); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                Oops! The page you're looking for doesn't exist or has been moved.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="<?= BASE_URL ?>/" class="btn btn-primary btn-lg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Go Home
                </a>
                <a href="<?= BASE_URL ?>/shop" class="btn btn-outline btn-lg">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    Browse Products
                </a>
            </div>
            
            <!-- Suggestions -->
            <div style="margin-top: 4rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">You might be looking for:</h2>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="<?= BASE_URL ?>/new-arrivals" class="btn btn-secondary">New Arrivals</a>
                    <a href="<?= BASE_URL ?>/best-sellers" class="btn btn-secondary">Best Sellers</a>
                    <a href="<?= BASE_URL ?>/sale" class="btn btn-secondary">Sale Items</a>
                    <a href="<?= BASE_URL ?>/help" class="btn btn-secondary">Help Center</a>
                </div>
            </div>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



