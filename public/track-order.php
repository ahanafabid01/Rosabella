<?php
/**
 * KARTLY - Track Order
 */
$pageTitle = 'Track Order';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

$error = '';
$order = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderNumber = sanitize($_POST['order_number'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    
    if (!empty($orderNumber) && !empty($email)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND shipping_email = ?");
        $stmt->execute([$orderNumber, $email]);
        $order = $stmt->fetch();
        
        if (!$order) {
            $error = 'Order not found. Please check your order number and email.';
        }
    } else {
        $error = 'Please enter both order number and email.';
    }
} elseif (isset($_GET['order']) && isLoggedIn()) {
    $orderNumber = sanitize($_GET['order']);
    $db = getDB();
    $user = getCurrentUser();
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
    $stmt->execute([$orderNumber, $user['id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $error = 'Order not found or you do not have permission to view it.';
    }
} else {
    $prefillOrder = isset($_GET['order']) ? sanitize($_GET['order']) : '';
}

$orderItems = [];
$coupon = null;
if ($order) {
    if (!isset($db)) $db = getDB();
    $stmt = $db->prepare("
        SELECT oi.*, p.main_image 
        FROM order_items oi 
        LEFT JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order['id']]);
    $orderItems = $stmt->fetchAll();
    
    if ($order['coupon_id']) {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
        $stmt->execute([$order['coupon_id']]);
        $coupon = $stmt->fetch();
    }
}
?>

    <!-- Page Header -->
    <section class="section-bg" style="padding: 2rem 0; border-bottom: 1px solid var(--color-border);">
        <div class="container">
            <nav style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;">
                <a href="<?= BASE_URL ?>/" style="color: var(--color-text-light); display: inline-flex; align-items: center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg></a>
                <span> / </span>
                <a href="<?= BASE_URL ?>/help" style="color: var(--color-text-light);">Help</a>
                <span> / </span>
                <span style="color: var(--color-text);">Track Order</span>
            </nav>
            <h1 style="font-size: 2rem; font-weight: 700;">Track Your Order</h1>
        </div>
    </section>

    <!-- Track Order Section -->
    <section class="section" style="padding-top: 2rem;">
        <div class="container" <?= !$order ? 'style="max-width: 800px;"' : 'style="max-width: 1200px;"' ?>>
            
            <?php if (!$order): ?>
            <!-- Search Form -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem; margin-bottom: 2rem;">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem;">Enter Your Order Details</h2>
                
                <?php if ($error): ?>
                    <div style="background: rgba(220, 53, 69, 0.1); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="order_number">Order Number</label>
                        <input type="text" id="order_number" name="order_number" class="form-input" placeholder="e.g., KAR-2024-12345" value="<?= htmlspecialchars($prefillOrder ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="Enter the email used for your order" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">Track Order</button>
                </form>
            </div>
            
            <div style="text-align: center;">
                <p style="color: var(--color-text-light); font-size: 0.875rem;">
                    Your order number can be found in your confirmation email.<br>
                    Check your spam folder if you can't find it.
                </p>
            </div>
            <?php else: ?>
            
            <!-- Order Found -->
            <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem; align-items: start;">
                <!-- Left Column: Order details -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                    
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">Order Information #<?= htmlspecialchars($order['order_number']) ?></h2>
                        <span class="badge badge-<?= $order['status'] === 'delivered' ? 'success' : 'primary' ?>" style="font-size: 0.875rem; padding: 0.4rem 0.8rem;"><?= ucfirst($order['status']) ?></span>
                    </div>

                    <!-- Address and Summary block -->
                    <div style="background: var(--color-bg-secondary); border-radius: var(--radius-md); padding: 1.5rem; display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div>
                            <h3 style="font-weight: 600; margin-bottom: 1rem; font-size: 1rem;">Shipping Address</h3>
                            <p style="font-style: italic; margin-bottom: 0.25rem; font-size: 0.9rem; line-height: 1.4;">
                                <?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?><br>
                                <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                                <?= htmlspecialchars($order['shipping_city']) ?><br>
                                <?= htmlspecialchars($order['shipping_country']) ?>
                            </p>
                            <p style="margin-top: 1rem; font-weight: 500; font-size: 0.9rem;">Mobile: <?= htmlspecialchars($order['shipping_phone']) ?></p>
                        </div>
                        <div>
                            <h3 style="font-weight: 600; margin-bottom: 1rem; font-size: 1rem;">Order Summary</h3>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <span>Sub-Total</span>
                                <span><?= formatPrice($order['subtotal']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <span>Home Delivery By Courier</span>
                                <span><?= formatPrice($order['shipping_cost']) ?></span>
                            </div>
                            <?php if ($order['discount'] > 0): ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                <span>Coupon <?= $coupon ? '('.htmlspecialchars($coupon['code']).')' : '' ?></span>
                                <span>-<?= formatPrice($order['discount']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-weight: 600; padding-top: 0.5rem; font-size: 0.9rem;">
                                <span>Total</span>
                                <span><?= formatPrice($order['total']) ?></span>
                            </div>
                            <?php
                            $paidAmount = 0;
                            $dueAmount = $order['total'];
                            
                            if ($order['payment_method'] === 'cod') {
                                $paidAmount = 200;
                                $dueAmount = max(0, $order['total'] - 200);
                            } elseif ($order['payment_status'] === 'paid') {
                                $paidAmount = $order['total'];
                                $dueAmount = 0;
                            }
                            ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--color-success); font-weight: 600; font-size: 0.9rem;">
                                <span>Paid</span>
                                <span><?= formatPrice($paidAmount) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-danger); font-weight: 600; font-size: 0.9rem;">
                                <span>Due</span>
                                <span><?= formatPrice($dueAmount) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Products -->
                    <h3 style="font-weight: 600; margin-bottom: 1rem; font-size: 1rem;">Products</h3>
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 2rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--color-border); color: var(--color-text-light); font-size: 0.875rem;">
                                <th style="padding: 0.5rem; text-align: left; font-weight: 600;">Image</th>
                                <th style="padding: 0.5rem; text-align: left; font-weight: 600;">Product Name</th>
                                <th style="padding: 0.5rem; text-align: right; font-weight: 600;">Quantity</th>
                                <th style="padding: 0.5rem; text-align: right; font-weight: 600;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                            <tr style="border-bottom: 1px solid var(--color-border);">
                                <td style="padding: 1rem 0.5rem;">
                                    <img src="<?= htmlspecialchars($item['main_image'] ? BASE_URL . '/' . ltrim($item['main_image'], '/') : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=80') ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--color-border);">
                                </td>
                                <td style="padding: 1rem 0.5rem;">
                                    <p style="font-weight: 500; font-size: 0.95rem;"><?= htmlspecialchars($item['product_name']) ?></p>
                                    <?php if (!empty($item['variant'])): ?>
                                    <p style="font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.2rem;">- Variant: <?= htmlspecialchars($item['variant']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['color'])): ?>
                                    <p style="font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.1rem;">- Color: <?= htmlspecialchars($item['color']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($item['size'])): ?>
                                    <p style="font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.1rem;">- Size: <?= htmlspecialchars($item['size']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem 0.5rem; text-align: right; font-weight: 500;"><?= $item['quantity'] ?></td>
                                <td style="padding: 1rem 0.5rem; text-align: right; font-weight: 600;"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Order Comments -->
                    <h3 style="font-weight: 600; margin-bottom: 0.5rem; font-size: 1rem;">Order Comments</h3>
                    <p style="font-size: 0.9rem; line-height: 1.5; color: var(--color-text);"><?= htmlspecialchars($order['notes'] ?: 'No comments provided.') ?></p>
                    
                </div>

                <!-- Right Column: Order History Timeline -->
                <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 600; margin-bottom: 2rem;">Order History</h3>
                    
                    <div style="position: relative; padding-left: 1.5rem;">
                        <!-- Vertical Line -->
                        <div style="position: absolute; left: 6px; top: 8px; bottom: 8px; width: 2px; background: var(--color-border);"></div>
                        
                        <?php
                        $history = [];
                        $date = date('d M Y', strtotime($order['created_at']));
                        $upDate = date('d M Y', strtotime($order['updated_at']));
                        
                        if (in_array($order['status'], ['shipped', 'delivered'])) {
                            $history[] = ['title' => 'Send To Courier', 'desc' => "Dear Customer,\nYour order has been sent to courier.\nAs soon as possible they will call you for delivery.\nThank you.", 'date' => $upDate];
                            $history[] = ['title' => 'Billing', 'desc' => 'INV-'.strtoupper($order['order_number']), 'date' => $upDate];
                        }
                        if (in_array($order['status'], ['processing', 'shipped', 'delivered'])) {
                            $history[] = ['title' => 'Confirmed', 'desc' => '', 'date' => $upDate];
                        }
                        if ($order['payment_status'] === 'paid') {
                            $history[] = ['title' => 'Payment Paid', 'desc' => 'Payment via ' . paymentDisplayName((string)$order['payment_method']) . '. Gateway Status: OK', 'date' => $upDate];
                        }
                        $history[] = ['title' => 'Pending for advance payment', 'desc' => '', 'date' => $date];
                        ?>
                        
                        <?php foreach ($history as $index => $step): ?>
                        <div style="position: relative; margin-bottom: 2rem;">
                            <!-- Circle marker -->
                            <div style="position: absolute; left: -1.82rem; top: 2px; width: 12px; height: 12px; border-radius: 50%; background: var(--color-bg); border: 2px solid var(--color-danger); z-index: 2;"></div>
                            
                            <h4 style="font-weight: 600; font-size: 0.9rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($step['title']) ?></h4>
                            <?php if ($step['desc']): ?>
                            <p style="font-size: 0.85rem; color: var(--color-text); margin-bottom: 0.5rem; line-height: 1.6;"><?= nl2br(htmlspecialchars($step['desc'])) ?></p>
                            <?php endif; ?>
                            <p style="font-size: 0.8rem; color: var(--color-text-light);"><?= htmlspecialchars($step['date']) ?></p>
                        </div>
                        <?php endforeach; ?>
                        
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



