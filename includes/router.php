<?php

function dispatchCleanRoute(): void
{
    require_once __DIR__ . '/../config/database.php';

    $route = getCleanRoute();
    if ($route === '' || $route === 'index') {
        return;
    }

    $target = resolveCleanRoute($route);
    if ($target !== null && is_file($target)) {
        http_response_code(200);
        require $target;
        exit;
    }

    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

function getCleanRoute(): string
{
    $requestPath = parse_url(
        $_SERVER['REDIRECT_URL']
            ?? $_SERVER['REDIRECT_SCRIPT_URL']
            ?? $_SERVER['REQUEST_URI']
            ?? '/',
        PHP_URL_PATH
    );
    $requestPath = '/' . trim(rawurldecode((string)$requestPath), '/');

    if (BASE_URL !== '') {
        if ($requestPath === BASE_URL) {
            $requestPath = '/';
        } elseif (str_starts_with($requestPath, BASE_URL . '/')) {
            $requestPath = substr($requestPath, strlen(BASE_URL));
        }
    }

    $route = trim($requestPath, '/');
    return preg_replace('/\.php$/', '', $route) ?? $route;
}

function resolveCleanRoute(string $route): ?string
{
    if (str_starts_with($route, 'admin/')) {
        return null;
    }

    $publicPages = [
        'shop' => __DIR__ . '/../public/products.php',
        'products' => __DIR__ . '/../public/products.php',
        'public/products' => __DIR__ . '/../public/products.php',
        'search' => __DIR__ . '/../public/products.php',
        'cart' => __DIR__ . '/../public/cart.php',
        'public/cart' => __DIR__ . '/../public/cart.php',
        'checkout' => __DIR__ . '/../public/checkout.php',
        'public/checkout' => __DIR__ . '/../public/checkout.php',
        'wishlist' => __DIR__ . '/../public/wishlist.php',
        'public/wishlist' => __DIR__ . '/../public/wishlist.php',
        'account' => __DIR__ . '/../public/account.php',
        'public/account' => __DIR__ . '/../public/account.php',
        'login' => __DIR__ . '/../public/login.php',
        'public/login' => __DIR__ . '/../public/login.php',
        'register' => __DIR__ . '/../public/register.php',
        'public/register' => __DIR__ . '/../public/register.php',
        'logout' => __DIR__ . '/../public/logout.php',
        'public/logout' => __DIR__ . '/../public/logout.php',
        'contact' => __DIR__ . '/../public/contact.php',
        'public/contact' => __DIR__ . '/../public/contact.php',
        'my-orders' => __DIR__ . '/../public/my-orders.php',
        'public/my-orders' => __DIR__ . '/../public/my-orders.php',
        'track-order' => __DIR__ . '/../public/track-order.php',
        'public/track-order' => __DIR__ . '/../public/track-order.php',
        'gift-cards' => __DIR__ . '/../public/gift-cards.php',
        'public/gift-cards' => __DIR__ . '/../public/gift-cards.php',
        'payment_callback' => __DIR__ . '/../public/payment_callback.php',
        'public/payment_callback' => __DIR__ . '/../public/payment_callback.php',
        'payment_ipn' => __DIR__ . '/../public/payment_ipn.php',
        'public/payment_ipn' => __DIR__ . '/../public/payment_ipn.php',
        'payment_result' => __DIR__ . '/../public/payment_result.php',
        'public/payment_result' => __DIR__ . '/../public/payment_result.php',
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

    if (isset($publicPages[$route])) {
        return $publicPages[$route];
    }

    if (isset($infoPages[$route])) {
        return $infoPages[$route];
    }

    if (isset($filters[$route])) {
        $_GET['filter'] = $filters[$route];
        return __DIR__ . '/../public/products.php';
    }

    if (preg_match('#^category/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['category'] = $match[1];
        return __DIR__ . '/../public/products.php';
    }

    if (preg_match('#^product/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['slug'] = $match[1];
        return __DIR__ . '/../public/product.php';
    }

    if (preg_match('#^track/([a-zA-Z0-9_-]+)/?$#', $route, $match)) {
        $_GET['order'] = $match[1];
        return __DIR__ . '/../public/track-order.php';
    }

    return null;
}
