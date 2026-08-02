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

<style>
/* ===== TRACK ORDER PAGE ===== */
.track-hero {
    padding: 2rem 0;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-bg-secondary);
}
.track-hero h1 {
    font-size: clamp(1.5rem, 5vw, 2rem);
    font-weight: 700;
    margin-bottom: 0.4rem;
}
.track-breadcrumb {
    font-size: 0.875rem;
    color: var(--color-text-light);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.track-breadcrumb a { color: var(--color-text-light); transition: color 0.2s; }
.track-breadcrumb a:hover { color: var(--color-primary); }

/* ===== SEARCH FORM ===== */
.track-search-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: clamp(1.25rem, 4vw, 2rem);
    max-width: 520px;
    margin: 0 auto 1.5rem;
}
.track-search-card h2 {
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    text-align: center;
}
.track-search-hint {
    text-align: center;
    font-size: 0.85rem;
    color: var(--color-text-light);
    line-height: 1.6;
}

/* ===== ORDER RESULT LAYOUT ===== */
.track-result-grid {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 1.5rem;
    align-items: start;
}
@media (max-width: 900px) {
    .track-result-grid {
        grid-template-columns: 1fr;
    }
    /* On mobile, put history below the main card */
}

/* ===== ORDER CARD ===== */
.track-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.track-card-body {
    padding: clamp(1rem, 4vw, 1.75rem);
}

/* ===== ORDER HEADER ===== */
.track-order-header {
    text-align: center;
    padding: clamp(1rem, 4vw, 1.5rem);
    border-bottom: 1px solid var(--color-border);
    background: linear-gradient(135deg, var(--color-bg-secondary) 0%, var(--color-bg) 100%);
}
.track-order-header h2 {
    font-size: clamp(0.95rem, 3.5vw, 1.25rem);
    font-weight: 700;
    margin-bottom: 0.6rem;
    word-break: break-all;
}

/* ===== ADDRESS + SUMMARY 2 COL ===== */
.track-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    background: var(--color-bg-secondary);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}
@media (max-width: 600px) {
    .track-info-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
}

.track-info-section h3 {
    font-weight: 700;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-light);
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--color-border);
}
.track-address-text {
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--color-text);
}
.track-address-phone {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.6rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-primary);
}

/* ===== ORDER SUMMARY ROWS ===== */
.track-summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.4rem 0;
    font-size: 0.875rem;
    border-bottom: 1px dashed var(--color-border);
}
.track-summary-row:last-child { border-bottom: none; }
.track-summary-row.total-row {
    font-weight: 700;
    font-size: 0.95rem;
    border-top: 2px solid var(--color-border);
    border-bottom: none;
    padding-top: 0.6rem;
    margin-top: 0.25rem;
}
.track-summary-row.paid-row { color: var(--color-success); font-weight: 600; }
.track-summary-row.due-row  { color: var(--color-danger);  font-weight: 600; }

/* ===== PRODUCTS TABLE ===== */
.track-products-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 1.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
}
.track-products-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 420px;
    white-space: nowrap;
}
.track-products-table thead tr {
    background: var(--color-bg-secondary);
}
.track-products-table th {
    padding: 0.75rem 1rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-light);
    text-align: left;
}
.track-products-table th:last-child,
.track-products-table td:last-child { text-align: right; }
.track-products-table td {
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--color-border);
    font-size: 0.9rem;
    vertical-align: middle;
}
.track-product-img {
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    display: block;
}
.track-product-name {
    font-weight: 600;
    font-size: 0.9rem;
    white-space: normal;
    max-width: 220px;
}
.track-product-meta {
    font-size: 0.78rem;
    color: var(--color-text-light);
    margin-top: 0.15rem;
    white-space: normal;
}

/* ===== ORDER COMMENTS ===== */
.track-comments {
    background: var(--color-bg-secondary);
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
}
.track-comments h3 {
    font-size: 0.875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-light);
    margin-bottom: 0.5rem;
}
.track-comments p {
    font-size: 0.9rem;
    line-height: 1.6;
    color: var(--color-text);
}

