<?php
/**
 * KARTLY - Checkout Page
 */
$pageTitle = 'Checkout';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login?redirect=checkout');
    exit;
}

$db = getDB();
$user = getCurrentUser();

// Get cart items
$sessionId = session_id();
$stmt = $db->prepare("SELECT c.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? OR c.session_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$_SESSION['user_id'], $sessionId]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    header('Location: ' . BASE_URL . '/cart');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $price = $item['sale_price'] ?: $item['price'];
    $subtotal += $price * $item['quantity'];
}
$taxRate = 0;
$tax = 0;

// Default shipping cost matches pre-selected 'Inside Dhaka' option
$shippingCost = 80;

$discount = 0;
$couponId = null;
if (isset($_SESSION['coupon'])) {
    $coupon = $_SESSION['coupon'];
    $couponId = $coupon['id'] ?? null;
    if ($coupon['type'] === 'percentage') {
        $discount = $subtotal * ($coupon['value'] / 100);
    } else {
        $discount = $coupon['value'];
    }
    $discount = min($discount, $subtotal);
}

$total = $subtotal + $shippingCost - $discount;

$error = '';
$success = '';

// Hardcoded explicit Payment Methods as requested
$availablePaymentMethods = [
    'cod' => [
        'label' => 'Cash on delivery',
        'description' => 'Pay with cash upon delivery.',
        'ready' => true,
    ],
    'bkash_manual' => [
        'label' => 'bKash',
        'description' => 'Pay manually via bKash',
        'ready' => true,
    ]
];
$hasReadyPaymentMethod = true;

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_first_name = sanitize($_POST['shipping_first_name'] ?? '');
    $shipping_last_name = sanitize($_POST['shipping_last_name'] ?? '');
    $shipping_email = sanitize($_POST['shipping_email'] ?? '');
    $shipping_phone = sanitize($_POST['shipping_phone'] ?? '');
    $shipping_address = sanitize($_POST['shipping_address'] ?? '');
    $shipping_city = sanitize($_POST['shipping_district'] ?? '');
    $shipping_upazila = sanitize($_POST['shipping_upazila'] ?? '');
    $shipping_postal_code = sanitize($_POST['shipping_postal_code'] ?? '');
    $order_notes = sanitize($_POST['order_notes'] ?? '');
    $shipping_country = 'Bangladesh';
    
    // Capture manual payment details
    $payment_method = strtolower(trim((string)($_POST['payment_method'] ?? '')));
    $payment_phone = sanitize($_POST['payment_phone'] ?? '');
    $payment_trx_id = sanitize($_POST['payment_trx_id'] ?? '');
    
    // Capture and calculate delivery method
    $delivery_method = sanitize($_POST['delivery_method'] ?? 'inside_dhaka');
    if ($delivery_method === 'inside_dhaka') {
        $shippingCost = 80;
    } elseif ($delivery_method === 'dhaka_sub') {
        $shippingCost = 120;
    } elseif ($delivery_method === 'outside_dhaka') {
        $shippingCost = 150;
    } else {
        $shippingCost = 80;
    }
    $total = $subtotal + $shippingCost;

    if (empty($availablePaymentMethods)) {
        $error = 'No payment methods are configured right now. Please contact support.';
    } elseif (!array_key_exists($payment_method, $availablePaymentMethods)) {
        $error = 'Invalid payment method selected.';
    } elseif (empty($payment_phone) || empty($payment_trx_id)) {
        $error = 'Please provide your bKash Number and Transaction ID.';
    } else {
        // Pre-flight Stock Verification
        foreach ($cartItems as $item) {
            if ($item['quantity'] > $item['stock_quantity']) {
                $error = "The item '{$item['name']}' only has {$item['stock_quantity']} units left in stock. Please reduce the quantity.";
                break;
            }
        }

        if (empty($error)) {
            try {
            $db->beginTransaction();

            $orderNumber = '';
            for ($i = 0; $i < 5; $i++) {
                $candidate = 'KAR-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
                $checkStmt = $db->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
                $checkStmt->execute([$candidate]);
                if (!$checkStmt->fetch()) {
                    $orderNumber = $candidate;
                    break;
                }
            }
            if ($orderNumber === '') {
                throw new RuntimeException('Unable to generate order number.');
            }

            $insertOrderStmt = $db->prepare("INSERT INTO orders (user_id, order_number, status, subtotal, discount, coupon_id, shipping_cost, tax, total, payment_method, payment_status, shipping_first_name, shipping_last_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_upazila, shipping_postal_code, shipping_country, order_notes, payment_phone, payment_trx_id, delivery_method) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertOrderStmt->execute([
                $_SESSION['user_id'],
                $orderNumber,
                $subtotal,
                $discount,
                $couponId,
                $shippingCost,
                $tax,
                $total,
                $payment_method,
                $shipping_first_name,
                $shipping_last_name,
                $shipping_email,
                $shipping_phone,
                $shipping_address,
                $shipping_city,
                $shipping_upazila,
                $shipping_postal_code,
                $shipping_country,
                $order_notes,
                $payment_phone,
                $payment_trx_id,
                $delivery_method
            ]);
            $orderId = (int)$db->lastInsertId();

            $insertItemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_sku, size, color, variant, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $updateStockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            foreach ($cartItems as $item) {
                $price = $item['sale_price'] ?: $item['price'];
                $insertItemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    '',
                    $item['size'] ?? null,
                    $item['color'] ?? null,
                    $item['variant'] ?? null,
                    $item['quantity'],
                    $price,
                    $price * $item['quantity'],
                ]);
                
                // Deduct stock
                $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Update coupon usage if applicable
            if ($couponId) {
                $updateCouponStmt = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                $updateCouponStmt->execute([$couponId]);
                unset($_SESSION['coupon']);
            }

            // Clear cart for all successful manual orders
            paymentClearUserCart($db, (int)$_SESSION['user_id']);
            $db->commit();

            $order = paymentGetOrderById($db, $orderId);
            if (!$order) {
                throw new RuntimeException('Order was not found after creation.');
            }

            $success = "Order placed successfully! Order number: {$order['order_number']}";

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Failed to place order. Please try again.';
        }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <h1 style="font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem;">Checkout</h1>
            <nav style="font-size: 0.875rem; color: var(--color-text-light);">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/cart" style="color: var(--color-text-light);">Cart</a>
                <span> / </span>
                <span style="color: var(--color-text);">Checkout</span>
            </nav>
        </div>
    </section>

    <!-- Checkout Form -->
    <section class="section">
        <div class="container">
            <?php if ($error): ?>
            <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div style="background: rgba(40, 167, 69, 0.1); border: 1px solid var(--color-success); color: var(--color-success); padding: 2rem; border-radius: var(--radius-lg); text-align: center;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 1rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Order Placed Successfully!</h2>
                <p style="color: var(--color-text-light); margin-bottom: 1rem;"><?= htmlspecialchars($success) ?></p>
                <a href="<?= BASE_URL ?>/account" class="btn btn-primary">View My Orders</a>
            </div>
            <?php else: ?>
            
            <form method="POST" action="">
                <div class="checkout-layout">
                    
                    <!-- Checkout Form -->
                    <div style="display: flex; flex-direction: column; gap: 2rem;">
                        <!-- Shipping Information -->
                        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="#e65100" stroke="none">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <path d="M4 4l4 4H4V4z" fill="#ffb74d"></path>
                                </svg>
                                <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0;">Shipping & Billing</h2>
                            </div>
                            
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="shipping_first_name" class="form-input" placeholder="First Name*" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="shipping_last_name" class="form-input" placeholder="Last Name*" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <input type="text" name="shipping_address" class="form-input" placeholder="Address*" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label class="form-label">Upazila/Thana</label>
                                    <input type="text" name="shipping_upazila" class="form-input" placeholder="Upazila/Thana*" value="<?= htmlspecialchars($user['upazila'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">District</label>
                                    <select name="shipping_district" class="form-input select2-district" required style="width: 100%;">
                                        <option value="">Search...</option>
                                        <?php
                                        $districts = ["Bagerhat", "Bandarban", "Barguna", "Barisal", "Bhola", "Bogura", "Brahmanbaria", "Chandpur", "Chapai Nawabganj", "Chattogram - City", "Chattogram - Suburb", "Chuadanga", "Cox's Bazar", "Cumilla", "Dhaka - City", "Dhaka - Suburb", "Dinajpur", "Faridpur", "Feni", "Gaibandha", "Gazipur - City", "Gazipur - Suburb", "Gopalganj", "Habiganj", "Jamalpur", "Jashore", "Jhalokati", "Jhenaidah", "Joypurhat", "Khagrachari", "Khulna - City", "Khulna - Suburb", "Kishoreganj", "Kurigram", "Kushtia", "Lakshmipur", "Lalmonirhat", "Madaripur", "Magura", "Manikganj", "Meherpur", "Moulvibazar", "Munshiganj", "Mymensingh", "Naogaon", "Narail", "Narayanganj", "Narsingdi", "Natore", "Netrokona", "Nilphamari", "Noakhali", "Pabna", "Panchagarh", "Patuakhali", "Pirojpur", "Rajbari", "Rajshahi - Suburb", "Rajshahi City", "Rangamati", "Rangpur - Suburb", "Rangpur City", "Satkhira", "Shariatpur", "Sherpur", "Sirajganj", "Sunamganj", "Sylhet", "Tangail", "Thakurgaon"];
                                        $userCity = $user['city'] ?? '';
                                        foreach ($districts as $district) {
                                            $selected = ($userCity == $district) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($district) . "\" $selected>" . htmlspecialchars($district) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" name="shipping_postal_code" class="form-input" placeholder="Postal Code" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">Mobile</label>
                                    <input type="tel" name="shipping_phone" class="form-input" placeholder="Telephone*" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="shipping_email" class="form-input" placeholder="E-Mail*" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Comment</label>
                                <textarea name="order_notes" class="form-input" placeholder="Any special requirement/instruction for us?" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <!-- Payment & Delivery Layout -->
                        <div class="form-grid-2">
                            
                            <!-- Payment Method Card -->
                            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e65100" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                    <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Payment Method</h2>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--color-text-light); margin-bottom: 1rem;">Select a payment method</p>

                                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                                        <input type="radio" name="payment_method" value="cod" checked style="width: 18px; height: 18px;" onchange="toggleBkash()">
                                        <span>Cash on Delivery</span>
                                    </label>
                                    
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                                        <input type="radio" name="payment_method" value="bkash_manual" id="bkash_radio" style="width: 18px; height: 18px;" onchange="toggleBkash()">
                                        <span>bKash</span>
                                    </label>
                                    
                                    <!-- bKash Instructions Box (Visible for both) -->
                                    <div id="bkash_instructions" style="background: #fafafa; border: 1px solid rgba(0,0,0,0.05); border-radius: var(--radius-md); padding: 1rem; margin-top: 0.5rem; font-size: 0.85rem;">
                                        <p style="margin-bottom: 0.5rem; color: #555;"><span id="cod_extra_msg1">We usually take payment 150 taka in advance to avoid fake orders.<br></span>Please complete your bKash payment at first, then fill up the form below.<span id="cod_extra_msg2"><br>Pay the remaining amount in cash when you receive the product.</span></p>
                                        <p style="margin-bottom: 1rem; font-weight: 600;">bKash Personal Number : 01706941756</p>
                                        
                                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                                                <div style="width: 140px; color: #555;">bKash Number <span style="color: var(--color-danger);">*</span></div>
                                                <input type="text" name="payment_phone" id="payment_phone" class="form-input" placeholder="017XXXXXXXX*" style="flex: 1; min-width: 150px; height: 36px;" required>
                                            </div>
                                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                                                <div style="width: 140px; color: #555;">bKash Transaction ID <span style="color: var(--color-danger);">*</span></div>
                                                <input type="text" name="payment_trx_id" id="payment_trx_id" class="form-input" placeholder="8N7A6D5EE7M*" style="flex: 1; min-width: 150px; height: 36px;" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border);">
                                    <p style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">We Accept :</p>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <span style="font-size: 0.75rem; font-weight: bold; background: #eee; padding: 0.2rem 0.4rem; border-radius: 3px;">CASH ON DELIVERY</span>
                                        <span style="font-size: 0.75rem; font-weight: bold; background: #eee; padding: 0.2rem 0.4rem; border-radius: 3px; color: #e1136c;">bKash</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Method Card -->
                            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#e65100" stroke-width="2"><path d="M1 3h15v13H1z"></path><path d="M16 8h4l3 3v5h-7V8z"></path><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                    <h2 style="font-size: 1.1rem; font-weight: 600; margin: 0;">Delivery Method</h2>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--color-text-light); margin-bottom: 1rem;">Select a delivery method</p>

                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.6rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: border-color 0.2s;">
                                        <input type="radio" name="delivery_method" value="inside_dhaka" data-cost="80" checked style="width: 16px; height: 16px; flex-shrink:0;" onchange="updateShipping(this)">
                                        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                            <span style="font-size:0.9rem; font-weight:500;">Inside Dhaka</span>
                                            <span style="font-weight:700; color:var(--color-primary);">Tk 80</span>
                                        </div>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.6rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: border-color 0.2s;">
                                        <input type="radio" name="delivery_method" value="dhaka_sub" data-cost="120" style="width: 16px; height: 16px; flex-shrink:0;" onchange="updateShipping(this)">
                                        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                            <span style="font-size:0.9rem; font-weight:500;">Dhaka Sub Area</span>
                                            <span style="font-weight:700; color:var(--color-primary);">Tk 120</span>
                                        </div>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.6rem 0.75rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); transition: border-color 0.2s;">
                                        <input type="radio" name="delivery_method" value="outside_dhaka" data-cost="150" style="width: 16px; height: 16px; flex-shrink:0;" onchange="updateShipping(this)">
                                        <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                            <span style="font-size:0.9rem; font-weight:500;">Outside Dhaka</span>
                                            <span style="font-weight:700; color:var(--color-primary);">Tk 150</span>
                                        </div>
                                    </label>
                                </div>
                            </div><!-- /delivery card -->
                        </div><!-- /form-grid-2 payment+delivery -->
                    </div><!-- /left column -->
                    
                    <!-- Order Summary -->
                    <div>
                        <div class="sticky-summary" style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Order Summary</h2>
                            
                            <!-- Cart Items -->
                            <div style="max-height: 200px; overflow-y: auto; margin-bottom: 1rem;">
                                <?php foreach ($cartItems as $item): ?>
                                <?php $price = $item['sale_price'] ?: $item['price']; ?>
                                <div style="display: flex; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--color-border);">
                                    <div style="width: 50px; height: 50px; background: var(--color-bg-secondary); border-radius: var(--radius-sm); overflow: hidden;">
                                        <img src="<?= htmlspecialchars($item['main_image'] ?: 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100&q=80') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <div style="flex: 1;">
                                        <p style="font-size: 0.875rem; font-weight: 500;"><?= htmlspecialchars($item['name']) ?></p>
                                        <?php if (!empty($item['variant'])): ?>
                                        <p style="font-size: 0.75rem; color: var(--color-text-light);">Variant: <?= htmlspecialchars($item['variant']) ?></p>
                                        <?php endif; ?>
                                        <p style="font-size: 0.75rem; color: var(--color-text-light);">Qty: <?= $item['quantity'] ?></p>
                                    </div>
                                    <div style="font-weight: 600;"><?= formatPrice($price * $item['quantity']) ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Totals -->
                            <div style="border-top: 1px solid var(--color-border); padding-top: 1rem; margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--color-text-light);">Subtotal</span>
                                    <span><?= formatPrice($subtotal) ?></span>
                                </div>
                                <?php if ($discount > 0 && isset($_SESSION['coupon'])): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--color-success);">Discount (<?= htmlspecialchars($_SESSION['coupon']['code']) ?>)</span>
                                    <span style="color: var(--color-success);">-<?= formatPrice($discount) ?></span>
                                </div>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--color-text-light);">Shipping</span>
                                    <span id="summary_shipping"><?= formatPrice($shippingCost) ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; padding-top: 1rem; border-top: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                                <span>Total</span>
                                <span id="summary_total"><?= formatPrice($total) ?></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;" <?= $hasReadyPaymentMethod ? '' : 'disabled' ?>>
                                Place Order
                            </button>
                            
                            <p style="font-size: 0.75rem; color: var(--color-text-light); text-align: center; margin-top: 1rem;">
                                By placing your order, you agree to our <a href="<?= BASE_URL ?>/terms" style="color: var(--color-primary);">Terms of Service</a> and <a href="<?= BASE_URL ?>/privacy" style="color: var(--color-primary);">Privacy Policy</a>
                            </p>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

<!-- Select2 CSS & JS for searchable district dropdown -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.select2-container .select2-selection--single {
    height: 42px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: var(--color-text);
    line-height: 40px;
    padding-left: 0.75rem;
}
</style>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-district').select2({
        placeholder: "Search district...",
        width: '100%'
    });
    
    // Initial state check for bkash
    toggleBkash();
});

function toggleBkash() {
    $('#payment_phone').prop('required', true);
    $('#payment_trx_id').prop('required', true);
    
    if ($('#bkash_radio').is(':checked')) {
        $('#cod_extra_msg1').hide();
        $('#cod_extra_msg2').hide();
    } else {
        $('#cod_extra_msg1').show();
        $('#cod_extra_msg2').show();
    }
}

function updateShipping(radio) {
    let cost = parseFloat($(radio).data('cost'));
    let subtotal = <?= $subtotal ?>;
    let total = subtotal + cost;
    $('#summary_shipping').text('৳' + cost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    $('#summary_total').text('৳' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
}



<?php require_once __DIR__ . '/../includes/footer.php'; ?>


