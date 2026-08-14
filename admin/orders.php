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

function ensureOrderStatusHistoryTable(PDO $db): void
{
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS order_status_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                status VARCHAR(50) NOT NULL,
                note TEXT NULL,
                changed_by VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_osh_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {}
}

ensureOrderStatusHistoryTable($db);

$statusMap = [
    'pending'      => ['label' => 'Pending',           'badge' => 'warning'],
    'confirmed'    => ['label' => 'Confirmed',         'badge' => 'info'],
    'processing'   => ['label' => 'Processing',        'badge' => 'primary'],
    'shipped'      => ['label' => 'Shipped',           'badge' => 'indigo'],
    'delivered'    => ['label' => 'Delivered',         'badge' => 'success'],
    'on_hold'      => ['label' => 'Hold',              'badge' => 'warning'],
    'unreachable'  => ['label' => 'Unreachable',       'badge' => 'danger'],
    'not_received' => ['label' => "Didn't Receive",    'badge' => 'danger'],
    'returned'     => ['label' => 'Returned',          'badge' => 'purple'],
    'cancelled'    => ['label' => 'Cancelled',         'badge' => 'secondary'],
    'refunded'     => ['label' => 'Refunded',          'badge' => 'pink'],
    'fake'         => ['label' => 'Fake Order',        'badge' => 'dark-red'],
];

