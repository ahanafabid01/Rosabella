<?php
/**
 * KARTLY - Admin Order Detail
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/payment_gateway.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$db = getDB();
$message = '';
$error = '';
$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $status = sanitize($_POST['status'] ?? 'pending');
    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    if (!in_array($status, $allowed, true)) {
        $error = 'Invalid order status.';
    } else {
        if ($status === 'delivered') {
            $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
        }

        if ($stmt->execute([$status, $orderId])) {
            $message = 'Order status updated.';
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
    header('Location: orders.php');
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - KARTLY Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-layout">
    <?php renderAdminSidebar('orders'); ?>

    <main class="admin-content">
        <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
<div class="admin-detail-header">
            <h1 class="admin-page-title">Order <?= htmlspecialchars($order['order_number']) ?></h1>
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="admin-card">
            <div class="admin-detail-meta-grid">
                <div>
                    <div class="admin-meta-label">Order Date</div>
                    <div class="admin-semi"><?= htmlspecialchars(date('M j, Y H:i', strtotime($order['created_at']))) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Payment Status</div>
                    <div class="admin-semi"><?= htmlspecialchars(ucfirst($order['payment_status'])) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Payment Method</div>
                    <div class="admin-semi"><?= htmlspecialchars(paymentDisplayName((string)($order['payment_method'] ?? ''))) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Transaction Ref</div>
                    <div class="admin-semi"><?= htmlspecialchars($order['transaction_id'] ?: '-') ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Total</div>
                    <div class="admin-strong"><?= formatPrice($order['total']) ?></div>
                </div>
            </div>
            <form method="POST" class="admin-form-row-center admin-mt-1">
                <input type="hidden" name="update_status" value="1">
                <label class="admin-label-medium">Status:</label>
                <select name="status" class="form-select admin-select-220">
                    <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $statusOption): ?>
                        <option value="<?= $statusOption ?>" <?= $order['status'] === $statusOption ? 'selected' : '' ?>><?= ucfirst($statusOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Update Status</button>
            </form>
        </div>

        <div class="admin-card">
            <h2 class="admin-subtitle">Customer & Shipping</h2>
            <div class="admin-detail-customer-grid">
                <div>
                    <div class="admin-meta-label">Name</div>
                    <div><?= htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? ''))) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Shipping Email</div>
                    <div><?= htmlspecialchars($order['shipping_email'] ?: ($order['account_email'] ?? '-')) ?></div>
                </div>
                <div>
                    <div class="admin-meta-label">Phone</div>
                    <div><?= htmlspecialchars($order['shipping_phone'] ?? '-') ?></div>
                </div>
                <div class="admin-col-full">
                    <div class="admin-meta-label">Address</div>
                    <div>
                        <?= htmlspecialchars($order['shipping_address'] ?? '-') ?>,
                        <?= htmlspecialchars($order['shipping_city'] ?? '-') ?>,
                        <?= htmlspecialchars($order['shipping_postal_code'] ?? '-') ?>,
                        <?= htmlspecialchars($order['shipping_country'] ?? '-') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <h2 class="admin-subtitle">Order Items</h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="admin-item-info">
                                    <?php if (!empty($item['main_image'])): ?>
                                        <img src="<?= htmlspecialchars($item['main_image']) ?>" alt="" class="admin-item-thumb">
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($item['product_sku'] ?: '-') ?></td>
                            <td><?= intval($item['quantity']) ?></td>
                            <td><?= formatPrice($item['price']) ?></td>
                            <td><?= formatPrice($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="admin-summary-grid">
                <div>Subtotal: <strong><?= formatPrice($order['subtotal']) ?></strong></div>
                <div>Discount: <strong><?= formatPrice($order['discount']) ?></strong></div>
                <div>Shipping: <strong><?= formatPrice($order['shipping_cost']) ?></strong></div>
                <div>Tax: <strong><?= formatPrice($order['tax']) ?></strong></div>
                <div class="admin-summary-total">Total: <strong><?= formatPrice($order['total']) ?></strong></div>
            </div>
        </div>
    </main>
</div>
    <script src="js/admin.js"></script>
</body>
</html>

