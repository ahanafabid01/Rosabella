<?php
/**
 * KARTLY - Wishlist API
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
$action = getInput('action', 'toggle');

try {
    switch ($action) {
        case 'toggle':
            handleToggleWishlist();
            break;
        case 'remove':
            handleRemoveWishlistItem();
            break;
        case 'get':
            handleGetWishlist();
            break;
        case 'count':
            handleGetWishlistCount();
            break;
        default:
            respond(false, 'Invalid action', [], 400);
    }
} catch (Throwable $e) {
    respond(false, 'Wishlist request failed', ['error' => $e->getMessage()], 500);
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
    echo json_encode(array_merge([
        'success' => (bool) $success,
        'message' => $message
    ], $payload));
    exit;
}

function handleToggleWishlist() {
    global $db, $sessionId, $userId;

    $productId = getIntInput('product_id');
    if ($productId <= 0) {
        respond(false, 'Invalid product', [], 422);
    }

    $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        respond(false, 'Product not found', [], 404);
    }

    if ($userId) {
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE product_id = ? AND (user_id = ? OR session_id = ?) LIMIT 1");
        $stmt->execute([$productId, $userId, $sessionId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE product_id = ? AND session_id = ? LIMIT 1");
        $stmt->execute([$productId, $sessionId]);
    }
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ?");
        $stmt->execute([$existing['id']]);
        respond(true, 'Removed from wishlist', ['active' => false, 'wishlist_count' => getWishlistCount()]);
    }

    $stmt = $db->prepare("INSERT INTO wishlist (session_id, user_id, product_id) VALUES (?, ?, ?)");
    $stmt->execute([$sessionId, $userId, $productId]);
    respond(true, 'Added to wishlist', ['active' => true, 'wishlist_count' => getWishlistCount()]);
}

function handleRemoveWishlistItem() {
    global $db, $sessionId, $userId;

    $wishlistId = getIntInput('wishlist_id');
    $productId = getIntInput('product_id');

    if ($wishlistId <= 0 && $productId <= 0) {
        respond(false, 'Invalid wishlist item', [], 422);
    }

    if ($wishlistId > 0) {
        if ($userId) {
            $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ? AND (user_id = ? OR session_id = ?)");
            $stmt->execute([$wishlistId, $userId, $sessionId]);
        } else {
            $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ? AND session_id = ?");
            $stmt->execute([$wishlistId, $sessionId]);
        }
    } else {
        if ($userId) {
            $stmt = $db->prepare("DELETE FROM wishlist WHERE product_id = ? AND (user_id = ? OR session_id = ?)");
            $stmt->execute([$productId, $userId, $sessionId]);
        } else {
            $stmt = $db->prepare("DELETE FROM wishlist WHERE product_id = ? AND session_id = ?");
            $stmt->execute([$productId, $sessionId]);
        }
    }

    respond(true, 'Wishlist item removed', ['wishlist_count' => getWishlistCount()]);
}

function handleGetWishlist() {
    global $db, $sessionId, $userId;

    if ($userId) {
        $stmt = $db->prepare("
            SELECT w.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity, p.status
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = ? OR w.session_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId, $sessionId]);
    } else {
        $stmt = $db->prepare("
            SELECT w.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity, p.status
            FROM wishlist w
            JOIN products p ON w.product_id = p.id
            WHERE w.session_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$sessionId]);
    }

    $items = $stmt->fetchAll();
    respond(true, 'Wishlist retrieved', ['items' => $items, 'count' => count($items), 'wishlist_count' => getWishlistCount()]);
}

function handleGetWishlistCount() {
    respond(true, 'Wishlist count retrieved', ['count' => getWishlistCount()]);
}
?>