// Update order status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $orderId = intval($_POST['order_id']);
    $status  = sanitize($_POST['status'] ?? 'pending');

    if (isset($statusMap[$status])) {
        $oldStatusStmt = $db->prepare("SELECT status, total FROM orders WHERE id = ?");
        $oldStatusStmt->execute([$orderId]);
        $oldRow = $oldStatusStmt->fetch();
        $oldStatus = $oldRow['status'] ?? '';
        $orderTotal = floatval($oldRow['total'] ?? 0);

        if ($status === 'delivered') {
            $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = 'paid', advance_payment = ? WHERE id = ?");
            $saved = $stmt->execute([$status, $orderTotal, $orderId]);
        } else {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $saved = $stmt->execute([$status, $orderId]);
        }

        if ($saved) {
            $message = 'Order status updated to ' . htmlspecialchars($statusMap[$status]['label']) . '.';
            
            // Record status audit log
            $changedBy = htmlspecialchars($_SESSION['user_name'] ?? 'Admin');
            $histStmt  = $db->prepare("INSERT INTO order_status_history (order_id, status, note, changed_by) VALUES (?, ?, ?, ?)");
            $histStmt->execute([$orderId, $status, 'Status updated from admin orders table', $changedBy]);

            // Stock Synchronization Logic
            if ($oldStatus && $oldStatus !== $status) {
                $isOldInactive = in_array($oldStatus, ['cancelled', 'refunded', 'fake'], true);
                $isNewInactive = in_array($status, ['cancelled', 'refunded', 'fake'], true);
                
                if (!$isOldInactive && $isNewInactive) {
                    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                    $itemsStmt->execute([$orderId]);
                    $restockStmt = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $restockStmt->execute([$item['quantity'], $item['product_id']]);
                    }
                } elseif ($isOldInactive && !$isNewInactive) {
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
}

// Status Counters for Header Cards
$counts = [
    'pending'     => 0,
    'confirmed'   => 0,
    'processing'  => 0,
    'delivered'   => 0,
    'cancelled'   => 0,
    'on_hold'     => 0,
    'returned'    => 0,
    'unreachable' => 0,
    'fake'        => 0,
];

$cntRes = $db->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($cntRes as $stKey => $stCount) {
    if (isset($counts[$stKey])) {
        $counts[$stKey] = (int)$stCount;
    }
}

// Get orders with filters
$statusFilter = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['search'] ?? '');
$whereParts   = [];
$params       = [];

if ($statusFilter) {
    $whereParts[] = "o.status = ?";
    $params[]     = $statusFilter;
}
if ($search) {
    $whereParts[] = "(o.order_number LIKE ? OR o.shipping_first_name LIKE ? OR o.shipping_last_name LIKE ? OR o.shipping_phone LIKE ?)";
    $sLike        = "%$search%";
    $params[]     = $sLike;
    $params[]     = $sLike;
    $params[]     = $sLike;
    $params[]     = $sLike;
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Pagination Setup
$perPage = max(1, min(100, intval($_GET['per_page'] ?? 15)));
$page    = max(1, intval($_GET['page'] ?? 1));

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $where");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages  = max(1, ceil($totalOrders / $perPage));
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
            
            <!-- Header Status Summary Cards (Matching Professional Dashboard) -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 1.5rem;">
                <?php
                $statCards = [
                    'pending'     => ['title' => 'Pending Orders',     'color' => '#f59e0b', 'bg' => '#fffbe8'],
                    'confirmed'   => ['title' => 'Confirmed Orders',   'color' => '#0284c7', 'bg' => '#f0f9ff'],
                    'processing'  => ['title' => 'Processing Orders',  'color' => '#2563eb', 'bg' => '#eff6ff'],
                    'delivered'   => ['title' => 'Completed Orders',   'color' => '#10b981', 'bg' => '#ecfdf5'],
                    'cancelled'   => ['title' => 'Cancelled Orders',   'color' => '#64748b', 'bg' => '#f8fafc'],
                    'on_hold'     => ['title' => 'Hold Orders',        'color' => '#d97706', 'bg' => '#fff7ed'],
                    'returned'    => ['title' => 'Returned Orders',    'color' => '#8b5cf6', 'bg' => '#f5f3ff'],
                    'unreachable' => ['title' => 'Unreachable',        'color' => '#ef4444', 'bg' => '#fef2f2'],
                    'fake'        => ['title' => 'Fake Orders',        'color' => '#991b1b', 'bg' => '#ffe4e6'],
                ];
                foreach ($statCards as $stKey => $stCfg):
                    $isSel = ($statusFilter === $stKey);
                ?>
                    <a href="?status=<?= $isSel ? '' : $stKey ?>" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1rem; background: #ffffff; border: 1.5px solid <?= $isSel ? $stCfg['color'] : '#e2e8f0' ?>; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.15s ease;">
                        <div>
                            <div style="font-size: 1.2rem; font-weight: 800; color: <?= $stCfg['color'] ?>; line-height: 1;"><?= $counts[$stKey] ?? 0 ?></div>
                            <div style="font-size: 0.76rem; font-weight: 600; color: #64748b; margin-top: 4px;"><?= htmlspecialchars($stCfg['title']) ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="admin-header">
                <h1 class="admin-title">Orders</h1>
                <div class="admin-actions-row">
                    <form method="GET" class="admin-actions-row" style="margin: 0; gap: 8px;">
                        <input type="text" name="search" class="form-input admin-input-max-280" placeholder="Search order #, customer..." value="<?= htmlspecialchars($search) ?>">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <?php foreach ($statusMap as $sKey => $sVal): ?>
                                <option value="<?= $sKey ?>" <?= $statusFilter === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sVal['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-secondary">Search</button>
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
                                <td><?= htmlspecialchars(trim(($order['shipping_first_name'] ?? '') . ' ' . ($order['shipping_last_name'] ?? '')) ?: 'Guest') ?></td>
                                <td><strong><?= formatPrice($order['total']) ?></strong></td>
                                <td>
                                    <form method="POST" class="admin-form-row-center">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" class="form-select admin-status-select" style="font-weight: 600; font-size: 0.82rem; padding: 0.3rem 0.5rem;" onchange="this.form.submit()">
                                            <?php foreach ($statusMap as $sKey => $sVal): ?>
                                                <option value="<?= $sKey ?>" <?= $order['status'] === $sKey ? 'selected' : '' ?>><?= htmlspecialchars($sVal['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </td>
                                <td><span class="badge badge-<?= ($order['status'] === 'delivered' || $order['payment_status'] === 'paid') ? 'success' : 'warning' ?>"><?= htmlspecialchars(($order['status'] === 'delivered' || $order['payment_status'] === 'paid') ? 'Paid' : ucfirst($order['payment_status'])) ?></span></td>
                                <td><?= htmlspecialchars(paymentDisplayName((string)$order['payment_method'])) ?></td>
                                <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <div class="admin-actions-row">
                                        <a href="<?= BASE_URL ?>/admin/order/<?= $order['id'] ?>" class="btn btn-sm btn-outline">View</a>
                                        <a href="<?= BASE_URL ?>/invoice?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-sm btn-outline" style="color: var(--color-primary); border-color: var(--color-primary);" title="Download Invoice">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderAdminPagination($page, $totalOrders, $perPage, BASE_URL . '/admin/orders', array_filter(['status' => $statusFilter, 'search' => $search])); ?>
        </main>
    </div>
    <script src="js/admin.js"></script>
</body>
</html>
