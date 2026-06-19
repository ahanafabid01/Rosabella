<?php
/**
 * KARTLY - Cart API
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$db = getDB();
$sessionId = session_id();
$userId = $_SESSION['user_id'] ?? null;
$requestData = getRequestData();
$action = getInput('action', '');

try {
    switch ($action) {
        case 'add':
            handleAddToCart();
            break;
        case 'apply_coupon':
            handleApplyCoupon();
            break;
        case 'remove_coupon':
            handleRemoveCoupon();
            break;
        case 'update':
            handleUpdateCart();
            break;
        case 'remove':
            handleRemoveFromCart();
            break;
        case 'get':
            handleGetCart();
            break;
        case 'count':
            handleGetCartCount();
            break;
        default:
            respond(false, 'Invalid action', [], 400);
    }
} catch (Throwable $e) {
    respond(false, 'Cart request failed', ['error' => $e->getMessage()], 500);
}

function getRequestData() {
    $data = $_POST;

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
    }

    if (!empty($_GET)) {
        $data = array_merge($_GET, $data);
    }

    return $data;
}

function getInput($key, $default = null) {
    global $requestData;
    return $requestData[$key] ?? $default;
}

function getIntInput($key, $default = 0) {
    return intval(getInput($key, $default));
}

function respond($success, $message = '', $payload = [], $statusCode = 200) {
    http_response_code($statusCode);
    $response = array_merge([
        'success' => (bool) $success,
        'message' => $message
    ], $payload);
    echo json_encode($response);
    exit;
}

function handleAddToCart() {
    global $db, $sessionId, $userId;

    $productId = getIntInput('product_id');
    $quantity = getIntInput('quantity', 1);
    $size = getInput('selected_size', null);
    if ($size === '') $size = null;
    $color = getInput('selected_color', null);
    if ($color === '') $color = null;
    $variant = getInput('selected_variant', null);
    if ($variant === '') $variant = null;

    if ($productId <= 0 || $quantity <= 0) {
        respond(false, 'Invalid product or quantity', [], 422);
    }

    $stmt = $db->prepare("SELECT id, stock_quantity FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        respond(false, 'Product not found', [], 404);
    }

    if ($product['stock_quantity'] < $quantity) {
        respond(false, 'Insufficient stock', [], 422);
    }

    if ($userId) {
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND IFNULL(size, '') = IFNULL(?, '') AND IFNULL(color, '') = IFNULL(?, '') AND IFNULL(variant, '') = IFNULL(?, '') AND (user_id = ? OR session_id = ?) LIMIT 1");
        $stmt->execute([$productId, $size, $color, $variant, $userId, $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE product_id = ? AND IFNULL(size, '') = IFNULL(?, '') AND IFNULL(color, '') = IFNULL(?, '') AND IFNULL(variant, '') = IFNULL(?, '') AND session_id = ? LIMIT 1");
        $stmt->execute([$productId, $size, $color, $variant, $sessionId]);
    }
    $existingItem = $stmt->fetch();

    if ($existingItem) {
        $newQuantity = min($existingItem['quantity'] + $quantity, $product['stock_quantity']);
        $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $existingItem['id']]);
    } else {
        $stmt = $db->prepare("INSERT INTO cart (session_id, user_id, product_id, size, color, variant, quantity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $productId, $size, $color, $variant, $quantity]);
    }

    respond(true, 'Product added to cart', ['cart_count' => getCartCount()]);
}

function handleUpdateCart() {
    global $db, $sessionId, $userId;

    $cartId = getIntInput('cart_id');
    $quantity = getIntInput('quantity', 0);
    $size = getInput('selected_size');
    $color = getInput('selected_color');
    $variant = getInput('selected_variant');

    if ($cartId <= 0) {
        respond(false, 'Invalid parameters', [], 422);
    }

    if ($userId) {
        $stmt = $db->prepare("SELECT c.id, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND (c.user_id = ? OR c.session_id = ?)");
        $stmt->execute([$cartId, $userId, $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT c.id, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.session_id = ?");
        $stmt->execute([$cartId, $sessionId]);
    }
    $item = $stmt->fetch();

    if (!$item) {
        respond(false, 'Cart item not found', [], 404);
    }

    $updates = [];
    $params = [];

    if ($quantity > 0) {
        if ($quantity > intval($item['stock_quantity'])) {
            respond(false, 'Insufficient stock', [], 422);
        }
        $updates[] = "quantity = ?";
        $params[] = $quantity;
    }

    if ($size !== null && $size !== '') {
        $updates[] = "size = ?";
        $params[] = $size;
    }
    if ($color !== null && $color !== '') {
        $updates[] = "color = ?";
        $params[] = $color;
    }
    if ($variant !== null && $variant !== '') {
        $updates[] = "variant = ?";
        $params[] = $variant;
    }

    if (!empty($updates)) {
        $sql = "UPDATE cart SET " . implode(', ', $updates) . " WHERE id = ?";
        $params[] = $cartId;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }

    respond(true, 'Cart updated', ['cart_count' => getCartCount()]);
}

function handleRemoveFromCart() {
    global $db, $sessionId, $userId;

    $cartId = getIntInput('cart_id');

    if ($cartId <= 0) {
        respond(false, 'Invalid cart item', [], 422);
    }

    if ($userId) {
        $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND (user_id = ? OR session_id = ?)");
        $stmt->execute([$cartId, $userId, $sessionId]);
    } else {
        $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
        $stmt->execute([$cartId, $sessionId]);
    }

    respond(true, 'Item removed from cart', ['cart_count' => getCartCount()]);
}

function handleGetCart() {
    global $db, $sessionId, $userId;

    if ($userId) {
        $stmt = $db->prepare("
            SELECT c.*, p.slug, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ? OR c.session_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$userId, $sessionId]);
    } else {
        $stmt = $db->prepare("
            SELECT c.*, p.slug, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.session_id = ?
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$sessionId]);
    }

    $items = $stmt->fetchAll();
    respond(true, 'Cart retrieved', ['items' => $items, 'count' => count($items), 'cart_count' => getCartCount()]);
}

function handleGetCartCount() {
    respond(true, 'Cart count retrieved', ['count' => getCartCount()]);
}

function handleApplyCoupon() {
    global $db;
    
    $code = getInput('code', '');
    if (empty($code)) {
        respond(false, 'Please enter a coupon code', [], 400);
    }
    
    // Check if coupon exists and is valid
    $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active'");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        respond(false, 'Invalid or inactive coupon code', [], 404);
    }
    
    // Check date validity
    $now = date('Y-m-d');
    if (($coupon['start_date'] && $coupon['start_date'] > $now) || ($coupon['end_date'] && $coupon['end_date'] < $now)) {
        respond(false, 'This coupon is expired or not yet active', [], 400);
    }
    
    // Check usage limits
    if ($coupon['max_uses'] && $coupon['used_count'] >= $coupon['max_uses']) {
        respond(false, 'This coupon has reached its maximum usage limit', [], 400);
    }
    
    // Get cart subtotal to check min order amount
    $cartItems = [];
    $sessionId = session_id();
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $stmtCart = $db->prepare("SELECT c.quantity, p.price, p.sale_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? OR c.session_id = ?");
        $stmtCart->execute([$userId, $sessionId]);
    } else {
        $stmtCart = $db->prepare("SELECT c.quantity, p.price, p.sale_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.session_id = ?");
        $stmtCart->execute([$sessionId]);
    }
    $cartItems = $stmtCart->fetchAll();
    
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $price = $item['sale_price'] ?: $item['price'];
        $subtotal += $price * $item['quantity'];
    }
    
    if ($subtotal < floatval($coupon['min_order_amount'])) {
        respond(false, 'Minimum order amount for this coupon is ' . formatPrice($coupon['min_order_amount']), [], 400);
    }
    
    // Store in session
    $_SESSION['coupon'] = [
        'id' => $coupon['id'],
        'code' => $coupon['code'],
        'type' => $coupon['type'],
        'value' => floatval($coupon['value'])
    ];
    
    respond(true, 'Coupon applied successfully', ['coupon' => $_SESSION['coupon']]);
}

function handleRemoveCoupon() {
    if (isset($_SESSION['coupon'])) {
        unset($_SESSION['coupon']);
    }
    respond(true, 'Coupon removed');
}
?>
