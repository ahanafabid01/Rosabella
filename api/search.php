<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$q = trim($q);

if (empty($q) || strlen($q) < 2) {
    echo json_encode(['success' => false, 'categories' => [], 'products' => []]);
    exit;
}

// Rate limit search: max 60 requests per minute per IP (anti-scraping)
if (!checkRateLimit('search', 60, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests.']);
    exit;
}

try {
    $db = getDB();
    
    // Search pattern
    $pattern = '%' . $q . '%';
    
    // 1. Search Categories
    $catStmt = $db->prepare("
        SELECT id, name, slug 
        FROM categories 
        WHERE status = 'active' 
        AND name LIKE :q 
        ORDER BY name ASC 
        LIMIT 3
    ");
    $catStmt->execute(['q' => $pattern]);
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Search Products
    $prodStmt = $db->prepare("
        SELECT id, name, slug, price, sale_price, main_image, brand 
        FROM products 
        WHERE status = 'active' 
        AND (
            name LIKE :q1 
            OR brand LIKE :q2 
            OR style LIKE :q3 
            OR sku LIKE :q4 
            OR short_description LIKE :q5
        )
        ORDER BY 
            CASE 
                WHEN name LIKE :exact THEN 1
                WHEN name LIKE :starts THEN 2
                ELSE 3 
            END ASC,
            name ASC
        LIMIT 5
    ");
    
    $prodStmt->execute([
        'q1' => $pattern,
        'q2' => $pattern,
        'q3' => $pattern,
        'q4' => $pattern,
        'q5' => $pattern,
        'exact' => $q,
        'starts' => $q . '%'
    ]);
    
    $products = $prodStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    error_log('Search error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Search failed. Please try again.']);
}
