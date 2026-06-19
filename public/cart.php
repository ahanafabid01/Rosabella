<?php
/**
 * KARTLY - Shopping Cart Page
 */
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$sessionId = session_id();

// Get cart items
if (isLoggedIn()) {
    $stmt = $db->prepare("
        SELECT c.*, p.name, p.slug, p.price, p.sale_price, p.main_image, p.stock_quantity, p.sizes, p.colors, p.variants
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? OR c.session_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $sessionId]);
} else {
    $stmt = $db->prepare("
        SELECT c.*, p.name, p.slug, p.price, p.sale_price, p.main_image, p.stock_quantity, p.sizes, p.colors, p.variants
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.session_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();

// Fetch recommended products
$stmtRec = $db->query("SELECT * FROM products WHERE status = 'active' ORDER BY RAND() LIMIT 4");
$recommendedProducts = $stmtRec->fetchAll();

// Calculate totals and validate checkout readiness
$subtotal = 0;
$canProceedToCheckout = true;
foreach ($cartItems as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
    
    // Check if any required attributes are missing
    if (!empty($item['sizes']) && empty($item['size'])) $canProceedToCheckout = false;
    if (!empty($item['colors']) && empty($item['color'])) $canProceedToCheckout = false;
    if (!empty($item['variants']) && empty($item['variant'])) $canProceedToCheckout = false;
}

$freeShippingThreshold = floatval(getSetting('free_shipping_threshold') ?: 5000);
$defaultShippingCost = floatval(getSetting('shipping_cost') ?: 120);
$taxRate = floatval(getSetting('tax_rate') ?: 0);

$discount = 0;
if (isset($_SESSION['coupon'])) {
    $coupon = $_SESSION['coupon'];
    if ($coupon['type'] === 'percentage') {
        $discount = $subtotal * ($coupon['value'] / 100);
    } else {
        $discount = $coupon['value'];
    }
    // Discount shouldn't exceed subtotal
    $discount = min($discount, $subtotal);
}

$total = $subtotal - $discount; // Shipping and tax are calculated at checkout
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <span style="color: var(--color-text);">Shopping Cart</span>
            </nav>
            <h1 style="font-size: 1.875rem; font-weight: 700;">Shopping Cart</h1>
        </div>
    </section>

    <!-- Cart Section -->
    <section class="section">
        <div class="container">
            <?php if (empty($cartItems)): ?>
                <!-- Empty Cart -->
                <div style="text-align: center; padding: 4rem 1rem;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" style="margin: 0 auto 1.5rem; color: var(--color-text-muted);">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Your cart is empty</h2>
                    <p style="color: var(--color-text-light); margin-bottom: 1.5rem;">Looks like you haven't added any items to your cart yet.</p>
                    <a href="<?= BASE_URL ?>/shop" class="btn btn-primary btn-lg">
                        Start Shopping
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    
                    <!-- Cart Items -->
                    <div>
                        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden;">
                            <!-- Header -->
                            <div class="cart-header-desktop" style="padding: 1rem; border-bottom: 1px solid var(--color-border); background: var(--color-bg-secondary); font-weight: 600; font-size: 0.875rem;">
                                <span>Product</span>
                                <span>Price</span>
                                <span>Quantity</span>
                                <span>Sub-Total</span>
                                <span></span>
                            </div>
                            
                            <!-- Items -->
                            <?php foreach ($cartItems as $item): ?>
                                <?php $price = $item['sale_price'] ?: $item['price']; ?>
                                <div class="cart-item" data-id="<?= $item['id'] ?>" style="position: relative; padding: 1rem; border-bottom: 1px solid var(--color-border);">
                                    <div class="cart-item-grid">
                                        
                                        <!-- Image -->
                                        <div style="width: 80px; height: 80px; border-radius: var(--radius-md); overflow: hidden; background: var(--color-bg-secondary);">
                                            <img src="<?= htmlspecialchars($item['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=80') ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        
                                        <!-- Product Info -->
                                        <div>
                                            <h3 style="font-weight: 600; margin-bottom: 0.25rem;">
                                                <a href="<?= BASE_URL ?>/product/<?= $item['slug'] ?>"><?= htmlspecialchars($item['name']) ?></a>
                                            </h3>
                                            <?php if (!empty($item['sizes'])): ?>
                                                <?php if (empty($item['size'])): ?>
                                                <div style="margin-bottom: 0.5rem;">
                                                    <select class="cart-attribute-select" data-cart-id="<?= $item['id'] ?>" data-attribute="selected_size" style="padding: 0.25rem; border: 1px solid var(--color-danger); border-radius: 4px; font-size: 0.875rem;">
                                                        <option value="">Select Size (Required)</option>
                                                        <?php foreach (explode(',', $item['sizes']) as $s): $s = trim($s); if ($s): ?>
                                                            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                                                        <?php endif; endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php else: ?>
                                                <p style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.25rem;">
                                                    Size: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['size']) ?></strong>
                                                </p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['colors'])): ?>
                                                <?php if (empty($item['color'])): ?>
                                                <div style="margin-bottom: 0.5rem;">
                                                    <select class="cart-attribute-select" data-cart-id="<?= $item['id'] ?>" data-attribute="selected_color" style="padding: 0.25rem; border: 1px solid var(--color-danger); border-radius: 4px; font-size: 0.875rem;">
                                                        <option value="">Select Color (Required)</option>
                                                        <?php foreach (explode(',', $item['colors']) as $c): $c = trim($c); if ($c): ?>
                                                            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                                        <?php endif; endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php else: ?>
                                                <p style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.25rem;">
                                                    Color: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['color']) ?></strong>
                                                </p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['variants'])): ?>
                                                <?php if (empty($item['variant'])): ?>
                                                <div style="margin-bottom: 0.5rem;">
                                                    <select class="cart-attribute-select" data-cart-id="<?= $item['id'] ?>" data-attribute="selected_variant" style="padding: 0.25rem; border: 1px solid var(--color-danger); border-radius: 4px; font-size: 0.875rem;">
                                                        <option value="">Select Variant (Required)</option>
                                                        <?php foreach (explode(',', $item['variants']) as $v): $v = trim($v); if ($v): ?>
                                                            <option value="<?= htmlspecialchars($v) ?>"><?= htmlspecialchars($v) ?></option>
                                                        <?php endif; endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php else: ?>
                                                <p style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.25rem;">
                                                    Variant: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['variant']) ?></strong>
                                                </p>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <p style="font-size: 0.875rem; color: var(--color-text-light);">
                                                <?= $item['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Price -->
                                        <div class="cart-price">
                                            <span><?= formatPrice($price) ?></span>
                                        </div>
                                        
                                        <!-- Quantity -->
                                        <div class="quantity-input cart-qty" style="display: flex; align-items: center; gap: 0.5rem;">
                                            <button class="btn btn-secondary quantity-minus" style="width: 32px; height: 32px; padding: 0;">-</button>
                                            <input type="number" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" data-cart-id="<?= $item['id'] ?>" style="width: 50px; text-align: center; padding: 0.375rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                                            <button class="btn btn-secondary quantity-plus" style="width: 32px; height: 32px; padding: 0;">+</button>
                                        </div>
                                        
                                        <!-- Total -->
                                        <div class="cart-total" style="font-weight: 700; color: var(--color-text); font-size: 1.1rem;">
                                            <?= formatPrice($price * $item['quantity']) ?>
                                        </div>
                                        
                                        <!-- Remove -->
                                        <button class="btn btn-ghost cart-remove" data-cart-id="<?= $item['id'] ?>" style="color: var(--color-danger);">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div>
                        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; position: sticky; top: 100px;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Order Summary</h2>
                            
                            <!-- Coupon -->
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Coupon Code</label>
                                <?php if (isset($_SESSION['coupon'])): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: var(--radius-md);">
                                    <div>
                                        <div style="font-weight: 600; color: var(--color-success);"><?= htmlspecialchars($_SESSION['coupon']['code']) ?> Applied</div>
                                        <div style="font-size: 0.75rem; color: var(--color-text-light);">
                                            <?= $_SESSION['coupon']['type'] === 'percentage' ? $_SESSION['coupon']['value'] . '% off' : formatPrice($_SESSION['coupon']['value']) . ' off' ?>
                                        </div>
                                    </div>
                                    <button class="btn btn-ghost" id="remove-coupon-btn" style="color: var(--color-danger); padding: 0.25rem;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                                <?php else: ?>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" id="coupon-code-input" placeholder="Enter code" class="form-input" style="flex: 1;">
                                    <button id="apply-coupon-btn" class="btn btn-secondary">Apply</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Totals -->
                            <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <span style="color: var(--color-text-light);">Subtotal</span>
                                    <span><?= formatPrice($subtotal) ?></span>
                                </div>
                                <?php if ($discount > 0): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <span style="color: var(--color-success);">Discount</span>
                                    <span style="color: var(--color-success);">-<?= formatPrice($discount) ?></span>
                                </div>
                                <?php endif; ?>

                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; padding-top: 1rem; border-top: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                                <span>Total</span>
                                <span style="color: var(--color-danger); font-size: 1.35rem;"><?= formatPrice($total) ?></span>
                            </div>
                            
                            <a href="<?= BASE_URL ?>/shop" class="btn btn-lg" style="width: 100%; background-color: #000; color: #fff; margin-bottom: 0.75rem;">
                                Continue Shopping
                            </a>
                            <?php if ($canProceedToCheckout): ?>
                            <a href="<?= BASE_URL ?>/checkout" class="btn btn-primary btn-lg" style="width: 100%;">
                                Proceed to Checkout
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                            <?php else: ?>
                            <button class="btn btn-primary btn-lg" style="width: 100%; opacity: 0.6; cursor: not-allowed;" onclick="showToast('Please select all required sizes, colors, and variants for your items before checking out.', 'error');">
                                Proceed to Checkout
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                            
                            <!-- Trust Badges -->
                            <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                    Secure Checkout
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: var(--color-text-light);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                                    </svg>
                                    Fast shipping on orders over <?= formatPrice($freeShippingThreshold) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Recommended Products -->
    <?php if (!empty($recommendedProducts)): ?>
    <section class="section" style="padding-top: 0; padding-bottom: 3rem;">
        <div class="container">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">You May Be Interested In...</h2>
            <div class="products-grid">
                <?php foreach ($recommendedProducts as $product): ?>
                    <?php
                    // Calculate discount
                    $discount = 0;
                    if ($product['sale_price'] && $product['price'] > 0) {
                        $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                    }
                    $image = $product['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&q=80';
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>" class="product-image-link" aria-label="View <?= htmlspecialchars($product['name']) ?>"></a>
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            
                            <!-- Badges -->
                            <div class="product-badges">
                                <?php if (isset($product['is_new']) && $product['is_new']): ?>
                                    <span class="badge badge-new">New</span>
                                <?php endif; ?>
                                <?php if (isset($product['is_bestseller']) && $product['is_bestseller']): ?>
                                    <span class="badge badge-bestseller">Best Seller</span>
                                <?php endif; ?>
                                <?php if ($discount > 0): ?>
                                    <span class="badge badge-sale">-<?= $discount ?>%</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Wishlist Button -->
                            <button class="product-wishlist" data-product-id="<?= $product['id'] ?>" aria-label="Add to wishlist">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                </svg>
                            </button>
                            
                            <!-- Quick Actions -->
                            <div class="product-actions">
                                <button class="btn btn-primary product-add-cart" data-product-id="<?= $product['id'] ?>" style="flex: 1;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                        
                        <div class="product-content">
                            <h3 class="product-name">
                                <a href="<?= BASE_URL ?>/product/<?= $product['slug'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                            </h3>
                            <div class="product-price">
                                <span class="price-current">
                                    <?= formatPrice($product['sale_price'] ?: $product['price']) ?>
                                </span>
                                <?php if ($product['sale_price']): ?>
                                    <span class="price-original"><?= formatPrice($product['price']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
