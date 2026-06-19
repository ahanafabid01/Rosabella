<?php
/**
 * KARTLY - Shopping Cart Page
 */
$pageTitle = 'Shopping Cart';
require_once 'includes/header.php';

$db = getDB();
$sessionId = session_id();

// Get cart items
if (isLoggedIn()) {
    $stmt = $db->prepare("
        SELECT c.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ? OR c.session_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $sessionId]);
} else {
    $stmt = $db->prepare("
        SELECT c.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.session_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$sessionId]);
}
$cartItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
}

$freeShippingThreshold = floatval(getSetting('free_shipping_threshold') ?: 5000);
$defaultShippingCost = floatval(getSetting('shipping_cost') ?: 120);
$taxRate = floatval(getSetting('tax_rate') ?: 0);
$shippingCost = $subtotal >= $freeShippingThreshold ? 0 : $defaultShippingCost;
$tax = $subtotal * ($taxRate / 100);
$total = $subtotal + $shippingCost + $tax;
?>

    <!-- Page Header -->
    <section class="section section-bg">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="index.php" style="color: var(--color-text-light);">Home</a>
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
                    <a href="products.php" class="btn btn-primary btn-lg">
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
                                <span>Total</span>
                                <span></span>
                            </div>
                            
                            <!-- Items -->
                            <?php foreach ($cartItems as $item): ?>
                                <?php $price = $item['sale_price'] ?: $item['price']; ?>
                                <div class="cart-item" data-id="<?= $item['id'] ?>" style="padding: 1rem; border-bottom: 1px solid var(--color-border);">
                                    <div class="cart-item-grid">
                                        
                                        <!-- Image -->
                                        <div style="width: 80px; height: 80px; border-radius: var(--radius-md); overflow: hidden; background: var(--color-bg-secondary);">
                                            <img src="<?= htmlspecialchars($item['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=80') ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        
                                        <!-- Product Info -->
                                        <div>
                                            <h3 style="font-weight: 600; margin-bottom: 0.25rem;">
                                                <a href="product.php?id=<?= $item['product_id'] ?>"><?= htmlspecialchars($item['name']) ?></a>
                                            </h3>
                                            <p style="font-size: 0.875rem; color: var(--color-text-light);">
                                                <?= $item['stock_quantity'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Price -->
                                        <div class="cart-price">
                                            <?php if ($item['sale_price']): ?>
                                                <span style="text-decoration: line-through; color: var(--color-text-light); font-size: 0.875rem;"><?= formatPrice($item['price']) ?></span>
                                            <?php endif; ?>
                                            <span style="font-weight: 600;"><?= formatPrice($price) ?></span>
                                        </div>
                                        
                                        <!-- Quantity -->
                                        <div class="quantity-input cart-qty" style="display: flex; align-items: center; gap: 0.5rem;">
                                            <button class="btn btn-secondary quantity-minus" style="width: 32px; height: 32px; padding: 0;">-</button>
                                            <input type="number" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" data-cart-id="<?= $item['id'] ?>" style="width: 50px; text-align: center; padding: 0.375rem; border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                                            <button class="btn btn-secondary quantity-plus" style="width: 32px; height: 32px; padding: 0;">+</button>
                                        </div>
                                        
                                        <!-- Total -->
                                        <div class="cart-total" style="font-weight: 700;">
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
                        
                        <!-- Continue Shopping -->
                        <div style="margin-top: 1.5rem;">
                            <a href="products.php" class="btn btn-ghost">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"/>
                                </svg>
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div>
                        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem; position: sticky; top: 100px;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Order Summary</h2>
                            
                            <!-- Coupon -->
                            <div style="margin-bottom: 1.5rem;">
                                <label style="display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Coupon Code</label>
                                <div style="display: flex; gap: 0.5rem;">
                                    <input type="text" placeholder="Enter code" class="form-input" style="flex: 1;">
                                    <button class="btn btn-secondary">Apply</button>
                                </div>
                            </div>
                            
                            <!-- Totals -->
                            <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <span style="color: var(--color-text-light);">Subtotal</span>
                                    <span><?= formatPrice($subtotal) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <span style="color: var(--color-text-light);">Shipping</span>
                                    <span><?= $shippingCost == 0 ? 'Free' : formatPrice($shippingCost) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                                    <span style="color: var(--color-text-light);">Tax (<?= number_format($taxRate, 0) ?>%)</span>
                                    <span><?= formatPrice($tax) ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 1.125rem; font-weight: 700; padding-top: 1rem; border-top: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                                <span>Total</span>
                                <span><?= formatPrice($total) ?></span>
                            </div>
                            
                            <a href="checkout.php" class="btn btn-primary btn-lg" style="width: 100%;">
                                Proceed to Checkout
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                                </svg>
                            </a>
                            
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
                                    Free shipping on orders over <?= formatPrice($freeShippingThreshold) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    

<?php require_once 'includes/footer.php'; ?>
