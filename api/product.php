<?php
/**
 * Rosabella - Product API (Quick View)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$productId = intval($_GET['id'] ?? 0);
if ($productId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Rate limit: max 120 requests per minute per IP (anti-scraping)
if (!checkRateLimit('product_api', 120, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests.']);
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.id, p.name, p.slug, p.description, p.short_description, p.price, p.sale_price, p.stock_quantity,
               p.main_image, p.gallery_images, p.status, p.is_new, p.is_bestseller, p.is_featured, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $galleryImages = [];
    if (!empty($product['gallery_images'])) {
        $decoded = json_decode($product['gallery_images'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $galleryImages = array_values(array_filter(array_map('trim', $decoded)));
        } else {
            $galleryImages = array_values(array_filter(array_map('trim', explode(',', (string)$product['gallery_images']))));
        }
    }
    $product['gallery_images'] = $galleryImages;
    if (empty($product['main_image']) && !empty($galleryImages)) {
        $product['main_image'] = $galleryImages[0];
    }

    echo json_encode(['success' => true, 'product' => $product]);
} catch (Throwable $e) {
    error_log('Product API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load product. Please try again.']);
}
?>
