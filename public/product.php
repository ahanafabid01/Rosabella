<?php
/**
 * KARTLY - Single Product Page
 */
require_once __DIR__ . '/../config/database.php';

$db = getDB();
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: products.php');
    exit;
}

$stmt = $db->prepare("SELECT p.*, c.name as category_name, c.slug as category_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

function parseProductGalleryImages(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded)));
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw))));
}

$fallbackImage = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
$productImages = [];
if (!empty($product['main_image'])) {
    $productImages[] = $product['main_image'];
}
$productImages = array_merge($productImages, parseProductGalleryImages($product['gallery_images'] ?? null));
$productImages = array_values(array_unique(array_filter($productImages)));
if (empty($productImages)) {
    $productImages[] = $fallbackImage;
}
$primaryProductImage = $productImages[0];

$pageTitle = $product['name'];
$footerProductDescription = trim((string)($product['description'] ?? $product['short_description'] ?? ''));

// Get related products
$stmt = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND status = 'active' LIMIT 4");
$stmt->execute([$product['category_id'], $productId]);
$relatedProducts = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Breadcrumb -->
    <section class="section section-bg" style="padding: 1rem 0 1.1rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="/Kartly/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <a href="/Kartly/shop" style="color: var(--color-text-light);">Products</a>
                <span> / </span>
                <a href="products.php?category=<?= urlencode($product['category_slug']) ?>" style="color: var(--color-text-light);"><?= htmlspecialchars($product['category_name']) ?></a>
                <span> / </span>
                <span style="color: var(--color-text);"><?= htmlspecialchars($product['name']) ?></span>
            </nav>
        </div>
    </section>

    <!-- Product Detail -->
    <section class="section" style="padding-top: 1.2rem;">
        <div class="container">
            <div class="product-detail-grid">
                
                <!-- Product Images -->
                <div>
                    <div class="product-gallery-main">
                        <img id="product-main-image" src="<?= htmlspecialchars($primaryProductImage) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    </div>
                    <?php if (count($productImages) > 1): ?>
                        <div class="product-gallery-thumbs">
                            <?php foreach ($productImages as $index => $imagePath): ?>
                                <button type="button" class="product-thumb-btn <?= $index === 0 ? 'active' : '' ?>" data-image-src="<?= htmlspecialchars($imagePath) ?>" data-image-alt="<?= htmlspecialchars($product['name']) ?>">
                                    <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Info -->
                <div>
                    <span style="font-size: 0.875rem; color: var(--color-text-light); text-transform: uppercase;"><?= htmlspecialchars($product['category_name']) ?></span>
                    
                    <h1 style="font-size: 2rem; font-weight: 700; margin: 0.5rem 0 1rem;"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <!-- Rating -->
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                        <div style="display: flex; gap: 2px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg class="star" viewBox="0 0 24 24" fill="#ffc107" style="width: 18px; height: 18px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor; ?>
                        </div>
                        <span style="color: var(--color-text-light);">(125 reviews)</span>
                    </div>
                    
                    <!-- Price -->
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <span style="font-size: 2rem; font-weight: 700;"><?= formatPrice($product['sale_price'] ?: $product['price']) ?></span>
                        <?php if ($product['sale_price']): ?>
                        <span style="font-size: 1.25rem; color: var(--color-text-light); text-decoration: line-through;"><?= formatPrice($product['price']) ?></span>
                        <?php $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>
                        <span class="badge badge-danger">Save <?= $discount ?>%</span>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <p style="color: var(--color-text-light); margin-bottom: 1.5rem;"><?= htmlspecialchars($product['description'] ?? $product['short_description'] ?? '') ?></p>
                    
                    <!-- Stock Status -->
                    <div style="margin-bottom: 1.5rem;">
                        <?php if ($product['stock_quantity'] > 0): ?>
                        <span style="color: var(--color-success); font-weight: 500;">✓ In Stock (<?= $product['stock_quantity'] ?> available)</span>
                        <?php else: ?>
                        <span style="color: var(--color-danger); font-weight: 500;">✗ Out of Stock</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <form action="api/cart.php" method="POST" class="add-to-cart-form" data-product-id="<?= $product['id'] ?>" style="margin-bottom: 1.5rem;">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <label style="font-weight: 500;">Quantity:</label>
                            <div class="quantity-input" style="display: flex; align-items: center; border: 1px solid var(--color-border); border-radius: var(--radius-md);">
                                <button type="button" class="quantity-minus" style="width: 40px; height: 40px; border: none; background: none; cursor: pointer; font-size: 1.25rem;">-</button>
                                <input type="number" name="quantity" value="1" min="1" max="<?= $product['stock_quantity'] ?>" style="width: 60px; text-align: center; border: none; font-size: 1rem;">
                                <button type="button" class="quantity-plus" style="width: 40px; height: 40px; border: none; background: none; cursor: pointer; font-size: 1.25rem;">+</button>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <button type="submit" class="btn btn-primary btn-lg" style="flex: 1;" <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                                Add to Cart
                            </button>
                            <button type="button" class="btn btn-outline btn-lg buy-now-btn" <?= $product['stock_quantity'] <= 0 ? 'disabled' : '' ?>>Buy Now</button>
                        </div>
                    </form>
                    
                    <!-- Features -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <span style="font-size: 0.875rem;">Free Shipping</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span style="font-size: 0.875rem;">30-Day Returns</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span style="font-size: 0.875rem;">Secure Payment</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <span style="font-size: 0.875rem;">Fast Delivery</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Products -->
            <?php if (count($relatedProducts) > 0): ?>
            <div style="margin-top: 4rem;">
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Related Products</h2>
                <div class="products-grid">
                    <?php foreach ($relatedProducts as $rp): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($rp['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80') ?>" alt="<?= htmlspecialchars($rp['name']) ?>">
                            <div class="product-actions">
                                <a href="product.php?id=<?= $rp['id'] ?>" class="btn btn-primary" style="flex: 1;">View Details</a>
                            </div>
                        </div>
                        <div class="product-content">
                            <span class="product-category"><?= htmlspecialchars($product['category_name']) ?></span>
                            <h3 class="product-name"><a href="product.php?id=<?= $rp['id'] ?>"><?= htmlspecialchars($rp['name']) ?></a></h3>
                            <div class="product-price">
                                <span class="price-current"><?= formatPrice($rp['sale_price'] ?: $rp['price']) ?></span>
                                <?php if ($rp['sale_price']): ?>
                                <span class="price-original"><?= formatPrice($rp['price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


