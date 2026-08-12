<?php
/**
 * Rosabella - Admin Order Detail
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$db = getDB();

// \u2500\u2500 Security: Verify CSRF on all admin POST requests \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';
$error = '';
$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: ' . BASE_URL . '/admin/orders');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = sanitize($_POST['status'] ?? 'pending');
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    if (!in_array($status, $allowed, true)) {
        $error = 'Invalid order status.';
    } else {
        // Fetch old status to handle stock properly
        $oldStatusStmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
        $oldStatusStmt->execute([$orderId]);
        $oldStatus = $oldStatusStmt->fetchColumn();

        if ($status === 'delivered') {
            $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        }

        if ($stmt->execute([$status, $orderId])) {
            $message = 'Order status updated.';
            
            // Stock Synchronization Logic
            if ($oldStatus && $oldStatus !== $status) {
                $isOldCancelledOrRefunded = in_array($oldStatus, ['cancelled', 'refunded'], true);
                $isNewCancelledOrRefunded = in_array($status, ['cancelled', 'refunded'], true);
                
                if (!$isOldCancelledOrRefunded && $isNewCancelledOrRefunded) {
                    // Order was active, now cancelled/refunded -> RESTOCK
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $restockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $restockStmt->execute([$item['quantity'], $item['product_id']]);
                    }
                } elseif ($isOldCancelledOrRefunded && !$isNewCancelledOrRefunded) {
                    // Order was cancelled/refunded, now active -> DEDUCT STOCK
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $destockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $destockStmt->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
        } else {
            $error = 'Unable to update order status.';
        }
    }
}

$stmt = $db->prepare("
    SELECT o.*, u.email AS account_email
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    WHERE o.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/admin/orders');
    exit;
}

$itemsStmt = $db->prepare("
    SELECT oi.*, p.main_image
    FROM order_items oi
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.id ASC
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

$pageTitle = 'Order Detail';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php $siteFavicon = getSetting('site_favicon'); if ($siteFavicon): ?>
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL . '/' . htmlspecialchars($siteFavicon) ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Rosabella Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/admin/css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('orders'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-detail-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 class="admin-page-title" style="margin-bottom: 0.25rem !important;">Order #<?= htmlspecialchars($order['order_number']) ?></h1>
                <div style="font-size: 0.875rem; color: var(--color-text-light);">
                    Placed on <?= htmlspecialchars(date('M j, Y \a\t H:i A', strtotime($order['created_at']))) ?>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Invoice
                </a>
                <a href="<?= BASE_URL ?>/admin/orders" class="btn btn-secondary">Back</a>
            </div>
        </div>

        <?php if ($message): ?><div class="alert alert-success" style="margin-bottom: 1.5rem; border-radius: 8px;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error" style="margin-bottom: 1.5rem; border-radius: 8px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0; max-width: 100vw;">
            <!-- CSS for desktop 2-column layout -->
            <style>
                .order-detail-layout { display: flex; flex-direction: column; gap: 1.5rem; }
                @media (min-width: 1024px) {
                    .order-detail-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start; }
                }
                .detail-section-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem; color: var(--color-text); padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border); }
                .info-block { margin-bottom: 1.25rem; }
                .info-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-text-light); margin-bottom: 0.3rem; font-weight: 600; }
                .info-value { font-size: 0.95rem; color: var(--color-text); line-height: 1.5; }
            </style>

            <div class="order-detail-layout" style="min-width: 0; max-width: 100%;">
                <!-- Left Column: Items & Summary -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0;">
                    <div class="admin-card" style="margin-bottom: 0; overflow: hidden; min-width: 0;">
                        <h2 class="detail-section-title">Order Items</h2>
                        <div class="admin-table-wrap" style="border: none; overflow-x: auto; width: 100%;">
                            <table class="admin-table" style="min-width: 600px; width: 100%;">
                                <thead>
                                <tr>
                                    <th style="padding-left: 0;">Product</th>
                                    <th>Variant</th>
                                    <th>Price</th>
                                    <th style="text-align: center;">Qty</th>
                                    <th style="text-align: right; padding-right: 0;">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding-left: 0;">
                                            <div class="admin-item-info">
                                                <?php if (!empty($item['main_image'])): ?>
                                                    <?php $imgSrc = filter_var($item['main_image'], FILTER_VALIDATE_URL) ? $item['main_image'] : BASE_URL . '/' . ltrim($item['main_image'], '/'); ?>
                                                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="" class="admin-item-thumb" style="width: 48px; height: 48px; border-radius: 6px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 48px; height: 48px; border-radius: 6px; background: #eee;"></div>
                                                <?php endif; ?>
                                                <div style="display: flex; flex-direction: column; gap: 0.2rem;">
                                                    <span style="font-weight: 500; color: var(--color-text);"><?= htmlspecialchars($item['product_name']) ?></span>
                                                    <?php if (!empty($item['product_sku'])): ?>
                                                        <span style="font-size: 0.8rem; color: var(--color-text-light);">SKU: <?= htmlspecialchars($item['product_sku']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem; color: var(--color-text-light); display: flex; flex-direction: column; gap: 0.2rem;">
                                                <?php if (!empty($item['size'])): ?><div>Size: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['size']) ?></strong></div><?php endif; ?>
                                                <?php if (!empty($item['color'])): ?><div>Color: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['color']) ?></strong></div><?php endif; ?>
                                                <?php if (!empty($item['variant'])): ?><div>Variant: <strong style="color: var(--color-text);"><?= htmlspecialchars($item['variant']) ?></strong></div><?php endif; ?>
                                                <?php if (empty($item['size']) && empty($item['color']) && empty($item['variant'])): ?>-<?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= formatPrice($item['price']) ?></td>
                                        <td style="text-align: center;">x<?= intval($item['quantity']) ?></td>
                                        <td style="text-align: right; padding-right: 0; font-weight: 600;"><?= formatPrice($item['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Order Summary</h2>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Subtotal</span>
                                <span style="color: var(--color-text);"><?= formatPrice($order['subtotal']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Discount</span>
                                <span style="color: var(--color-text);"><?= formatPrice($order['discount']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Shipping</span>
                                <span style="color: var(--color-text);"><?= formatPrice($order['shipping_cost']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; color: var(--color-text-light);">
                                <span>Tax</span>
                                <span style="color: var(--color-text);"><?= formatPrice($order['tax']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 1.15rem; font-weight: 700; color: var(--color-text); padding-top: 0.75rem; border-top: 1px dashed var(--color-border); margin-top: 0.25rem;">
                                <span>Total</span>
                                <span><?= formatPrice($order['total']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Customer, Payment & Status -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem; min-width: 0;">
                    
                    <!-- Status Update Card -->
                    <div class="admin-card" style="margin-bottom: 0; background: var(--color-bg-secondary); border-color: var(--color-border);">
                        <h2 class="detail-section-title" style="border-bottom-color: rgba(0,0,0,0.05);">Order Status</h2>
                        <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                        <!-- Security: CSRF token -->
                        <?= csrfField() ?>
                            <input type="hidden" name="update_status" value="1">
                            <div>
                                <select name="status" class="form-select" style="width: 100%; border-radius: 6px; padding: 0.75rem; font-weight: 500;">
                                    <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $statusOption): ?>
                                        <option value="<?= $statusOption ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>><?= ucfirst($statusOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-primary" type="submit" style="width: 100%; padding: 0.75rem; border-radius: 6px;">Update Status</button>
                        </form>
                    </div>

                    <!-- Customer Info -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Customer Details</h2>
                        
                        <div class="info-block">
                            <div class="info-label">Contact Person</div>
                            <div class="info-value" style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.85rem;">
                                    <?= strtoupper(substr($order['shipping_first_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <span style="font-weight: 500;"><?= htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? ''))) ?></span>
                            </div>
                        </div>

                        <div class="info-block">
                            <div class="info-label">Contact Info</div>
                            <div class="info-value">
                                <div style="margin-bottom: 0.2rem;"><a href="mailto:<?= htmlspecialchars($order['shipping_email'] ?: ($order['account_email'] ?? '')) ?>" style="color: var(--color-primary); text-decoration: none;"><?= htmlspecialchars($order['shipping_email'] ?: ($order['account_email'] ?? '-')) ?></a></div>
                                <div><a href="tel:<?= htmlspecialchars($order['shipping_phone'] ?? '') ?>" style="color: var(--color-text); text-decoration: none;"><?= htmlspecialchars($order['shipping_phone'] ?? '-') ?></a></div>
                            </div>
                        </div>

                        <div class="info-block" style="margin-bottom: 0;">
                            <div class="info-label">Shipping Address</div>
                            <div class="info-value">
                                <?= htmlspecialchars($order['shipping_address'] ?? '-') ?><br>
                                <?= htmlspecialchars($order['shipping_city'] ?? '-') ?>, <?= htmlspecialchars($order['shipping_postal_code'] ?? '-') ?><br>
                                <?= htmlspecialchars($order['shipping_country'] ?? '-') ?>
                            </div>
                        </div>
                    </div>

                    <?php $orderNotes = !empty($order['order_notes']) ? $order['order_notes'] : (!empty($order['notes']) ? $order['notes'] : ''); ?>
                    <?php if ($orderNotes): ?>
                    <!-- Order Notes -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Order Notes</h2>
                        <div style="font-size: 0.95rem; line-height: 1.6; color: var(--color-text); background: var(--color-bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--color-border);">
                            <?= nl2br(htmlspecialchars($orderNotes)) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Info -->
                    <div class="admin-card" style="margin-bottom: 0;">
                        <h2 class="detail-section-title">Payment Info</h2>
                        <div class="info-block">
                            <div class="info-label">Payment Method</div>
                            <div class="info-value" style="font-weight: 500;"><?= htmlspecialchars(paymentDisplayName((string)($order['payment_method'] ?? ''))) ?></div>
                        </div>
                        <?php if (!empty($order['payment_phone'])): ?>
                        <div class="info-block">
                            <div class="info-label">Payment Mobile Number</div>
                            <div class="info-value" style="font-weight: 500;"><?= htmlspecialchars($order['payment_phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-block">
                            <div class="info-label">Payment Status</div>
                            <div class="info-value">
                                <span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>" style="font-size: 0.75rem; padding: 0.35rem 0.65rem;">
                                    <?= htmlspecialchars(ucfirst($order['payment_status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-block" style="margin-bottom: 0;">
                            <div class="info-label">Transaction ID</div>
                            <div class="info-value" style="font-family: monospace; font-size: 0.9rem; background: var(--color-bg-secondary); padding: 0.35rem 0.5rem; border-radius: 4px; display: inline-block;">
                                <?= htmlspecialchars(!empty($order['transaction_id']) ? $order['transaction_id'] : (!empty($order['payment_trx_id']) ? $order['payment_trx_id'] : 'N/A')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
    <script src="<?= BASE_URL ?>/admin/js/admin.js"></script>
</body>
</html>


