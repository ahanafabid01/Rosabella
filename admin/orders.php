<?php
/**
 * KARTLY - Admin Orders Management
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

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $status = sanitize($_POST['status']);

    if ($status === 'delivered') {
        $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid' WHERE id = ?");
    } else {
        $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    }

    if ($stmt->execute([$status, $orderId])) {
        $message = 'Order status updated';
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
$stmt = $db->prepare("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN users u ON o.user_id = u.id $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Orders Management';
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
<div class="admin-header">
                <h1 class="admin-title">Orders</h1>
                <form method="GET" class="admin-actions-row">
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

            <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>

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
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_number']) ?></td>
                            <td><?= htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']) ?></td>
                            <td><?= formatPrice($order['total']) ?></td>
                            <td>
                                <form method="POST" class="admin-form-row-center">
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
                            <td><a href="order-detail.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>

