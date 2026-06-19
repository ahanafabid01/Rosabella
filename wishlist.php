<?php
/**
 * KARTLY - Wishlist
 */
$pageTitle = 'My Wishlist';
require_once 'includes/header.php';

$db = getDB();
$sessionId = session_id();

// Get wishlist items
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT w.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity, p.status FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.user_id = ? OR w.session_id = ? ORDER BY w.created_at DESC");
    $stmt->execute([$_SESSION['user_id'], $sessionId]);
} else {
    $stmt = $db->prepare("SELECT w.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity, p.status FROM wishlist w JOIN products p ON w.product_id = p.id WHERE w.session_id = ? ORDER BY w.created_at DESC");
    $stmt->execute([$sessionId]);
}
$wishlistItems = $stmt->fetchAll();
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="index.php" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <span style="color: var(--color-text);">Wishlist</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">My Wishlist</h1>
            <p style="color: var(--color-text-light);"><?= count($wishlistItems) ?> item<?= count($wishlistItems) != 1 ? 's' : '' ?></p>
        </div>
    </section>

    <!-- Wishlist Content -->
    <section class="section">
        <div class="container">
            <?php if (empty($wishlistItems)): ?>
            <div style="text-align: center; padding: 4rem 1rem;">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 1.5rem; color: var(--color-text-muted);">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Your wishlist is empty</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">Start adding items you love to your wishlist!</p>
                <a href="products.php" class="btn btn-primary btn-lg">Browse Products</a>
            </div>
            <?php else: ?>
            <div class="products-grid">
                <?php foreach ($wishlistItems as $item): ?>
                <?php
                $price = $item['sale_price'] ?: $item['price'];
                $discount = $item['sale_price'] ? round((($item['price'] - $item['sale_price']) / $item['price']) * 100) : 0;
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($item['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80') ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                        
                        <?php if ($discount > 0): ?>
                        <span class="badge badge-sale" style="position: absolute; top: 0.5rem; left: 0.5rem;">-<?= $discount ?>%</span>
                        <?php endif; ?>
                        
                        <button class="product-wishlist active" data-product-id="<?= $item['product_id'] ?>" aria-label="Remove from wishlist" style="color: var(--color-danger);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </button>
                        
                        <div class="product-actions">
                            <a href="product.php?id=<?= $item['product_id'] ?>" class="btn btn-primary" style="flex: 1;">View Details</a>
                        </div>
                    </div>
                    
                    <div class="product-content">
                        <h3 class="product-name"><a href="product.php?id=<?= $item['product_id'] ?>"><?= htmlspecialchars($item['name']) ?></a></h3>
                        
                        <?php if ($item['status'] !== 'active' || $item['stock_quantity'] <= 0): ?>
                        <span class="badge badge-danger" style="margin-bottom: 0.5rem;">Out of Stock</span>
                        <?php endif; ?>
                        
                        <div class="product-price">
                            <span class="price-current"><?= formatPrice($price) ?></span>
                            <?php if ($item['sale_price']): ?>
                            <span class="price-original"><?= formatPrice($item['price']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