/* ===== TIMELINE (History) ===== */
.track-history-card {
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: clamp(1rem, 4vw, 1.5rem);
    position: sticky;
    top: 1rem;
}
@media (max-width: 900px) {
    .track-history-card { position: static; }
}
.track-history-card h3 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.track-timeline {
    position: relative;
    padding-left: 1.75rem;
}
.track-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 8px;
    bottom: 8px;
    width: 2px;
    background: var(--color-border);
}
.track-timeline-item {
    position: relative;
    margin-bottom: 1.75rem;
}
.track-timeline-item:last-child { margin-bottom: 0; }
.track-timeline-dot {
    position: absolute;
    left: -1.9rem;
    top: 3px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: var(--color-bg);
    border: 2px solid var(--color-primary);
    z-index: 2;
    transition: transform 0.2s;
}
.track-timeline-dot.completed {
    background: var(--color-primary);
}
.track-timeline-item h4 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
    color: var(--color-text);
}
.track-timeline-item p.desc {
    font-size: 0.82rem;
    color: var(--color-text-light);
    line-height: 1.6;
    margin-bottom: 0.3rem;
    white-space: pre-wrap;
}
.track-timeline-item p.date {
    font-size: 0.78rem;
    color: var(--color-text-light);
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

/* ===== STATUS BADGE OVERRIDES ===== */
.status-pending   { background: rgba(234,179,8,.15);   color: #a16207;   }
.status-processing{ background: rgba(59,130,246,.15);  color: #1d4ed8;   }
.status-shipped   { background: rgba(168,85,247,.15);  color: #7e22ce;   }
.status-delivered { background: rgba(34,197,94,.15);   color: #15803d;   }
.status-cancelled { background: rgba(239,68,68,.15);   color: #b91c1c;   }
.track-status-pill {
    display: inline-block;
    padding: 0.35rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
</style>

<!-- Page Header -->
<section class="track-hero">
    <div class="container">
        <h1>Track Your Order</h1>
        <nav class="track-breadcrumb">
            <a href="<?= BASE_URL ?>/">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
            </a>
            <span>/</span>
            <a href="<?= BASE_URL ?>/my-orders">Orders</a>
            <span>/</span>
            <span style="color: var(--color-text);">Track Order</span>
        </nav>
    </div>
</section>

<!-- Track Order Section -->
<section class="section" style="padding-top: 2rem; padding-bottom: 3rem;">
    <div class="container" style="max-width: <?= $order ? '1100px' : '560px' ?>;">

        <?php if (!$order): ?>
        <!-- ===== SEARCH FORM ===== -->
        <div class="track-search-card">
            <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:0.35rem; color: var(--color-primary);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                Enter Your Order Details
            </h2>

            <?php if ($error): ?>
            <div style="background: rgba(220,53,69,.08); border: 1px solid var(--color-danger); color: var(--color-danger); padding: 0.75rem 1rem; border-radius: var(--radius-md); margin-bottom: 1rem; font-size: 0.875rem; display:flex; align-items:center; gap:0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="order_number">Order Number</label>
                    <input type="text" id="order_number" name="order_number" class="form-input"
                           placeholder="e.g., KAR-2024-12345"
                           value="<?= htmlspecialchars($prefillOrder ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input"
                           placeholder="Email used for your order" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding: 0.85rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle; margin-right:0.35rem;"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    Track My Order
                </button>
            </form>
        </div>

        <p class="track-search-hint">
            Your order number can be found in your confirmation email.<br>
            Check your spam folder if you can't find it.
        </p>

        <?php else: ?>
        <!-- ===== ORDER RESULT ===== -->
        <div class="track-result-grid">

            <!-- LEFT: Main order details -->
            <div class="track-card">
                <!-- Order header -->
                <div class="track-order-header">
                    <h2>#<?= htmlspecialchars($order['order_number']) ?></h2>
                    <?php
                    $statusMap = [
                        'pending'    => ['label' => 'Pending',    'class' => 'status-pending'],
                        'processing' => ['label' => 'Processing', 'class' => 'status-processing'],
                        'shipped'    => ['label' => 'Shipped',    'class' => 'status-shipped'],
                        'delivered'  => ['label' => 'Delivered',  'class' => 'status-delivered'],
                        'cancelled'  => ['label' => 'Cancelled',  'class' => 'status-cancelled'],
                    ];
                    $sInfo = $statusMap[$order['status']] ?? ['label' => ucfirst($order['status']), 'class' => 'status-pending'];
                    ?>
                    <span class="track-status-pill <?= $sInfo['class'] ?>"><?= $sInfo['label'] ?></span>
                    <div style="margin-top:0.6rem; font-size:0.8rem; color: var(--color-text-light);">
                        Placed on <?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?>
                    </div>
                </div>

                <div class="track-card-body">
                    <!-- Download Invoice button -->
                    <div style="display:flex; justify-content:flex-end; margin-bottom:1.25rem;">
                        <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>&email=<?= urlencode($order['shipping_email']) ?>"
                           target="_blank"
                           id="btn-download-invoice"
                           style="display:inline-flex; align-items:center; gap:0.4rem; padding:0.55rem 1.15rem; background:linear-gradient(135deg,#1a1a2e,#2d2d5e); color:#fff; border-radius:8px; font-size:0.82rem; font-weight:600; text-decoration:none; transition:opacity 0.2s,transform 0.15s; box-shadow:0 2px 10px rgba(0,0,0,0.15);"
                           onmouseover="this.style.opacity='0.85';this.style.transform='translateY(-1px)'"
                           onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="8 17 12 21 16 17"/>
                                <line x1="12" y1="12" x2="12" y2="21"/>
                                <path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/>
                            </svg>
                            Download Invoice (PDF)
                        </a>
                    </div>

                    <!-- Address + Summary -->
                    <div class="track-info-grid">
                        <!-- Shipping Address -->
                        <div class="track-info-section">
                            <h3>Shipping Address</h3>
                            <p class="track-address-text">
                                <strong><?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?></strong><br>
                                <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                                <?= htmlspecialchars($order['shipping_city']) ?><br>
                                <?= htmlspecialchars($order['shipping_country']) ?>
                            </p>
                            <p class="track-address-phone">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.15 11.83a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.06 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21 16z"/></svg>
                                <?= htmlspecialchars($order['shipping_phone']) ?>
                            </p>
                        </div>

                        <!-- Order Summary -->
                        <div class="track-info-section">
                            <h3>Order Summary</h3>
                            <div class="track-summary-row">
                                <span>Sub-Total</span>
                                <span><?= formatPrice($order['subtotal']) ?></span>
                            </div>
                            <div class="track-summary-row">
                                <span>Delivery</span>
                                <span><?= formatPrice($order['shipping_cost']) ?></span>
                            </div>
                            <?php if ($order['discount'] > 0): ?>
                            <div class="track-summary-row" style="color: var(--color-success);">
                                <span>Coupon <?= $coupon ? '(' . htmlspecialchars($coupon['code']) . ')' : '' ?></span>
                                <span>-<?= formatPrice($order['discount']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="track-summary-row total-row">
                                <span>Total</span>
                                <span><?= formatPrice($order['total']) ?></span>
                            </div>
                            <?php
                            $paidAmount = 0;
                            $dueAmount  = $order['total'];
                            if ($order['payment_method'] === 'cod') {
                                $paidAmount = 200;
                                $dueAmount  = max(0, $order['total'] - 200);
                            } elseif ($order['payment_status'] === 'paid') {
                                $paidAmount = $order['total'];
                                $dueAmount  = 0;
                            }
                            ?>
                            <div class="track-summary-row paid-row">
                                <span>Paid</span>
                                <span><?= formatPrice($paidAmount) ?></span>
                            </div>
                            <div class="track-summary-row due-row">
                                <span>Due</span>
                                <span><?= formatPrice($dueAmount) ?></span>
                            </div>
                            <div style="margin-top:0.75rem; font-size:0.78rem; color: var(--color-text-light);">
                                via <?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Products -->
                    <h3 style="font-weight:700; font-size:0.875rem; text-transform:uppercase; letter-spacing:0.05em; color: var(--color-text-light); margin-bottom:0.75rem;">
                        Products (<?= count($orderItems) ?>)
                    </h3>
                    <div class="track-products-wrap">
                        <table class="track-products-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th style="text-align:center;">Qty</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td>
                                        <img class="track-product-img"
                                             src="<?= htmlspecialchars($item['main_image'] ? BASE_URL . '/' . ltrim($item['main_image'], '/') : 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=200&q=80') ?>"
                                             alt="<?= htmlspecialchars($item['product_name']) ?>">
                                    </td>
                                    <td>
                                        <p class="track-product-name"><?= htmlspecialchars($item['product_name']) ?></p>
                                        <?php if (!empty($item['color'])): ?>
                                        <p class="track-product-meta">Color: <?= htmlspecialchars($item['color']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['size'])): ?>
                                        <p class="track-product-meta">Size: <?= htmlspecialchars($item['size']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($item['variant'])): ?>
                                        <p class="track-product-meta">Variant: <?= htmlspecialchars($item['variant']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center; font-weight:600;"><?= intval($item['quantity']) ?></td>
                                    <td style="font-weight:700;"><?= formatPrice($item['price'] * $item['quantity']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Order Comments -->
                    <div class="track-comments">
                        <h3>Order Notes</h3>
                        <p><?= htmlspecialchars($order['notes'] ?: 'No comments provided.') ?></p>
                    </div>
                </div>
            </div><!-- end left -->

            <!-- RIGHT: Timeline -->
            <div class="track-history-card">
                <h3>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Order History
                </h3>

                <div class="track-timeline">
                    <?php
                    $history = [];
                    $date   = date('d M Y', strtotime($order['created_at']));
                    $upDate = date('d M Y', strtotime($order['updated_at']));

                    if (in_array($order['status'], ['shipped', 'delivered'])) {
                        $history[] = ['title' => 'Sent to Courier', 'desc' => "Dear Customer,\nYour order has been sent to courier.\nAs soon as possible they will call you for delivery.\nThank you.", 'date' => $upDate, 'done' => true];
                        $history[] = ['title' => 'Billing', 'desc' => 'INV-' . strtoupper($order['order_number']), 'date' => $upDate, 'done' => true];
                    }
                    if (in_array($order['status'], ['processing', 'shipped', 'delivered'])) {
                        $history[] = ['title' => 'Confirmed', 'desc' => '', 'date' => $upDate, 'done' => true];
                    }
                    if ($order['payment_status'] === 'paid') {
                        $history[] = ['title' => 'Payment Received', 'desc' => 'via ' . paymentDisplayName((string)$order['payment_method']) . '. Gateway Status: OK', 'date' => $upDate, 'done' => true];
                    }
                    $history[] = ['title' => 'Order Placed', 'desc' => '', 'date' => $date, 'done' => true];
                    ?>

                    <?php foreach ($history as $step): ?>
                    <div class="track-timeline-item">
                        <div class="track-timeline-dot <?= $step['done'] ? 'completed' : '' ?>"></div>
                        <h4><?= htmlspecialchars($step['title']) ?></h4>
                        <?php if ($step['desc']): ?>
                        <p class="desc"><?= nl2br(htmlspecialchars($step['desc'])) ?></p>
                        <?php endif; ?>
                        <p class="date">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?= htmlspecialchars($step['date']) ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div><!-- end right -->

        </div><!-- end track-result-grid -->
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
