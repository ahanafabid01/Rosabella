<?php
/**
 * KARTLY - Checkout Page
 */
$pageTitle = 'Checkout';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

$db = getDB();
$user = getCurrentUser();

// Get cart items
$stmt = $db->prepare("SELECT c.*, p.name, p.price, p.sale_price, p.main_image, p.stock_quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ? ORDER BY c.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$cartItems = $stmt->fetchAll();

if (empty($cartItems)) {
    header('Location: cart.php');
    exit;
}

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

$error = '';
$success = '';
$availablePaymentMethods = [];
$hasReadyPaymentMethod = false;

// Show enabled methods even if credentials are incomplete.
$sslToggleEnabled = paymentBoolSetting('payment_sslcommerz_enabled', true);
$sslCfg = paymentGetSslCommerzConfig();
$sslConfigured = $sslCfg['store_id'] !== '' && $sslCfg['store_passwd'] !== '';
if ($sslToggleEnabled) {
    $availablePaymentMethods['sslcommerz'] = [
        'label' => 'SSLCOMMERZ',
        'description' => $sslConfigured
            ? 'Cards, Mobile Banking, and Internet Banking'
            : 'Setup required in Admin > Settings (store ID/password)',
        'ready' => $sslConfigured,
    ];
    if ($sslConfigured) {
        $hasReadyPaymentMethod = true;
    }
}

$bkashToggleEnabled = paymentBoolSetting('payment_bkash_enabled', false);
$bkashCfg = paymentGetBkashConfig();
$bkashConfigured = $bkashCfg['app_key'] !== ''
    && $bkashCfg['app_secret'] !== ''
    && $bkashCfg['username'] !== ''
    && $bkashCfg['password'] !== '';
if ($bkashToggleEnabled) {
    $availablePaymentMethods['bkash'] = [
        'label' => 'bKash',
        'description' => $bkashConfigured
            ? 'Direct bKash payment'
            : 'Setup required in Admin > Settings (app key/secret/username/password)',
        'ready' => $bkashConfigured,
    ];
    if ($bkashConfigured) {
        $hasReadyPaymentMethod = true;
    }
}

$nagadToggleEnabled = paymentBoolSetting('payment_nagad_enabled', false);
$nagadConfigured = $sslConfigured;
if ($nagadToggleEnabled) {
    $availablePaymentMethods['nagad'] = [
        'label' => 'Nagad',
        'description' => $nagadConfigured
            ? 'Nagad checkout (processed via SSLCOMMERZ)'
            : 'Setup required: configure SSLCOMMERZ credentials first',
        'ready' => $nagadConfigured,
    ];
    if ($nagadConfigured) {
        $hasReadyPaymentMethod = true;
    }
}

$codEnabled = paymentBoolSetting('payment_cod_enabled', true);
if ($codEnabled) {
    $availablePaymentMethods['cod'] = [
        'label' => 'Cash on Delivery',
        'description' => 'Pay when the order arrives',
        'ready' => true,
    ];
    $hasReadyPaymentMethod = true;
}

// Process order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shipping_first_name = sanitize($_POST['shipping_first_name']);
    $shipping_last_name = sanitize($_POST['shipping_last_name']);
    $shipping_email = sanitize($_POST['shipping_email']);
    $shipping_phone = sanitize($_POST['shipping_phone']);
    $shipping_address = sanitize($_POST['shipping_address']);
    $shipping_city = sanitize($_POST['shipping_city']);
    $shipping_postal_code = sanitize($_POST['shipping_postal_code']);
    $shipping_country = 'Bangladesh';
    $payment_method = strtolower(trim((string)($_POST['payment_method'] ?? '')));

    if (empty($availablePaymentMethods)) {
        $error = 'No payment methods are configured right now. Please contact support.';
    } elseif (!array_key_exists($payment_method, $availablePaymentMethods)) {
        $error = 'Invalid payment method selected.';
    } elseif (empty($availablePaymentMethods[$payment_method]['ready'])) {
        $error = 'This payment method is not fully configured yet. Please choose another method or contact support.';
    } else {
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

            $insertOrderStmt = $db->prepare("INSERT INTO orders (user_id, order_number, status, subtotal, shipping_cost, tax, total, payment_method, payment_status, shipping_first_name, shipping_last_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_postal_code, shipping_country) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertOrderStmt->execute([
                $_SESSION['user_id'],
                $orderNumber,
                $subtotal,
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
                $shipping_postal_code,
                $shipping_country,
            ]);
            $orderId = (int)$db->lastInsertId();

            $insertItemStmt = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($cartItems as $item) {
                $price = $item['sale_price'] ?: $item['price'];
                $insertItemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['name'],
                    '',
                    $item['quantity'],
                    $price,
                    $price * $item['quantity'],
                ]);
            }

            if ($payment_method === 'cod') {
                paymentClearUserCart($db, (int)$_SESSION['user_id']);
            }

            $db->commit();

            $order = paymentGetOrderById($db, $orderId);
            if (!$order) {
                throw new RuntimeException('Order was not found after creation.');
            }

            if ($payment_method === 'cod') {
                $success = "Order placed successfully! Order number: {$order['order_number']}";
            } else {
                $gatewayResult = ['success' => false, 'error' => 'Payment gateway not configured.'];

                if ($payment_method === 'sslcommerz') {
                    $gatewayResult = paymentStartSslCommerz($db, $order);
                } elseif ($payment_method === 'bkash') {
                    $gatewayResult = paymentStartBkash($db, $order);
                } elseif ($payment_method === 'nagad') {
                    $gatewayResult = paymentStartSslCommerz($db, $order, 'nagad');
                }

                if (!empty($gatewayResult['success']) && !empty($gatewayResult['redirect_url'])) {
                    $_SESSION['pending_payment_order'] = $order['order_number'];
                    header('Location: ' . $gatewayResult['redirect_url']);
                    exit;
                }

                $gatewayError = trim((string)($gatewayResult['error'] ?? 'Payment initialization failed.'));
                paymentMarkOrderFailed($db, (int)$order['id'], $payment_method, 'Payment init failed: ' . $gatewayError);
                $error = $gatewayError;
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'Failed to place order. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

    <!-- Page Header -->
    <section class="section section-bg" style="padding: 1.5rem 0 2rem;">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light);">Home</a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/cart" style="color: var(--color-text-light);">Cart</a>
                <span> / </span>
                <span style="color: var(--color-text);">Checkout</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Checkout</h1>
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
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Shipping Information</h2>
                            
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="shipping_first_name" class="form-input" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="shipping_last_name" class="form-input" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="shipping_email" class="form-input" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone *</label>
                                <input type="tel" name="shipping_phone" class="form-input" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address *</label>
                                <input type="text" name="shipping_address" class="form-input" value="<?= htmlspecialchars($user['address'] ?? '') ?>" required>
                            </div>
                            
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label class="form-label">City *</label>
                                    <input type="text" name="shipping_city" class="form-input" value="<?= htmlspecialchars($user['city'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Postal Code *</label>
                                    <input type="text" name="shipping_postal_code" class="form-input" value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Country *</label>
                                    <input type="text" class="form-input" value="Bangladesh" readonly>
                                    <input type="hidden" name="shipping_country" value="Bangladesh">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Payment Method</h2>

                            <?php if (empty($availablePaymentMethods)): ?>
                            <div style="padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--color-danger); background: rgba(220, 53, 69, 0.08); color: var(--color-danger);">
                                No payment methods are currently available.
                            </div>
                            <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php $paymentOptionIndex = 0; ?>
                                <?php $hasCheckedOption = false; ?>
                                <?php foreach ($availablePaymentMethods as $methodKey => $methodMeta): ?>
                                <?php
                                $ready = !empty($methodMeta['ready']);
                                $checked = !$hasCheckedOption && $ready;
                                if ($checked) {
                                    $hasCheckedOption = true;
                                }
                                ?>
                                <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; border: 1px solid var(--color-border); border-radius: var(--radius-md); cursor: <?= $ready ? 'pointer' : 'not-allowed' ?>; opacity: <?= $ready ? '1' : '0.65' ?>;">
                                    <input type="radio" name="payment_method" value="<?= htmlspecialchars($methodKey) ?>" <?= $checked ? 'checked' : '' ?> <?= $ready ? '' : 'disabled' ?> style="width: 20px; height: 20px; margin-top: 0.15rem;">
                                    <span>
                                        <span style="display: block; font-weight: 600;"><?= htmlspecialchars($methodMeta['label']) ?></span>
                                        <span style="display: block; font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.15rem;"><?= htmlspecialchars($methodMeta['description']) ?></span>
                                    </span>
                                </label>
                                <?php $paymentOptionIndex++; ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
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
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--color-text-light);">Shipping</span>
                                    <span><?= $shippingCost == 0 ? 'Free' : formatPrice($shippingCost) ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="color: var(--color-text-light);">Tax (<?= number_format($taxRate, 0) ?>%)</span>
                                    <span><?= formatPrice($tax) ?></span>
                                </div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: 700; padding-top: 1rem; border-top: 1px solid var(--color-border); margin-bottom: 1.5rem;">
                                <span>Total</span>
                                <span><?= formatPrice($total) ?></span>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



