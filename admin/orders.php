<?php
/**
 * Rosabella - Admin Orders Management
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

// ── Security: Verify CSRF on all admin POST requests ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
}

$message = '';

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $status = sanitize($_POST['status']);

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
        $message = 'Order status updated';
        
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
    }
}

// Get orders with filters
$status = $_GET['status'] ?? '';
$where = '';
$params = [];
if ($status) {
    $where = "WHERE status = ?";
    $params[] = $status;
}

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $where");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalOrders / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id $where ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders Management';
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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php renderAdminSidebar('orders'); ?>

        <main class="admin-content">
            <?php renderAdminTopbar($pageTitle ?? 'Admin Panel'); ?>
            
            <div class="admin-header">
                <h1 class="admin-title">Orders</h1>
                <div class="admin-actions-row">
                    <form method="GET" class="admin-actions-row" style="margin: 0;">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
                            <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                            <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                        </select>
                    </form>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Gateway</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No orders found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                                <td><?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?></td>
                                <td><?= formatPrice($order['total']) ?></td>
                                <td>
                                    <form method="POST" class="admin-form-row-center">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" class="form-select admin-status-select" onchange="this.form.submit()">
                                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                            <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                            <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            <option value="refunded" <?= $order['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                        </select>
                                    </form>
                                </td>
                                <td><span class="badge badge-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($order['payment_status']) ?></span></td>
                                <td><?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td style="display: flex; gap: 0.5rem; align-items: center;">
                                    <a href="<?= BASE_URL ?>/admin/order/<?= $order['id'] ?>" class="btn btn-sm btn-outline">View</a>
                                    <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline" style="color: var(--color-primary); border-color: var(--color-primary);" title="Download Invoice">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderAdminPagination($page, $totalOrders, $perPage, BASE_URL . '/admin/orders', array_filter(['status' => $status])); ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
